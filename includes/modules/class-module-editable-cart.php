<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Editable Cart - mogucnost uklanjanja stavki na checkoutu.
 *
 * Dva moda (v1.2.0.0, opcija EDITABLE_CART_MODE):
 * - 'table':     zasebna kosarica (cart.php) iznad checkout forme + fragment sync (staro).
 * - 'summary_x': X gumb uz svaku stavku u sazetku "Tvoja narudzba" - nema druge
 *                kosarice, nema sync problema (sazetak JE fragment koji se osvjezava).
 *                Cilj je dugorocno ovim modom zamijeniti tablicu.
 *
 * Zajednicko za oba moda: cart URL -> checkout, emptied flag + redirect na shop.
 */
final class RPSM_Checkout_Module_Editable_Cart {

	private static bool $in_review = false;

	public static function init(): void {
		$mode = RPSM_Checkout_Options::get( RPSM_Checkout_Options::EDITABLE_CART_MODE );

		/* Zajednicko */
		add_filter( 'woocommerce_get_cart_url', [ __CLASS__, 'cart_url_to_checkout' ] );
		add_action( 'woocommerce_cart_item_removed', [ __CLASS__, 'set_emptied_flag' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_if_empty' ] );

		if ( 'summary_x' === $mode ) {
			/* X u sazetku narudzbe - samo unutar review-order konteksta */
			add_action( 'woocommerce_review_order_before_cart_contents', [ __CLASS__, 'review_flag_on' ] );
			add_action( 'woocommerce_review_order_after_cart_contents', [ __CLASS__, 'review_flag_off' ] );
			add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'add_remove_x' ], 20, 3 );
			add_action( 'wc_ajax_rpsm_checkout_remove_item', [ __CLASS__, 'ajax_remove_item' ] );
			/* Inline CSS fallback - imun na CSS agregat/cache (Autoptimize) */
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'inline_x_css' ], 20 );
			return;
		}

		/* 'table' mod (legacy) */
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_cart' ], 5 );
		add_filter( 'woocommerce_cart_item_remove_link', [ __CLASS__, 'remove_link_to_checkout' ], 10, 2 );
		add_filter( 'woocommerce_update_order_review_fragments', [ __CLASS__, 'cart_fragment' ] );
	}

	/* ══════════ Mod: summary_x ══════════ */

	/**
	 * Inline kopija X stilova na checkout stranici - i ako je vanjski CSS
	 * zaostao u agregatu/cacheu, gumb je ispravno stiliziran.
	 */
	public static function inline_x_css(): void {
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		$css = 'button.rpsm-review-remove{display:inline-grid!important;place-items:center;width:21px!important;height:21px!important;min-height:0!important;margin:0 6px 0 0!important;padding:0!important;border:1.5px solid #d17954!important;border-radius:50%!important;background:#fff!important;color:#d17954!important;font-size:15px!important;line-height:1!important;font-weight:700!important;cursor:pointer;vertical-align:-3px;box-shadow:none!important;}button.rpsm-review-remove:hover{background:#993a25!important;border-color:#993a25!important;color:#fff!important;}button.rpsm-review-remove:disabled{opacity:.5;cursor:wait;}';
		if ( wp_style_is( 'rpsm-checkout-public', 'enqueued' ) || wp_style_is( 'rpsm-checkout-public', 'registered' ) ) {
			wp_add_inline_style( 'rpsm-checkout-public', $css );
		} else {
			wp_register_style( 'rpsm-checkout-inline-x', false, [], RPSM_CHECKOUT_VERSION );
			wp_enqueue_style( 'rpsm-checkout-inline-x' );
			wp_add_inline_style( 'rpsm-checkout-inline-x', $css );
		}
	}

	public static function review_flag_on(): void {
		self::$in_review = true;
	}

	public static function review_flag_off(): void {
		self::$in_review = false;
	}

	/**
	 * Dodaj vidljivi X gumb ispred naziva stavke u "Tvoja narudzba".
	 * Flag garantira da se ne dira mini-cart, cart stranica ni emailovi.
	 */
	public static function add_remove_x( $name, $cart_item, $cart_item_key ) {
		if ( ! self::$in_review || empty( $cart_item_key ) ) {
			return $name;
		}
		$x = sprintf(
			'<button type="button" class="rpsm-review-remove" data-cart-key="%s" data-nonce="%s" aria-label="%s" title="%s">&times;</button> ',
			esc_attr( $cart_item_key ),
			esc_attr( wp_create_nonce( 'rpsm-checkout-remove' ) ),
			esc_attr( 'Ukloni iz narudžbe' ),
			esc_attr( 'Ukloni iz narudžbe' )
		);
		return $x . $name;
	}

	/**
	 * AJAX uklanjanje stavke iz sazetka.
	 */
	public static function ajax_remove_item(): void {
		check_ajax_referer( 'rpsm-checkout-remove', 'nonce' );

		$key = sanitize_text_field( wp_unslash( $_POST['cart_key'] ?? '' ) );
		if ( '' === $key || ! WC()->cart || ! isset( WC()->cart->get_cart()[ $key ] ) ) {
			wp_send_json_error( [ 'message' => 'Stavka nije pronađena.' ] );
		}

		WC()->cart->remove_cart_item( $key );

		wp_send_json_success( [
			'cart_empty' => 0 === WC()->cart->get_cart_contents_count(),
		] );
	}

	/* ══════════ Mod: table (legacy) ══════════ */

	/**
	 * Render cart.php template inside a wrapper above checkout form.
	 */
	public static function render_cart(): void {
		if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		echo '<div class="mv-checkout-cart">';
		wc_get_template( 'cart/cart.php' );
		echo '</div>';
	}

	/**
	 * v1.1.2.0: kosarica kao checkout fragment - osvjezava se na SVAKI
	 * update_checkout (upsell blok, kuponi, promjene kolicine...), inace
	 * ostane zaledjena na stanju s inicijalnog page loada.
	 */
	public static function cart_fragment( array $fragments ): array {
		ob_start();
		self::render_cart();
		$html = trim( ob_get_clean() );
		if ( '' === $html ) {
			$html = '<div class="mv-checkout-cart"></div>';
		}
		$fragments['.mv-checkout-cart'] = $html;
		return $fragments;
	}

	/**
	 * Modify remove link to redirect back to checkout after removal.
	 */
	public static function remove_link_to_checkout( string $link, string $cart_item_key ): string {
		if ( is_checkout() ) {
			$link = str_replace( wc_get_cart_url(), wc_get_checkout_url(), $link );
		}
		return $link;
	}

	/* ══════════ Zajednicko ══════════ */

	/**
	 * Cart page link → checkout (so "Update cart" stays on checkout).
	 */
	public static function cart_url_to_checkout( string $url ): string {
		if ( is_checkout() ) {
			return wc_get_checkout_url();
		}
		return $url;
	}

	/**
	 * Set session flag when user removes last item.
	 */
	public static function set_emptied_flag(): void {
		if ( WC()->cart && 0 === WC()->cart->get_cart_contents_count() && WC()->session ) {
			WC()->session->set( 'rpsm_user_emptied_cart', '1' );
		}
	}

	/**
	 * Redirect to shop if user emptied cart on checkout.
	 */
	public static function redirect_if_empty(): void {
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( WC()->cart && 0 === WC()->cart->get_cart_contents_count() && WC()->session ) {
			$flag = WC()->session->get( 'rpsm_user_emptied_cart' );
			if ( '1' === $flag ) {
				WC()->session->set( 'rpsm_user_emptied_cart', '' );
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
				exit;
			}
		}
	}
}
