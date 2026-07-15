<?php
/**
 * Compact WooCommerce checkout shell for the Get this Pass modal iframe.
 * Loaded when ?passpress_wc_embed=1 is present on the checkout URL.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$css_url = PASSPRESS_PLUGIN_URL . '/assets/frontend/passpress-wc-embed.css';
$css_ver = defined( 'PASSPRESS_PLUGIN_VERSION' ) ? PASSPRESS_PLUGIN_VERSION : '1.0.0';

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?ver=<?php echo esc_attr( $css_ver ); ?>">
</head>
<body <?php body_class( 'passpress-wc-embed' ); ?>>
	<div class="passpress-wc-embed-wrap">
		<?php
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			echo do_shortcode( '[woocommerce_checkout]' );
		} else {
			?>
			<div class="passpress-wc-embed-layout">
				<?php echo do_shortcode( '[woocommerce_checkout]' ); ?>
			</div>
			<?php
		}
		?>
	</div>
	<?php wp_footer(); ?>
	<script>
	(function () {
		function notifyParent( payload ) {
			try {
				if ( window.parent && window.parent !== window ) {
					window.parent.postMessage( payload, window.location.origin );
				}
			} catch ( e ) {}
		}

		function fitParentFrame() {
			try {
				var height = Math.ceil( document.documentElement.scrollHeight || document.body.scrollHeight || 0 );
				if ( height > 0 ) {
					notifyParent( { type: 'passpress_wc_checkout_resize', height: height } );
				}
			} catch ( e ) {}
		}

		if ( document.body.classList.contains( 'woocommerce-order-received' ) || document.querySelector( '.woocommerce-order' ) ) {
			notifyParent( { type: 'passpress_wc_checkout_complete', url: window.location.href } );
		}

		window.addEventListener( 'load', fitParentFrame );
		window.setTimeout( fitParentFrame, 200 );
		window.setTimeout( fitParentFrame, 800 );

		if ( window.MutationObserver ) {
			var obs = new MutationObserver( function () { fitParentFrame(); } );
			obs.observe( document.body, { childList: true, subtree: true, attributes: true } );
		}
	})();
	</script>
</body>
</html>
