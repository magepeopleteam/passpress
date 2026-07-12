( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof PassPressBooking === 'undefined' ) {
			return;
		}

		// Each .passpress-booking-calendar reads its own facility id from its
		// data attribute (not from the shared PassPressBooking object), so
		// multiple calendars for different facilities on one page each work
		// independently.
		document.querySelectorAll( '.passpress-booking-calendar' ).forEach( initCalendar );
	} );

	function initCalendar( root ) {
		var facilityId     = root.dataset.facilityId;
		var dateInput      = root.querySelector( '.pp-booking-date' );
		var slotsContainer = root.querySelector( '.pp-booking-slots' );
		var messageEl      = root.querySelector( '.pp-booking-message' );
		var i18n           = PassPressBooking.i18n || {};

		function showMessage( text, isError ) {
			messageEl.style.display = 'block';
			messageEl.textContent   = text;
			messageEl.className     = 'passpress-checkout-notice ' + ( isError ? 'passpress-checkout-notice-error' : 'passpress-checkout-notice-success' );
		}

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		function post( action, extra ) {
			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', PassPressBooking.nonce );
			body.append( 'facility_id', facilityId );
			Object.keys( extra || {} ).forEach( function ( key ) {
				body.append( key, extra[ key ] );
			} );

			return fetch( PassPressBooking.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } );
		}

		function loadSlots() {
			slotsContainer.innerHTML = '<p>' + escapeHtml( i18n.loading || 'Loading...' ) + '</p>';

			post( 'pp_get_availability', { date: dateInput.value } ).then( function ( response ) {
				if ( ! response.success ) {
					slotsContainer.innerHTML = '<p>' + escapeHtml( ( response.data && response.data.message ) || i18n.error || '' ) + '</p>';
					return;
				}
				renderSlots( response.data.slots );
			} ).catch( function () {
				slotsContainer.innerHTML = '<p>' + escapeHtml( i18n.error || '' ) + '</p>';
			} );
		}

		function renderSlots( slots ) {
			if ( ! slots.length ) {
				slotsContainer.innerHTML = '<p>' + escapeHtml( i18n.noSlots || '' ) + '</p>';
				return;
			}

			var html = '';
			slots.forEach( function ( slot ) {
				var label = slot.start + ' - ' + slot.end + ' (' + slot.available + '/' + slot.capacity + ' ' + escapeHtml( i18n.open || 'open' ) + ')';
				if ( slot.full ) {
					html += '<div class="passpress-slot passpress-slot-full"><span>' + label + '</span> <button type="button" class="button pp-waitlist-btn" data-start="' + slot.start + '" data-end="' + slot.end + '">' + escapeHtml( i18n.waitlist || 'Join Waitlist' ) + '</button></div>';
				} else {
					html += '<div class="passpress-slot"><span>' + label + '</span> <button type="button" class="button button-primary pp-book-btn" data-start="' + slot.start + '" data-end="' + slot.end + '">' + escapeHtml( i18n.book || 'Book' ) + '</button></div>';
				}
			} );
			slotsContainer.innerHTML = html;
		}

		slotsContainer.addEventListener( 'click', function ( e ) {
			if ( ! PassPressBooking.isLoggedIn ) {
				window.location.href = PassPressBooking.loginUrl;
				return;
			}

			var target = e.target;
			if ( target.classList.contains( 'pp-book-btn' ) ) {
				post( 'pp_create_booking', { date: dateInput.value, start: target.dataset.start, end: target.dataset.end } ).then( function ( response ) {
					showMessage( ( response.data && response.data.message ) || '', ! response.success );
					loadSlots();
				} );
			} else if ( target.classList.contains( 'pp-waitlist-btn' ) ) {
				post( 'pp_join_waitlist', { date: dateInput.value, start: target.dataset.start, end: target.dataset.end } ).then( function ( response ) {
					showMessage( ( response.data && response.data.message ) || '', ! response.success );
				} );
			}
		} );

		dateInput.addEventListener( 'change', loadSlots );
		loadSlots();
	}
} )();
