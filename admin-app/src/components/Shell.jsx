import { useState } from 'react';
import { usePage } from '../router.jsx';
import NavLink from './NavLink.jsx';

// Mirrors the yacht-booking-system plugin's admin shell (dark rail, sticky
// topbar, orange accent) per explicit user request — one flat nav list here
// since PassPress has ~15 screens vs. that plugin's handful, too many for a
// single un-sectioned rail to stay scannable without a bit of grouping.
const NAV_SECTIONS = [
	{
		items: [
			{ page: 'passpress', label: 'Dashboard', icon: 'dashicons-dashboard' },
		],
	},
	{
		title: 'Front desk',
		items: [
			{ page: 'passpress-memberships', label: 'Memberships', icon: 'dashicons-id-alt' },
			{ page: 'passpress-visitors', label: 'Visitors', icon: 'dashicons-admin-users' },
			{ page: 'passpress-bookings', label: 'Bookings', icon: 'dashicons-calendar' },
			{ page: 'passpress-scan-gate', label: 'Scan Gate', icon: 'dashicons-id' },
		],
	},
	{
		title: 'Catalog',
		items: [
			{ page: 'passpress-plans', label: 'Membership Plans', icon: 'dashicons-tickets-alt' },
			{ page: 'passpress-coupons', label: 'Coupons', icon: 'dashicons-tag' },
			{ page: 'passpress-facilities', label: 'Facilities', icon: 'dashicons-building' },
			{ page: 'passpress-class-sessions', label: 'Class Sessions', icon: 'dashicons-calendar-alt' },
		],
	},
	{
		title: 'Insights',
		items: [
			{ page: 'passpress-billing-history', label: 'Billing History', icon: 'dashicons-money-alt' },
			{ page: 'passpress-attendance', label: 'Attendance', icon: 'dashicons-chart-bar' },
			{ page: 'passpress-reports', label: 'Reports', icon: 'dashicons-chart-area' },
			{ page: 'passpress-activity-log', label: 'Activity Log', icon: 'dashicons-list-view' },
		],
	},
	{
		title: 'Configuration',
		items: [
			{ page: 'passpress-setup', label: 'Setup Wizard', icon: 'dashicons-admin-home' },
			{ page: 'passpress-settings', label: 'Settings', icon: 'dashicons-admin-generic' },
		],
	},
];

export default function Shell( { children } ) {
	const currentPage = usePage();
	const [ mobileOpen, setMobileOpen ] = useState( false );

	return (
		<div className="pp-shell">
			{ mobileOpen && <div className="pp-shell-scrim" onClick={ () => setMobileOpen( false ) }></div> }

			<aside className={ `pp-shell-rail${ mobileOpen ? ' is-open' : '' }` }>
				<div className="pp-shell-rail__top">
					<span className="pp-shell-rail__mark"><span className="dashicons dashicons-id-alt" aria-hidden="true"></span></span>
					<span className="pp-shell-rail__brand">PassPress</span>
				</div>

				<nav className="pp-shell-rail__menu">
					{ NAV_SECTIONS.map( ( section, i ) => (
						<div className="pp-shell-rail__section" key={ i }>
							{ section.title && <p className="pp-shell-rail__section-title">{ section.title }</p> }
							<ul>
								{ section.items.map( ( item ) => (
									<li key={ item.page }>
										<NavLink
											to={ item.page }
											className={ `pp-shell-rail__link${ currentPage === item.page ? ' is-active' : '' }` }
											onClick={ () => setMobileOpen( false ) }
										>
											<span className={ `dashicons ${ item.icon }` } aria-hidden="true"></span>
											<span>{ item.label }</span>
										</NavLink>
									</li>
								) ) }
							</ul>
						</div>
					) ) }
				</nav>

				<a className="pp-shell-rail__back" href={ window.location.pathname.split( 'admin.php' )[ 0 ] }>
					<span className="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					Back to WordPress
				</a>
			</aside>

			<div className="pp-shell-main">
				<header className="pp-shell-topbar">
					<button type="button" className="pp-shell-burger" aria-label="Toggle navigation" onClick={ () => setMobileOpen( ( o ) => ! o ) }>
						<span className="dashicons dashicons-menu" aria-hidden="true"></span>
					</button>
					<span className="pp-shell-topbar__label">PassPress</span>
				</header>

				<main className="pp-shell-content">
					{ children }
				</main>
			</div>
		</div>
	);
}
