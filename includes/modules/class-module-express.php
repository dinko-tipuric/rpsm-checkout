<?php
defined( 'ABSPATH' ) || exit;

/**
 * Express stranica: opis proizvoda + puni checkout na jednoj stranici.
 *
 * Arhitektura (SPEC-express-checkout.md): Elementor gradi stranicu, ovaj modul
 * je cini checkoutom. Shortcode [rpsm_express product_id=X] renderira STANDARDNI
 * [woocommerce_checkout] - ne Elementor Checkout widget - pa svi postojeci
 * moduli (legal, kuponi, upsell, R1, atribucija...) rade bez izmjena cim
 * woocommerce_is_checkout filter vrati true.
 *
 * Redoslijed u requestu:
 *  1. 'wp' (prio 4): detekcija shortcodea na queried stranici (post_content
 *     ILI _elementor_data - Elementorov Shortcode widget ne zavrsava nuzno
 *     u post_contentu) + parsiranje product_id atributa.
 *  2. woocommerce_is_checkout filter: true kad je express kontekst aktivan.
 *  3. 'template_redirect' (prio 5, PRIJE WC empty-cart redirecta na prio 10):
 *     auto-add proizvoda (uz clobber ako je ukljucen).
 *  4. Shortcode render: checkout forma ILI "vec posjedujes" kartica
 *     (is_purchasable false - guard iz v1.3.0.1 lekcije, petlja nemoguca).
 */
final class RPSM_Checkout_Module_Express {

	private const SHORTCODE = 'rpsm_express';

	/** Rezultat detekcije za trenutni request. */
	private static ?int $product_id = null;
	private static bool $detected   = false;

