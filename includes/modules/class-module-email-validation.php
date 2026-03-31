<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Email Validation — JS inline suggestion + PHP hard stop for typos.
 *
 * JS part is in rpsm-checkout-public.js (reads rpsmCheckout.emailVal data).
 * PHP part validates on woocommerce_after_checkout_validation.
 */
final class RPSM_Checkout_Module_Email_Validation {

	public static function init(): void {
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_email' ], 10, 2 );
	}

	/**
	 * Server-side validation — hard stop for known bad patterns.
	 */
	public static function validate_email( array $data, \WP_Error $errors ): void {

		$email = $data['billing_email'] ?? '';
		if ( '' === $email ) {
			return;
		}

		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return;
		}

		$domain = strtolower( $parts[1] );

		/* 1. Comma in domain */
		if ( false !== strpos( $domain, ',' ) ) {
			$errors->add(
				'rpsm_email_comma',
				RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_ERR_COMMA )
			);
			return;
		}

		/* 2. Bad TLD */
		$tld_map = self::parse_fix_list( RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_TLD_FIXES ) );
		$tld     = substr( $domain, strrpos( $domain, '.' ) + 1 );
		if ( isset( $tld_map[ $tld ] ) ) {
			$errors->add(
				'rpsm_email_tld',
				RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_ERR_TLD )
			);
			return;
		}

		/* 3. Bad domain name */
		$domain_map  = self::parse_fix_list( RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_DOMAIN_FIXES ) );
		$domain_name = substr( $domain, 0, strrpos( $domain, '.' ) ); // gmail from gmail.com
		if ( isset( $domain_map[ $domain_name ] ) ) {
			$errors->add(
				'rpsm_email_domain',
				RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_VAL_ERR_DOMAIN )
			);
		}
	}

	/**
	 * Parse "bad:good,bad2:good2" into [ bad => good ] map.
	 */
	private static function parse_fix_list( string $raw ): array {
		$map = [];
		foreach ( explode( ',', $raw ) as $pair ) {
			$kv = explode( ':', trim( $pair ) );
			if ( 2 === count( $kv ) ) {
				$map[ strtolower( trim( $kv[0] ) ) ] = strtolower( trim( $kv[1] ) );
			}
		}
		return $map;
	}
}
