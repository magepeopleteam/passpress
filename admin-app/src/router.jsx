import { createContext, useContext, useEffect, useState } from 'react';

// WP admin URLs here are query-string based (admin.php?page=passpress-xxx),
// not path segments, so a real react-router basename doesn't fit — this is
// a small matcher over URLSearchParams instead. Every submenu slug keeps
// pointing at a real admin.php?page=... URL (capability-gated visibility,
// bookmarks, and direct links all keep working); intra-app navigation uses
// pushState to update `page` (and any other params a screen needs) without a
// full reload.

const RouterContext = createContext( null );

function readParams() {
	return new URLSearchParams( window.location.search );
}

export function RouterProvider( { children } ) {
	const [ params, setParams ] = useState( readParams );

	useEffect( () => {
		const onPopState = () => setParams( readParams() );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [] );

	const navigate = ( nextParams, { replace = false } = {} ) => {
		const merged = new URLSearchParams( window.location.search );
		Object.entries( nextParams ).forEach( ( [ key, value ] ) => {
			if ( value === null || value === undefined || value === '' ) {
				merged.delete( key );
			} else {
				merged.set( key, value );
			}
		} );
		const url = `${ window.location.pathname }?${ merged.toString() }`;
		if ( replace ) {
			window.history.replaceState( {}, '', url );
		} else {
			window.history.pushState( {}, '', url );
		}
		setParams( merged );
	};

	return (
		<RouterContext.Provider value={ { params, navigate } }>
			{ children }
		</RouterContext.Provider>
	);
}

export function useRouter() {
	const ctx = useContext( RouterContext );
	if ( ! ctx ) {
		throw new Error( 'useRouter must be used inside a RouterProvider' );
	}
	return ctx;
}

export function usePage() {
	const { params } = useRouter();
	return params.get( 'page' ) || 'passpress';
}
