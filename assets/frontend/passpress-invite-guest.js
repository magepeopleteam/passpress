( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.passpress-invite-guest-form' );
		if ( ! form || typeof PassPressInviteGuest === 'undefined' ) {
			return;
		}

		var messageEl = form.querySelector( '.passpress-invite-guest-message' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var body = new URLSearchParams();
			body.append( 'action', 'pp_invite_guest' );
			body.append( 'nonce', PassPressInviteGuest.nonce );
			body.append( 'guest_name', form.querySelector( '[name="guest_name"]' ).value );
			body.append( 'guest_email', form.querySelector( '[name="guest_email"]' ).value );

			fetch( PassPressInviteGuest.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					messageEl.style.display = 'block';
					messageEl.textContent   = ( response.data && response.data.message ) || '';
					messageEl.className     = 'passpress-invite-guest-message passpress-checkout-notice ' + ( response.success ? 'passpress-checkout-notice-success' : 'passpress-checkout-notice-error' );
					if ( response.success ) {
						form.reset();
					}
				} );
		} );
	} );
} )();
