<?php
/**
 * Plugin Name: RPSM Checkout
 * Plugin URI:  https://radimposvom.com.hr
 * Description: Checkout customizations za #radimposvom portal - TnC suglasnost, payment prikaz, kuponi, validacija, prijevodi i ostalo.
 * Version:     1.10.0.0
 * Author:      Business Labs d.o.o.
 * Author URI:  https://radimposvom.com.hr
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rpsm-checkout
 * Domain Path: /languages
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 * GitHub Plugin URI: dinko-tipuric/rpsm-checkout
 */

defined( 'ABSPATH' ) || exit;

/* ── Double-load guard ────────────────────────────────────────────── */
if ( defined( 'RPSM_CHECKOUT_VERSION' ) ) {
	return;
}

define( 'RPSM_CHECKOUT_VERSION', '1.10.0.0' );
define( 'RPSM_CHECKOUT_PLUGIN_FILE', __FILE__ );
define( 'RPSM_CHECKOUT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RPSM_CHECKOUT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/* ── GitHub auto-updater (immediately, before any hooks) ──────────── */
require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-github-updater.php';
new RPSM_GitHub_Updater_v2( __FILE__ );

/* ── HPOS compatibility ───────────────────────────────────────────── */
add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

/* ── Activation ───────────────────────────────────────────────────── */
register_activation_hook( __FILE__, static function (): void {
	require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-checkout-activator.php';
	RPSM_Checkout_Activator::activate();
} );

/* ── Boot on plugins_loaded (after WC=10, after rpsm-alati=11) ──── */
add_action( 'plugins_loaded', static function (): void {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( 'RPSM Checkout zahtijeva WooCommerce. Aktiviraj WooCommerce za korištenje ovog plugina.' );
			echo '</p></div>';
		} );
		return;
	}

	require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-checkout-options.php';
	require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-checkout-debug.php';

	RPSM_Checkout::instance();

}, 12 );

/* ══════════════════════════════════════════════════════════════════ */
/*  Main Singleton                                                   */
/* ══════════════════════════════════════════════════════════════════ */

final class RPSM_Checkout {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks(): void {

		/* Admin */
		if ( is_admin() ) {
			require_once RPSM_CHECKOUT_PLUGIN_DIR . 'admin/class-rpsm-checkout-admin.php';
			RPSM_Checkout_Admin::init();
		}

		/* Public / Frontend modules */
		require_once RPSM_CHECKOUT_PLUGIN_DIR . 'public/class-rpsm-checkout-public.php';
		RPSM_Checkout_Public::init();
	}
}

/* ── Helper for external code ─────────────────────────────────────── */
function rpsm_checkout_active(): bool {
	return defined( 'RPSM_CHECKOUT_VERSION' );
}
