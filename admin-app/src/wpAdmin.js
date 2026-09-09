// Builds a real admin.php?page=... URL for screens this app hasn't ported
// yet — plain full-page links, same as the WP submenu itself uses, rather
// than client-side navigation into a route that doesn't exist here.
export function adminUrl( params ) {
	const url = new URL( window.location.href );
	url.search = new URLSearchParams( params ).toString();
	return url.toString();
}
