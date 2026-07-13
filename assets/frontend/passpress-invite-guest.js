( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.passpress-invite-guest-form' );
		if ( ! form || typeof PassPressInviteGuest === 'undefined' ) {
			return;
		}

		var messageEl = form.querySelector( '.passpress-invite-guest-message' );
		var submitBtn = form.querySelector( '.passpress-invite-guest-submit' );
		var defaultLabel = submitBtn ? submitBtn.textContent : '';

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = ( PassPressInviteGuest.i18n && PassPressInviteGuest.i18n.sending ) || 'Sending…';
			}

			var body = new URLSearchParams();
			body.append( 'action', 'pp_invite_guest' );
			body.append( 'nonce', PassPressInviteGuest.nonce );
			body.append( 'guest_name', form.querySelector( '[name="guest_name"]' ).value );
			body.append( 'guest_email', form.querySelector( '[name="guest_email"]' ).value );

			fetch( PassPressInviteGuest.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					if ( messageEl ) {
						messageEl.hidden = false;
						messageEl.textContent = ( response.data && response.data.message ) || '';
						messageEl.className = 'passpress-invite-guest-message ' + ( response.success ? 'is-success' : 'is-error' );
					}
					if ( response.success ) {
						form.reset();
					}
				} )
				.catch( function () {
					if ( messageEl ) {
						messageEl.hidden = false;
						messageEl.textContent = ( PassPressInviteGuest.i18n && PassPressInviteGuest.i18n.error ) || 'Something went wrong.';
						messageEl.className = 'passpress-invite-guest-message is-error';
					}
				} )
				.then( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = defaultLabel;
					}
				} );
		} );
	} );
} )();
