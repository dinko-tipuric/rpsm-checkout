<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Fields — shipping phone field on checkout.
 */
final class RPSM_Checkout_Module_Fields {

	public static function init(): void {
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'add_shipping_phone' ] );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'display_admin_shipping_phone' ] );
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
