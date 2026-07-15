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

		function syncLastName() {
			var first = document.getElementById( 'billing_first_name' );
			var last = document.getElementById( 'billing_last_name' );
			if ( ! first || ! last ) {
				return;
			}
			var full = ( first.value || '' ).trim();
			if ( ! full ) {
				return;
			}
			var parts = full.split( /\s+/ );
			if ( parts.length > 1 ) {
				last.value = parts.slice( 1 ).join( ' ' );
			} else if ( ! ( last.value || '' ).trim() ) {
				last.value = '-';
			}
		}

		function fillBillingFromSummary() {
			var summary = document.querySelector( '.passpress-wc-member-summary' );
			if ( ! summary ) {
				return;
			}
			var map = {
				billing_first_name: summary.getAttribute( 'data-pp-full-name' ) || '',
				billing_phone: summary.getAttribute( 'data-pp-phone' ) || '',
				billing_email: summary.getAttribute( 'data-pp-email' ) || '',
				billing_address_1: summary.getAttribute( 'data-pp-address' ) || ''
			};
			Object.keys( map ).forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el && map[ id ] ) {
					el.value = map[ id ];
					el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
			syncLastName();
		}

		function bindNameSync() {
			var form = document.querySelector( 'form.checkout, form.woocommerce-checkout' );
			var first = document.getElementById( 'billing_first_name' );
			fillBillingFromSummary();
			if ( first ) {
				first.addEventListener( 'input', syncLastName );
				first.addEventListener( 'change', syncLastName );
				syncLastName();
			}
			if ( form ) {
				form.addEventListener( 'submit', syncLastName, true );
			}
			document.body.addEventListener( 'click', function ( e ) {
				if ( e.target && ( e.target.id === 'place_order' || ( e.target.closest && e.target.closest( '#place_order' ) ) ) ) {
					syncLastName();
				}
			}, true );
		}

		function fixCountryStateSelects() {
			if ( typeof window.jQuery === 'undefined' ) {
				return;
			}
			var $ = window.jQuery;
			$( '#billing_country, #billing_state' ).each( function () {
				var $field = $( this );
				var $row = $field.closest( '.form-row' );
				var $container = $field.next( '.select2-container' );
				if ( $container.length ) {
					$container.css( { width: '100%', display: 'block' } );
				}
				// Re-init SelectWoo with a parent that does not clip the dropdown.
				if ( $field.data( 'select2' ) && $row.length ) {
					try {
						var val = $field.val();
						$field.selectWoo( 'destroy' );
						$field.selectWoo( {
							width: '100%',
							dropdownParent: $row
						} );
						if ( val ) {
							$field.val( val ).trigger( 'change.select2' );
						}
					} catch ( e ) {}
				} else if ( ! $field.data( 'select2' ) && $field.is( 'select' ) && typeof $field.selectWoo === 'function' ) {
					try {
						$field.selectWoo( {
							width: '100%',
							dropdownParent: $row.length ? $row : $( document.body )
						} );
					} catch ( e ) {}
				}
			} );
		}

		if ( document.body.classList.contains( 'woocommerce-order-received' ) || document.querySelector( '.woocommerce-order' ) ) {
			notifyParent( { type: 'passpress_wc_checkout_complete', url: window.location.href } );
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', bindNameSync );
		} else {
			bindNameSync();
		}

		window.addEventListener( 'load', function () {
			fitParentFrame();
			fixCountryStateSelects();
		} );
		window.setTimeout( fitParentFrame, 200 );
		window.setTimeout( fixCountryStateSelects, 300 );
		window.setTimeout( fixCountryStateSelects, 900 );

		if ( typeof window.jQuery !== 'undefined' ) {
			window.jQuery( document.body ).on( 'updated_checkout country_to_state_changed', function () {
				window.setTimeout( fixCountryStateSelects, 50 );
				window.setTimeout( fitParentFrame, 100 );
			} );
		}

		if ( window.MutationObserver ) {
			var obs = new MutationObserver( function () { fitParentFrame(); } );
			obs.observe( document.body, { childList: true, subtree: true, attributes: true } );
		}
	})();
	</script>
</body>
</html>
