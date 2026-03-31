<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Translations — gettext overrides for WooCommerce + Elementor Pro.
 */
final class RPSM_Checkout_Module_Translations {

	private static ?array $pairs = null;

	public static function init(): void {
		add_filter( 'gettext', [ __CLASS__, 'translate' ], 20, 3 );
	}

	/**
	 * Override specific strings based on stored translation pairs.
	 */
	public static function translate( $translated, $text, $domain ): string {

		/* Guard: some plugins pass null for any of these */
		if ( ! is_string( $translated ) || ! is_string( $text ) || ! is_string( $domain ) ) {
			return (string) $translated;
		}

		if ( null === self::$pairs ) {
			self::load_pairs();
		}

		if ( empty( self::$pairs ) ) {
			return $translated;
		}

		/* Quick domain check — skip irrelevant domains */
		if ( 'woocommerce' !== $domain && 'elementor-pro' !== $domain ) {
			return $translated;
		}

		$key = $domain . '::' . $text;
		if ( isset( self::$pairs[ $key ] ) ) {
			return self::$pairs[ $key ];
		}

		return $translated;
	}

	/**
	 * Parse stored JSON into lookup map: "domain::original" => "translation".
	 */
	private static function load_pairs(): void {
		self::$pairs = [];

		$raw = RPSM_Checkout_Options::get( RPSM_Checkout_Options::TRANSLATIONS_PAIRS );
		if ( '' === $raw ) {
			return;
		}

		$arr = json_decode( $raw, true );
		if ( ! is_array( $arr ) ) {
			return;
		}

		foreach ( $arr as $pair ) {
			$orig  = $pair['original'] ?? '';
			$trans = $pair['translation'] ?? '';
			$dom   = $pair['domain'] ?? 'woocommerce';

			if ( '' !== $orig && '' !== $trans ) {
				self::$pairs[ $dom . '::' . $orig ] = $trans;
			}
		}
	}
}
