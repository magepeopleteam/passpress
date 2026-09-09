import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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

export default function BillingHistoryPage() {
	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( { queryKey: [ 'billing-history' ], queryFn: () => apiFetch( { path: '/passpress/v1/billing-history' } ) } );

	const actionMutation = useMutation( {
		mutationFn: ( { id, action } ) => apiFetch( { path: `/passpress/v1/billing-history/${ id }/${ action }`, method: 'POST' } ),
		onSuccess: () => queryClient.invalidateQueries( { queryKey: [ 'billing-history' ] } ),
	} );

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-billing-page"><p>Loading…</p></div>;
	}

	const { items, counts } = data;

	return (
		<div className="wrap passpress-wrap passpress-billing-page">
			<div className="passpress-billing-page-header">
				<div className="passpress-billing-page-copy">
					<p className="passpress-billing-page-eyebrow">Payments</p>
					<h1>Billing History</h1>
					<p className="passpress-billing-page-desc">Showing { items.length } recent { 1 === items.length ? 'transaction' : 'transactions' }</p>
				</div>
			</div>

			<div className="passpress-billing-stat-row">
				<div className="passpress-billing-stat">
					<span className="passpress-billing-stat-label">Paid</span>
					<span className="passpress-billing-stat-number is-paid">{ counts.paid }</span>
				</div>
				<div className="passpress-billing-stat">
					<span className="passpress-billing-stat-label">Pending</span>
					<span className="passpress-billing-stat-number is-pending">{ counts.pending }</span>
				</div>
				<div className="passpress-billing-stat">
					<span className="passpress-billing-stat-label">Failed</span>
					<span className="passpress-billing-stat-number is-failed">{ counts.failed }</span>
				</div>
				<div className="passpress-billing-stat">
					<span className="passpress-billing-stat-label">Other</span>
					<span className="passpress-billing-stat-number">{ counts.other }</span>
				</div>
			</div>

			{ 0 === items.length ? (
				<div className="passpress-billing-empty">
					<p className="passpress-billing-empty-eyebrow">Ledger</p>
					<h2 className="passpress-billing-empty-title">No billing activity yet</h2>
					<p className="passpress-billing-empty-desc">Checkout attempts will show up here once members start purchasing plans.</p>
				</div>
			) : (
				<div className="passpress-billing-table-wrap">
					<table className="passpress-billing-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Member</th>
								<th>Plan</th>
								<th>Type</th>
								<th>Gateway</th>
								<th>Amount</th>
								<th>Coupon</th>
								<th>Status</th>
								<th><span className="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( row ) => (
								<tr key={ row.id }>
									<td>
										<div className="passpress-billing-date">
											<strong>{ row.date_label }</strong>
											<span>{ row.time_label }</span>
										</div>
									</td>
									<td>
										<div className="passpress-billing-member">
											<span className="passpress-billing-avatar">{ initials( row.member_name ) }</span>
											<span className="passpress-billing-member-info">
												<strong>{ row.member_name }</strong>
												{ row.member_email && <span>{ row.member_email }</span> }
											</span>
										</div>
									</td>
									<td><span className="passpress-billing-plan">{ row.plan_title }</span></td>
									<td><span className="passpress-billing-meta">{ row.type }</span></td>
									<td><span className={ `passpress-billing-gateway passpress-billing-gateway-${ row.gateway }` }>{ row.gateway_label }</span></td>
									<td>
										<div className="passpress-billing-amount">
											<strong>{ row.amount }</strong>
											<span>{ row.currency }</span>
										</div>
									</td>
									<td>
										{ row.coupon_code ? (
											<span className="passpress-billing-coupon">{ row.coupon_code } <em>−{ row.discount_amount }</em></span>
										) : (
											<span className="passpress-billing-empty-cell">—</span>
										) }
									</td>
									<td>
										<span className={ `passpress-billing-status passpress-billing-status-${ row.status }` }>
											<span className="passpress-billing-status-dot"></span>
											{ row.status_label }
										</span>
									</td>
									<td className="passpress-billing-actions-cell">
										{ row.can_confirm ? (
											<div className="passpress-billing-actions">
												<a href="#" className="passpress-billing-action is-confirm" onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: row.id, action: 'confirm' } ); } }>Mark paid</a>
												<a href="#" className="passpress-billing-action is-fail" onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: row.id, action: 'fail' } ); } }>Mark failed</a>
											</div>
										) : (
											<span className="passpress-billing-empty-cell">—</span>
										) }
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
