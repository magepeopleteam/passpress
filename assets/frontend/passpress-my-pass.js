( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof QRCode === 'undefined' ) {
			return;
		}

		var size = ( window.PassPressPass && PassPressPass.qrSize ) ? parseInt( PassPressPass.qrSize, 10 ) : 200;

		document.querySelectorAll( '.passpress-pass-qr' ).forEach( function ( el ) {
			var token = el.getAttribute( 'data-token' );
			if ( ! token ) {
				return;
			}
			new QRCode( el, { text: token, width: size, height: size } );
		} );
	} );
} )();
