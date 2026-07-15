<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Atribucija - narudžba pamti svoj izvor (SPEC-atribucija.md, Sloj 2).
 *
 * ARHITEKTURA (vidi SPEC-atribucija.md za "zašto"):
 * - PRIMARNI put je WC SESIJA, ne skriveno polje. Narudžbe na portalu nastaju i
 *   programski (buy-now, rpsm-upsell post-purchase, WCS obnove/switchevi) pa bi
 *   skriveno polje na checkoutu sve te promašilo.
 * - JS na SVAKOJ portal stranici čita kolačić `rpsm_attr` (piše ga rpsm-web na
 *   www, domain=.radimposvom.com.hr) i, ako WC sesija još nema atribuciju,
 *   jednom pošalje POST na rpsm-checkout/v1/attr. Ruta whitelista/sanitizira,
 *   NIKAD ne vjeruje inputu, i sprema u WC()->session->set('rpsm_attr', $data).
 * - Prepis sesije u order meta na woocommerce_checkout_create_order (klasični
 *   checkout) I woocommerce_new_order prio 20 (pokriva programske narudžbe -
 *   buy-now, upsell, blocks checkout).
 * - FALLBACK skriveno polje rpsm_attr_payload (JSON) na woocommerce_after_order_notes,
 *   ista server-side sanitizacija, za slučaj da je sesija prazna.
 * - WCS obnove i switchevi: atribucija se kopira s parenta/pretplate, NE iz
 *   sesije (koja u tom trenutku vjerojatno uopće nije aktualna), i force-a se
 *   _rpsm_attr_type = 'renewal'. Bez ovoga obnove padnu u "direct" (razvodne
 *   izvještaj) ili se broje kao nova akvizicija (napušu ROAS).
 * - rpsm-upsell narudžbe: nastaju programski (wc_create_order), a plugin svoj
 *   `_rpsm_upsell_parent_order` meta upisuje TEK nakon prvog save() (koji je
 *   već okinuo woocommerce_new_order). Zato se detekcija odgađa na 'shutdown' -
 *   do tog trenutka je, u istom requestu, ta meta već spremljena.
 *
 * Bez privole na www nema kolačića -> nema atribucije. Narudžba tada NE dobiva
 * _rpsm_attr_type uopće (ostaje "bez privole" u izvještaju, nikad "direct").
 */
final class RPSM_Checkout_Module_Attribution {

	/** Ime kolačića koji piše rpsm-web (Sloj 1). Samo se čita, nikad piše ovdje. */
	private const COOKIE_NAME = 'rpsm_attr';

	/** WC session key pod kojim živi sanitizirana atribucija. */
	private const SESSION_KEY = 'rpsm_attr';

	/** Data-key (iz sanitiziranog payloada) => order meta ključ. */
	private const META_MAP = [
		'first_source'   => '_rpsm_attr_first_source',
		'first_medium'   => '_rpsm_attr_first_medium',
		'first_campaign' => '_rpsm_attr_first_campaign',
		'first_content'  => '_rpsm_attr_first_content',
		'first_term'     => '_rpsm_attr_first_term',
		'first_landing'  => '_rpsm_attr_first_landing',
		'first_ts'       => '_rpsm_attr_first_ts',
		'last_source'    => '_rpsm_attr_last_source',
		'last_medium'    => '_rpsm_attr_last_medium',
		'last_campaign'  => '_rpsm_attr_last_campaign',
		'lp'             => '_rpsm_attr_lp',
		'cta'            => '_rpsm_attr_cta',
		'click_id'       => '_rpsm_attr_click_id',
	];

	private const META_TYPE = '_rpsm_attr_type';

	/** Rate limit REST rute - max N zahtjeva po IP-u u prozoru od M sekundi. */
	private const RATE_LIMIT_MAX    = 20;
	private const RATE_LIMIT_WINDOW = 60;

	private const MAX_LEN = 128;

	public static function init(): void {

		/* REST ruta - JS push s bilo koje portal stranice */
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );

		/* Fallback skriveno polje na klasičnom checkoutu */
		add_action( 'woocommerce_after_order_notes', [ __CLASS__, 'render_fallback_field' ] );