	public static function init(): void {
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp', [ __CLASS__, 'detect' ], 4 );
		add_filter( 'woocommerce_is_checkout', [ __CLASS__, 'filter_is_checkout' ] );
		add_action( 'template_redirect', [ __CLASS__, 'auto_add_to_cart' ], 5 );
		add_filter( 'woocommerce_available_payment_gateways', [ __CLASS__, 'gateway_first' ], 50 );
		add_filter( 'woocommerce_enable_order_notes_field', [ __CLASS__, 'hide_order_notes' ], 50 );

		/* Kupon polje van na expressu (URL kupon kroz Kuponi modul i dalje
		   radi) - remove na prio 5, WC ga dodaje na before_checkout_form@10 */
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'maybe_remove_coupon_form' ], 5 );

		/* Ogranicena ponuda (countdown popust) - SPEC Dio 5. Pozicioniranje
		   po industrijskom standardu (SamCart/Deadline Funnel/ThriveCart):
		   fiksna traka na vrhu ekrana + usteda red u sazetku + timer ispod
		   CTA gumba - sve sinkronizirano na isti countdown. */
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'deal_apply_price' ], 1000 );
		add_filter( 'woocommerce_cart_item_subtotal', [ __CLASS__, 'deal_subtotal_display' ], 15, 2 );
		add_action( 'wp_footer', [ __CLASS__, 'render_deal_bar' ], 5 );
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_deal_expired_slot' ], 8 );
		add_action( 'woocommerce_review_order_before_order_total', [ __CLASS__, 'render_deal_savings_row' ] );
		add_action( 'woocommerce_review_order_after_submit', [ __CLASS__, 'render_deal_cta_note' ], 5 );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'deal_expiry_guard' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'deal_order_meta' ] );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'wp_footer', [ __CLASS__, 'render_sticky_cta' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ __CLASS__, 'sticky_total_fragment' ] );
		add_action( 'wp_head', [ __CLASS__, 'seo_tags' ], 1 );
	}

	/* ── Kontekst ──────────────────────────────────────────────────── */

	private const SESSION_FLAG = 'rpsm_express_pid';

	/**
	 * Je li trenutni request express kontekst.
	 *
	 * Page load: detekcija shortcodea na 'wp'. AJAX (wc-ajax update_order_review,
	 * fragmenti): 'wp' se NE izvrsava pa se kontekst cita iz WC sesije - bez
	 * toga bi prvi fragment refresh vratio gateway redoslijed i upsell compact
	 * na standardni prikaz (nadjeno u dev testu v1.7.0.0).
	 */
	public static function is_express(): bool {
		if ( self::$detected ) {
			return true;
		}
		return self::is_ajax_request() && self::session_pid() > 0;
	}

	/** Proizvod express stranice (null van konteksta). */
	public static function product_id(): ?int {
		if ( null !== self::$product_id ) {
			return self::$product_id;
		}
		$pid = self::session_pid();
		return ( $pid > 0 && self::is_ajax_request() ) ? $pid : null;
	}

	private static function is_ajax_request(): bool {
		return wp_doing_ajax() || ! empty( $_GET['wc-ajax'] ); // phpcs:ignore
	}

	private static function session_pid(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return 0;
		}
		return (int) WC()->session->get( self::SESSION_FLAG );
	}

	/**
	 * Detekcija na 'wp': queried stranica sadrzi nas shortcode?
	 * Gleda post_content I _elementor_data (Shortcode widget zivi u JSON-u).
	 */
	public static function detect(): void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$haystacks = [ (string) $post->post_content ];
		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $elementor ) && '' !== $elementor ) {
			$haystacks[] = $elementor;
		}

		foreach ( $haystacks as $haystack ) {
			if ( false === strpos( $haystack, '[' . self::SHORTCODE ) ) {
				continue;
			}
			self::$detected = true;

			/* product_id atribut - radi i u post_contentu i u Elementor JSON-u
			   (tamo su navodnici escapani: product_id=\"123\") */
			if ( preg_match( '/\[' . self::SHORTCODE . '[^\]]*?product_id[^\d\]]{0,4}(\d+)/', $haystack, $m ) ) {
				self::$product_id = (int) $m[1];
			}
			break;
		}

		if ( self::$detected && null === self::$product_id ) {
			RPSM_Checkout_Debug::info( 'Express: shortcode bez product_id atributa', [ 'page' => $post->ID ], 'express' );
		}

		/* Express flag u WC sesiju - AJAX fragmenti ('wp' se tamo ne vrti)
		   iz njega znaju da su u express kontekstu. Na PRAVOM checkoutu se
		   flag brise da express postavke (gateway redoslijed, compact upsell)
		   ne procure na standardni flow. */
		if ( function_exists( 'WC' ) && WC()->session ) {
			if ( self::$detected && null !== self::$product_id ) {
				WC()->session->set( self::SESSION_FLAG, self::$product_id );
			} elseif ( self::session_pid() > 0 && is_checkout() ) {
				WC()->session->set( self::SESSION_FLAG, null );
			}
		}
	}

	/** Express stranica JE checkout - pali sve is_checkout() guardove. */
	public static function filter_is_checkout( $is_checkout ) {
		return self::$detected ? true : $is_checkout;
	}

	public static function body_class( array $classes ): array {
		if ( self::$detected ) {
			$classes[] = 'rpsm-express-page';
		}
		return $classes;
	}

	/* ── Auto-add ──────────────────────────────────────────────────── */

	/**
	 * Dodaj proizvod u kosaricu PRIJE WC-ovog empty-cart redirecta.
	 * Clobber: postojeca kosarica se prazni (express = "kupi ovo sada").
	 */
	public static function auto_add_to_cart(): void {
		if ( ! self::$detected || null === self::$product_id || ! WC()->cart ) {
			return;
		}

		$product = wc_get_product( self::$product_id );
		if ( ! $product ) {
			return;
		}

		/* Vlasnik zasticenog proizvoda (Jednokratna kupnja -> is_purchasable
		   false): NE dodajemo i NE renderiramo formu - shortcode ce prikazati
		   "vec posjedujes" karticu. Bez ovoga bi add bio blokiran -> prazan
		   checkout -> redirect petlja (poznati v1.3.0.1 scenarij). */
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}

		/* Countdown ponuda: deadline krece od PRVOG posjeta (refresh ga ne
		   resetira). Postavlja se prije manipulacije kosaricom. */
		self::deal_start( $product );

		/* Stanje kosarice: express proizvod moze vec biti unutra (raniji
		   posjet), ali uz njega i DRUGE stavke (npr. add-to-cart link u
		   medjuvremenu). Clobber garantira: kosarica = TOCNO ovaj proizvod. */
		$has_product = false;
		$has_others  = false;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === self::$product_id ) {
				$has_product = true;
			} else {
				$has_others = true;
			}
		}

		$clobber = '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_CLOBBER );

		if ( $has_product && ( ! $has_others || ! $clobber ) ) {
			return;
		}

		if ( $clobber && ! WC()->cart->is_empty() ) {
			WC()->cart->empty_cart();
			RPSM_Checkout_Debug::info( 'Express: kosarica ispraznjena (clobber)', [ 'product' => self::$product_id ], 'express' );
		}

		$added = WC()->cart->add_to_cart( self::$product_id );
		if ( false === $added ) {
			/* Validacija (npr. jednokratna kupnja po sesiji) je blokirala add -
			   njen notice ostaje, forma ce pokazati prazno stanje kroz shortcode. */
			RPSM_Checkout_Debug::info( 'Express: add_to_cart blokiran validacijom', [ 'product' => self::$product_id ], 'express' );
		}
	}

	/* ── Gateway redoslijed (samo express) ─────────────────────────── */

	/**
	 * Kartica prva i predodabrana NA EXPRESS STRANICI; globalni checkout
	 * ostaje netaknut. Uz EXPRESS_CARD_ONLY (default ON) ostali gatewayi
	 * (virman/BACS) se na expressu uopce ne nude - "pristup odmah" ponuda
	 * nema smisla s uplatom koja sjeda za dva dana. Virman zivi na
	 * standardnom checkoutu.
	 */
	public static function gateway_first( $gateways ) {
		if ( ! self::is_express() || ! is_array( $gateways ) ) {
			return $gateways;
		}
		$first = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_FIRST_GATEWAY );
		if ( '' === $first || ! isset( $gateways[ $first ] ) ) {
			return $gateways;
		}
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_CARD_ONLY ) ) {
			return [ $first => $gateways[ $first ] ];
		}
		$gateway = $gateways[ $first ];
		unset( $gateways[ $first ] );
		return [ $first => $gateway ] + $gateways;
	}

	/** Napomene uz narudzbu ne nose nista na express stranici - van. */
	public static function hide_order_notes( $enabled ) {
		if ( self::is_express() && '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_HIDE_NOTES ) ) {
			return false;
		}
		return $enabled;
	}

	/* ── Shortcode ─────────────────────────────────────────────────── */

	public static function render_shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts, self::SHORTCODE );
		$pid  = (int) $atts['product_id'];
		if ( $pid <= 0 ) {
			$pid = (int) ( self::$product_id ?? 0 );
		}

		$product = $pid > 0 ? wc_get_product( $pid ) : null;
		if ( ! $product ) {
			return current_user_can( 'manage_woocommerce' )
				? '<div class="woocommerce-error">[rpsm_express] Proizvod nije pronađen - provjeri product_id atribut. (Poruka vidljiva samo adminima.)</div>'
				: '';
		}

		/* Vlasnik: umjesto forme kartica s linkom na Moj racun. */
		if ( ! $product->is_purchasable() ) {
			return self::render_owned_card( $product );
		}

		$checkout = do_shortcode( '[woocommerce_checkout]' );

		return '<div class="rpsm-express-checkout" id="rpsm-express-checkout">' . $checkout . '</div>';
	}

	private static function render_owned_card( WC_Product $product ): string {
		$msg = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_OWNED_MESSAGE );
		$msg = str_replace( '{proizvod}', $product->get_name(), $msg );

		$link_text = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_OWNED_LINK_TEXT );
		$link      = '';
		if ( '' !== trim( $link_text ) ) {
			$link = sprintf(
				'<p><a class="button alt" href="%s">%s</a></p>',
				esc_url( wc_get_page_permalink( 'myaccount' ) ),
				esc_html( $link_text )
			);
		}

		return '<div class="rpsm-express-checkout rpsm-express-owned" id="rpsm-express-checkout">'
			. '<p>' . esc_html( $msg ) . '</p>' . $link . '</div>';
	}

	/* ── Sticky mobilna CTA traka ──────────────────────────────────── */

	public static function render_sticky_cta(): void {
		if ( ! self::$detected || '1' !== RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_STICKY_CTA ) ) {
			return;
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$text = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_STICKY_CTA_TEXT );

		echo '<div class="rpsm-express-sticky" id="rpsm-express-sticky">';
		echo '<span class="rpsm-express-sticky-total">' . wp_kses_post( WC()->cart->get_total() ) . '</span>';
		echo '<a href="#rpsm-express-checkout" class="button alt rpsm-express-sticky-btn">' . esc_html( $text ) . '</a>';
		echo '</div>';

		/* Sakrij traku dok je checkout forma u viewportu (traka je precica DO
		   forme, ne duplikat CTA uz nju). Fallback bez IO: traka uvijek vidljiva. */
		echo '<script>(function(){var b=document.getElementById("rpsm-express-sticky"),f=document.getElementById("rpsm-express-checkout");if(!b||!f||!("IntersectionObserver"in window))return;new IntersectionObserver(function(e){b.style.display=e[0].isIntersecting?"none":"";}).observe(f);})();</script>';
	}

	/** Total u sticky traci prati promjene (bump, kupon) kroz WC fragmente. */
	public static function sticky_total_fragment( array $fragments ): array {
		if ( self::is_express() && WC()->cart ) {
			$fragments['.rpsm-express-sticky-total'] =
				'<span class="rpsm-express-sticky-total">' . wp_kses_post( WC()->cart->get_total() ) . '</span>';
		}
		return $fragments;
	}

	/** Rucni unos kupona ne postoji na expressu - kupon ili dolazi kroz URL ili ga nema. */
	public static function maybe_remove_coupon_form(): void {
		if ( self::is_express() && '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_HIDE_COUPON ) ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}
	}

	/* ── Ogranicena ponuda (countdown popust, SPEC Dio 5) ──────────── */

	private const DEAL_SESSION_PREFIX = 'rpsm_express_deal_';
	private const DEAL_ACK_PREFIX     = 'rpsm_express_deal_ack_';

	/**
	 * Konfiguracija ponude s proizvoda (meta box Prodajna stranica).
	 * null = ponuda nije ukljucena ili nije valjana.
	 */
	private static function deal_config( int $product_id ): ?array {
		if ( ! class_exists( 'RPSM_Checkout_Module_Product_Content' ) ) {
			return null;
		}
		$deal = RPSM_Checkout_Module_Product_Content::get_data( $product_id )['deal'] ?? null;
		if ( ! is_array( $deal ) || '1' !== ( $deal['on'] ?? '0' ) ) {
			return null;
		}
		$amount  = (float) str_replace( ',', '.', (string) ( $deal['amount'] ?? '' ) );
		$minutes = (int) ( $deal['minutes'] ?? 0 );
		if ( $amount <= 0 || $minutes <= 0 ) {
			return null;
		}
		return [
			'type'    => ( $deal['type'] ?? 'percent' ) === 'fixed' ? 'fixed' : 'percent',
			'amount'  => $amount,
			'minutes' => $minutes,
			'title'   => (string) ( $deal['title'] ?? '' ),
			'expired' => (string) ( $deal['expired'] ?? '' ),
		];
	}

	/** Postavi deadline u sesiju pri prvom posjetu (pretplate preskacemo). */
	private static function deal_start( WC_Product $product ): void {
		$pid    = (int) $product->get_id();
		$config = self::deal_config( $pid );
		if ( null === $config || ! WC()->session ) {
			return;
		}
		if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}
		$key = self::DEAL_SESSION_PREFIX . $pid;
		if ( ! WC()->session->get( $key ) ) {
			WC()->session->set( $key, time() + $config['minutes'] * 60 );
			RPSM_Checkout_Debug::info( 'Express deal: countdown pokrenut', [ 'product' => $pid, 'minutes' => $config['minutes'] ], 'express' );
		}
	}

	/** Preostale sekunde ponude; null = ponuda ne postoji, 0 = istekla. */
	private static function deal_seconds_left( int $product_id ): ?int {
		if ( null === self::deal_config( $product_id ) || ! WC()->session ) {
			return null;
		}
		$deadline = (int) WC()->session->get( self::DEAL_SESSION_PREFIX . $product_id );
		if ( $deadline <= 0 ) {
			return null;
		}
		return max( 0, $deadline - time() );
	}

	/** Snizena cijena iz SVJEZE kataloske (nikad iz vec snizene instance). */
	private static function deal_price( int $product_id, array $config ): ?float {
		$fresh = wc_get_product( $product_id );
		if ( ! $fresh ) {
			return null;
		}
		$full = (float) $fresh->get_price();
		if ( $full <= 0 ) {
			return null;
		}
		$price = 'fixed' === $config['type']
			? max( 0.0, $full - $config['amount'] )
			: $full * ( 1 - $config['amount'] / 100 );
		return round( $price, 2 );
	}

	/**
	 * Price override dok ponuda traje. SERVER JE AUTORITET: racuna se u
	 * svakom calculate_totals passu (page load, fragmenti, checkout submit),
	 * pa JS manipulacija countdownom ne moze kupiti po isteklom popustu.
	 */
	public static function deal_apply_price( $cart ): void {
		if ( ! self::is_express() || ! $cart instanceof WC_Cart ) {
			return;
		}
		$pid = self::product_id();
		if ( null === $pid ) {
			return;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds <= 0 ) {
			return;
		}
		$config = self::deal_config( $pid );
		$price  = null !== $config ? self::deal_price( $pid, $config ) : null;
		if ( null === $price ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === $pid && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( $price );
			}
		}
	}

	/** Precrtana puna cijena u sazetku narudzbe dok ponuda traje. */
	public static function deal_subtotal_display( $subtotal, $cart_item ) {
		if ( ! self::is_express() || ! is_array( $cart_item ) ) {
			return $subtotal;
		}
		$pid = self::product_id();
		if ( null === $pid || (int) ( $cart_item['product_id'] ?? 0 ) !== $pid ) {
			return $subtotal;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds <= 0 ) {
			return $subtotal;
		}
		$fresh = wc_get_product( $pid );
		if ( ! $fresh ) {
			return $subtotal;
		}
		$full = (float) $fresh->get_price() * (int) ( $cart_item['quantity'] ?? 1 );
		return '<del class="rpsm-express-deal-full">' . wc_price( $full ) . '</del> ' . $subtotal;
	}

	/** Oznaka popusta ("-20%" ili "-10,00 EUR") + apsolutna usteda za prikaze. */
	private static function deal_labels( int $product_id, array $config ): ?array {
		$fresh = wc_get_product( $product_id );
		$price = self::deal_price( $product_id, $config );
		if ( ! $fresh || null === $price ) {
			return null;
		}
		$full    = (float) $fresh->get_price();
		$savings = max( 0.0, $full - $price );
		$label   = 'fixed' === $config['type']
			? '-' . wp_strip_all_tags( wc_price( $config['amount'] ) )
			: '-' . rtrim( rtrim( number_format( $config['amount'], 1, ',', '.' ), '0' ), ',' ) . '%';
		return [ 'label' => $label, 'savings' => $savings, 'full' => $full ];
	}

	/**
	 * Fiksna traka na VRHU EKRANA (industrijski standard: uvijek vidljiva,
	 * iznad folda) - popust oznaka + usteda + naslov + timer. Poruka isteka
	 * se pojavi u traci i u formi; fragmenti vrate punu cijenu.
	 */
	public static function render_deal_bar(): void {
		if ( ! self::is_express() || null === self::$product_id ) {
			return;
		}
		$config = self::deal_config( self::$product_id );
		if ( null === $config ) {
			return;
		}
		$seconds = self::deal_seconds_left( self::$product_id );
		if ( null === $seconds || $seconds <= 0 ) {
			return;
		}
		$labels = self::deal_labels( self::$product_id, $config );
		if ( null === $labels ) {
			return;
		}

		echo '<div class="rpsm-express-dealbar" id="rpsm-express-dealbar" data-seconds="' . (int) $seconds . '">';
		echo '<span class="rpsm-express-dealbar-pill">' . esc_html( $labels['label'] ) . ' &middot; ušteda ' . wp_kses_post( wc_price( $labels['savings'] ) ) . '</span>';
		echo '<span class="rpsm-express-dealbar-title">' . esc_html( $config['title'] ) . '</span>';
		echo '<b class="rpsm-express-deal-timer" aria-live="polite">--:--</b>';
		echo '</div>';

		/* Countdown je samo PRIKAZ (server je autoritet). Isti timer pogoni
		   SVE .rpsm-express-deal-timer elemente (traka + linija ispod CTA). */
		echo '<script>(function(){
			var bar = document.getElementById("rpsm-express-dealbar");
			if (!bar) return;
			document.body.classList.add("rpsm-express-dealbar-on");
			var left = parseInt(bar.dataset.seconds, 10) || 0;
			var timers = document.querySelectorAll(".rpsm-express-deal-timer");
			function fmt(s){
				var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
				var mm = (m<10?"0":"")+m, ss = (sec<10?"0":"")+sec;
				return h > 0 ? h+":"+mm+":"+ss : mm+":"+ss;
			}
			function tick(){
				if (left <= 0) {
					bar.remove();
					document.body.classList.remove("rpsm-express-dealbar-on");
					var ex = document.getElementById("rpsm-express-deal-expired");
					if (ex) ex.hidden = false;
					var note = document.getElementById("rpsm-express-deal-cta-note");
					if (note) note.remove();
					if (window.jQuery) { jQuery(document.body).trigger("update_checkout"); }
					return;
				}
				timers.forEach(function(t){ t.textContent = fmt(left); });
				left--;
				setTimeout(tick, 1000);
			}
			tick();
		})();</script>';
	}

	/** Slot za poruku isteka unutar forme (JS je otkrije, fragmenti potvrde). */
	public static function render_deal_expired_slot(): void {
		if ( ! self::is_express() || null === self::$product_id ) {
			return;
		}
		$config = self::deal_config( self::$product_id );
		if ( null === $config ) {
			return;
		}
		$seconds = self::deal_seconds_left( self::$product_id );
		if ( null === $seconds ) {
			return;
		}
		$hidden = $seconds > 0 ? ' hidden' : '';
		echo '<div class="rpsm-express-deal is-expired" id="rpsm-express-deal-expired"' . $hidden . '>' . esc_html( $config['expired'] ) . '</div>';
	}

	/** "Ušteda" red u sazetku narudzbe, odmah iznad totala. */
	public static function render_deal_savings_row(): void {
		if ( ! self::is_express() ) {
			return;
		}
		$pid = self::product_id();
		if ( null === $pid ) {
			return;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds <= 0 ) {
			return;
		}
		$config = self::deal_config( $pid );
		$labels = null !== $config ? self::deal_labels( $pid, $config ) : null;
		if ( null === $labels || $labels['savings'] <= 0 ) {
			return;
		}
		echo '<tr class="rpsm-express-deal-savings"><th>Ušteda (' . esc_html( $labels['label'] ) . ')</th>';
		echo '<td>-' . wp_kses_post( wc_price( $labels['savings'] ) ) . '</td></tr>';
	}

	/** Mala linija ispod CTA gumba - timer ponovljen uz tocku odluke. */
	public static function render_deal_cta_note(): void {
		if ( ! self::is_express() ) {
			return;
		}
		$pid = self::product_id();
		if ( null === $pid ) {
			return;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds <= 0 ) {
			return;
		}
		echo '<p class="rpsm-express-deal-cta-note" id="rpsm-express-deal-cta-note">Popust istječe za <b class="rpsm-express-deal-timer">--:--</b></p>';
	}

	/**
	 * Istek IZMEDJU rendera i submita: total se preracunao na punu cijenu,
	 * ali kupac to jos nije vidio - JEDNOM blokiraj s obavijesti, drugi
	 * submit prolazi po redovnoj cijeni.
	 */
	public static function deal_expiry_guard( $data, $errors ): void {
		if ( ! self::is_express() || ! WC()->session ) {
			return;
		}
		$pid = self::product_id();
		if ( null === $pid ) {
			return;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds > 0 ) {
			return;
		}
		$ack_key = self::DEAL_ACK_PREFIX . $pid;
		if ( WC()->session->get( $ack_key ) ) {
			return;
		}
		$in_cart = false;
		foreach ( WC()->cart ? WC()->cart->get_cart() : [] as $item ) {
			if ( (int) $item['product_id'] === $pid ) {
				$in_cart = true;
				break;
			}
		}
		if ( ! $in_cart ) {
			return;
		}
		WC()->session->set( $ack_key, 1 );
		$errors->add(
			'rpsm_express_deal_expired',
			'Vremenska ponuda je u međuvremenu istekla pa je cijena vraćena na redovnu. Provjeri iznos i ponovno potvrdi narudžbu.'
		);
		RPSM_Checkout_Debug::info( 'Express deal: istek na submitu, kupac obavijesten', [ 'product' => $pid ], 'express' );
	}

	/** Trag na narudzbi kad je kupljeno s aktivnom ponudom. */
	public static function deal_order_meta( $order ): void {
		if ( ! self::is_express() || ! $order instanceof WC_Order ) {
			return;
		}
		$pid = self::product_id();
		if ( null === $pid ) {
			return;
		}
		$seconds = self::deal_seconds_left( $pid );
		if ( null === $seconds || $seconds <= 0 ) {
			return;
		}
		$config = self::deal_config( $pid );
		if ( null !== $config ) {
			$order->update_meta_data( '_rpsm_express_deal', $config['type'] . ':' . $config['amount'] );
		}
	}

	/* ── SEO ───────────────────────────────────────────────────────── */

	/**
	 * Express je landing za placeni/direktni promet: noindex + canonical na
	 * pravu stranicu proizvoda (bez SEO duplikata s product stranicom).
	 */
	public static function seo_tags(): void {
		if ( ! self::$detected || null === self::$product_id ) {
			return;
		}
		echo '<meta name="robots" content="noindex,follow">' . "\n";
		$permalink = get_permalink( self::$product_id );
		if ( $permalink ) {
			echo '<link rel="canonical" href="' . esc_url( $permalink ) . '">' . "\n";
		}
	}
}

/* ── Globalni helper za druge pluginove (rpsm-upsell compact itd.) ── */
if ( ! function_exists( 'rpsm_checkout_is_express' ) ) {
	function rpsm_checkout_is_express(): bool {
		return class_exists( 'RPSM_Checkout_Module_Express' ) && RPSM_Checkout_Module_Express::is_express();
	}
}
