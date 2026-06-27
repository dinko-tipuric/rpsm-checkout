<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Editable Cart - display mini cart on checkout with qty change + remove.
 *
 * 4 parts: A) cart template, B) URL filters, C) auto-update JS (via localized data), D) redirect on empty.
 */
final class RPSM_Checkout_Module_Editable_Cart {

	public static function init(): void {
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_cart' ], 5 );
		add_filter( 'woocommerce_get_cart_url', [ __CLASS__, 'cart_url_to_checkout' ] );
		add_filter( 'woocommerce_cart_item_remove_link', [ __CLASS__, 'remove_link_to_checkout' ], 10, 2 );
		add_action( 'woocommerce_cart_item_removed', [ __CLASS__, 'set_emptied_flag' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_if_empty' ] );
	}

	/**
	 * Render cart.php template inside a wrapper above checkout form.
	 */
	public static function render_cart(): void {
		if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		echo '<div class="mv-checkout-cart">';
		wc_get_template( 'cart/cart.php' );
		echo '</div>';
	}

	/**
	 * Cart page link → checkout (so "Update cart" stays on checkout).
	 */
	public static function cart_url_to_checkout( string $url ): string {
		if ( is_checkout() ) {
			return wc_get_checkout_url();
		}
		return $url;
	}

	/**
	 * Modify remove link to redirect back to checkout after removal.
	 */
	public static function remove_link_to_checkout( string $link, string $cart_item_key ): string {
		if ( is_checkout() ) {
			/* Replace return-to-cart URL with checkout URL inside the link HTML */
			$link = str_replace(
				wc_get_cart_url(),
				wc_get_checkout_url(),
				$link
			);
		}
		return $link;
	}

	/**
	 * Set session flag when user removes last item.
	 */
	public static function set_emptied_flag(): void {
		if ( WC()->cart && 0 === WC()->cart->get_cart_contents_count() && WC()->session ) {
			WC()->session->set( 'rpsm_user_emptied_cart', '1' );
		}
	}

	/**
	 * Redirect to shop if user emptied cart on checkout.
	 */
	public static function redirect_if_empty(): void {
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( WC()->cart && 0 === WC()->cart->get_cart_contents_count() && WC()->session ) {
			$flag = WC()->session->get( 'rpsm_user_emptied_cart' );
			if ( '1' === $flag ) {
				WC()->session->set( 'rpsm_user_emptied_cart', '' );
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
				exit;
			}
		}
	}
}
