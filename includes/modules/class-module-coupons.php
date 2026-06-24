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

		/* Restrict to switches that target a configured product/variation. */
		$targets = RPSM_Checkout_Options::get_product_ids( RPSM_Checkout_Options::COUPON_SWITCH_PRODUCTS );
		if ( empty( $targets ) || ! self::switch_cart_has_target( $targets ) ) {
			return;
		}

		/* Ensure session is active so applied coupons persist into checkout. */
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$applied = array_map( 'strtolower', WC()->cart->get_applied_coupons() );

		$codes = [
			RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_ONCE ),
			RPSM_Checkout_Options::get( RPSM_Checkout_Options::COUPON_SWITCH_CODE_RECUR ),
		];

		foreach ( $codes as $code ) {
			$code = trim( (string) $code );
			if ( '' === $code ) {
				continue;
			}
			if ( in_array( strtolower( $code ), $applied, true ) ) {
				continue;
			}
			WC()->cart->apply_coupon( $code );
		}
	}

	/**
	 * True if any subscription-switch cart item matches one of the target IDs
	 * (product or variation).
	 */
	private static function switch_cart_has_target( array $targets ): bool {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['subscription_switch'] ) ) {
				continue;
			}
			$product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
			if ( in_array( $product_id, $targets, true ) || in_array( $variation_id, $targets, true ) ) {
				return true;
			}
		}
		return false;
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
