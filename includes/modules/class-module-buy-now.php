<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Buy Now - "Idi na plaćanje" button on product page (simple products only).
 */
final class RPSM_Checkout_Module_Buy_Now {

	public static function init(): void {
		add_action( 'woocommerce_after_add_to_cart_button', [ __CLASS__, 'render_button' ] );
	}

	/**
	 * Render Buy Now button next to Add to Cart (simple products only).
	 */
	public static function render_button(): void {
		global $product;

		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return;
		}

		$text = RPSM_Checkout_Options::get( RPSM_Checkout_Options::BUY_NOW_TEXT );
		$url  = add_query_arg(
			[ 'add-to-cart' => $product->get_id(), 'quantity' => 1 ],
			wc_get_checkout_url()
		);

		printf(
			'<a href="%s" class="button alt mv-buy-now">%s</a>',
			esc_url( $url ),
			esc_html( $text )
		);
	}
}
