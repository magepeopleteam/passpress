import apiFetch from '@wordpress/api-fetch';

// PP_Admin::enqueue_assets() localizes window.passpressApp = { root, nonce }
// (a REST-nonce/root pair, distinct from the legacy per-action
// PassPressScan.nonce the old admin-ajax.php code still uses on
// not-yet-ported screens). apiFetch then handles the X-WP-Nonce header and
// the nonce-refresh heartbeat for us.
const config = window.passpressApp || {};

if ( config.root ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( config.root ) );
}
if ( config.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
}

export default apiFetch;
