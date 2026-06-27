<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Fields - shipping phone + email as username.
 */
final class RPSM_Checkout_Module_Fields {

	public static function init(): void {
		/* Shipping phone */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::SHIPPING_PHONE_ENABLED ) ) {
			add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'add_shipping_phone' ] );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'display_admin_shipping_phone' ] );
		}

		/* Email as username */
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::EMAIL_AS_USERNAME_ENABLED ) ) {
			add_filter( 'woocommerce_new_customer_username', [ __CLASS__, 'use_email_as_username' ], 10, 4 );
		}
	}

	/**
	 * Use full email as WooCommerce username (consistent with MemberPress).
	 */
	public static function use_email_as_username( string $username, string $email, array $new_user_args, string $suffix ): string {
		return $email;
	}

	/**
	 * Add required shipping phone field.
	 */
	public static function add_shipping_phone( array $fields ): array {
		$fields['shipping']['shipping_phone'] = [
			'type'     => 'tel',
			'label'    => 'Telefon za dostavu',
			'required' => true,
			'class'    => [ 'form-row-wide' ],
			'priority' => 25,
		];
		return $fields;
	}

	/**
	 * Display shipping phone in admin order edit.
	 */
	public static function display_admin_shipping_phone( \WC_Order $order ): void {
		$phone = $order->get_meta( '_shipping_phone' );
		if ( '' !== $phone ) {
			echo '<p><strong>Telefon za dostavu:</strong> ' . esc_html( $phone ) . '</p>';
		}
	}
}
