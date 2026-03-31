<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Payment Display — card logos below checkout button for Stripe.
 *
 * - Triggers checkout update on payment method change (JS)
 * - Renders card image after submit button
 */
final class RPSM_Checkout_Module_Payment_Display {

	public static function init(): void {
		add_action( 'woocommerce_review_order_after_submit', [ __CLASS__, 'render_logos' ] );
	}

	/**
	 * Show card logos below checkout button when Stripe is selected.
	 */
	public static function render_logos(): void {
		$gateway = RPSM_Checkout_Options::get( RPSM_Checkout_Options::PAYMENT_LOGOS_GATEWAY );
		$url     = RPSM_Checkout_Options::get( RPSM_Checkout_Options::PAYMENT_LOGOS_URL );

		if ( '' === $url || '' === $gateway ) {
			return;
		}

		printf(
			'<div class="card_notice" data-rpsm-gateway="%s"><img src="%s" alt="Podržane kartice" class="img_center"></div>',
			esc_attr( $gateway ),
			esc_url( $url )
		);
	}
}
