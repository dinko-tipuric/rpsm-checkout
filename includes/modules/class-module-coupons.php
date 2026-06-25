<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Coupons — hide coupon form if applied + apply coupon from URL.
 */
final class RPSM_Checkout_Module_Coupons {

	public static function init(): void {

		/* Hide coupon form if already applied */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_HIDE_ENABLED ) ) {
			add_filter( 'woocommerce_coupons_enabled', [ __CLASS__, 'hide_coupon_if_applied' ] );
		}

		/* Apply coupon from URL (?coupon=CODE) */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_URL_ENABLED ) ) {
			add_action( 'wp_loaded', [ __CLASS__, 'apply_coupon_from_url' ], 30 );
			add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'apply_coupon_from_url' ] );
		}

		/* Auto-apply switch coupon(s) when changing a subscription to a target product */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_ENABLED ) ) {
			add_action( 'wp_loaded', [ __CLASS__, 'auto_apply_switch_coupons' ], 35 );
			add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'auto_apply_switch_coupons' ] );
		}

		/* Force-render coupon form on the switch checkout (bypasses Elementor coupon toggle) */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_SHOW_FIELD ) ) {
			add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_switch_coupon_form' ], 9 );
		}

		/* Diagnostics: whenever Debug is ON and a switch is in the cart, log the real
		 * cart item IDs - independent of the auto-apply toggle / coupon codes, so the
		 * correct target product/variation can be identified (e.g. grouped products). */
		if ( RPSM_Checkout_Debug::is_enabled() ) {
			add_action( 'wp_loaded', [ __CLASS__, 'log_switch_diagnostics' ], 36 );
			add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'log_switch_diagnostics' ] );
		}
	}

	/**
	 * Log a snapshot of any subscription switch in the cart, so the real
	 * product/variation IDs are visible in the debug log. Runs only when Debug
	 * mode is enabled. Logged once per request (guarded by a static flag).
	 */
	public static function log_switch_diagnostics(): void {
		static $logged = false;
		if ( $logged ) {
			return;
		}
		if ( ! function_exists( 'wcs_cart_contains_subscription_switch' ) || ! WC()->cart ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! wcs_cart_contains_subscription_switch() ) {
			return;
		}
		$logged = true;

		RPSM_Checkout_Debug::info(
			'DIJAGNOSTIKA: switch detektiran u košarici.',
			[
				'switch_items'        => self::describe_switch_items(),
				'configured_targets'  => RPSM_Checkout_Options::get_product_ids( RPSM_Checkout_Options::COUPON_SWITCH_PRODUCTS ),
				'auto_apply_enabled'  => RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_ENABLED ),
				'code_once_set'       => '' !== trim( (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_ONCE ) ),
				'code_recur_set'      => '' !== trim( (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_RECUR ) ),
				'applied_coupons'     => WC()->cart->get_applied_coupons(),
			],
			'Coupons::log_switch_diagnostics'
		);
	}

	/**
	 * Disable coupon form on checkout if a coupon is already applied.
	 */
	public static function hide_coupon_if_applied( bool $enabled ): bool {
		if ( is_checkout() && WC()->cart && ! empty( WC()->cart->get_applied_coupons() ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Apply coupon from ?coupon= URL parameter.
	 */
	public static function apply_coupon_from_url(): void {
		if ( ! isset( $_GET['coupon'] ) || '' === $_GET['coupon'] ) {
			return;
		}

		$coupon_code = sanitize_text_field( wp_unslash( $_GET['coupon'] ) );

		if ( ! WC()->cart ) {
			return;
		}

		/* Ensure session is active */
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$applied = array_map( 'strtolower', WC()->cart->get_applied_coupons() );
		if ( ! in_array( strtolower( $coupon_code ), $applied, true ) ) {
			WC()->cart->apply_coupon( $coupon_code );
		}
	}

	/**
	 * Auto-apply configured switch coupon(s) when the cart contains a subscription
	 * switch to one of the configured target products.
	 *
	 * Two independent codes are supported:
	 *  - "once"  : one-time discount on the switch payment (WC "Fixed cart discount")
	 *  - "recur" : discount on every future renewal (WCS "Recurring Product Discount")
	 * WooCommerce allows both to be applied to the same cart simultaneously.
	 */
	public static function auto_apply_switch_coupons(): void {
		if ( ! function_exists( 'wcs_cart_contains_subscription_switch' ) ) {
			return;
		}
		if ( ! WC()->cart ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! wcs_cart_contains_subscription_switch() ) {
			return;
		}

		$src = 'Coupons::auto_apply_switch_coupons';

		$codes = array_values( array_filter( array_map( 'trim', [
			(string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_ONCE ),
			(string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_RECUR ),
		] ) ) );
		if ( empty( $codes ) ) {
			RPSM_Checkout_Debug::debug( 'Switch detektiran, ali nijedan kupon kod nije konfiguriran.', [], $src );
			return;
		}

		/* Restrict to switches that target a configured product/variation. */
		$targets = RPSM_Checkout_Options::get_product_ids( RPSM_Checkout_Options::COUPON_SWITCH_PRODUCTS );
		$matched = $targets ? self::get_matched_switch_item( $targets ) : null;

		if ( empty( $targets ) || null === $matched ) {
			/* Log every switch item's IDs so the correct target can be picked in admin
			 * (with grouped products the cart carries the CHILD product/variation ID). */
			RPSM_Checkout_Debug::debug(
				'Switch ne odgovara ciljanim proizvodima - kupon NIJE primijenjen.',
				[
					'configured_targets' => $targets,
					'switch_cart_items'  => self::describe_switch_items(),
				],
				$src
			);
			return;
		}

		/* Skip applying the discount when the target product is already on sale, so a
		 * NEW switch doesn't stack on top of an existing reduced price. This ONLY
		 * prevents a fresh application on the current switch cart - it never removes
		 * a coupon, and it never touches existing subscriptions (grandfathered
		 * recurring coupons live on the subscription and persist across renewals). */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_SKIP_ON_SALE )
			&& self::cart_item_is_on_sale( $matched ) ) {
			RPSM_Checkout_Debug::info(
				'Ciljani proizvod je na popustu - switch kupon se NE primjenjuje na ovu novu narudžbu (skip-on-sale). Postojeće pretplate nisu dirane.',
				[ 'product' => self::describe_item( $matched ) ],
				$src
			);
			return;
		}

		/* Ensure session is active so applied coupons persist into checkout. */
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$applied = array_map( 'strtolower', WC()->cart->get_applied_coupons() );

		foreach ( $codes as $code ) {
			if ( in_array( strtolower( $code ), $applied, true ) ) {
				continue;
			}
			$ok = WC()->cart->apply_coupon( $code );
			RPSM_Checkout_Debug::info(
				'Switch kupon primijenjen.',
				[ 'code' => $code, 'result' => $ok ? 'ok' : 'odbijen', 'product' => self::describe_item( $matched ) ],
				$src
			);
		}
	}

	/**
	 * Return the first subscription-switch cart item whose product OR variation ID
	 * matches one of the target IDs, or null.
	 */
	private static function get_matched_switch_item( array $targets ): ?array {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['subscription_switch'] ) ) {
				continue;
			}
			$product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
			if ( in_array( $product_id, $targets, true ) || in_array( $variation_id, $targets, true ) ) {
				return $cart_item;
			}
		}
		return null;
	}

	/**
	 * Whether the product/variation in a cart item is currently on sale.
	 */
	private static function cart_item_is_on_sale( array $cart_item ): bool {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			$id      = (int) ( $cart_item['variation_id'] ?? 0 ) ?: (int) ( $cart_item['product_id'] ?? 0 );
			$product = $id ? wc_get_product( $id ) : null;
		}
		return $product instanceof WC_Product ? $product->is_on_sale() : false;
	}

	/* ── Debug helpers ─────────────────────────────────────────────── */

	private static function describe_item( array $cart_item ): array {
		$product = $cart_item['data'] ?? null;
		$id      = (int) ( $cart_item['variation_id'] ?? 0 ) ?: (int) ( $cart_item['product_id'] ?? 0 );
		return [
			'product_id'   => (int) ( $cart_item['product_id'] ?? 0 ),
			'variation_id' => (int) ( $cart_item['variation_id'] ?? 0 ),
			'name'         => $product instanceof WC_Product ? $product->get_name() : ( $id ? "#{$id}" : 'n/a' ),
			'on_sale'      => self::cart_item_is_on_sale( $cart_item ),
		];
	}

	private static function describe_switch_items(): array {
		$out = [];
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['subscription_switch'] ) ) {
				continue;
			}
			$out[] = self::describe_item( $cart_item );
		}
		return $out;
	}

	/**
	 * Render the standard WooCommerce coupon form on the checkout when a switch is
	 * in progress. Skips if WC's own coupon form is still hooked (avoids duplicate)
	 * or if coupons are disabled (e.g. one already applied via COUPON_HIDE_ENABLED).
	 */
	public static function render_switch_coupon_form(): void {
		if ( ! function_exists( 'wcs_cart_contains_subscription_switch' ) ) {
			return;
		}
		if ( ! WC()->cart || ! wcs_cart_contains_subscription_switch() ) {
			return;
		}
		if ( ! wc_coupons_enabled() ) {
			return;
		}
		/* WC core hooks woocommerce_checkout_coupon_form at prio 10 - don't duplicate it. */
		if ( has_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' ) ) {
			return;
		}

		echo '<div class="rpsm-switch-coupon">';
		wc_get_template( 'checkout/form-coupon.php', [ 'checkout' => WC()->checkout() ] );
		echo '</div>';
	}
}
