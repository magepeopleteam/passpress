import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import apiFetch from '../api.js';
import { useRouter } from '../router.jsx';
import NavLink from '../components/NavLink.jsx';

function initials( name ) {
	const parts = String( name || '' ).trim().split( /\s+/ );
	if ( ! parts[ 0 ] ) {
		return '?';
	}
	const first = parts[ 0 ][ 0 ] || '';
	const last = parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '';
	return ( first + last ).toUpperCase();
}

function BarList( { rows, variant } ) {
	if ( 0 === rows.length ) {
		return <div className="passpress-reports-card-empty"><p>No data in this window.</p></div>;
	}
	return (
		<ul className="passpress-reports-bar-list">
			{ rows.map( ( r ) => (
				<li className="passpress-reports-bar-row" key={ r.day }>
					<span className="passpress-reports-bar-day">{ r.date_label }</span>
					<div className="passpress-reports-bar-track" aria-hidden="true">
						<span className={ `passpress-reports-bar-fill passpress-reports-bar-fill-${ variant }` } style={ { width: `${ r.width_pct }%` } }></span>
					</div>
					<span className="passpress-reports-bar-value">{ r.value_label }</span>
				</li>
			) ) }
		</ul>
	);
}

function EmptySection( { title, desc } ) {
	return (
		<div className="passpress-reports-empty">
			<p className="passpress-reports-empty-eyebrow">Empty</p>
			<h3 className="passpress-reports-empty-title">{ title }</h3>
			<p className="passpress-reports-empty-desc">{ desc }</p>
		</div>
	);
}

