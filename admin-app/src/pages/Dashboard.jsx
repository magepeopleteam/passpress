import { useQuery } from '@tanstack/react-query';
import apiFetch from '../api.js';
import { useRouter } from '../router.jsx';
import KpiTile from '../components/KpiTile.jsx';
import NavLink from '../components/NavLink.jsx';
import SetupWizardModal from '../components/SetupWizardModal.jsx';

const ACTIONS = ( plansCount, activeLabel ) => [
	{
		page: 'passpress-memberships',
		icon: 'dashicons-id-alt',
		tone: 'membership',
		title: 'Issue or manage memberships',
		desc: 'Front-desk walk-ins and renewals',
	},
	{
		page: 'passpress-plans',
		icon: 'dashicons-tickets-alt',
		tone: 'plan',
		title: 'Membership plans',
		desc: `${ plansCount } ${ 1 === plansCount ? 'plan' : 'plans' } in catalog`,
	},
	{
		page: 'passpress-bookings',
		icon: 'dashicons-calendar',
		tone: 'booking',
		title: 'Bookings',
		desc: 'Facility slots and class sessions',
	},
	{
		page: 'passpress-visitors',
		icon: 'dashicons-admin-users',
		tone: 'visitor',
		title: 'Visitors',
		desc: 'Day passes and guest invites',
	},
	{
		page: 'passpress-coupons',
		icon: 'dashicons-tag',
		tone: 'billing',
		title: 'Coupons',
		desc: 'Promo codes for checkout',
	},
	{
		page: 'passpress-reports',
		icon: 'dashicons-chart-area',
		tone: 'system',
		title: 'Reports',
		desc: 'Revenue, growth, and usage',
	},
	{
		page: 'passpress-settings',
		icon: 'dashicons-admin-generic',
		tone: 'system',
		title: 'Settings',
		desc: 'Currency, payments, emails',
	},
	{
		page: 'passpress-setup',
		icon: 'dashicons-admin-home',
		tone: 'accent',
		title: 'Setup Wizard',
		desc: activeLabel ? `Business type: ${ activeLabel }` : 'Import a business template',
	},
];

const CATEGORY_LABELS = {
	membership: 'Membership',
	visitor: 'Visitor',
	billing: 'Billing',
	booking: 'Booking',
	facility: 'Facility',
	class: 'Class',
	plan: 'Plan',
	system: 'System',
};

function GateOperatorView() {
	return (
		<div className="wrap passpress-wrap passpress-dashboard-page passpress-dashboard-gate">
			<div className="passpress-dashboard-page-header">
				<div className="passpress-dashboard-page-copy">
					<p className="passpress-dashboard-page-eyebrow">Front desk</p>
					<h1>PassPress</h1>
					<p className="passpress-dashboard-page-desc">Check members and visitors in or out with QR or PIN.</p>
				</div>
			</div>
			<div className="passpress-dashboard-gate-card">
				<span className="passpress-dashboard-gate-icon">
					<span className="dashicons dashicons-id" aria-hidden="true"></span>
				</span>
				<div>
					<strong>Ready when you are</strong>
					<p>Open Scan Gate to validate passes at the door.</p>
				</div>
				<NavLink to="passpress-scan-gate" className="pp-btn-solid">
					Go to Scan Gate
				</NavLink>
			</div>
		</div>
	);
}

