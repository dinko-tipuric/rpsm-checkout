<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Thank-you - redirect na custom stranicu s GTM dataLayer čekanjem.
 *
 * Dva puta:
 * 1. Stripe (gateway hook `woocommerce_thankyou_{gateway}`) - postojeće ponašanje.
 * 2. Besplatne narudžbe (v1.1.1.0): total 0 € bez odabranog načina plaćanja - WC
 *    tada preskoči odabir plaćanja pa gateway hook nikad ne okine. Generički
 *    `woocommerce_thankyou` hook hvata te narudžbe i radi isti redirect, uz
 *    auto-redirect na GTM timeout (korisnik nikad ne ostane na thank-you).
 */
final class RPSM_Checkout_Module_Thankyou {

	private static bool $rendered = false;

	public static function init(): void {
		$gateway = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_GATEWAY );
		if ( '' !== $gateway ) {
			add_action( 'woocommerce_thankyou_' . $gateway, [ __CLASS__, 'render_redirect' ], 5 );
		}
		if ( '1' === RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_FREE_ENABLED ) ) {
			/* Prije woocommerce_order_details_table (prio 10) da remove_action stigne */
			add_action( 'woocommerce_thankyou', [ __CLASS__, 'maybe_redirect_free_order' ], 5 );
		}
	}

	/**
	 * Besplatna narudžba: bez payment methoda i total 0 - isti redirect UI.
	 */
	public static function maybe_redirect_free_order( int $order_id ): void {
		if ( self::$rendered ) {
			return; // gateway hook je vec renderirao (isti page load)
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( '' !== $order->get_payment_method() ) {
			return; // ima gateway - pokriva ga (ili ne) gateway put
		}
		if ( (float) $order->get_total() > 0 ) {
			return; // neplacena narudzba bez gatewaya - ne diramo
		}

		RPSM_Checkout_Debug::info( 'Thankyou: free order redirect', [ 'order_id' => $order_id ] );
		self::render_redirect( $order_id, true );
	}

	/**
	 * Replace default thank-you content with redirect UI.
	 *
	 * @param bool $is_free_order Besplatna narudžba: custom naslov + auto-redirect na GTM timeout.
	 */
	public static function render_redirect( int $order_id, bool $is_free_order = false ): void {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::$rendered = true;

		/* Remove default WC order details on this thank-you page */
		remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

		$title = $is_free_order
			? RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_FREE_TITLE )
			: RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_TITLE );
		$btn_text     = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_BTN_TEXT );
		$redirect     = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_REDIRECT_URL );
		$timeout      = absint( RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_GTM_TIMEOUT ) );
		$order_id     = absint( $order_id );
		$fallback     = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_FALLBACK_MSG );
		$redirect_url = ( 0 === strpos( $redirect, 'http' ) ) ? $redirect : home_url( $redirect );

		?>
		<div class="rpsm-thankyou-wrap" style="text-align:center;padding:40px 20px;">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p id="rpsm-ty-status">Preusmjeravanje...</p>
			<a href="<?php echo esc_url( $redirect_url ); ?>" class="button alt" id="rpsm-ty-btn" style="display:none;">
				<?php echo esc_html( $btn_text ); ?>
			</a>
		</div>
		<script>
		(function(){
			var redirectUrl  = <?php echo wp_json_encode( $redirect_url ); ?>;
			var timeout      = <?php echo $timeout; ?>;
			var fallbackMsg  = <?php echo wp_json_encode( $fallback ); ?>;
			var autoRedirect = <?php echo $is_free_order ? 'true' : 'false'; ?>;
			var found = false;

			/* Poll dataLayer for 'purchase' event */
			var start = Date.now();
			var poll = setInterval(function(){
				var dl = window.dataLayer || [];
				for(var i=0;i<dl.length;i++){
					if(dl[i].event==='purchase'){
						found = true;
						clearInterval(poll);
						setTimeout(function(){ window.location.href = redirectUrl; }, 600);
						return;
					}
				}
				if(Date.now()-start > timeout){
					clearInterval(poll);
					/* Push helper event for GTM debugging */
					(window.dataLayer = window.dataLayer || []).push({event:'rpsm_gtm_timeout',order_id:<?php echo $order_id; ?>});
					if(autoRedirect){
						/* Besplatna narudžba: GTM možda nema purchase event za 0 € -
						   korisnik svejedno ide na redirect, ne ostaje na thank-you */
						setTimeout(function(){ window.location.href = redirectUrl; }, 300);
						return;
					}
					document.getElementById('rpsm-ty-status').textContent = fallbackMsg;
					document.getElementById('rpsm-ty-btn').style.display = 'inline-block';
				}
			}, 100);
		})();
		</script>
		<?php
	}
}
