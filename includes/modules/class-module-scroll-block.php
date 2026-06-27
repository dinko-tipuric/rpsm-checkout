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
