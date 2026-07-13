( function () {
	'use strict';

	function qs( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function qsa( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
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
			var checked = qs( 'input[data-sync-gateway]:checked', form );
			if ( checked && gatewayInput ) {
				gatewayInput.value = checked.value;
			} else if ( gatewayInput && ! gatewayInput.value ) {
				var first = qs( 'input[data-sync-gateway]', form );
				if ( first ) {
					gatewayInput.value = first.value;
				}
			}
			toggleCardFields();
		}

		function toggleCardFields() {
			var cardFields = document.getElementById( 'pp_modal_card_fields' );
			if ( ! cardFields ) {
				return;
			}
			var gateway = gatewayInput ? String( gatewayInput.value || '' ) : '';
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

		function openModal( trigger ) {
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
			document.getElementById( 'pp_modal_is_gift' ).checked = false;
			syncGatewayFromUi();
			toggleCardFields();
			updateTotals();
			updateSubmitState();
			showNotice( '', false );

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
			submitBtn.textContent = ( cfg.i18n && cfg.i18n.payNow ) || 'Pay now';
		}

		// Capture-phase so we beat other handlers / theme links.
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

		// Toggle card fields on the same click as gateway selection (under Stripe).
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

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
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
			} );
		}

		updateSubmitState();
		toggleCardFields();
	}

	ready( init );
} )();
