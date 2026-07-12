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

		// New Plan modal (PassPress → Membership Plans).
		var $modal = $( '#passpress-new-plan-modal' );
		if ( $modal.length ) {
			var $form   = $( '#passpress-new-plan-form' );
			var $notice = $modal.find( '.passpress-modal-notice' );
			var $submit = $( '#passpress-new-plan-submit' );

			function openModal() {
				$modal.show();
				$( '#pp_new_plan_title' ).trigger( 'focus' );
			}

			function closeModal() {
				$modal.hide();
				$notice.hide();
			}

			$( '#passpress-new-plan-trigger' ).on( 'click', openModal );
			$modal.find( '.passpress-modal-close, .passpress-modal-cancel' ).on( 'click', closeModal );

			// Click on the dark overlay (not the modal box itself) closes it.
			$modal.on( 'click', function ( e ) {
				if ( e.target === this ) {
					closeModal();
				}
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && $modal.is( ':visible' ) ) {
					closeModal();
				}
			} );

			$form.on( 'submit', function ( e ) {
				e.preventDefault();

				$submit.prop( 'disabled', true );
				$notice.hide();

				$.post( PassPressScan.ajaxUrl, $form.serialize() + '&action=pp_create_plan' )
					.done( function ( response ) {
						if ( response.success ) {
							window.location.href = response.data.reload_url;
						} else {
							$notice.text( ( response.data && response.data.message ) || 'Something went wrong.' ).show();
							$submit.prop( 'disabled', false );
						}
					} )
					.fail( function () {
						$notice.text( 'Something went wrong. Please try again.' ).show();
						$submit.prop( 'disabled', false );
					} );
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
