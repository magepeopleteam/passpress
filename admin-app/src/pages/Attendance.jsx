import { useQuery } from '@tanstack/react-query';
import apiFetch from '../api.js';

function initials( name ) {
	const parts = String( name || '' ).trim().split( /\s+/ );
	if ( ! parts[ 0 ] ) {
		return '?';
	}
	const first = parts[ 0 ][ 0 ] || '';
	const last = parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '';
	return ( first + last ).toUpperCase();
}

export default function AttendancePage() {
	const { data, isLoading } = useQuery( { queryKey: [ 'attendance' ], queryFn: () => apiFetch( { path: '/passpress/v1/attendance' } ) } );

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-attendance-page"><p>Loading…</p></div>;
	}

	const { today_count: todayCount, week_total: weekTotal, month_total: monthTotal, week_days: weekDays, peak_hours: peakHours, late_arrivals: lateArrivals } = data;
	const weekMax = Math.max( 1, ...weekDays.map( ( d ) => d.count ) );
	const peakMax = Math.max( 1, ...peakHours.map( ( p ) => p.count ) );

	return (
		<div className="wrap passpress-wrap passpress-attendance-page">
			<div className="passpress-attendance-page-header">
				<div className="passpress-attendance-page-copy">
					<p className="passpress-attendance-page-eyebrow">Insights</p>
					<h1>Attendance</h1>
					<p className="passpress-attendance-page-desc">Check-ins, peak hours, and late class arrivals from access logs and bookings.</p>
				</div>
			</div>

			<div className="passpress-attendance-stat-row">
				<div className="passpress-attendance-stat">
					<span className="passpress-attendance-stat-label">Today's check-ins</span>
					<span className="passpress-attendance-stat-number is-today">{ todayCount }</span>
				</div>
				<div className="passpress-attendance-stat">
					<span className="passpress-attendance-stat-label">Last 7 days</span>
					<span className="passpress-attendance-stat-number">{ weekTotal }</span>
				</div>
				<div className="passpress-attendance-stat">
					<span className="passpress-attendance-stat-label">This month</span>
					<span className="passpress-attendance-stat-number">{ monthTotal }</span>
				</div>
			</div>

			<div className="passpress-attendance-grid">
				<section className="passpress-attendance-card">
					<div className="passpress-attendance-card-header">
						<p className="passpress-attendance-card-eyebrow">Trend</p>
						<h2>Daily attendance</h2>
						<p className="passpress-attendance-card-desc">Last 7 days</p>
					</div>
					<ul className="passpress-attendance-daily-list">
						{ weekDays.map( ( d ) => (
							<li className={ `passpress-attendance-daily-row${ d.is_today ? ' is-today' : '' }` } key={ d.date }>
								<div className="passpress-attendance-daily-meta">
									<strong>{ d.date_label }</strong>
									<span>{ d.count }</span>
								</div>
								<div className="passpress-attendance-bar-track" aria-hidden="true">
									<span className="passpress-attendance-bar-fill" style={ { width: `${ Math.round( ( d.count / weekMax ) * 100 ) }%` } }></span>
								</div>
							</li>
						) ) }
					</ul>
				</section>

				<section className="passpress-attendance-card">
					<div className="passpress-attendance-card-header">
						<p className="passpress-attendance-card-eyebrow">Busy times</p>
						<h2>Peak hours</h2>
						<p className="passpress-attendance-card-desc">Last 30 days</p>
					</div>
					{ 0 === peakHours.length ? (
						<div className="passpress-attendance-card-empty">
							<p>No peak-hour data yet. Check-ins will appear here once members start scanning in.</p>
						</div>
					) : (
						<ul className="passpress-attendance-peak-list">
							{ peakHours.map( ( p ) => (
								<li className="passpress-attendance-peak-row" key={ p.hour }>
									<span className="passpress-attendance-peak-hour">{ p.label }</span>
									<div className="passpress-attendance-bar-track" aria-hidden="true">
										<span className="passpress-attendance-bar-fill is-peak" style={ { width: `${ Math.round( ( p.count / peakMax ) * 100 ) }%` } }></span>
									</div>
									<span className="passpress-attendance-peak-count">{ p.count }</span>
								</li>
							) ) }
						</ul>
					) }
				</section>
			</div>

			<section className="passpress-attendance-late">
				<div className="passpress-attendance-late-header">
					<div>
						<p className="passpress-attendance-card-eyebrow">Classes</p>
						<h2>Late class arrivals</h2>
						<p className="passpress-attendance-card-desc">Marked "Complete" after the scheduled start time. Early exit isn't tracked — there's no reliable checkout signal for classes.</p>
					</div>
					<span className="passpress-attendance-late-range">Last 30 days</span>
				</div>

				{ 0 === lateArrivals.length ? (
					<div className="passpress-attendance-empty">
						<p className="passpress-attendance-empty-eyebrow">On time</p>
						<h3 className="passpress-attendance-empty-title">No late arrivals recorded</h3>
						<p className="passpress-attendance-empty-desc">When staff complete a class booking after the start time, it will show up here.</p>
					</div>
				) : (
					<div className="passpress-attendance-table-wrap">
						<table className="passpress-attendance-table">
							<thead>
								<tr>
									<th>Date</th>
									<th>Class</th>
									<th>Member</th>
									<th>Scheduled</th>
									<th>Minutes late</th>
								</tr>
							</thead>
							<tbody>
								{ lateArrivals.map( ( row, i ) => (
									<tr key={ i }>
										<td><div className="passpress-attendance-when"><strong>{ row.date_label }</strong></div></td>
										<td><span className="passpress-attendance-class">{ row.class_title }</span></td>
										<td>
											<div className="passpress-attendance-person">
												<span className="passpress-attendance-avatar">{ initials( row.member_name ) }</span>
												<strong>{ row.member_name }</strong>
											</div>
										</td>
										<td><span className="passpress-attendance-scheduled">{ row.scheduled }</span></td>
										<td><span className="passpress-attendance-late-pill">{ row.late_minutes } { 1 === row.late_minutes ? 'min' : 'mins' }</span></td>
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
