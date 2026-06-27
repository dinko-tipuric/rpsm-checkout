<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module: Thank-you - Stripe redirect to custom page with GTM dataLayer waiting.
 */
final class RPSM_Checkout_Module_Thankyou {

	public static function init(): void {
		$gateway = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_GATEWAY );
		if ( '' === $gateway ) {
			return;
		}
		add_action( 'woocommerce_thankyou_' . $gateway, [ __CLASS__, 'render_redirect' ], 5 );
	}

	/**
	 * Replace default thank-you content with redirect UI.
	 */
	public static function render_redirect( int $order_id ): void {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		/* Remove default WC order details on this thank-you page */
		remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

		$title       = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_TITLE );
		$btn_text    = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_BTN_TEXT );
		$redirect    = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_REDIRECT_URL );
		$timeout     = absint( RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_GTM_TIMEOUT ) );
		$order_id    = absint( $order_id );
		$fallback    = RPSM_Checkout_Options::get( RPSM_Checkout_Options::THANKYOU_FALLBACK_MSG );
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
			var redirectUrl = <?php echo wp_json_encode( $redirect_url ); ?>;
			var timeout     = <?php echo $timeout; ?>;
			var fallbackMsg = <?php echo wp_json_encode( $fallback ); ?>;
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
					document.getElementById('rpsm-ty-status').textContent = fallbackMsg;
					document.getElementById('rpsm-ty-btn').style.display = 'inline-block';
				}
			}, 100);
		})();
		</script>
		<?php
	}
}