export default function DashboardPage() {
	const { params, navigate } = useRouter();
	const { data, isLoading, isError } = useQuery( {
		queryKey: [ 'dashboard' ],
		queryFn: () => apiFetch( { path: '/passpress/v1/dashboard' } ),
	} );

	if ( isLoading ) {
		return <div className="wrap passpress-wrap passpress-dashboard-page"><p>Loading…</p></div>;
	}

	if ( isError || ! data ) {
		return <div className="wrap passpress-wrap passpress-dashboard-page"><p>Couldn't load the dashboard. Try refreshing.</p></div>;
	}

	if ( ! data.can_manage ) {
		return <GateOperatorView />;
	}

	const { stats, activity, active_label: activeLabel, plans_count: plansCount, needs_setup: needsSetup, notice } = data;
	const actions = ACTIONS( plansCount, activeLabel );

	return (
		<div className="wrap passpress-wrap passpress-dashboard-page">
			<div className="passpress-dashboard-page-header">
				<div className="passpress-dashboard-page-copy">
					<p className="passpress-dashboard-page-eyebrow">Overview</p>
					<h1>Dashboard</h1>
					<p className="passpress-dashboard-page-desc">
						{ activeLabel
							? `Running as ${ activeLabel } — memberships, check-ins, and what's next.`
							: 'Membership health, today’s attendance, and shortcuts to run the front desk.' }
					</p>
				</div>
				<div className="passpress-dashboard-header-actions">
					<NavLink to="passpress-scan-gate" className="passpress-dashboard-primary-btn">
						<span className="dashicons dashicons-id" aria-hidden="true"></span>
						Open Scan Gate
					</NavLink>
					<NavLink to="passpress-memberships" className="passpress-dashboard-secondary-btn">
						Manage members
					</NavLink>
				</div>
			</div>

			{ notice && (
				<div className="passpress-dashboard-flash is-success">
					<span className="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p>{ notice }</p>
				</div>
			) }

			{ needsSetup && (
				<div className="passpress-dashboard-setup-cta">
					<div className="passpress-dashboard-setup-copy">
						<p className="passpress-dashboard-setup-eyebrow">Get started</p>
						<strong>Finish setup with a business template</strong>
						<span>Import sample plans, facilities, and pages so you can start selling and scanning sooner.</span>
					</div>
					<NavLink to="passpress-setup" className="pp-btn-solid">
						Open Setup Wizard
					</NavLink>
				</div>
			) }

			<div className="passpress-dashboard-kpis">
				<KpiTile
					to="passpress-memberships"
					params={ { status: 'active' } }
					tone="active"
					icon="dashicons-groups"
					label="Active members"
					value={ stats.active_memberships }
					hint="View active list"
				/>
				<KpiTile
					to="passpress-memberships"
					params={ { status: 'active' } }
					tone="warn"
					icon="dashicons-calendar-alt"
					label="Expiring in 7 days"
					value={ stats.expiring_soon }
					hint="Renew or follow up"
				/>
				<KpiTile
					to="passpress-attendance"
					tone="checkin"
					icon="dashicons-yes"
					label="Today's check-ins"
					value={ stats.todays_checkins }
					hint="Attendance reports"
				/>
				<KpiTile
					to="passpress-memberships"
					tone="muted"
					icon="dashicons-warning"
					label="Frozen / suspended"
					value={ stats.frozen_suspended }
					hint="Review holds"
				/>
			</div>

			<div className="passpress-dashboard-grid">
				<section className="passpress-dashboard-panel">
					<header className="passpress-dashboard-panel-head">
						<div>
							<p className="passpress-dashboard-panel-eyebrow">Shortcuts</p>
							<h2>Quick actions</h2>
						</div>
					</header>
					<div className="passpress-dashboard-actions">
						{ actions.map( ( action ) => (
							<NavLink to={ action.page } className="passpress-dashboard-action" key={ action.page }>
								<span className={ `passpress-dashboard-action-icon is-${ action.tone }` }>
									<span className={ `dashicons ${ action.icon }` } aria-hidden="true"></span>
								</span>
								<span className="passpress-dashboard-action-copy">
									<strong>{ action.title }</strong>
									<em>{ action.desc }</em>
								</span>
								<span className="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</NavLink>
						) ) }
					</div>
				</section>

				<section className="passpress-dashboard-panel">
					<header className="passpress-dashboard-panel-head">
						<div>
							<p className="passpress-dashboard-panel-eyebrow">Live</p>
							<h2>Recent activity</h2>
						</div>
						<NavLink to="passpress-activity-log" className="passpress-dashboard-panel-link">
							View all
						</NavLink>
					</header>

					{ activity.length === 0 ? (
						<div className="passpress-dashboard-empty">
							<p className="passpress-dashboard-empty-eyebrow">Nothing yet</p>
							<strong>Activity will show up here</strong>
							<span>Issuing passes, scans, bookings, and payments all land in this feed.</span>
						</div>
					) : (
						<ul className="passpress-dashboard-activity">
							{ activity.map( ( row, index ) => (
								<li key={ index }>
									<div className="passpress-dashboard-activity-body">
										<span className={ `passpress-activity-event passpress-activity-event-${ row.category }` }>
											<span className="passpress-activity-event-dot" aria-hidden="true"></span>
											{ CATEGORY_LABELS[ row.category ] || 'Activity' }
										</span>
										<strong>{ row.message }</strong>
										<span>{ row.meta }</span>
									</div>
								</li>
							) ) }
						</ul>
					) }
				</section>
			</div>

			<SetupWizardModal
				open={ needsSetup && '1' === params.get( 'pp_welcome' ) }
				onClose={ () => navigate( { pp_welcome: null } ) }
			/>
		</div>
	);
}
