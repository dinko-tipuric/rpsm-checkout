<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Scroll Block - prevent WooCommerce auto-scroll to top, allow scroll only to error/notice.
 *
 * Injects JS via wp_footer (all logic is client-side).
 */
final class RPSM_Checkout_Module_Scroll_Block {

	public static function init(): void {
		add_action( 'wp_footer', [ __CLASS__, 'inject_script' ], 99 );
		add_action( 'template_redirect', [ __CLASS__, 'purge_success_notices' ], 20 );
	}

	/**
	 * Success notice-i ("dodano u kosaricu" od buy-now linka) na checkoutu
	 * nemaju svrhu, a Elementor ih re-renderira pri SVAKOM AJAX updateu pa
	 * observer svaki put dopusti scroll -> stranica skace na vrh kod svake
	 * promjene (staging nalaz). Brisemo ih prije rendera; greske ostaju.
	 */
	public static function purge_success_notices(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		if ( ! WC()->session || ! function_exists( 'wc_get_notices' ) ) {
			return;
		}
		$notices = wc_get_notices();
		if ( ! empty( $notices['success'] ) ) {
			unset( $notices['success'] );
			wc_set_notices( $notices );
		}
	}

	/**
	 * Only runs on checkout pages.
	 */
	public static function inject_script(): void {
		if ( ! is_checkout() ) {
			return;
		}
		// JS logic handled in rpsm-checkout-public.js via rpsmCheckout.scrollBlock flag
		// No inline script needed - the public JS reads the localized data
	}
}
