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

export default function ActivityLogPage() {
	const { data, isLoading } = useQuery( { queryKey: [ 'activity-log' ], queryFn: () => apiFetch( { path: '/passpress/v1/activity-log' } ) } );

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-activity-page"><p>Loading…</p></div>;
	}

	const { items } = data;

	return (
		<div className="wrap passpress-wrap passpress-activity-page">
			<div className="passpress-activity-page-header">
				<div className="passpress-activity-page-copy">
					<p className="passpress-activity-page-eyebrow">Audit</p>
					<h1>Activity Log</h1>
					<p className="passpress-activity-page-desc">Showing the { items.length } most recent { 1 === items.length ? 'event' : 'events' }</p>
				</div>
			</div>

			{ 0 === items.length ? (
				<div className="passpress-activity-empty">
					<p className="passpress-activity-empty-eyebrow">Quiet</p>
					<h2 className="passpress-activity-empty-title">No activity yet</h2>
					<p className="passpress-activity-empty-desc">Memberships, bookings, payments, and other PassPress actions will show up here.</p>
				</div>
			) : (
				<div className="passpress-activity-table-wrap">
					<table className="passpress-activity-table">
						<thead>
							<tr>
								<th>When</th>
								<th>Event</th>
								<th>Message</th>
								<th>User</th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( log, i ) => (
								<tr key={ i }>
									<td>
										<div className="passpress-activity-when">
											<strong>{ log.date_label }</strong>
											<span>{ log.time_label }</span>
										</div>
									</td>
									<td>
										<span className={ `passpress-activity-event passpress-activity-event-${ log.category }` }>
											<span className="passpress-activity-event-dot"></span>
											{ log.event_label }
										</span>
									</td>
									<td><p className="passpress-activity-message">{ log.message }</p></td>
									<td>
										<div className="passpress-activity-person">
											<span className="passpress-activity-avatar">{ initials( log.user_name ) }</span>
											<strong>{ log.user_name }</strong>
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</div>
	);
}
