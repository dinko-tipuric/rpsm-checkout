<?php
defined( 'ABSPATH' ) || exit;

/**
 * Public orchestrator - loads enabled modules + enqueues assets.
 */
final class RPSM_Checkout_Public {

	/**
	 * Module slug => option toggle (or array of toggles for multi-option modules).
	 */
	private const MODULES = [
		'legal'            => RPSM_Checkout_Options::LEGAL_ENABLED,
		'payment-display'  => RPSM_Checkout_Options::PAYMENT_LOGOS_ENABLED,
		'bacs-control'     => RPSM_Checkout_Options::BACS_CONTROL_ENABLED,
		'coupons'          => [ RPSM_Checkout_Options::COUPON_HIDE_ENABLED, RPSM_Checkout_Options::COUPON_URL_ENABLED ],
		'editable-cart'    => RPSM_Checkout_Options::EDITABLE_CART_ENABLED,
		'scroll-block'     => RPSM_Checkout_Options::SCROLL_BLOCK_ENABLED,
		'single-purchase'  => RPSM_Checkout_Options::SINGLE_PURCHASE_ENABLED,
		'buy-now'          => RPSM_Checkout_Options::BUY_NOW_ENABLED,
		'email-validation' => RPSM_Checkout_Options::EMAIL_VAL_ENABLED,
		'thankyou'         => RPSM_Checkout_Options::THANKYOU_ENABLED,
		'translations'     => RPSM_Checkout_Options::TRANSLATIONS_ENABLED,
		'fields'           => [ RPSM_Checkout_Options::SHIPPING_PHONE_ENABLED, RPSM_Checkout_Options::EMAIL_AS_USERNAME_ENABLED ],
	];

	public static function init(): void {

		/* Enqueue frontend assets */
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		/* Load each enabled module */
		foreach ( self::MODULES as $slug => $toggle ) {
			if ( ! self::is_module_enabled( $toggle ) ) {
				continue;
			}

			$file  = RPSM_CHECKOUT_PLUGIN_DIR . "includes/modules/class-module-{$slug}.php";
			if ( ! file_exists( $file ) ) {
				continue;
			}

			require_once $file;

			/* Convert slug to class name: 'email-validation' → 'RPSM_Checkout_Module_Email_Validation' */
			$suffix = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $slug ) ) );
			$class  = 'RPSM_Checkout_Module_' . $suffix;

			if ( class_exists( $class ) && method_exists( $class, 'init' ) ) {
				$class::init();
			}
		}
	}

	/**
	 * Check if a module's toggle(s) indicate it should load.
	 */
	/**
	 * @param string|array $toggle
	 */
	private static function is_module_enabled( $toggle ): bool {
		if ( is_array( $toggle ) ) {
			/* Load if ANY of the toggles is enabled */
			foreach ( $toggle as $t ) {
				if ( '1' === RPSM_Checkout_Options::get( $t ) ) {
					return true;
				}
			}
			return false;
		}
		return '1' === RPSM_Checkout_Options::get( $toggle );
	}

	/* ── Frontend assets ───────────────────────────────────────────── */

	public static function enqueue_assets(): void {

		/* CSS - load on checkout + product pages */
		if ( is_checkout() || is_product() || is_cart() ) {
			wp_enqueue_style(
				'rpsm-checkout-public',
				RPSM_CHECKOUT_PLUGIN_URL . 'public/css/rpsm-checkout-public.css',
				[],
				RPSM_CHECKOUT_VERSION
			);
		}

		/* JS - load on checkout (most modules need it) */
		if ( is_checkout() ) {
			wp_enqueue_script(
				'rpsm-checkout-public',
				RPSM_CHECKOUT_PLUGIN_URL . 'public/js/rpsm-checkout-public.js',
				[ 'jquery' ],
				RPSM_CHECKOUT_VERSION,
				true
			);

			wp_localize_script( 'rpsm-checkout-public', 'rpsmCheckout', self::get_js_data() );
		}
	}

	/**
	 * Build data object for wp_localize_script - only active modules' data.
	 */
	private static function get_js_data(): array {
		$data = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		];

		/* Email Validation */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_ENABLED ) ) {
			$data['emailVal'] = [
				'tldFixes'    => RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_TLD_FIXES ),
				'domainFixes' => RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_DOMAIN_FIXES ),
				'hintText'    => RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_HINT_TEXT ),
				'btnFix'      => RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_BTN_FIX ),
				'btnKeep'     => RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_BTN_KEEP ),
			];
		}

		/* Scroll Block */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::SCROLL_BLOCK_ENABLED ) ) {
			$data['scrollBlock'] = true;
		}

		/* Editable Cart */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EDITABLE_CART_ENABLED ) ) {
			$mode = RPSM_Checkout_Options::get( RPSM_Checkout_Options::EDITABLE_CART_MODE );
			if ( 'summary_x' === $mode ) {
				$data['editableCartX'] = [
					'endpoint' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'rpsm_checkout_remove_item' ) : '',
				];
			} else {
				$data['editableCart'] = true;
			}
		}

		/* Payment Display - trigger checkout update on gateway change */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::PAYMENT_LOGOS_ENABLED ) ) {
			$data['paymentLogos'] = [
				'gateway' => RPSM_Checkout_Options::get( RPSM_Checkout_Options::PAYMENT_LOGOS_GATEWAY ),
			];
		}

		return $data;
	}
}