export default function ReportsPage() {
	const { params, navigate } = useRouter();
	const startDate = params.get( 'start_date' ) || '';
	const endDate = params.get( 'end_date' ) || '';

	const { data, isLoading } = useQuery( {
		queryKey: [ 'reports', { startDate, endDate } ],
		queryFn: () => apiFetch( { path: `/passpress/v1/reports?start_date=${ startDate }&end_date=${ endDate }` } ),
	} );

	const [ fromInput, setFromInput ] = useState( startDate );
	const [ toInput, setToInput ] = useState( endDate );

	const submit = ( e ) => {
		e.preventDefault();
		navigate( { start_date: fromInput || null, end_date: toInput || null } );
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-reports-page"><p>Loading…</p></div>;
	}

	const from = fromInput || data.start_date;
	const to = toInput || data.end_date;

	return (
		<div className="wrap passpress-wrap passpress-reports-page">
			<div className="passpress-reports-page-header">
				<div className="passpress-reports-page-copy">
					<p className="passpress-reports-page-eyebrow">Analytics</p>
					<h1>Reports</h1>
					<p className="passpress-reports-page-desc">Revenue, growth, facility usage, and payment activity for the selected date range.</p>
				</div>
				<NavLink to="passpress-attendance" className="passpress-reports-peak-link">Peak hours →</NavLink>
			</div>

			<form className="passpress-reports-toolbar" onSubmit={ submit }>
				<div className="passpress-reports-date-fields">
					<label className="passpress-reports-date-field">
						<span>From</span>
						<input type="date" value={ from } onChange={ ( e ) => setFromInput( e.target.value ) } />
					</label>
					<label className="passpress-reports-date-field">
						<span>To</span>
						<input type="date" value={ to } onChange={ ( e ) => setToInput( e.target.value ) } />
					</label>
				</div>
				<button type="submit" className="passpress-reports-update-btn">Update</button>
			</form>

			<div className="passpress-reports-stat-row">
				<div className="passpress-reports-stat">
					<span className="passpress-reports-stat-label">Revenue</span>
					<span className="passpress-reports-stat-number is-revenue">{ data.revenue_total }</span>
				</div>
				<div className="passpress-reports-stat">
					<span className="passpress-reports-stat-label">New members</span>
					<span className="passpress-reports-stat-number">{ data.new_members }</span>
				</div>
				<div className="passpress-reports-stat">
					<span className="passpress-reports-stat-label">Renewal rate</span>
					<span className="passpress-reports-stat-number">{ data.renewal_display }</span>
				</div>
			</div>

			<div className="passpress-reports-grid">
				<section className="passpress-reports-card">
					<div className="passpress-reports-card-header">
						<p className="passpress-reports-card-eyebrow">Money</p>
						<h2>Revenue by day</h2>
					</div>
					<BarList rows={ data.revenue_by_day } variant="revenue" />
				</section>

				<section className="passpress-reports-card">
					<div className="passpress-reports-card-header">
						<p className="passpress-reports-card-eyebrow">Growth</p>
						<h2>Membership growth</h2>
					</div>
					<BarList rows={ data.growth_by_day } variant="growth" />
				</section>
			</div>

			<section className="passpress-reports-renewal">
				<div className="passpress-reports-renewal-copy">
					<p className="passpress-reports-card-eyebrow">Retention</p>
					<h2>Renewal rate</h2>
					<p className="passpress-reports-renewal-desc">{ data.renewal_renewed } renewed, { data.renewal_lapsed } lapsed without renewing in this window.</p>
				</div>
				<div className="passpress-reports-renewal-rate">
					<span className="passpress-reports-renewal-value">{ data.renewal_display }</span>
					<span className="passpress-reports-renewal-label">of eligible cycles</span>
				</div>
			</section>

			<div className="passpress-reports-grid passpress-reports-grid-tables">
				<section className="passpress-reports-section">
					<div className="passpress-reports-section-header">
						<p className="passpress-reports-card-eyebrow">Members</p>
						<h2>Expired members</h2>
					</div>
					{ 0 === data.expired_members.length ? (
						<EmptySection title="No expired members" desc="Members who lapse will appear in this list." />
					) : (
						<div className="passpress-reports-table-wrap">
							<table className="passpress-reports-table">
								<thead><tr><th>Member</th><th>Plan</th><th>Expired on</th></tr></thead>
								<tbody>
									{ data.expired_members.map( ( m, i ) => (
										<tr key={ i }>
											<td>
												<div className="passpress-reports-person">
													<span className="passpress-reports-avatar">{ initials( m.member_name ) }</span>
													<strong>{ m.member_name }</strong>
												</div>
											</td>
											<td><span className="passpress-reports-pill">{ m.plan_title }</span></td>
											<td>{ m.expiry_date_label }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</section>

				<section className="passpress-reports-section">
					<div className="passpress-reports-section-header">
						<p className="passpress-reports-card-eyebrow">Usage</p>
						<h2>Facility usage</h2>
					</div>
					{ 0 === data.facilities.length ? (
						<EmptySection title="No activity in this window" desc="Bookings and door entries will show up here." />
					) : (
						<div className="passpress-reports-table-wrap">
							<table className="passpress-reports-table">
								<thead><tr><th>Facility</th><th>Bookings</th><th>Entries</th></tr></thead>
								<tbody>
									{ data.facilities.map( ( f, i ) => (
										<tr key={ i }>
											<td><strong>{ f.name }</strong></td>
											<td>{ f.bookings }</td>
											<td>{ f.entries }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</section>
			</div>

			<div className="passpress-reports-grid passpress-reports-grid-tables">
				<section className="passpress-reports-section">
					<div className="passpress-reports-section-header">
						<p className="passpress-reports-card-eyebrow">Catalog</p>
						<h2>Popular plans</h2>
					</div>
					{ 0 === data.plans.length ? (
						<EmptySection title="No memberships yet" desc="Issued plans will rank here by member count." />
					) : (
						<div className="passpress-reports-table-wrap">
							<table className="passpress-reports-table">
								<thead><tr><th>Plan</th><th>Active / total</th></tr></thead>
								<tbody>
									{ data.plans.map( ( p, i ) => (
										<tr key={ i }>
											<td><span className="passpress-reports-pill">{ p.name }</span></td>
											<td><strong>{ p.count }</strong></td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</section>

				<section className="passpress-reports-section">
					<div className="passpress-reports-section-header">
						<p className="passpress-reports-card-eyebrow">Checkout</p>
						<h2>Payment reports</h2>
					</div>
					{ 0 === data.payments.length ? (
						<EmptySection title="No payment activity" desc="Gateway totals for this range will appear here." />
					) : (
						<div className="passpress-reports-table-wrap">
							<table className="passpress-reports-table">
								<thead><tr><th>Gateway</th><th>Status</th><th>Count</th><th>Total</th></tr></thead>
								<tbody>
									{ data.payments.map( ( p, i ) => (
										<tr key={ i }>
											<td><strong>{ p.gateway_label }</strong></td>
											<td>
												<span className={ `passpress-reports-status passpress-reports-status-${ p.status }` }>
													<span className="passpress-reports-status-dot"></span>
													{ p.status_label }
												</span>
											</td>
											<td>{ p.count }</td>
											<td><strong>{ p.total_label }</strong></td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</section>
			</div>

			<section className="passpress-reports-section">
				<div className="passpress-reports-section-header">
					<p className="passpress-reports-card-eyebrow">Classes</p>
					<h2>Trainer performance</h2>
				</div>
				{ 0 === data.instructors.length ? (
					<EmptySection title="No instructor activity" desc="No instructor-assigned classes with activity in this window." />
				) : (
					<div className="passpress-reports-table-wrap">
						<table className="passpress-reports-table">
							<thead><tr><th>Instructor</th><th>Classes</th><th>Bookings</th><th>Attended</th><th>No-shows</th></tr></thead>
							<tbody>
								{ data.instructors.map( ( t, i ) => (
									<tr key={ i }>
										<td>
											<div className="passpress-reports-person">
												<span className="passpress-reports-avatar">{ initials( t.name ) }</span>
												<strong>{ t.name }</strong>
											</div>
										</td>
										<td>{ t.classes }</td>
										<td>{ t.total_bookings }</td>
										<td>{ t.attended }</td>
										<td>{ t.no_shows }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</section>
		</div>
	);
}
