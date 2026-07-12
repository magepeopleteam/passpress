( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.passpress-class-schedule' );
		if ( ! root || typeof PassPressClassSchedule === 'undefined' ) {
			return;
		}

		var messageEl = root.querySelector( '.passpress-class-message' );
		var i18n      = PassPressClassSchedule.i18n || {};

		function showMessage( text, isError ) {
			messageEl.style.display = 'block';
			messageEl.textContent   = text;
			messageEl.className     = 'passpress-class-message passpress-checkout-notice ' + ( isError ? 'passpress-checkout-notice-error' : 'passpress-checkout-notice-success' );
		}

		root.addEventListener( 'click', function ( e ) {
			var target = e.target;
			var isBook     = target.classList.contains( 'pp-class-book-btn' );
			var isWaitlist = target.classList.contains( 'pp-class-waitlist-btn' );
			if ( ! isBook && ! isWaitlist ) {
				return;
			}

			if ( ! PassPressClassSchedule.isLoggedIn ) {
				window.location.href = PassPressClassSchedule.loginUrl;
				return;
			}

			var row      = target.closest( 'tr' );
			var classId  = row.dataset.classId;
			var date     = row.dataset.date;

			var body = new URLSearchParams();
			body.append( 'action', isBook ? 'pp_book_class' : 'pp_join_class_waitlist' );
			body.append( 'nonce', PassPressClassSchedule.nonce );
			body.append( 'class_session_id', classId );
			body.append( 'date', date );

			target.disabled = true;

			fetch( PassPressClassSchedule.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					showMessage( ( response.data && response.data.message ) || i18n.error || '', ! response.success );
					if ( response.success ) {
						target.textContent = isBook ? '✓' : i18n.waitlist;
					} else {
						target.disabled = false;
					}
				} )
				.catch( function () {
					showMessage( i18n.error || '', true );
					target.disabled = false;
				} );
		} );
	} );
} )();