		/* Primarni put: sesija -> order meta. Dva hooka - klasični checkout i
		   sve ostale (programske) narudžbe. */
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'on_checkout_create_order' ], 20, 2 );
		add_action( 'woocommerce_new_order', [ __CLASS__, 'on_new_order' ], 20, 2 );

		/* WCS obnove i switchevi - kopiraj s parenta/pretplate, force type=renewal */
		if ( class_exists( 'WC_Subscriptions' ) ) {
			// ⚠️ accepted_args MORA biti 3 (callback prima i $recurring_cart). S "2" je
			// callback dobivao manje argumenata nego što PHP 8 zahtijeva ->
			// ArgumentCountError FATAL usred checkouta za SVAKU pretplatu (incident
			// 2026-07-15: "Došlo je do greške prilikom obrade vaše narudžbe").
			add_action( 'woocommerce_checkout_subscription_created', [ __CLASS__, 'on_subscription_created' ], 20, 3 );
			// ⚠️ wcs_renewal_order_created je FILTER (WCS radi return apply_filters(...)),
			// NE action. Callback MORA vratiti $renewal_order netaknut, inace
			// wcs_create_renewal_order() vrati null i obnova pukne. Zato add_filter + return.
			add_filter( 'wcs_renewal_order_created', [ __CLASS__, 'on_renewal_order_created' ], 10, 2 );
			add_action( 'woocommerce_subscription_checkout_switch_order_processed', [ __CLASS__, 'on_switch_order_processed' ], 10, 2 );
		}

		/* Admin: stupac "Izvor" u listi narudžbi (HPOS + Legacy CPT) */
		add_filter( 'woocommerce_shop_order_list_table_columns', [ __CLASS__, 'add_admin_column' ] );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ __CLASS__, 'render_admin_column' ], 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', [ __CLASS__, 'add_admin_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'render_admin_column_legacy' ], 10, 2 );

		/* Admin: cijeli lanac na order edit stranici */
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'render_meta_box' ] );
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  REST ruta                                                     */
	/* ══════════════════════════════════════════════════════════════ */

	public static function register_routes(): void {
		register_rest_route(
			'rpsm-checkout/v1',
			'/attr',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_rest_push' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Samo rate limit. Radi i za goste (kupac je gost dok se ne registrira).
	 *
	 * ⚠️ NAMJERNO BEZ NONCE-a. Portal ima file-based page cache (WP Super Cache),
	 * pa bi nonce bio zapečen u keširani HTML i istekao (~12-24 h) prije nego
	 * posjetitelj otvori stranicu. Rezultat bi bio 403 i TIHI gubitak atribucije
	 * na upravo onim stranicama koje se najviše serviraju iz cachea.
	 *
	 * Nonce ovdje ionako ne štiti ništa: payload dolazi iz klijentskog kolačića,
	 * dakle po definiciji je nepovjerljiv i sanitizira se server-side, a upisuje
	 * se isključivo u VLASTITU sesiju pozivatelja. Najgori scenarij zloupotrebe
	 * je da si netko sam sebi pokvari atribuciju. Obrana je sanitizacija + rate
	 * limit, ne nonce.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_permission( \WP_REST_Request $request ) {
		if ( ! self::rate_limit_ok() ) {
			return new \WP_Error( 'rpsm_attr_rate_limit', 'Previše zahtjeva.', [ 'status' => 429 ] );
		}

		return true;
	}

	private static function rate_limit_ok(): bool {
		$key   = 'rpsm_attr_rl_' . md5( self::get_client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	private static function get_client_ip(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) ); // phpcs:ignore
	}

	/**
	 * Prima kolačić s klijenta, sanitizira, sprema u WC sesiju.
	 * Ako sesija VEĆ ima atribuciju, ne prepisuje (server je zadnja linija
	 * obrane - JS šalje "samo ako sesija nema", ali ne vjerujemo klijentu).
	 */
	public static function handle_rest_push( \WP_REST_Request $request ): \WP_REST_Response {

		if ( ! function_exists( 'WC' ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_wc' ], 200 );
		}

		/* ⚠️ HOTFIX 1.5.0.2: REST ruta VISE NE DIRA WC sesiju.
		   Prijasnji initialize_session() + set_customer_session_cookie(true) je na SVAKOM
		   page loadu (JS gadja rutu) pisao NOVI WC session kolacic i time razbijao
		   postojecu checkout sesiju -> vanjski Stripe redirect bi otkazao (narudzba se
		   kreirala, ali placanje nije islo dalje). Atribucija se sada cita DIREKTNO iz
		   $_COOKIE server-side u apply_attribution() - kolacic rpsm_attr se ionako salje
		   sa svakim zahtjevom na portal (isti root domen), pa REST/sesija nisu potrebni.
		   Ruta ostaje kao no-op da JS koji jos gadja endpoint dobije cist 200. */
		return new \WP_REST_Response( [ 'ok' => true, 'reason' => 'noop' ], 200 );
	}

	/**
	 * Whitelist + sanitizacija + cap na 128 znakova. NIKAD ne vjeruj inputu.
	 * Očekuje sirovi oblik kolačića (f/l/lp/cta/cid iz rpsm-web Sloja 1) i
	 * vraća plosnati interni oblik (data-key => vrijednost) koji koriste i
	 * REST ruta i fallback skriveno polje.
	 */
	private static function sanitize_payload( array $raw ): array {

		$cap = static function ( $value ): string {
			$value = is_scalar( $value ) ? (string) $value : '';
			$value = sanitize_text_field( $value );
			return mb_substr( $value, 0, self::MAX_LEN );
		};

		$f   = is_array( $raw['f'] ?? null ) ? $raw['f'] : [];
		$l   = is_array( $raw['l'] ?? null ) ? $raw['l'] : [];
		$cid = is_array( $raw['cid'] ?? null ) ? $raw['cid'] : [];

		/* Click ID: prvi neprazan od gclid/fbclid/msclkid/ttclid, format "kljuc=vrijednost" */
		$click_id = '';
		foreach ( [ 'gclid', 'fbclid', 'msclkid', 'ttclid' ] as $cid_key ) {
			if ( ! empty( $cid[ $cid_key ] ) ) {
				$click_id = $cap( $cid_key . '=' . $cid[ $cid_key ] );
				break;
			}
		}

		return [
			'first_source'   => $cap( $f['s'] ?? '' ),
			'first_medium'   => $cap( $f['m'] ?? '' ),
			'first_campaign' => $cap( $f['c'] ?? '' ),
			'first_content'  => $cap( $f['ct'] ?? '' ),
			'first_term'     => $cap( $f['t'] ?? '' ),
			'first_landing'  => $cap( $f['lp'] ?? '' ),
			'first_ts'       => $cap( isset( $f['ts'] ) ? absint( $f['ts'] ) : '' ),
			/* "last" pada natrag na "first" ako još nema drugog dodira */
			'last_source'    => $cap( $l['s'] ?? ( $f['s'] ?? '' ) ),
			'last_medium'    => $cap( $l['m'] ?? ( $f['m'] ?? '' ) ),
			'last_campaign'  => $cap( $l['c'] ?? ( $f['c'] ?? '' ) ),
			'lp'             => $cap( $raw['lp'] ?? '' ),
			'cta'            => $cap( $raw['cta'] ?? '' ),
			'click_id'       => $click_id,
		];
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Fallback skriveno polje                                       */
	/* ══════════════════════════════════════════════════════════════ */

	/**
	 * Skriveno polje popunjava JS iz istog kolačića - koristi se SAMO ako je
	 * WC sesija prazna (npr. gost s blokiranim REST pozivom).
	 */
	public static function render_fallback_field(): void {
		echo '<input type="hidden" name="rpsm_attr_payload" id="rpsm_attr_payload_field" value="">';
	}

	private static function get_fallback_payload(): array {
		$raw = isset( $_POST['rpsm_attr_payload'] ) ? wp_unslash( $_POST['rpsm_attr_payload'] ) : ''; // phpcs:ignore
		if ( '' === $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		return self::sanitize_payload( $decoded );
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Sesija -> order meta                                          */
	/* ══════════════════════════════════════════════════════════════ */

	private static function get_session_attribution(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return [];
		}

		$data = WC()->session->get( self::SESSION_KEY );
		if ( ! is_array( $data ) ) {
			return [];
		}

		/* Retencija: perzistentna WC sesija ulogiranog korisnika može živjeti
		   mjesecima. Ne lijepimo staru atribuciju na sasvim novu narudžbu. */
		$retention_days = (int) RPSM_Checkout_Options::get( RPSM_Checkout_Options::ATTR_RETENTION_DAYS );
		$received_at    = (int) ( $data['_received_ts'] ?? 0 );

		if ( $retention_days > 0 && $received_at > 0 && ( time() - $received_at ) > ( $retention_days * DAY_IN_SECONDS ) ) {
			RPSM_Checkout_Debug::debug( 'Atribucija u sesiji je istekla (retencija) - preskočeno', [ 'received_at' => $received_at ] );
			return [];
		}

		unset( $data['_received_ts'] );
		return $data;
	}

	/**
	 * Cita atribuciju DIREKTNO iz kolacica rpsm_attr (server-side). Kolacic je na
	 * .radimposvom.com.hr pa se salje sa svakim zahtjevom na portal, ukljucujuci
	 * checkout submit i sve frontend narudzbe (buy-now, upsell). Ovo je primarni
	 * izvor od 1.5.0.2 - zamjenjuje raniji REST->sesija put koji je razbijao checkout.
	 */
	private static function get_cookie_attribution(): array {
		$raw = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			/* Defenzivno: neki setupovi ostave URL-encoded vrijednost u $_COOKIE. */
			$decoded = json_decode( rawurldecode( $raw ), true );
		}
		return is_array( $decoded ) ? self::sanitize_payload( $decoded ) : [];
	}

	/**
	 * Upiši atribuciju (sesija, ili fallback ako je sesija prazna) na narudžbu.
	 * Idempotentno - ako narudžba već ima _rpsm_attr_type, ne diraj (drugi hook
	 * ju je već obradio u istom requestu).
	 *
	 * @return bool True ako je nešto upisano (poziva treba onda spremiti order).
	 */
	private static function apply_attribution( \WC_Order $order, string $type = 'acquisition' ): bool {

		if ( '' !== $order->get_meta( self::META_TYPE ) ) {
			return false;
		}

		$data = self::get_session_attribution();

		if ( empty( array_filter( $data ) ) ) {
			$data = self::get_cookie_attribution();
		}

		if ( empty( array_filter( $data ) ) ) {
			$data = self::get_fallback_payload();
		}

		if ( empty( $data ) || empty( array_filter( $data ) ) ) {
			/* Nema atribucije - bez privole na www ili prazna sesija. NE
			   izmišljamo "direct" - narudžba ostaje bez _rpsm_attr_type. */
			return false;
		}

		foreach ( self::META_MAP as $data_key => $meta_key ) {
			if ( '' !== ( $data[ $data_key ] ?? '' ) ) {
				$order->update_meta_data( $meta_key, $data[ $data_key ] );
			}
		}
		$order->update_meta_data( self::META_TYPE, $type );

		RPSM_Checkout_Debug::info(
			'Atribucija upisana na narudžbu',
			[
				'order_id' => $order->get_id(),
				'type'     => $type,
				'source'   => $data['first_source'] ?? '',
				'campaign' => $data['first_campaign'] ?? '',
			]
		);

		return true;
	}

	/**
	 * Klasični checkout - hook prije finalnog $order->save() (WC_Checkout::create_order
	 * sam sprema narudžbu odmah nakon ovog hooka, pa ovdje NE zovemo save()).
	 */
	public static function on_checkout_create_order( \WC_Order $order, $data ): void {
		self::apply_attribution( $order, 'acquisition' );
	}

	/**
	 * Sve ostale narudžbe (buy-now, blocks checkout, programske narudžbe).
	 *
	 * ⚠️ HOTFIX 1.5.0.3: OVDJE SE NIŠTA NE PIŠE I NE SPREMA. woocommerce_new_order
	 * se okida USRED spremanja narudžbe (unutar datastore create()), pa je raniji
	 * apply + $order->save() radio ugniježđeni save u nedovršenom checkout toku -
	 * narudžba se kreirala, ali obrada plaćanja/Stripe redirect je pucao (staging
	 * incident 2026-07-15, order 10324: dupli "Atribucija upisana" u logu).
	 * Sve se odgađa na shutdown - narudžba se tada svježe učita i sigurno spremi,
	 * izvan kritičnog puta. Idempotencija u apply_attribution() (META_TYPE) i dalje
	 * garantira da narudžbi koju je klasični checkout već obradio ne diramo ništa.
	 */
	public static function on_new_order( int $order_id, \WC_Order $order ): void {
		add_action(
			'shutdown',
			static function () use ( $order_id ): void {
				self::finalize_order_attribution( $order_id );
			}
		);
	}

	/**
	 * Shutdown: svježe učitaj narudžbu pa (1) upiši atribuciju ako je klasični
	 * checkout nije već upisao, (2) upsell korekcija ako je narudžba upsell.
	 */
	private static function finalize_order_attribution( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( self::apply_attribution( $order, 'acquisition' ) ) {
			$order->save();
		}

		self::maybe_apply_upsell_attribution( $order_id );
	}

	private static function maybe_apply_upsell_attribution( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$parent_id = (int) $order->get_meta( '_rpsm_upsell_parent_order' );
		if ( ! $parent_id ) {
			return;
		}

		$parent = wc_get_order( $parent_id );
		if ( ! $parent ) {
			return;
		}

		self::copy_from_source( $order, $parent, 'upsell' );
	}

	/**
	 * Bezuvjetno kopira atribuciju s izvora (parent order / subscription) na
	 * odredište i force-a tip. Za razliku od apply_attribution() NIJE
	 * idempotentno-gated - ovo je autoritativna korekcija (WCS obnova/switch/
	 * upsell), zove se namjerno i nakon što je generički hook već upisao
	 * privremeni "acquisition" default.
	 */
	private static function copy_from_source( \WC_Order $destination, \WC_Order $source, string $type ): void {

		foreach ( self::META_MAP as $meta_key ) {
			$val = $source->get_meta( $meta_key );
			if ( '' !== $val ) {
				$destination->update_meta_data( $meta_key, $val );
			}
		}
		$destination->update_meta_data( self::META_TYPE, $type );
		$destination->save();

		RPSM_Checkout_Debug::info(
			'Atribucija kopirana s izvorne narudžbe/pretplate',
			[
				'destination_id' => $destination->get_id(),
				'source_id'      => $source->get_id(),
				'type'           => $type,
			]
		);
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  WCS: obnove i switchevi                                       */
	/* ══════════════════════════════════════════════════════════════ */

	/**
	 * Safety net: order -> subscription. WCS kopira meta automatski
	 * (exclusion lista ne smeta našim ključevima), ali eksplicitno
	 * osiguravamo naše ključeve - isti pattern kao R1-računov
	 * RPOM_R1_Subscriptions::copy_r1_to_subscription().
	 */
	public static function on_subscription_created( \WC_Subscription $subscription, \WC_Order $order, $recurring_cart = null ): void {
		$copied = false;

		foreach ( self::META_MAP as $meta_key ) {
			$val = $order->get_meta( $meta_key );
			if ( '' !== $val ) {
				$subscription->update_meta_data( $meta_key, $val );
				$copied = true;
			}
		}

		$type = $order->get_meta( self::META_TYPE );
		if ( '' !== $type ) {
			$subscription->update_meta_data( self::META_TYPE, $type );
			$copied = true;
		}

		if ( $copied ) {
			$subscription->save();
		}
	}

	/**
	 * Renewal order nastaje iz subscriptiona, ne iz originalne narudžbe.
	 * ⚠️ Ključno: bez ovoga obnove padnu u "direct" (razvodne izvještaj) ili
	 * se broje kao nova akvizicija (napušu ROAS).
	 */
	public static function on_renewal_order_created( $renewal_order, $subscription = null ) {
		// Defenzivno: filter moze dobiti sto drugi plugini vrate; ne fatalaj.
		// WC_Subscription extends WC_Order pa instanceof WC_Order hvata i pretplatu.
		if ( $renewal_order instanceof \WC_Order && $subscription instanceof \WC_Order ) {
			self::copy_from_source( $renewal_order, $subscription, 'renewal' );
		}
		return $renewal_order; // ⚠️ FILTER - UVIJEK vrati order netaknut.
	}

	/**
	 * Switch narudžba nastaje kroz normalni checkout (isti hookovi gore je
	 * već obrade s privremenim "acquisition" tipom), ali switch je nastavak
	 * postojeće pretplate, ne nova akvizicija - zato ovdje force-amo
	 * kopiranje s pretplate i tip 'renewal' (isto grupiranje kao obnove,
	 * po specifikaciji).
	 *
	 * Hook: woocommerce_subscription_checkout_switch_order_processed, fired
	 * unutar WC_Subscriptions_Switcher::process_checkout() ODMAH nakon što je
	 * WCS potvrdio da je narudžba switch i postavio subscription_switch_data -
	 * pouzdaniji trenutak za detekciju nego nagađanje preko wcs_order_contains_switch()
	 * na woocommerce_checkout_create_order (switch meta tada još nije spremljena).
	 */
	public static function on_switch_order_processed( \WC_Order $order, array $switch_order_data ): void {
		foreach ( array_keys( $switch_order_data ) as $subscription_id ) {
			$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : null;
			if ( $subscription ) {
				self::copy_from_source( $order, $subscription, 'renewal' );
				return; // order-level tip je singularan - prvi pronađeni izvor je dovoljan
			}
		}
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Admin: stupac "Izvor" u listi narudžbi                       */
	/* ══════════════════════════════════════════════════════════════ */

	public static function add_admin_column( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new_columns['rpsm_attr'] = 'Izvor';
			}
		}
		return $new_columns;
	}

	public static function render_admin_column( $column, $order ): void {
		if ( 'rpsm_attr' !== $column ) {
			return;
		}
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order ) {
			return;
		}

		$source   = $order->get_meta( self::META_MAP['first_source'] );
		$campaign = $order->get_meta( self::META_MAP['first_campaign'] );
		$type     = $order->get_meta( self::META_TYPE );

		if ( '' === $source && '' === $campaign && '' === $type ) {
			echo '<span style="color:#aaa;">-</span>';
			return;
		}

		$label = trim( $source . ( $campaign ? ' / ' . $campaign : '' ) );
		if ( '' === $label ) {
			$label = 'bez privole';
		}

		printf(
			'<span title="%s">%s</span>%s',
			esc_attr( self::build_tooltip( $order ) ),
			esc_html( $label ),
			$type ? ' <br><small style="color:#888;">' . esc_html( $type ) . '</small>' : ''
		);
	}

	/**
	 * Legacy CPT wrapper - manage_shop_order_posts_custom_column prima post_id, ne WC_Order.
	 */
	public static function render_admin_column_legacy( $column, $post_id ): void {
		if ( 'rpsm_attr' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			self::render_admin_column( $column, $order );
		}
	}

	private static function build_tooltip( \WC_Order $order ): string {
		$lines = [];
		foreach ( self::META_MAP as $data_key => $meta_key ) {
			$val = $order->get_meta( $meta_key );
			if ( '' !== $val ) {
				$lines[] = $data_key . ': ' . $val;
			}
		}
		return implode( "\n", $lines );
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Admin: cijeli lanac na order edit stranici                   */
	/* ══════════════════════════════════════════════════════════════ */

	public static function render_meta_box( \WC_Order $order ): void {

		$type = $order->get_meta( self::META_TYPE );

		$rows = [
			'Prvi izvor'      => $order->get_meta( '_rpsm_attr_first_source' ),
			'Prvi medij'      => $order->get_meta( '_rpsm_attr_first_medium' ),
			'Prva kampanja'   => $order->get_meta( '_rpsm_attr_first_campaign' ),
			'Prvi sadržaj'    => $order->get_meta( '_rpsm_attr_first_content' ),
			'Prvi termin'     => $order->get_meta( '_rpsm_attr_first_term' ),
			'Prvi landing'    => $order->get_meta( '_rpsm_attr_first_landing' ),
			'Prvi dodir'      => self::format_ts( $order->get_meta( '_rpsm_attr_first_ts' ) ),
			'Zadnji izvor'    => $order->get_meta( '_rpsm_attr_last_source' ),
			'Zadnji medij'    => $order->get_meta( '_rpsm_attr_last_medium' ),
			'Zadnja kampanja' => $order->get_meta( '_rpsm_attr_last_campaign' ),
			'Landing (CTA)'   => $order->get_meta( '_rpsm_attr_lp' ),
			'CTA'             => $order->get_meta( '_rpsm_attr_cta' ),
			'Click ID'        => $order->get_meta( '_rpsm_attr_click_id' ),
		];

		$non_empty = array_filter( $rows, static fn( $v ) => '' !== (string) $v );

		if ( '' === $type && empty( $non_empty ) ) {
			return; // nema nikakve atribucije - ne prikazuj prazan blok
		}

		echo '<div class="rpsm-attr-box" style="margin-top:12px;padding:10px 12px;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;">';
		echo '<h4 style="margin:0 0 6px;">Atribucija</h4>';
		echo '<table style="width:100%;font-size:12px;">';
		printf( '<tr><td style="padding:2px 8px 2px 0;color:#666;width:40%%;">Tip</td><td><strong>%s</strong></td></tr>', esc_html( $type ?: 'bez privole' ) );

		foreach ( $rows as $label => $val ) {
			if ( '' === (string) $val ) {
				continue;
			}
			printf( '<tr><td style="padding:2px 8px 2px 0;color:#666;">%s</td><td>%s</td></tr>', esc_html( $label ), esc_html( $val ) );
		}

		echo '</table></div>';
	}

	private static function format_ts( $ts ): string {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '';
		}
		return wp_date( 'Y-m-d H:i', $ts );
	}
}
