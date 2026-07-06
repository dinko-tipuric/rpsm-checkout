<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Buy Now - "Idi na plaćanje" button on product page (simple products only).
 */
final class RPSM_Checkout_Module_Buy_Now {

	public static function init(): void {
		add_action( 'woocommerce_after_add_to_cart_button', [ __CLASS__, 'render_button' ] );

		/* v1.1.2.0: nakon sto WC obradi ?add-to-cart na checkoutu, ocisti URL.
		   Inace svaki reload stranice ponovno pokusa dodati proizvod pa
		   "sold individually" artikli bacaju error notice. */
		add_action( 'template_redirect', [ __CLASS__, 'clean_checkout_url' ], 20 );
	}

	/**
	 * Redirect na cisti checkout URL nakon add-to-cart obrade.
	 */
	public static function clean_checkout_url(): void {
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		if ( ! isset( $_GET['add-to-cart'] ) ) { // phpcs:ignore
			return;
		}
		wp_safe_redirect( remove_query_arg( [ 'add-to-cart', 'quantity', 'variation_id' ] ) );
		exit;
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
