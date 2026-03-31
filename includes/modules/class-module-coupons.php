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
}
