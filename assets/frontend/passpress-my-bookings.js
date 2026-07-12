( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.querySelector( '.passpress-my-bookings' );
		if ( ! container || typeof PassPressMyBookings === 'undefined' ) {
			return;
		}

		container.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'pp-cancel-booking-btn' ) ) {
				return;
			}

			var button     = e.target;
			var bookingId  = button.dataset.bookingId;
			var row        = button.closest( 'tr' );

			var body = new URLSearchParams();
			body.append( 'action', 'pp_cancel_booking' );
			body.append( 'nonce', PassPressMyBookings.nonce );
			body.append( 'booking_id', bookingId );

			fetch( PassPressMyBookings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					if ( response.success && row ) {
						var statusCell = row.querySelector( '.pp-booking-status' );
						if ( statusCell ) {
							statusCell.textContent = 'cancelled';
						}
						button.remove();
					} else if ( ! response.success ) {
						alert( ( response.data && response.data.message ) || 'Could not cancel booking.' );
					}
				} );
		} );
	} );
} )();
