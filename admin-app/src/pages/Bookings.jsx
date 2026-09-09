import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import { useRouter } from '../router.jsx';

const STATUS_FILTERS = [
	{ value: '', label: 'All' },
	{ value: 'confirmed', label: 'Confirmed' },
	{ value: 'completed', label: 'Completed' },
	{ value: 'no_show', label: 'No show' },
	{ value: 'cancelled', label: 'Cancelled' },
];

const ROW_ACTIONS = [
	{ key: 'complete', label: 'Complete' },
	{ key: 'no_show', label: 'No-show' },
	{ key: 'cancel', label: 'Cancel' },
];

export default function BookingsPage() {
	const { params, navigate } = useRouter();
	const status = params.get( 'status' ) || '';
	const facilityId = Number( params.get( 'facility_id' ) || 0 );
	const classSessionId = Number( params.get( 'class_session_id' ) || 0 );
	const paged = Number( params.get( 'paged' ) || 1 );

	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( {
		queryKey: [ 'bookings', { status, facilityId, classSessionId, paged } ],
		queryFn: () => apiFetch( { path: `/passpress/v1/bookings?status=${ status }&facility_id=${ facilityId }&class_session_id=${ classSessionId }&paged=${ paged }` } ),
	} );

	const actionMutation = useMutation( {
		mutationFn: ( { id, action } ) => apiFetch( { path: `/passpress/v1/bookings/${ id }/${ action }`, method: 'POST' } ),
		onSuccess: () => queryClient.invalidateQueries( { queryKey: [ 'bookings' ] } ),
	} );

	const submitFilters = ( e ) => {
		e.preventDefault();
		const form = new FormData( e.target );
		navigate( { facility_id: form.get( 'facility_id' ) || null, class_session_id: form.get( 'class_session_id' ) || null, paged: null } );
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-bookings-page"><p>Loading…</p></div>;
	}

	const { items, total, per_page: perPage, counts, facilities, classes } = data;
	const totalPages = Math.ceil( total / Math.max( 1, perPage ) );
	const first = total ? ( paged - 1 ) * perPage + 1 : 0;
	const last = Math.min( total, paged * perPage );

	return (
		<div className="wrap passpress-wrap passpress-bookings-page">
			<div className="passpress-bookings-page-header">
				<div className="passpress-bookings-page-copy">
					<p className="passpress-bookings-page-eyebrow">Schedule</p>
					<h1>Bookings</h1>
					<p className="passpress-bookings-page-desc">Review facility and class bookings, check-ins, and attendance outcomes.</p>
				</div>
			</div>

			<div className="passpress-bookings-stat-row">
				<div className="passpress-bookings-stat">
					<span className="passpress-bookings-stat-label">Confirmed</span>
					<span className="passpress-bookings-stat-number is-confirmed">{ counts.confirmed }</span>
				</div>
				<div className="passpress-bookings-stat">
					<span className="passpress-bookings-stat-label">Completed</span>
					<span className="passpress-bookings-stat-number is-completed">{ counts.completed }</span>
				</div>
				<div className="passpress-bookings-stat">
					<span className="passpress-bookings-stat-label">No show</span>
					<span className="passpress-bookings-stat-number is-no-show">{ counts.no_show }</span>
				</div>
				<div className="passpress-bookings-stat">
					<span className="passpress-bookings-stat-label">Cancelled</span>
					<span className="passpress-bookings-stat-number is-cancelled">{ counts.cancelled }</span>
				</div>
			</div>

			<div className="passpress-bookings-toolbar">
				<div className="passpress-bookings-toolbar-top">
					<div className="passpress-bookings-tabs">
						{ STATUS_FILTERS.map( ( f ) => (
							<button
								type="button"
								key={ f.value || 'all' }
								className={ `passpress-bookings-tab${ status === f.value ? ' is-active' : '' }` }
								onClick={ () => navigate( { status: f.value || null, paged: null } ) }
							>
								{ f.label }
							</button>
						) ) }
					</div>

					<form className="passpress-bookings-filters" onSubmit={ submitFilters }>
						<select name="facility_id" className="passpress-bookings-select" defaultValue={ facilityId }>
							<option value="0">All facilities</option>
							{ facilities.map( ( f ) => <option key={ f.id } value={ f.id }>{ f.name }</option> ) }
						</select>
						<select name="class_session_id" className="passpress-bookings-select" defaultValue={ classSessionId }>
							<option value="0">All classes</option>
							{ classes.map( ( c ) => <option key={ c.id } value={ c.id }>{ c.name }</option> ) }
						</select>
						<button type="submit" className="passpress-bookings-filter-btn">Filter</button>
					</form>
				</div>
			</div>

			{ 0 === items.length ? (
				<div className="passpress-bookings-empty">
					<p className="passpress-bookings-empty-eyebrow">Calendar</p>
					<h2 className="passpress-bookings-empty-title">No bookings found</h2>
					<p className="passpress-bookings-empty-desc">Try a different status or clear the facility and class filters.</p>
				</div>
			) : (
				<>
					<div className="passpress-bookings-table-wrap">
						<table className="passpress-bookings-table">
							<thead>
								<tr>
									<th>Facility / Class</th>
									<th>Member</th>
									<th>When</th>
									<th>Checked in</th>
									<th>Status</th>
									<th><span className="screen-reader-text">Actions</span></th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( b ) => (
									<tr key={ b.id }>
										<td>
											<div className="passpress-bookings-session">
												<span className="passpress-bookings-session-type">{ b.session_type }</span>
												<strong>{ b.session_title }</strong>
											</div>
										</td>
										<td>
											<div className="passpress-bookings-person">
												<span className="passpress-bookings-avatar">{ initials( b.member_name ) }</span>
												<span className="passpress-bookings-person-info">
													<strong>{ b.member_name }</strong>
													{ b.member_email && <span>{ b.member_email }</span> }
												</span>
											</div>
										</td>
										<td>
											<div className="passpress-bookings-when">
												<strong>{ b.date_label }</strong>
												<span>{ b.time_label }</span>
											</div>
										</td>
										<td>
											{ b.checked_in_label ? <span className="passpress-bookings-checkin">{ b.checked_in_label }</span> : <span className="passpress-bookings-empty-cell">—</span> }
										</td>
										<td>
											<span className={ `passpress-bookings-status passpress-bookings-status-${ b.status }` }>
												<span className="passpress-bookings-status-dot"></span>
												{ b.status_label }
											</span>
										</td>
										<td className="passpress-bookings-actions-cell">
											{ 'confirmed' !== b.status ? (
												<span className="passpress-bookings-empty-cell">&mdash;</span>
											) : (
												<div className="passpress-bookings-actions">
													{ ROW_ACTIONS.map( ( a ) => (
														<a
															href="#"
															key={ a.key }
															className={ `passpress-bookings-action passpress-bookings-action-${ a.key }` }
															onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: b.id, action: a.key } ); } }
														>
															{ a.label }
														</a>
													) ) }
												</div>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>

					<div className="passpress-bookings-footer">
						<p className="passpress-bookings-count">
							Showing <strong>{ first }</strong>–{ last } of <strong>{ total }</strong> bookings
						</p>
						{ totalPages > 1 && (
							<div className="passpress-bookings-pagination">
								{ Array.from( { length: totalPages }, ( _, i ) => i + 1 ).map( ( page ) => (
									<button type="button" key={ page } className={ page === paged ? 'current' : '' } onClick={ () => navigate( { paged: page } ) }>
										{ page }
									</button>
								) ) }
							</div>
						) }
					</div>
				</>
			) }
		</div>
	);
}

function initials( name ) {
	const parts = String( name || '' ).trim().split( /\s+/ );
	if ( ! parts[ 0 ] ) {
		return '?';
	}
	const first = parts[ 0 ][ 0 ] || '';
	const last = parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '';
	return ( first + last ).toUpperCase();
}
