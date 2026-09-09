import { useRouter } from '../router.jsx';
import { adminUrl } from '../wpAdmin.js';

/**
 * A link to another screen inside this same app. Renders a real
 * admin.php?page=... href (so middle-click/open-in-new-tab and screen
 * readers see a real URL, and it still works if JS is off) but intercepts a
 * plain left-click to navigate client-side via the router instead of a full
 * page reload — this is what makes the persistent Shell (rail nav + topbar)
 * feel like one app instead of flashing out/in on every click.
 */
export default function NavLink( { to, params, className, children, onClick: onClickProp, ...rest } ) {
	const { navigate } = useRouter();
	const target = { page: to, ...( params || {} ) };

	const onClick = ( e ) => {
		onClickProp && onClickProp( e );
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		e.preventDefault();
		navigate( target );
	};

	return (
		<a href={ adminUrl( target ) } className={ className } onClick={ onClick } { ...rest }>
			{ children }
		</a>
	);
}
