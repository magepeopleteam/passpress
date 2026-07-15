( function () {
	'use strict';

	function qs( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function init() {
		var cfg = window.PassPressCheckout || {};
		var overlay = document.getElementById( 'passpress-checkout-modal' );
		if ( ! overlay ) {
			return;
		}

		var form = document.getElementById( 'passpress-checkout-modal-form' );
		var notice = qs( '.passpress-checkout-modal-notice', overlay );
		var submitBtn = document.getElementById( 'pp_modal_submit' );
		var gatewayInput = document.getElementById( 'pp_modal_gateway' );
		var stepForm = document.getElementById( 'pp_modal_step_form' );
		var stepWc = document.getElementById( 'pp_modal_step_wc' );
		var wcIframe = document.getElementById( 'pp_modal_wc_iframe' );
		var modalBox = qs( '.passpress-checkout-modal', overlay );
		var paymentMode = overlay.getAttribute( 'data-payment-mode' ) || cfg.paymentMode || 'native';
		var state = {
			basePrice: 0,
			baseLabel: '',
			discount: 0,
			total: 0,
			totalLabel: '',
			discountLabel: ''
		};

		function showNotice( message, isError ) {
			if ( ! notice ) {
				return;
			}
			if ( message ) {
				notice.hidden = false;
				notice.textContent = message;
				notice.classList.toggle( 'is-error', !! isError );
				notice.classList.toggle( 'is-success', ! isError );
			} else {
				notice.hidden = true;
				notice.textContent = '';
			}
		}

		function syncGatewayFromUi() {
			if ( paymentMode !== 'native' || ! gatewayInput ) {
				return;
			}
			var checked = qs( 'input[data-sync-gateway]:checked', form );
			if ( checked ) {
				gatewayInput.value = checked.value;
			} else if ( ! gatewayInput.value ) {
				var first = qs( 'input[data-sync-gateway]', form );
				if ( first ) {
					gatewayInput.value = first.value;
				}
			}
			toggleCardFields();
		}

		function toggleCardFields() {
			var cardFields = document.getElementById( 'pp_modal_card_fields' );
			if ( ! cardFields || ! gatewayInput ) {
				return;
			}
			var gateway = String( gatewayInput.value || '' );
			var showCards = gateway === 'stripe';
			if ( showCards ) {
				cardFields.hidden = false;
				cardFields.removeAttribute( 'hidden' );
				cardFields.classList.add( 'is-open' );
				cardFields.style.display = 'block';
			} else {
				cardFields.hidden = true;
				cardFields.setAttribute( 'hidden', 'hidden' );
				cardFields.classList.remove( 'is-open' );
				cardFields.style.display = 'none';
			}
		}

		function showFormStep() {
			if ( stepForm ) {
				stepForm.hidden = false;
			}
			if ( stepWc ) {
				stepWc.hidden = true;
			}
			if ( wcIframe ) {
				wcIframe.src = 'about:blank';
			}
			if ( modalBox ) {
				modalBox.classList.remove( 'is-wc-checkout' );
			}
		}

		function showWcStep( checkoutUrl ) {
			if ( stepForm ) {
				stepForm.hidden = true;
			}
			if ( stepWc ) {
				stepWc.hidden = false;
			}
			if ( modalBox ) {
				modalBox.classList.add( 'is-wc-checkout' );
			}
			if ( wcIframe && checkoutUrl ) {
				wcIframe.src = checkoutUrl;
			}
			showNotice( '', false );
		}

		function openModal( trigger ) {
			paymentMode = trigger.getAttribute( 'data-payment-mode' )
				|| overlay.getAttribute( 'data-payment-mode' )
				|| cfg.paymentMode
				|| 'native';
			overlay.setAttribute( 'data-payment-mode', paymentMode );
			var modeField = document.getElementById( 'pp_modal_payment_mode' );
			if ( modeField ) {
				modeField.value = paymentMode;
			}

			document.getElementById( 'pp_modal_plan_id' ).value = trigger.getAttribute( 'data-plan-id' ) || '';
			document.getElementById( 'pp_modal_renew_id' ).value = trigger.getAttribute( 'data-renew-id' ) || '0';
			document.getElementById( 'pp_modal_nonce' ).value = trigger.getAttribute( 'data-nonce' ) || '';
			document.getElementById( 'pp_modal_plan_name' ).textContent = trigger.getAttribute( 'data-plan-name' ) || 'Pass';

			state.basePrice = parseFloat( trigger.getAttribute( 'data-plan-price' ) || '0' ) || 0;
			state.baseLabel = trigger.getAttribute( 'data-plan-price-label' ) || '';
			state.discount = 0;
			state.total = state.basePrice;
			state.totalLabel = state.baseLabel;
			state.discountLabel = '';

			document.getElementById( 'pp_modal_coupon' ).value = '';
			var couponMsg = document.getElementById( 'pp_modal_coupon_msg' );
			if ( couponMsg ) {
				couponMsg.hidden = true;
			}
			syncGatewayFromUi();
			toggleCardFields();
			updateTotals();
			updateSubmitState();
			showNotice( '', false );
			showFormStep();

			overlay.hidden = false;
			overlay.style.display = 'flex';
			overlay.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'passpress-checkout-open' );

			var nameField = document.getElementById( 'pp_modal_full_name' );
			if ( nameField ) {
				window.setTimeout( function () { nameField.focus(); }, 50 );
			}
		}

		function closeModal() {
			showFormStep();
			overlay.hidden = true;
			overlay.style.display = 'none';
			overlay.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'passpress-checkout-open' );
		}

		function updateTotals() {
			document.getElementById( 'pp_modal_plan_price' ).textContent = state.baseLabel;
			document.getElementById( 'pp_modal_total' ).textContent = state.totalLabel || state.baseLabel;
			var discountRow = document.getElementById( 'pp_modal_discount_row' );
			if ( state.discount > 0 ) {
				discountRow.hidden = false;
				discountRow.style.display = 'flex';
				document.getElementById( 'pp_modal_discount_amount' ).textContent = state.discountLabel;
			} else {
				discountRow.hidden = true;
				discountRow.style.display = 'none';
			}
		}

		function updateSubmitState() {
			if ( ! submitBtn ) {
				return;
			}
			submitBtn.disabled = false;
			if ( paymentMode === 'woocommerce' ) {
				submitBtn.textContent = ( cfg.i18n && cfg.i18n.completeRegistration ) || 'Complete Registration';
			} else {
				submitBtn.textContent = ( cfg.i18n && cfg.i18n.payNow ) || 'Pay now';
			}
		}

		function validateMemberFields() {
			var fullName = ( document.getElementById( 'pp_modal_full_name' ).value || '' ).trim();
			var phone = ( document.getElementById( 'pp_modal_phone' ).value || '' ).trim();
			var email = ( document.getElementById( 'pp_modal_email' ).value || '' ).trim();
			var address = ( document.getElementById( 'pp_modal_address' ).value || '' ).trim();
			if ( ! fullName || ! phone || ! email || ! address ) {
				showNotice( ( cfg.i18n && cfg.i18n.needMemberInfo ) || 'Please fill in all membership information fields.', true );
				return false;
			}
			return true;
		}

		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest ? e.target.closest( '.passpress-open-checkout' ) : null;
			if ( trigger ) {
				e.preventDefault();
				e.stopPropagation();
				openModal( trigger );
				return;
			}
			if ( e.target.closest && e.target.closest( '.passpress-checkout-modal-close' ) ) {
				e.preventDefault();
				closeModal();
			}
		}, true );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! overlay.hidden ) {
				closeModal();
			}
		} );

		if ( form ) {
			form.addEventListener( 'change', function ( e ) {
				if ( e.target && e.target.getAttribute( 'data-sync-gateway' ) !== null ) {
					syncGatewayFromUi();
				}
			} );
			form.addEventListener( 'click', function ( e ) {
				var target = e.target;
				if ( ! target ) {
					return;
				}
				var radio = null;
				if ( target.getAttribute && target.getAttribute( 'data-sync-gateway' ) !== null ) {
					radio = target;
				} else if ( target.closest ) {
					var gatewayLabel = target.closest( '.passpress-checkout-gateway' );
					if ( gatewayLabel ) {
						radio = qs( 'input[data-sync-gateway]', gatewayLabel );
					}
				}
				if ( ! radio ) {
					return;
				}
				radio.checked = true;
				if ( gatewayInput ) {
					gatewayInput.value = radio.value;
				}
				toggleCardFields();
			}, true );
		}

		var couponApply = document.getElementById( 'pp_modal_coupon_apply' );
		if ( couponApply ) {
			couponApply.addEventListener( 'click', function () {
				var planId = document.getElementById( 'pp_modal_plan_id' ).value;
				var nonce = document.getElementById( 'pp_modal_nonce' ).value;
				var code = document.getElementById( 'pp_modal_coupon' ).value;
				var msg = document.getElementById( 'pp_modal_coupon_msg' );

				couponApply.disabled = true;
				var body = new FormData();
				body.append( 'action', 'passpress_apply_coupon' );
				body.append( 'plan_id', planId );
				body.append( 'nonce', nonce );
				body.append( 'coupon_code', code );

				fetch( cfg.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: body, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( resp ) {
						couponApply.disabled = false;
						if ( ! resp || ! resp.success ) {
							msg.hidden = false;
							msg.className = 'passpress-checkout-coupon-msg is-error';
							msg.textContent = ( resp && resp.data && resp.data.message ) || 'Something went wrong.';
							state.discount = 0;
							state.total = state.basePrice;
							state.totalLabel = state.baseLabel;
							updateTotals();
							return;
						}
						state.discount = parseFloat( resp.data.discount_amount || 0 ) || 0;
						state.total = parseFloat( resp.data.final_amount || state.basePrice ) || state.basePrice;
						state.totalLabel = resp.data.total_label || state.baseLabel;
						state.discountLabel = resp.data.discount_label || '';
						if ( resp.data.price_label ) {
							state.baseLabel = resp.data.price_label;
						}
						updateTotals();
						msg.hidden = ! resp.data.message;
						msg.className = 'passpress-checkout-coupon-msg is-success';
						msg.textContent = resp.data.message || '';
					} )
					.catch( function () {
						couponApply.disabled = false;
						msg.hidden = false;
						msg.className = 'passpress-checkout-coupon-msg is-error';
						msg.textContent = 'Something went wrong.';
					} );
			} );
		}

		function submitNative( e ) {
			e.preventDefault();
			if ( ! validateMemberFields() ) {
				return;
			}
			syncGatewayFromUi();

			if ( ! gatewayInput || ! gatewayInput.value ) {
				showNotice( ( cfg.i18n && cfg.i18n.needGateway ) || 'Please choose a payment method.', true );
				return;
			}

			submitBtn.disabled = true;
			var original = submitBtn.textContent;
			submitBtn.textContent = ( cfg.i18n && cfg.i18n.processing ) || 'Processing…';
			showNotice( '', false );

			var body = new FormData( form );
			body.set( 'gateway', gatewayInput.value );
			body.append( 'action', 'passpress_modal_checkout' );

			fetch( cfg.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( resp ) {
					if ( ! resp || ! resp.success ) {
						submitBtn.disabled = false;
						updateSubmitState();
						submitBtn.textContent = original;
						showNotice( ( resp && resp.data && resp.data.message ) || 'Something went wrong.', true );
						if ( resp && resp.data && resp.data.loginUrl ) {
							window.setTimeout( function () {
								window.location.href = resp.data.loginUrl;
							}, 1000 );
						}
						return;
					}
					if ( resp.data.state === 'redirect' && resp.data.redirect ) {
						window.location.href = resp.data.redirect;
						return;
					}
					if ( resp.data.state === 'success' ) {
						showNotice( resp.data.message || 'Payment received!', false );
						window.setTimeout( function () {
							window.location.href = resp.data.passUrl || window.location.href;
						}, 800 );
						return;
					}
					showNotice( resp.data.message || '', false );
					submitBtn.disabled = false;
					updateSubmitState();
					submitBtn.textContent = original;
				} )
				.catch( function () {
					submitBtn.disabled = false;
					updateSubmitState();
					submitBtn.textContent = original;
					showNotice( 'Something went wrong.', true );
				} );
		}

		function submitWooCommerce( e ) {
			e.preventDefault();
			if ( ! validateMemberFields() ) {
				return;
			}

			submitBtn.disabled = true;
			var original = submitBtn.textContent;
			submitBtn.textContent = ( cfg.i18n && cfg.i18n.processing ) || 'Processing…';
			showNotice( '', false );

			var body = new FormData( form );
			body.append( 'action', 'passpress_wc_prepare_checkout' );

			fetch( cfg.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( resp ) {
					if ( ! resp || ! resp.success ) {
						submitBtn.disabled = false;
						updateSubmitState();
						submitBtn.textContent = original;
						showNotice( ( resp && resp.data && resp.data.message ) || 'Something went wrong.', true );
						if ( resp && resp.data && resp.data.loginUrl ) {
							window.setTimeout( function () {
								window.location.href = resp.data.loginUrl;
							}, 1200 );
						}
						return;
					}
					submitBtn.disabled = false;
					updateSubmitState();
					submitBtn.textContent = original;
					if ( resp.data.checkoutUrl ) {
						showWcStep( resp.data.checkoutUrl );
						return;
					}
					showNotice( ( cfg.i18n && cfg.i18n.error ) || 'Something went wrong.', true );
				} )
				.catch( function () {
					submitBtn.disabled = false;
					updateSubmitState();
					submitBtn.textContent = original;
					showNotice( 'Something went wrong.', true );
				} );
		}

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var mode = document.getElementById( 'pp_modal_payment_mode' );
				paymentMode = ( mode && mode.value ) || paymentMode;
				if ( paymentMode === 'woocommerce' ) {
					submitWooCommerce( e );
				} else {
					submitNative( e );
				}
			} );
		}

		window.addEventListener( 'message', function ( e ) {
			if ( e.origin !== window.location.origin ) {
				return;
			}
			var data = e.data || {};
			if ( data.type === 'passpress_wc_checkout_resize' && data.height ) {
				var frameWrap = qs( '.passpress-checkout-wc-frame-wrap', overlay );
				var next = Math.max( 420, Math.min( parseInt( data.height, 10 ) || 0, Math.floor( window.innerHeight * 0.78 ) ) );
				if ( frameWrap && next ) {
					frameWrap.style.height = next + 'px';
				}
				if ( wcIframe && next ) {
					wcIframe.style.height = next + 'px';
					wcIframe.style.minHeight = next + 'px';
				}
				return;
			}
			if ( data.type === 'passpress_wc_checkout_complete' ) {
				showNotice( ( cfg.i18n && cfg.i18n.wcSuccess ) || 'Payment received! Your membership will activate shortly.', false );
				window.setTimeout( function () {
					window.location.href = cfg.passUrl || window.location.href;
				}, 900 );
			}
		} );

		updateSubmitState();
		toggleCardFields();
	}

	ready( init );
} )();
