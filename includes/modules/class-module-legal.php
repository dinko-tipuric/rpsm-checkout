<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Legal - TnC checkbox + subscription notice.
 *
 * Hooks:
 * - woocommerce_checkout_after_terms_and_conditions (display)
 * - woocommerce_checkout_process (validation)
 */
final class RPSM_Checkout_Module_Legal {

	public static function init(): void {
		add_action( 'woocommerce_checkout_after_terms_and_conditions', [ __CLASS__, 'render_checkbox' ] );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'validate_checkbox' ] );
	}

	/**
	 * Render TnC checkbox + optional subscription notice.
	 */
	public static function render_checkbox(): void {
		$text = RPSM_Checkout_Options::get( RPSM_Checkout_Options::LEGAL_CHECKBOX_TEXT );
		if ( '' === $text ) {
			return;
		}

		echo '<p class="form-row validate-required extra_privacy">';
		echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
		echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="rpsm_checkout_tnc" id="rpsm_checkout_tnc" value="1">';
		echo '<span class="woocommerce-terms-and-conditions-checkbox-text">' . esc_html( $text ) . ' <abbr class="required" title="obavezno">*</abbr></span>';
		echo '</label>';
		echo '</p>';

		/* Subscription notice */
		if ( '1' !== RPSM_Checkout_Options::get( RPSM_Checkout_Options::LEGAL_SUB_NOTICE ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Subscriptions_Cart' ) || ! \WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return;
		}

		$notice_text = RPSM_Checkout_Options::get( RPSM_Checkout_Options::LEGAL_SUB_NOTICE_TEXT );
		if ( '' === $notice_text ) {
			return;
		}

		/* Bold the first word/phrase up to the first colon (or first space if no colon) */
		$html = esc_html( $notice_text );
		$colon_pos = strpos( $html, ':' );
		if ( false !== $colon_pos ) {
			$html = '<strong>' . substr( $html, 0, $colon_pos + 1 ) . '</strong>' . substr( $html, $colon_pos + 1 );
		}

		echo '<div class="rpsm-checkout-sub-notice">' . $html . '</div>';
	}

	/**
	 * Server-side validation - block checkout if not checked.
	 */
	public static function validate_checkbox(): void {
		/* Only validate if checkbox text is configured (i.e. checkbox is actually rendered) */
		$text = RPSM_Checkout_Options::get( RPSM_Checkout_Options::LEGAL_CHECKBOX_TEXT );
		if ( '' === $text ) {
			return;
		}

		if ( empty( $_POST['rpsm_checkout_tnc'] ) ) {
			wc_add_notice(
				'Moraš potvrditi suglasnost za nastavak kupnje.',
				'error'
			);
		}
	}
}
