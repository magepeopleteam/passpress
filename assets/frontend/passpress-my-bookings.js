( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.querySelector( '.passpress-my-bookings' );
		if ( ! container || typeof PassPressMyBookings === 'undefined' ) {
			return;
		}

		container.addEventListener( 'click', function ( e ) {
			var button = e.target.closest( '.pp-cancel-booking-btn' );
			if ( ! button ) {
				return;
			}

			var bookingId = button.dataset.bookingId;
			var item      = button.closest( '.passpress-booking-item' );
			var actions   = button.closest( '.passpress-booking-item-actions' );

			button.disabled = true;

			var body = new URLSearchParams();
			body.append( 'action', 'pp_cancel_booking' );
			body.append( 'nonce', PassPressMyBookings.nonce );
			body.append( 'booking_id', bookingId );

			fetch( PassPressMyBookings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					if ( response.success && item ) {
						var statusCell = item.querySelector( '.pp-booking-status' );
						var cancelledLabel = ( PassPressMyBookings.i18n && PassPressMyBookings.i18n.cancelled ) || 'Cancelled';

						if ( statusCell ) {
							statusCell.dataset.status = 'cancelled';
							statusCell.innerHTML = '<span class="pp-booking-status-dot" aria-hidden="true"></span>' + cancelledLabel;
						}

						item.classList.remove( 'passpress-booking-status-confirmed' );
						item.classList.add( 'passpress-booking-status-cancelled' );

						if ( actions ) {
							actions.remove();
						} else {
							button.remove();
						}
					} else {
						button.disabled = false;
						if ( ! response.success ) {
							alert( ( response.data && response.data.message ) || 'Could not cancel booking.' );
						}
					}
				} )
				.catch( function () {
					button.disabled = false;
					alert( 'Could not cancel booking.' );
				} );
		} );
	} );
} )();
