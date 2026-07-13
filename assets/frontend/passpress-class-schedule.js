( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.passpress-class-schedule' );
		if ( ! root || typeof PassPressClassSchedule === 'undefined' ) {
			return;
		}

		var messageEl = root.querySelector( '.passpress-class-message' );
		var i18n = PassPressClassSchedule.i18n || {};

		function showMessage( text, isError ) {
			if ( ! messageEl ) {
				return;
			}
			messageEl.hidden = false;
			messageEl.textContent = text || '';
			messageEl.className = 'passpress-class-message ' + ( isError ? 'is-error' : 'is-success' );
			messageEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		root.addEventListener( 'click', function ( e ) {
			var target = e.target.closest ? e.target.closest( '.pp-class-book-btn, .pp-class-waitlist-btn' ) : null;
			if ( ! target ) {
				return;
			}

			var isBook = target.classList.contains( 'pp-class-book-btn' );
			var isWaitlist = target.classList.contains( 'pp-class-waitlist-btn' );
			if ( ! isBook && ! isWaitlist ) {
				return;
			}

			if ( ! PassPressClassSchedule.isLoggedIn ) {
				window.location.href = PassPressClassSchedule.loginUrl;
				return;
			}

			var row = target.closest( '.passpress-class-occurrence' );
			if ( ! row ) {
				return;
			}

			var classId = row.getAttribute( 'data-class-id' );
			var date = row.getAttribute( 'data-date' );
			var defaultLabel = target.textContent;

			var body = new URLSearchParams();
			body.append( 'action', isBook ? 'pp_book_class' : 'pp_join_class_waitlist' );
			body.append( 'nonce', PassPressClassSchedule.nonce );
			body.append( 'class_session_id', classId );
			body.append( 'date', date );

			target.disabled = true;
			target.textContent = i18n.processing || '…';

			fetch( PassPressClassSchedule.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					showMessage( ( response.data && response.data.message ) || i18n.error || '', ! response.success );
					if ( response.success ) {
						target.textContent = isBook ? ( i18n.booked || 'Booked' ) : ( i18n.waitlisted || i18n.waitlist || 'Waitlisted' );
						target.classList.add( 'is-done' );
						row.classList.add( 'is-booked' );
					} else {
						target.disabled = false;
						target.textContent = defaultLabel;
					}
				} )
				.catch( function () {
					showMessage( i18n.error || '', true );
					target.disabled = false;
					target.textContent = defaultLabel;
				} );
		} );
	} );
} )();
