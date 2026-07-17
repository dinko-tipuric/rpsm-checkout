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

		/* Vec u kosarici? Gotovo. */
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === self::$product_id ) {
				return;
			}
		}

		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_CLOBBER ) && ! WC()->cart->is_empty() ) {
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
	 * ostaje netaknut. Ako kupac vec ima izbor u sesiji, on se postuje.
	 */
	public static function gateway_first( $gateways ) {
		if ( ! self::is_express() || ! is_array( $gateways ) ) {
			return $gateways;
		}
		$first = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::EXPRESS_FIRST_GATEWAY );
		if ( '' === $first || ! isset( $gateways[ $first ] ) ) {
			return $gateways;
		}
		$gateway = $gateways[ $first ];
		unset( $gateways[ $first ] );
		return [ $first => $gateway ] + $gateways;
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
