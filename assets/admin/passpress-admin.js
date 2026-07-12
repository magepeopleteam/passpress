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
	} );
} )( jQuery );
