<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: BACS Control — hide BACS gateway for subscription products unless unlock coupon is applied.
 */
final class RPSM_Checkout_Module_Bacs_Control {

	public static function init(): void {
		add_filter( 'woocommerce_available_payment_gateways', [ __CLASS__, 'maybe_hide_bacs' ], 999 );
	}

	/**
	 * Remove BACS if cart contains restricted products and unlock coupon is not applied.
	 */
	public static function maybe_hide_bacs( array $gateways ): array {

		if ( ! isset( $gateways['bacs'] ) || is_admin() || ! is_checkout() ) {
			return $gateways;
		}

		$restricted = RPSM_Checkout_Options::get_product_ids( RPSM_Checkout_Options::BACS_CONTROL_PRODUCTS );
		if ( empty( $restricted ) ) {
			return $gateways;
		}

		/* Check if cart contains any restricted product */
		$cart_has_restricted = false;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( in_array( (int) $item['product_id'], $restricted, true ) ) {
					$cart_has_restricted = true;
					break;
				}
			}
		}

		if ( ! $cart_has_restricted ) {
			return $gateways;
		}

		/* Check if unlock coupon is applied */
		$coupon = strtolower( trim( RPSM_Checkout_Options::get( RPSM_Checkout_Options::BACS_CONTROL_COUPON ) ) );
		if ( '' !== $coupon && WC()->cart ) {
			$applied = array_map( 'strtolower', WC()->cart->get_applied_coupons() );
			if ( in_array( $coupon, $applied, true ) ) {
				return $gateways; // coupon applied → keep BACS
			}
		}

		unset( $gateways['bacs'] );
		return $gateways;
	}
}
