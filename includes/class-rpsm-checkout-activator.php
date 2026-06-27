<?php
defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation - creates upload dir + seeds defaults.
 */
final class RPSM_Checkout_Activator {

	public static function activate(): void {
		self::create_upload_dir();
		self::seed_defaults();
		self::seed_translations();
	}

	private static function create_upload_dir(): void {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/rpsm-checkout';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/.htaccess', "deny from all\n" );
		}
	}

	/**
	 * Seed WP options that don't exist yet (preserves existing values).
	 */
	private static function seed_defaults(): void {
		require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-checkout-options.php';

		foreach ( RPSM_Checkout_Options::get_all_keys() as $key ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, RPSM_Checkout_Options::get_default( $key ) );
			}
		}
	}

	/**
	 * Seed default translation pairs if not yet set.
	 */
	private static function seed_translations(): void {
		require_once RPSM_CHECKOUT_PLUGIN_DIR . 'includes/class-rpsm-checkout-options.php';

		if ( '' !== get_option( RPSM_Checkout_Options::TRANSLATIONS_PAIRS, '' ) ) {
			return;
		}

		$defaults = [
			[
				'original'    => 'If you have shopped with us before, please enter your details in the boxes below. If you are a new customer, please proceed to the Billing section.',
				'translation' => 'Ako već imaš korisnički račun u #radimposvom zajednici, prijavi se ispod. Inače, nastavi s kupnjom kao novi korisnik.',
				'domain'      => 'woocommerce',
			],
			[
				'original'    => 'Lost your password?',
				'translation' => 'Zaboravljena lozinka?',
				'domain'      => 'woocommerce',
			],
			[
				'original'    => 'Remember me',
				'translation' => 'Zapamti me',
				'domain'      => 'woocommerce',
			],
			[
				'original'    => 'Email',
				'translation' => 'E-mail',
				'domain'      => 'woocommerce',
			],
			[
				'original'    => 'Login',
				'translation' => 'Prijava',
				'domain'      => 'woocommerce',
			],
			[
				'original'    => 'If you have a coupon code, please apply it below.',
				'translation' => 'Ako imaš kupon kod, unesi ga ispod.',
				'domain'      => 'elementor-pro',
			],
			[
				'original'    => 'Coupon code',
				'translation' => 'Kupon',
				'domain'      => 'elementor-pro',
			],
			[
				'original'    => 'Apply',
				'translation' => 'Primijeni',
				'domain'      => 'elementor-pro',
			],
		];

		update_option( RPSM_Checkout_Options::TRANSLATIONS_PAIRS, wp_json_encode( $defaults, JSON_UNESCAPED_UNICODE ) );
	}
}
