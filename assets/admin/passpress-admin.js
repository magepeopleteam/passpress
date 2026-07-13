( function ( $ ) {
	$( function () {
		var $token  = $( '#pp-scan-token' );
		var $result = $( '#pp-scan-result' );

		function currentFacility() {
			return $( '#pp-scan-facility' ).val();
		}

		function currentDirection() {
			return $( '#pp-scan-direction' ).val();
		}

		function showResult( success, data ) {
			data = data || {};
			$result.removeClass( 'passpress-result-success passpress-result-error' );

			if ( success ) {
				$result.addClass( 'passpress-result-success' );
				$result.html(
					'<strong>' + ( data.member_name ? escapeHtml( data.member_name ) : '' ) + '</strong><br>' +
					( data.plan_name ? escapeHtml( data.plan_name ) : '' ) + '<br>' +
					escapeHtml( data.reason || 'Access granted' )
				);
			} else {
				$result.addClass( 'passpress-result-error' );
				$result.html( '<strong>' + escapeHtml( PassPressScan.deniedLabel ) + '</strong><br>' + escapeHtml( data.reason || '' ) );
			}
		}

		function escapeHtml( str ) {
			return $( '<div>' ).text( str ).html();
		}

		if ( $token.length ) {
			$token.trigger( 'focus' );

			$token.on( 'keypress', function ( e ) {
				if ( e.which !== 13 ) {
					return;
				}
				e.preventDefault();

				var token = $token.val().trim();
				if ( ! token ) {
					return;
				}

				$.post( PassPressScan.ajaxUrl, {
					action: 'pp_scan_validate',
					nonce: PassPressScan.nonce,
					token: token,
					facility_id: currentFacility(),
					direction: currentDirection()
				} ).done( function ( response ) {
					showResult( response.success, response.data );
				} ).always( function () {
					$token.val( '' ).trigger( 'focus' );
				} );
			} );
		}

		$( '#pp-pin-submit' ).on( 'click', function () {
			var number = $( '#pp-pin-number' ).val().trim();
			var pin    = $( '#pp-pin-code' ).val().trim();

			if ( ! number || ! pin ) {
				return;
			}

			$.post( PassPressScan.ajaxUrl, {
				action: 'pp_pin_validate',
				nonce: PassPressScan.nonce,
				membership_number: number,
				pin: pin,
				facility_id: currentFacility(),
				direction: currentDirection()
			} ).done( function ( response ) {
				showResult( response.success, response.data );
			} ).always( function () {
				$( '#pp-pin-number' ).val( '' );
				$( '#pp-pin-code' ).val( '' ).trigger( 'focus' );
			} );
		} );

		// Setup Wizard modal (PassPress → Setup Wizard / post-activation).
		var $setupModal = $( '#passpress-setup-wizard-modal' );
		if ( $setupModal.length ) {
			function openSetupModal() {
				$setupModal.show();
				$( 'body' ).addClass( 'passpress-modal-open' );
			}

			function closeSetupModal() {
				// First-run welcome flow: keep the modal until they pick or skip.
				if ( '1' === String( $setupModal.data( 'welcome' ) ) ) {
					return;
				}
				$setupModal.hide();
				$( 'body' ).removeClass( 'passpress-modal-open' );
			}

			if ( '1' === String( $setupModal.data( 'auto-open' ) ) ) {
				openSetupModal();
			}

			$( '#passpress-open-setup-modal' ).on( 'click', openSetupModal );
			$setupModal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeSetupModal );

			$setupModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeSetupModal();
				}
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && $setupModal.is( ':visible' ) ) {
					closeSetupModal();
				}
			} );

			$setupModal.on( 'change', 'input[name="business_type"]', function () {
				$setupModal.find( '.passpress-template-card' ).removeClass( 'is-selected' );
				$( this ).closest( '.passpress-template-card' ).addClass( 'is-selected' );
			} );
		}

		// Payment Method settings (PassPress → Settings → Billing tab).
		var $pmForm = $( '#passpress-payment-method-form' );
		if ( $pmForm.length ) {
			var confirmCopy = {};
			try {
				confirmCopy = JSON.parse( $( '#passpress-pm-confirm-copy' ).text() || '{}' );
			} catch ( e ) {
				confirmCopy = {};
			}

			var $confirmModal = $( '#passpress-pm-confirm-modal' );

			function syncPaymentToggles( value ) {
				$( '#passpress_payment_method_type_input' ).val( value );
				$( '#passpress_wc_enable_toggle' ).prop( 'checked', value === 'woocommerce' );
				$( '#passpress_native_enable_toggle' ).prop( 'checked', value === 'native' );
				$( '.passpress-wc-dependent' ).toggle( value === 'woocommerce' );
				$( '.passpress-native-dependent' ).toggleClass( 'is-locked', value !== 'native' );
			}

			function setPaymentMode( value, keepPanel ) {
				$( '.passpress-pm-toggle-btn' ).removeClass( 'is-active' );
				$( '.passpress-pm-toggle-btn[data-value="' + value + '"]' ).addClass( 'is-active' );
				syncPaymentToggles( value );
				if ( ! keepPanel ) {
					$( '.passpress-pm-panel[data-panel="woocommerce"]' ).toggle( value === 'woocommerce' );
					$( '.passpress-pm-panel[data-panel="native"]' ).toggle( value === 'native' );
				}
			}

			function openConfirmModal( copy, onConfirm ) {
				$confirmModal.find( '#passpress-pm-confirm-text' ).html(
					'<p>' + copy.intro + '</p><p>' + copy.question + '</p>'
				);
				$confirmModal.css( 'display', 'flex' );
				$confirmModal.find( '[data-pp-confirm-ok]' ).off( 'click' ).on( 'click', function () {
					closeConfirmModal();
					onConfirm();
				} );
				$confirmModal.find( '[data-pp-confirm-cancel]' ).off( 'click' ).on( 'click', closeConfirmModal );
			}

			function closeConfirmModal() {
				$confirmModal.hide();
			}

			$confirmModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeConfirmModal();
				}
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && $confirmModal.is( ':visible' ) ) {
					closeConfirmModal();
				}
			} );

			function guardPaymentSwitch( newValue, apply, onCancel ) {
				var current = $( '#passpress_payment_method_type_input' ).val() || 'none';
				var isCrossOver = ( newValue === 'woocommerce' && current === 'native' )
					|| ( newValue === 'native' && current === 'woocommerce' );
				if ( ! isCrossOver ) {
					apply();
					return;
				}
				if ( onCancel ) {
					onCancel();
				}
				openConfirmModal( confirmCopy[ newValue ] || confirmCopy.native, apply );
			}

			$( '.passpress-pm-toggle-btn' ).on( 'click', function () {
				var value = $( this ).data( 'value' );
				guardPaymentSwitch( value, function () {
					setPaymentMode( value );
				} );
			} );

			$( '#passpress_wc_enable_toggle' ).on( 'change', function () {
				var $toggle = $( this );
				if ( ! this.checked ) {
					syncPaymentToggles( 'none' );
					return;
				}
				guardPaymentSwitch( 'woocommerce', function () {
					$toggle.prop( 'checked', true );
					syncPaymentToggles( 'woocommerce' );
				}, function () {
					$toggle.prop( 'checked', false );
				} );
			} );

			$( '#passpress_native_enable_toggle' ).on( 'change', function () {
				var $toggle = $( this );
				if ( ! this.checked ) {
					syncPaymentToggles( 'none' );
					return;
				}
				guardPaymentSwitch( 'native', function () {
					$toggle.prop( 'checked', true );
					syncPaymentToggles( 'native' );
				}, function () {
					$toggle.prop( 'checked', false );
				} );
			} );

			$( '.passpress-pm-accordion-header' ).on( 'click', function () {
				var $header = $( this );
				$( '#' + $header.data( 'target' ) ).slideToggle( 150 );
				$header.toggleClass( 'is-open' );
			} );

			$( '.passpress-gateway-configure' ).on( 'click', function () {
				$( '#' + $( this ).data( 'target' ) ).slideToggle( 150 );
			} );

			$( '.passpress-gw-enable-toggle' ).on( 'change', function () {
				var key = $( this ).data( 'status-for' );
				var $status = $( '.passpress-gateway-status[data-status-for="' + key + '"]' );
				if ( this.checked ) {
					$status.addClass( 'is-enabled' ).text( 'Enabled' );
				} else {
					$status.removeClass( 'is-enabled' ).text( 'Disabled' );
				}
			} );

			$( '.passpress-wc-gateway-toggle' ).on( 'change', function () {
				var $toggle = $( this );
				var gateway = $toggle.data( 'gateway' );
				var $status = $( '.passpress-wc-gateway-status[data-status-for="' + gateway + '"]' );
				var enabled = this.checked;
				$.post( PassPressScan.ajaxUrl, {
					action: 'passpress_toggle_wc_gateway',
					gateway: gateway,
					enabled: enabled ? '1' : '0',
					nonce: $toggle.data( 'nonce' )
				} ).done( function ( resp ) {
					if ( resp && resp.success ) {
						$status.toggleClass( 'is-enabled', enabled ).text( enabled ? 'ENABLED' : 'DISABLED' );
					} else {
						$toggle.prop( 'checked', ! enabled );
					}
				} ).fail( function () {
					$toggle.prop( 'checked', ! enabled );
				} );
			} );

			$( '.passpress-wc-gw-save' ).on( 'click', function () {
				var $btn = $( this );
				var $panel = $btn.closest( '.passpress-wc-gw-panel' );
				var $status = $panel.find( '.passpress-wc-gw-save-status' );
				var gatewayId = $btn.data( 'gateway' );
				var $enableToggle = $( '.passpress-wc-gateway-toggle[data-gateway="' + gatewayId + '"]' );
				var data = $panel.find( 'table.form-table :input' ).serializeArray();
				if ( $enableToggle.is( ':checked' ) ) {
					data.push( { name: $enableToggle.data( 'enabledField' ), value: 'yes' } );
				}
				data.push( { name: 'action', value: 'passpress_save_wc_gateway_settings' } );
				data.push( { name: 'gateway', value: gatewayId } );
				data.push( { name: 'nonce', value: $btn.data( 'nonce' ) } );
				$btn.prop( 'disabled', true );
				$status.css( 'color', '' ).text( 'Saving…' );
				$.post( PassPressScan.ajaxUrl, data ).done( function ( resp ) {
					if ( resp && resp.success ) {
						$status.css( 'color', '#1c9a5b' ).text( 'Saved.' );
					} else {
						$status.css( 'color', '#b32d2e' ).text( ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Something went wrong.' );
					}
				} ).fail( function () {
					$status.css( 'color', '#b32d2e' ).text( 'Request failed.' );
				} ).always( function () {
					$btn.prop( 'disabled', false );
				} );
			} );

			$( '#passpress_install_wc_btn' ).on( 'click', function () {
				var $btn = $( this );
				var $status = $( '#passpress_install_wc_status' );
				var original = $btn.text();
				$btn.prop( 'disabled', true ).text( 'Please wait…' );
				$.post( PassPressScan.ajaxUrl, {
					action: 'passpress_install_activate_woocommerce',
					nonce: $btn.data( 'nonce' )
				} ).done( function ( resp ) {
					if ( resp && resp.success ) {
						window.location.reload();
					} else {
						$status.css( 'color', '#b32d2e' ).text( ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Something went wrong.' );
						$btn.prop( 'disabled', false ).text( original );
					}
				} ).fail( function () {
					$status.css( 'color', '#b32d2e' ).text( 'Request failed.' );
					$btn.prop( 'disabled', false ).text( original );
				} );
			} );
		}

		// Plan create/edit modal (PassPress → Membership Plans).
		var $modal = $( '#passpress-new-plan-modal' );
		if ( $modal.length ) {
			var $form        = $( '#passpress-new-plan-form' );
			var $notice      = $modal.find( '.passpress-modal-notice' );
			var $submit      = $( '#passpress-new-plan-submit' );
			var $eyebrow     = $modal.find( '.pp-modal-eyebrow' );
			var $title       = $( '#passpress-new-plan-title' );
			var $planId      = $( '#pp_plan_id' );
			var $statusBox   = $modal.find( '.pp-plan-status-box' );
			var mode         = 'create';

			function setMode( nextMode ) {
				mode = nextMode;
				var labelKey = 'edit' === mode ? 'labelEdit' : 'labelCreate';
				$eyebrow.text( $eyebrow.data( labelKey ) || $eyebrow.text() );
				$title.text( $title.data( labelKey ) || $title.text() );
				$submit.text( $submit.data( labelKey ) || $submit.text() );
				if ( 'edit' === mode ) {
					$statusBox.prop( 'hidden', false );
				} else {
					$statusBox.prop( 'hidden', true );
				}
			}

			function resetForm() {
				$form[0].reset();
				$planId.val( '0' );
				$( '#pp_new_plan_duration_unit' ).val( 'month' );
				$( '#pp_new_plan_live' ).prop( 'checked', true );
				$( '#pp_new_plan_most_popular' ).prop( 'checked', false );
				$notice.prop( 'hidden', true ).hide().text( '' );
				$submit.prop( 'disabled', false );
			}

			function fillForm( plan ) {
				$planId.val( plan.plan_id || 0 );
				$( '#pp_new_plan_title' ).val( plan.title || '' );
				$( '#pp_new_plan_type' ).val( plan._pp_plan_type || 'monthly' );
				$( '#pp_new_plan_price' ).val( plan._pp_price != null ? plan._pp_price : 0 );
				$( '#pp_new_plan_duration_value' ).val( plan._pp_duration_value || 0 );
				$( '#pp_new_plan_duration_unit' ).val( plan._pp_duration_unit || 'month' );
				$( '#pp_new_plan_restriction' ).val( plan._pp_entry_restriction || 'none' );
				$( '#pp_new_plan_time_start' ).val( plan._pp_time_restriction_start || '' );
				$( '#pp_new_plan_time_end' ).val( plan._pp_time_restriction_end || '' );
				$( '#pp_new_plan_max_per_day' ).val( plan._pp_max_entries_per_day || 0 );
				$( '#pp_new_plan_features' ).val( plan._pp_features || '' );
				$( '#pp_new_plan_most_popular' ).prop( 'checked', !! parseInt( plan._pp_most_popular, 10 ) );
				$( '#pp_new_plan_live' ).prop( 'checked', !! parseInt( plan.is_live, 10 ) );
			}

			function openModal() {
				$modal.prop( 'hidden', false ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'passpress-modal-open' );
				window.setTimeout( function () {
					$( '#pp_new_plan_title' ).trigger( 'focus' );
				}, 30 );
			}

			function closeModal() {
				$modal.prop( 'hidden', true ).css( 'display', 'none' );
				$( 'body' ).removeClass( 'passpress-modal-open' );
				$notice.prop( 'hidden', true ).hide().text( '' );
			}

			function openCreateModal() {
				resetForm();
				setMode( 'create' );
				openModal();
			}

			function openEditModal( planId ) {
				resetForm();
				setMode( 'edit' );
				openModal();
				$submit.prop( 'disabled', true ).text( 'Loading…' );

				$.post( PassPressScan.ajaxUrl, {
					action: 'pp_get_plan',
					pp_create_plan_nonce: $( '#pp_create_plan_nonce' ).val(),
					plan_id: planId
				} )
					.done( function ( response ) {
						if ( ! response || ! response.success ) {
							$notice
								.text( ( response && response.data && response.data.message ) || 'Could not load plan.' )
								.prop( 'hidden', false )
								.show();
							$submit.prop( 'disabled', false ).text( $submit.data( 'labelEdit' ) );
							return;
						}
						fillForm( response.data );
						$submit.prop( 'disabled', false ).text( $submit.data( 'labelEdit' ) );
						$( '#pp_new_plan_title' ).trigger( 'focus' );
					} )
					.fail( function () {
						$notice
							.text( 'Could not load plan. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$submit.prop( 'disabled', false ).text( $submit.data( 'labelEdit' ) );
					} );
			}

			// jQuery data() camelCases data-label-create → labelCreate.
			$eyebrow.data( 'labelCreate', $eyebrow.attr( 'data-label-create' ) );
			$eyebrow.data( 'labelEdit', $eyebrow.attr( 'data-label-edit' ) );
			$title.data( 'labelCreate', $title.attr( 'data-label-create' ) );
			$title.data( 'labelEdit', $title.attr( 'data-label-edit' ) );
			$submit.data( 'labelCreate', $submit.attr( 'data-label-create' ) );
			$submit.data( 'labelEdit', $submit.attr( 'data-label-edit' ) );

			$( document ).on( 'click', '#passpress-new-plan-trigger, [data-open-new-plan]', function ( e ) {
				e.preventDefault();
				openCreateModal();
			} );

			$( document ).on( 'click', '[data-edit-plan]', function ( e ) {
				e.preventDefault();
				var planId = $( this ).attr( 'data-edit-plan' );
				if ( planId ) {
					openEditModal( planId );
				}
			} );

			$modal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeModal );

			$modal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeModal();
				}
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! $modal.prop( 'hidden' ) ) {
					closeModal();
				}
			} );

			$form.on( 'submit', function ( e ) {
				e.preventDefault();

				var action = 'edit' === mode ? 'pp_update_plan' : 'pp_create_plan';
				$submit.prop( 'disabled', true );
				$notice.prop( 'hidden', true ).hide().text( '' );

				$.post( PassPressScan.ajaxUrl, $form.serialize() + '&action=' + action )
					.done( function ( response ) {
						if ( response.success ) {
							window.location.href = response.data.reload_url;
						} else {
							$notice
								.text( ( response.data && response.data.message ) || 'Something went wrong.' )
								.prop( 'hidden', false )
								.show();
							$submit.prop( 'disabled', false );
						}
					} )
					.fail( function () {
						$notice
							.text( 'Something went wrong. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$submit.prop( 'disabled', false );
					} );
			} );
		}

		// Facility create/edit modal (PassPress → Facilities).
		var $facilityModal = $( '#passpress-facility-modal' );
		if ( $facilityModal.length ) {
			var $facilityForm = $( '#passpress-facility-form' );
			var $facilityNotice = $facilityModal.find( '.passpress-modal-notice' );
			var $facilitySubmit = $( '#passpress-facility-submit' );
			var $facilityEyebrow = $facilityModal.find( '.pp-modal-eyebrow' );
			var $facilityTitle = $( '#passpress-facility-modal-title' );
			var $facilityId = $( '#pp_facility_id' );
			var $facilityStatusBox = $facilityModal.find( '.pp-facility-status-box' );
			var facilityMode = 'create';

			$facilityEyebrow.data( 'labelCreate', $facilityEyebrow.attr( 'data-label-create' ) );
			$facilityEyebrow.data( 'labelEdit', $facilityEyebrow.attr( 'data-label-edit' ) );
			$facilityTitle.data( 'labelCreate', $facilityTitle.attr( 'data-label-create' ) );
			$facilityTitle.data( 'labelEdit', $facilityTitle.attr( 'data-label-edit' ) );
			$facilitySubmit.data( 'labelCreate', $facilitySubmit.attr( 'data-label-create' ) );
			$facilitySubmit.data( 'labelEdit', $facilitySubmit.attr( 'data-label-edit' ) );

			function setFacilityMode( nextMode ) {
				facilityMode = nextMode;
				var labelKey = 'edit' === facilityMode ? 'labelEdit' : 'labelCreate';
				$facilityEyebrow.text( $facilityEyebrow.data( labelKey ) || $facilityEyebrow.text() );
				$facilityTitle.text( $facilityTitle.data( labelKey ) || $facilityTitle.text() );
				$facilitySubmit.text( $facilitySubmit.data( labelKey ) || $facilitySubmit.text() );
				$facilityStatusBox.prop( 'hidden', 'edit' !== facilityMode );
			}

			function resetFacilityForm() {
				$facilityForm[0].reset();
				$facilityId.val( '0' );
				$( '#pp_facility_type_field' ).val( 'gym' );
				$( '#pp_facility_capacity' ).val( '10' );
				$( '#pp_facility_slot_duration' ).val( '60' );
				$( '#pp_facility_buffer' ).val( '0' );
				$( '#pp_facility_open_time' ).val( '09:00' );
				$( '#pp_facility_close_time' ).val( '21:00' );
				$( '#pp_facility_cancel_hours' ).val( '2' );
				$( '#pp_facility_booking_required' ).prop( 'checked', false );
				$( '#pp_facility_live' ).prop( 'checked', true );
				$( '#pp_facility_days_open input[type="checkbox"]' ).prop( 'checked', true );
				$( '#pp_facility_staff_ids input[type="checkbox"]' ).prop( 'checked', false );
				$facilityNotice.prop( 'hidden', true ).hide().text( '' );
				$facilitySubmit.prop( 'disabled', false );
			}

			function fillFacilityForm( facility ) {
				$facilityId.val( facility.facility_id || 0 );
				$( '#pp_facility_title' ).val( facility.title || '' );
				$( '#pp_facility_type_field' ).val( facility._pp_facility_type || 'gym' );
				$( '#pp_facility_capacity' ).val( facility._pp_capacity != null ? facility._pp_capacity : 0 );
				$( '#pp_facility_booking_required' ).prop( 'checked', !! parseInt( facility._pp_booking_required, 10 ) );
				$( '#pp_facility_slot_duration' ).val( facility._pp_slot_duration || 60 );
				$( '#pp_facility_buffer' ).val( facility._pp_buffer_minutes || 0 );
				$( '#pp_facility_open_time' ).val( facility._pp_open_time || '09:00' );
				$( '#pp_facility_close_time' ).val( facility._pp_close_time || '21:00' );
				$( '#pp_facility_cancel_hours' ).val( facility._pp_cancellation_lead_hours != null ? facility._pp_cancellation_lead_hours : 2 );
				$( '#pp_facility_live' ).prop( 'checked', !! parseInt( facility.is_live, 10 ) );

				var days = facility._pp_days_open || [];
				$( '#pp_facility_days_open input[type="checkbox"]' ).each( function () {
					var val = parseInt( $( this ).val(), 10 );
					$( this ).prop( 'checked', days.indexOf( val ) !== -1 || days.indexOf( String( val ) ) !== -1 );
				} );

				var staff = facility._pp_staff_ids || [];
				$( '#pp_facility_staff_ids input[type="checkbox"]' ).each( function () {
					var val = parseInt( $( this ).val(), 10 );
					$( this ).prop( 'checked', staff.indexOf( val ) !== -1 || staff.indexOf( String( val ) ) !== -1 );
				} );
			}

			function openFacilityModal() {
				$facilityModal.prop( 'hidden', false ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'passpress-modal-open' );
				window.setTimeout( function () {
					$( '#pp_facility_title' ).trigger( 'focus' );
				}, 30 );
			}

			function closeFacilityModal() {
				$facilityModal.prop( 'hidden', true ).css( 'display', 'none' );
				$( 'body' ).removeClass( 'passpress-modal-open' );
				$facilityNotice.prop( 'hidden', true ).hide().text( '' );
			}

			$( document ).on( 'click', '#passpress-new-facility-trigger, [data-open-new-facility]', function ( e ) {
				e.preventDefault();
				resetFacilityForm();
				setFacilityMode( 'create' );
				openFacilityModal();
			} );

			$( document ).on( 'click', '[data-edit-facility]', function ( e ) {
				e.preventDefault();
				var id = $( this ).attr( 'data-edit-facility' );
				if ( ! id ) {
					return;
				}
				resetFacilityForm();
				setFacilityMode( 'edit' );
				openFacilityModal();
				$facilitySubmit.prop( 'disabled', true ).text( 'Loading…' );

				$.post( PassPressScan.ajaxUrl, {
					action: 'pp_get_facility',
					pp_facility_modal_nonce: $( '#pp_facility_modal_nonce' ).val(),
					facility_id: id
				} )
					.done( function ( response ) {
						if ( ! response || ! response.success ) {
							$facilityNotice
								.text( ( response && response.data && response.data.message ) || 'Could not load facility.' )
								.prop( 'hidden', false )
								.show();
							$facilitySubmit.prop( 'disabled', false ).text( $facilitySubmit.data( 'labelEdit' ) );
							return;
						}
						fillFacilityForm( response.data );
						$facilitySubmit.prop( 'disabled', false ).text( $facilitySubmit.data( 'labelEdit' ) );
						$( '#pp_facility_title' ).trigger( 'focus' );
					} )
					.fail( function () {
						$facilityNotice
							.text( 'Could not load facility. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$facilitySubmit.prop( 'disabled', false ).text( $facilitySubmit.data( 'labelEdit' ) );
					} );
			} );

			$facilityModal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeFacilityModal );
			$facilityModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeFacilityModal();
				}
			} );
			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! $facilityModal.prop( 'hidden' ) ) {
					closeFacilityModal();
				}
			} );

			$facilityForm.on( 'submit', function ( e ) {
				e.preventDefault();
				var action = 'edit' === facilityMode ? 'pp_update_facility' : 'pp_create_facility';
				$facilitySubmit.prop( 'disabled', true );
				$facilityNotice.prop( 'hidden', true ).hide().text( '' );

				$.post( PassPressScan.ajaxUrl, $facilityForm.serialize() + '&action=' + action )
					.done( function ( response ) {
						if ( response.success ) {
							window.location.href = response.data.reload_url;
						} else {
							$facilityNotice
								.text( ( response.data && response.data.message ) || 'Something went wrong.' )
								.prop( 'hidden', false )
								.show();
							$facilitySubmit.prop( 'disabled', false );
						}
					} )
					.fail( function () {
						$facilityNotice
							.text( 'Something went wrong. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$facilitySubmit.prop( 'disabled', false );
					} );
			} );
		}

		// Class session create/edit modal (PassPress → Class Sessions).
		var $classModal = $( '#passpress-class-modal' );
		if ( $classModal.length ) {
			var $classForm = $( '#passpress-class-form' );
			var $classNotice = $classModal.find( '.passpress-modal-notice' );
			var $classSubmit = $( '#passpress-class-submit' );
			var $classEyebrow = $classModal.find( '.pp-modal-eyebrow' );
			var $classTitle = $( '#passpress-class-modal-title' );
			var $classId = $( '#pp_class_id' );
			var $classStatusBox = $classModal.find( '.pp-class-status-box' );
			var classMode = 'create';

			$classEyebrow.data( 'labelCreate', $classEyebrow.attr( 'data-label-create' ) );
			$classEyebrow.data( 'labelEdit', $classEyebrow.attr( 'data-label-edit' ) );
			$classTitle.data( 'labelCreate', $classTitle.attr( 'data-label-create' ) );
			$classTitle.data( 'labelEdit', $classTitle.attr( 'data-label-edit' ) );
			$classSubmit.data( 'labelCreate', $classSubmit.attr( 'data-label-create' ) );
			$classSubmit.data( 'labelEdit', $classSubmit.attr( 'data-label-edit' ) );

			function setClassMode( nextMode ) {
				classMode = nextMode;
				var labelKey = 'edit' === classMode ? 'labelEdit' : 'labelCreate';
				$classEyebrow.text( $classEyebrow.data( labelKey ) || $classEyebrow.text() );
				$classTitle.text( $classTitle.data( labelKey ) || $classTitle.text() );
				$classSubmit.text( $classSubmit.data( labelKey ) || $classSubmit.text() );
				$classStatusBox.prop( 'hidden', 'edit' !== classMode );
			}

			function resetClassForm() {
				$classForm[0].reset();
				$classId.val( '0' );
				$( '#pp_class_type_field' ).val( 'yoga' );
				$( '#pp_class_capacity_field' ).val( '10' );
				$( '#pp_class_instructor_field' ).val( '0' );
				$( '#pp_class_facility_field' ).val( '0' );
				$( '#pp_class_day_field' ).val( '1' );
				$( '#pp_class_start_field' ).val( '09:00' );
				$( '#pp_class_end_field' ).val( '10:00' );
				$( '#pp_class_live' ).prop( 'checked', true );
				$classNotice.prop( 'hidden', true ).hide().text( '' );
				$classSubmit.prop( 'disabled', false );
			}

			function fillClassForm( data ) {
				$classId.val( data.class_id || 0 );
				$( '#pp_class_title' ).val( data.title || '' );
				$( '#pp_class_type_field' ).val( data._pp_class_type || 'yoga' );
				$( '#pp_class_capacity_field' ).val( data._pp_capacity || 10 );
				$( '#pp_class_instructor_field' ).val( String( data._pp_instructor_id || 0 ) );
				$( '#pp_class_facility_field' ).val( String( data._pp_facility_id || 0 ) );
				$( '#pp_class_day_field' ).val( String( data._pp_day_of_week || 1 ) );
				$( '#pp_class_start_field' ).val( data._pp_start_time || '09:00' );
				$( '#pp_class_end_field' ).val( data._pp_end_time || '10:00' );
				$( '#pp_class_live' ).prop( 'checked', !! parseInt( data.is_live, 10 ) );
			}

			function openClassModal() {
				$classModal.prop( 'hidden', false ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'passpress-modal-open' );
				window.setTimeout( function () {
					$( '#pp_class_title' ).trigger( 'focus' );
				}, 30 );
			}

			function closeClassModal() {
				$classModal.prop( 'hidden', true ).css( 'display', 'none' );
				$( 'body' ).removeClass( 'passpress-modal-open' );
				$classNotice.prop( 'hidden', true ).hide().text( '' );
			}

			$( document ).on( 'click', '#passpress-new-class-trigger, [data-open-new-class]', function ( e ) {
				e.preventDefault();
				resetClassForm();
				setClassMode( 'create' );
				openClassModal();
			} );

			$( document ).on( 'click', '[data-edit-class]', function ( e ) {
				e.preventDefault();
				var id = $( this ).attr( 'data-edit-class' );
				if ( ! id ) {
					return;
				}
				resetClassForm();
				setClassMode( 'edit' );
				openClassModal();
				$classSubmit.prop( 'disabled', true ).text( 'Loading…' );

				$.post( PassPressScan.ajaxUrl, {
					action: 'pp_get_class_session',
					pp_class_modal_nonce: $( '#pp_class_modal_nonce' ).val(),
					class_id: id
				} )
					.done( function ( response ) {
						if ( ! response || ! response.success ) {
							$classNotice
								.text( ( response && response.data && response.data.message ) || 'Could not load class.' )
								.prop( 'hidden', false )
								.show();
							$classSubmit.prop( 'disabled', false ).text( $classSubmit.data( 'labelEdit' ) );
							return;
						}
						fillClassForm( response.data );
						$classSubmit.prop( 'disabled', false ).text( $classSubmit.data( 'labelEdit' ) );
						$( '#pp_class_title' ).trigger( 'focus' );
					} )
					.fail( function () {
						$classNotice
							.text( 'Could not load class. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$classSubmit.prop( 'disabled', false ).text( $classSubmit.data( 'labelEdit' ) );
					} );
			} );

			$classModal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeClassModal );
			$classModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeClassModal();
				}
			} );
			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! $classModal.prop( 'hidden' ) ) {
					closeClassModal();
				}
			} );

			$classForm.on( 'submit', function ( e ) {
				e.preventDefault();
				var action = 'edit' === classMode ? 'pp_update_class_session' : 'pp_create_class_session';
				$classSubmit.prop( 'disabled', true );
				$classNotice.prop( 'hidden', true ).hide().text( '' );

				$.post( PassPressScan.ajaxUrl, $classForm.serialize() + '&action=' + action )
					.done( function ( response ) {
						if ( response.success ) {
							window.location.href = response.data.reload_url;
						} else {
							$classNotice
								.text( ( response.data && response.data.message ) || 'Something went wrong.' )
								.prop( 'hidden', false )
								.show();
							$classSubmit.prop( 'disabled', false );
						}
					} )
					.fail( function () {
						$classNotice
							.text( 'Something went wrong. Please try again.' )
							.prop( 'hidden', false )
							.show();
						$classSubmit.prop( 'disabled', false );
					} );
			} );
		}

		// Issue membership modal (PassPress → Memberships).
		var $issueModal = $( '#passpress-issue-membership-modal' );
		if ( $issueModal.length ) {
			function openIssueModal() {
				$issueModal.prop( 'hidden', false ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'passpress-modal-open' );
				window.setTimeout( function () {
					$( '#pp_user_id' ).trigger( 'focus' );
				}, 30 );
			}

			function closeIssueModal() {
				$issueModal.prop( 'hidden', true ).css( 'display', 'none' );
				$( 'body' ).removeClass( 'passpress-modal-open' );
			}

			$( '#passpress-issue-membership-trigger' ).on( 'click', function ( e ) {
				e.preventDefault();
				openIssueModal();
			} );
			$issueModal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeIssueModal );
			$issueModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeIssueModal();
				}
			} );
			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! $issueModal.prop( 'hidden' ) ) {
					closeIssueModal();
				}
			} );
		}

		// Register visitor modal (PassPress → Visitors).
		var $visitorModal = $( '#passpress-register-visitor-modal' );
		if ( $visitorModal.length ) {
			function openVisitorModal() {
				$visitorModal.prop( 'hidden', false ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'passpress-modal-open' );
				window.setTimeout( function () {
					$( '#pp_visitor_name' ).trigger( 'focus' );
				}, 30 );
			}

			function closeVisitorModal() {
				$visitorModal.prop( 'hidden', true ).css( 'display', 'none' );
				$( 'body' ).removeClass( 'passpress-modal-open' );
			}

			$( '#passpress-register-visitor-trigger' ).on( 'click', function ( e ) {
				e.preventDefault();
				openVisitorModal();
			} );
			$visitorModal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeVisitorModal );
			$visitorModal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeVisitorModal();
				}
			} );
			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! $visitorModal.prop( 'hidden' ) ) {
					closeVisitorModal();
				}
			} );
		}

		// Quick-filter tabs + pagination, AJAX-driven (PassPress → Memberships)
		// — clicking a tab or a page number re-fetches just the table/footer
		// instead of reloading the whole screen. The URL is kept in sync via
		// pushState so refresh/back-forward/bookmarking still work correctly.
		var $filterTabs = $( '#passpress-scope-tabs' );
		if ( $filterTabs.length ) {
			var $searchInput = $( '#passpress-members-search-input' );

			function fetchMembers( status, planScope, paged, skipPushState ) {
				var search = $searchInput.length ? $searchInput.val() : '';

				$.post( PassPressScan.ajaxUrl, {
					action: 'pp_filter_members',
					nonce: $filterTabs.data( 'nonce' ),
					status: status,
					plan_scope: planScope,
					s: search,
					paged: paged
				} ).done( function ( response ) {
					if ( ! response.success ) {
						return;
					}

					$( '#passpress-members-tbody' ).html( response.data.rows );
					$( '#passpress-members-footer' ).replaceWith( response.data.footer );

					$filterTabs.attr( 'data-status', status ).data( 'status', status );
					$filterTabs.attr( 'data-plan-scope', planScope ).data( 'plan-scope', planScope );

					$filterTabs.find( '.passpress-scope-tab' ).each( function () {
						var $tab = $( this );
						$tab.toggleClass( 'is-active', ( $tab.data( 'status' ) || '' ) === status && ( $tab.data( 'plan-scope' ) || '' ) === planScope );
					} );

					$( '.passpress-stat-tile' ).each( function () {
						var $tile = $( this );
						$tile.toggleClass( 'is-active', '' !== status && $tile.data( 'status' ) === status );
					} );

					if ( ! skipPushState && window.history && window.URL ) {
						var url = new URL( window.location.href );
						if ( status ) {
							url.searchParams.set( 'status', status );
						} else {
							url.searchParams.delete( 'status' );
						}
						if ( planScope ) {
							url.searchParams.set( 'plan_scope', planScope );
						} else {
							url.searchParams.delete( 'plan_scope' );
						}
						if ( search ) {
							url.searchParams.set( 's', search );
						} else {
							url.searchParams.delete( 's' );
						}
						if ( paged > 1 ) {
							url.searchParams.set( 'paged', paged );
						} else {
							url.searchParams.delete( 'paged' );
						}
						window.history.pushState( { status: status, planScope: planScope, paged: paged }, '', url.toString() );
					}
				} );
			}

			$filterTabs.on( 'click', '.passpress-scope-tab', function ( e ) {
				e.preventDefault();
				var $tab = $( this );
				fetchMembers( $tab.data( 'status' ) || '', $tab.data( 'plan-scope' ) || '', 1, false );
			} );

			$( '.passpress-members-search-form' ).on( 'submit', function ( e ) {
				e.preventDefault();
				fetchMembers( $filterTabs.data( 'status' ) || '', $filterTabs.data( 'plan-scope' ) || '', 1, false );
			} );

			// The footer (and its pagination links) gets replaced wholesale on
			// every fetch, so this is bound on document via delegation rather
			// than on the footer element itself.
			$( document ).on( 'click', '#passpress-members-footer .passpress-members-pagination a', function ( e ) {
				e.preventDefault();
				var hrefMatch = $( this ).attr( 'href' ).match( /[?&]paged=(\d+)/ );
				var paged     = hrefMatch ? parseInt( hrefMatch[ 1 ], 10 ) : 1;
				fetchMembers( $filterTabs.data( 'status' ) || '', $filterTabs.data( 'plan-scope' ) || '', paged, false );
			} );

			if ( window.addEventListener ) {
				window.addEventListener( 'popstate', function () {
					if ( ! window.URLSearchParams ) {
						return;
					}
					var params = new URLSearchParams( window.location.search );
					if ( $searchInput.length ) {
						$searchInput.val( params.get( 's' ) || '' );
					}
					fetchMembers( params.get( 'status' ) || '', params.get( 'plan_scope' ) || '', parseInt( params.get( 'paged' ), 10 ) || 1, true );
				} );
			}
		}
	} );
} )( jQuery );
