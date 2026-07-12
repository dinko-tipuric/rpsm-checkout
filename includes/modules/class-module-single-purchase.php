<?php
defined( 'ABSPATH' ) || exit;

/**
 * Jednokratna kupnja: odabrani proizvodi se mogu kupiti samo JEDNOM po kupcu.
 *
 * Nastanak (produkcijski nalaz 2026-07-12): kupac je isti program platio
 * dvaput u dva dana - shop nema povijesnu zastitu, WC "sold individually"
 * ogranicava samo kolicinu unutar iste kosarice, a upsell skip-owned stiti
 * samo upsell blok.
 *
 * Tri linije obrane:
 *  1. add-to-cart validacija (pokriva i Buy Now / ?add-to-cart= linkove
 *     i programatske add-ove poput rpsm-upsell)
 *  2. provjera kosarice (kupac se ulogirao NAKON dodavanja - stavka se
 *     uklanja uz jasnu poruku)
 *  3. checkout validacija po billing emailu (jos nema racuna / drugi email)
 *
 * Vlasnistvo = wc_customer_bought_product (samo PLACENE narudzbe; pending
 * ne blokira). Pretplate se ne stavljaju na listu - WCS ima svoju logiku.
 */
final class RPSM_Checkout_Module_Single_Purchase {

	/** Per-request memo za already_bought (katalog petlje zovu is_purchasable vise puta). */
	private static array $bought_memo = [];

	public static function init(): void {
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_add' ], 20, 2 );
		add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'validate_cart_items' ] );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_checkout' ], 10, 2 );
		/* Zasticeni proizvodi ni u ISTOJ kosarici ne mogu biti 2x */
		add_filter( 'woocommerce_is_sold_individually', [ __CLASS__, 'force_sold_individually' ], 10, 2 );
		/* Vlasniku se kupnja uopce ne nudi: nestaju add-to-cart forma i Buy Now
		   gumb (renderiraju se unutar forme), katalog pokazuje "Saznaj vise" */
		add_filter( 'woocommerce_is_purchasable', [ __CLASS__, 'hide_purchase_for_owner' ], 20, 2 );
		add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_owned_notice' ], 31 );
	}

	private static function product_ids(): array {
		return RPSM_Checkout_Options::get_product_ids( RPSM_Checkout_Options::SINGLE_PURCHASE_PRODUCTS );
	}

	private static function is_protected( int $product_id ): bool {
		return in_array( $product_id, self::product_ids(), true );
	}

	/**
	 * Je li kupac vec kupio proizvod (placene narudzbe - processing/completed).
	 * Bez user_id/emaila (nepoznat posjetitelj) vraca false - njega hvata
	 * checkout validacija po billing emailu.
	 */
	private static function already_bought( int $product_id, int $user_id = 0, string $email = '' ): bool {
		if ( $user_id <= 0 && '' === $email ) {
			$user_id = get_current_user_id();
			$email   = $user_id > 0 ? (string) wp_get_current_user()->user_email : '';
		}
		if ( $user_id <= 0 && '' === $email ) {
			return false;
		}
		$memo_key = $product_id . '|' . $user_id . '|' . $email;
		if ( ! isset( self::$bought_memo[ $memo_key ] ) ) {
			self::$bought_memo[ $memo_key ] = (bool) wc_customer_bought_product( $email, $user_id, $product_id );
		}
		return self::$bought_memo[ $memo_key ];
	}

	/** Poruka kupcu ({proizvod} placeholder) + link na Moj racun. */
	private static function notice_text( int $product_id ): string {
		$product = wc_get_product( $product_id );
		$name    = $product ? $product->get_name() : ( '#' . $product_id );
		$msg     = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::SINGLE_PURCHASE_MESSAGE );
		$msg     = str_replace( '{proizvod}', $name, $msg );

		$link_text = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::SINGLE_PURCHASE_LINK_TEXT );
		$link      = '';
		if ( '' !== trim( $link_text ) ) {
			$link = sprintf(
				' <a href="%s">%s</a>',
				esc_url( wc_get_page_permalink( 'myaccount' ) ),
				esc_html( $link_text )
			);
		}
		return esc_html( $msg ) . $link;
	}

	/* ── 1. Add-to-cart ────────────────────────────────────────────── */

	public static function validate_add( $passed, $product_id ): bool {
		$passed     = (bool) $passed;
		$product_id = (int) $product_id;
		if ( ! $passed || ! self::is_protected( $product_id ) ) {
			return $passed;
		}
		if ( self::already_bought( $product_id ) ) {
			wc_add_notice( self::notice_text( $product_id ), 'error' );
			RPSM_Checkout_Debug::info( 'Jednokratna kupnja: add-to-cart blokiran (vec kupljeno)', [
				'product' => $product_id,
				'user'    => get_current_user_id(),
			], 'single-purchase' );
			return false;
		}
		return $passed;
	}

	/* ── 2. Kosarica (login nakon dodavanja) ───────────────────────── */

	public static function validate_cart_items(): void {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$pid = (int) $item['product_id'];
			if ( ! self::is_protected( $pid ) || ! self::already_bought( $pid ) ) {
				continue;
			}
			WC()->cart->remove_cart_item( $key );
			wc_add_notice( self::notice_text( $pid ), 'error' );
			RPSM_Checkout_Debug::info( 'Jednokratna kupnja: stavka uklonjena iz kosarice (vec kupljeno)', [
				'product' => $pid,
				'user'    => get_current_user_id(),
			], 'single-purchase' );
		}
	}

	/* ── 3. Checkout (billing email) ───────────────────────────────── */

	public static function validate_checkout( $data, $errors ): void {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$email = strtolower( trim( (string) ( $data['billing_email'] ?? '' ) ) );
		if ( '' === $email ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$pid = (int) $item['product_id'];
			if ( ! self::is_protected( $pid ) ) {
				continue;
			}
			if ( self::already_bought( $pid, get_current_user_id(), $email ) ) {
				$errors->add( 'rpsm_single_purchase_' . $pid, self::notice_text( $pid ) );
				RPSM_Checkout_Debug::info( 'Jednokratna kupnja: checkout blokiran (vec kupljeno po emailu)', [
					'product' => $pid,
					'email'   => $email,
				], 'single-purchase' );
			}
		}
	}

	/* ── Sold individually ─────────────────────────────────────────── */

	public static function force_sold_individually( $sold_individually, $product ) {
		if ( $product instanceof WC_Product && self::is_protected( (int) $product->get_id() ) ) {
			return true;
		}
		return $sold_individually;
	}

	/* ── Prikaz vlasniku: bez kupnje + info poruka ─────────────────── */

	public static function hide_purchase_for_owner( $purchasable, $product ) {
		if ( ! $purchasable || ! $product instanceof WC_Product ) {
			return $purchasable;
		}
		$pid = (int) $product->get_id();
		if ( self::is_protected( $pid ) && self::already_bought( $pid ) ) {
			return false;
		}
		return $purchasable;
	}

	/** Na stranici proizvoda objasni ZASTO nema gumba za kupnju. */
	public static function render_owned_notice(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$pid = (int) $product->get_id();
		if ( ! self::is_protected( $pid ) || ! self::already_bought( $pid ) ) {
			return;
		}
		echo '<div class="woocommerce-info rpsm-single-purchase-owned" style="margin:12px 0;">' . wp_kses_post( self::notice_text( $pid ) ) . '</div>';
	}
}
