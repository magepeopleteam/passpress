import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import { useRouter } from '../router.jsx';

const STATUS_FILTERS = [
	{ value: '', label: 'All' },
	{ value: 'active', label: 'Active' },
	{ value: 'expired', label: 'Expired' },
	{ value: 'cancelled', label: 'Cancelled' },
];

function initials( name ) {
	const parts = String( name || '' ).trim().split( /\s+/ );
	if ( ! parts[ 0 ] ) {
		return '?';
	}
	const first = parts[ 0 ][ 0 ] || '';
	const last = parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '';
	return ( first + last ).toUpperCase();
}

function InvitationRow( { invitation, plans, onFinalize, pending } ) {
	const [ planId, setPlanId ] = useState( '' );
	return (
		<tr>
			<td>
				<div className="passpress-visitors-person">
					<span className="passpress-visitors-avatar">{ initials( invitation.guest_name ) }</span>
					<strong>{ invitation.guest_name }</strong>
				</div>
			</td>
			<td>{ invitation.guest_email || '—' }</td>
			<td>{ invitation.host_name || '—' }</td>
			<td>
				<form
					className="passpress-visitors-issue-form"
					onSubmit={ ( e ) => { e.preventDefault(); if ( planId ) { onFinalize( invitation.guest_user_id, planId ); } } }
				>
					<select className="pp-input pp-input-select" required value={ planId } onChange={ ( e ) => setPlanId( e.target.value ) }>
						<option value="">Select a pass type</option>
						{ plans.map( ( p ) => <option key={ p.id } value={ p.id }>{ p.name }</option> ) }
					</select>
					<button type="submit" className="passpress-visitors-issue-btn" disabled={ pending }>Issue</button>
				</form>
			</td>
		</tr>
	);
}

export default function VisitorsPage() {
	const { params, navigate } = useRouter();
	const status = params.get( 'status' ) || '';
	const search = params.get( 's' ) || '';
	const paged = Number( params.get( 'paged' ) || 1 );

	const [ searchInput, setSearchInput ] = useState( search );
	const [ modalOpen, setModalOpen ] = useState( false );
	const [ form, setForm ] = useState( { visitor_name: '', visitor_email: '', visitor_phone: '', plan_id: '' } );
	const [ error, setError ] = useState( '' );

	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( {
		queryKey: [ 'visitors', { status, search, paged } ],
		queryFn: () => apiFetch( { path: `/passpress/v1/visitors?status=${ status }&s=${ encodeURIComponent( search ) }&paged=${ paged }` } ),
	} );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'visitors' ] } );

	const registerMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/visitors', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const finalizeMutation = useMutation( {
		mutationFn: ( { guestUserId, planId } ) => apiFetch( { path: `/passpress/v1/visitors/invitations/${ guestUserId }/finalize`, method: 'POST', data: { plan_id: planId } } ),
		onSuccess: () => invalidate(),
	} );

	const actionMutation = useMutation( {
		mutationFn: ( { id, action } ) => apiFetch( { path: `/passpress/v1/visitors/${ id }/${ action }`, method: 'POST' } ),
		onSuccess: () => invalidate(),
	} );

	const submitSearch = ( e ) => {
		e.preventDefault();
		navigate( { s: searchInput, paged: null } );
	};

	const submitRegister = ( e ) => {
		e.preventDefault();
		setError( '' );
		registerMutation.mutate( form );
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-visitors-page"><p>Loading…</p></div>;
	}

	const { items, invitations, plans } = data;

	return (
		<div className="wrap passpress-wrap passpress-visitors-page">
			<div className="passpress-visitors-page-header">
				<div className="passpress-visitors-page-copy">
					<p className="passpress-visitors-page-eyebrow">Front desk</p>
					<h1>Visitors</h1>
					<p className="passpress-visitors-page-desc">Register walk-ins, finalize guest invites, and manage visitor passes.</p>
				</div>
				<button type="button" className="passpress-visitors-register-btn" onClick={ () => { setForm( { visitor_name: '', visitor_email: '', visitor_phone: '', plan_id: '' } ); setError( '' ); setModalOpen( true ); } }>
					Register visitor
				</button>
			</div>

			{ invitations.length > 0 && (
				<section className="passpress-visitors-invites">
					<div className="passpress-visitors-invites-header">
						<p className="passpress-visitors-invites-eyebrow">Invitations</p>
						<h2>Pending guest invitations</h2>
					</div>
					<div className="passpress-visitors-table-wrap">
						<table className="passpress-visitors-table">
							<thead>
								<tr>
									<th>Guest</th>
									<th>Email</th>
									<th>Invited by</th>
									<th>Issue pass</th>
								</tr>
							</thead>
							<tbody>
								{ invitations.map( ( inv ) => (
									<InvitationRow
										key={ inv.guest_user_id }
										invitation={ inv }
										plans={ plans }
										pending={ finalizeMutation.isPending }
										onFinalize={ ( guestUserId, planId ) => finalizeMutation.mutate( { guestUserId, planId } ) }
									/>
								) ) }
							</tbody>
						</table>
					</div>
				</section>
			) }

			<div className="passpress-visitors-toolbar">
				<div className="passpress-visitors-toolbar-top">
					<div className="passpress-visitors-tabs">
						{ STATUS_FILTERS.map( ( f ) => (
							<button
								type="button"
								key={ f.value || 'all' }
								className={ `passpress-visitors-tab${ status === f.value ? ' is-active' : '' }` }
								onClick={ () => navigate( { status: f.value || null, paged: null } ) }
							>
								{ f.label }
							</button>
						) ) }
					</div>

					<form className="passpress-visitors-search-form" onSubmit={ submitSearch }>
						<input type="search" className="passpress-visitors-search" placeholder="Search pass #…" value={ searchInput } onChange={ ( e ) => setSearchInput( e.target.value ) } />
						<button type="submit" className="passpress-visitors-search-btn">Search</button>
					</form>
				</div>
			</div>

			{ 0 === items.length ? (
				<div className="passpress-visitors-empty">
					<p className="passpress-visitors-empty-eyebrow">History</p>
					<h2 className="passpress-visitors-empty-title">No visitor passes found</h2>
					<p className="passpress-visitors-empty-desc">Register a walk-in or issue a pass from a pending invitation to get started.</p>
				</div>
			) : (
				<div className="passpress-visitors-table-wrap">
					<table className="passpress-visitors-table">
						<thead>
							<tr>
								<th>Pass #</th>
								<th>Visitor</th>
								<th>Host</th>
								<th>Pass type</th>
								<th>Status</th>
								<th>Expiry</th>
								<th>PIN</th>
								<th><span className="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( v ) => (
								<tr key={ v.id }>
									<td><code className="passpress-visitors-code">{ v.membership_number }</code></td>
									<td>
										<div className="passpress-visitors-person">
											<span className="passpress-visitors-avatar">{ initials( v.visitor_name ) }</span>
											<span className="passpress-visitors-person-info">
												<strong>{ v.visitor_name }</strong>
												{ v.visitor_email && <span>{ v.visitor_email }</span> }
											</span>
										</div>
									</td>
									<td>{ v.host_name || '—' }</td>
									<td><span className="passpress-visitors-plan">{ v.plan_title }</span></td>
									<td>
										<span className={ `passpress-visitors-status passpress-visitors-status-${ v.status }` }>
											<span className="passpress-visitors-status-dot"></span>
											{ v.status_label }
										</span>
									</td>
									<td>{ v.expiry_date_label }</td>
									<td><code className="passpress-visitors-pin">{ v.pin_code }</code></td>
									<td className="passpress-visitors-actions-cell">
										<div className="passpress-visitors-actions">
											<a href="#" className="passpress-visitors-action passpress-visitors-action-renew" onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: v.id, action: 'renew' } ); } }>Renew</a>
											<a href="#" className="passpress-visitors-action passpress-visitors-action-cancel" onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: v.id, action: 'cancel' } ); } }>Cancel</a>
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }

			<Modal open={ modalOpen } onClose={ () => setModalOpen( false ) } eyebrow="Walk-in" title="Register visitor" error={ error } modalClassName="passpress-visitor-modal">
				<form className="pp-plan-form" onSubmit={ submitRegister }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_visitor_name">Name</label>
						<input type="text" id="pp_visitor_name" className="pp-input" placeholder="Alex Rivera" required value={ form.visitor_name } onChange={ ( e ) => setForm( { ...form, visitor_name: e.target.value } ) } />
					</div>
					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_visitor_email">Email <span className="pp-label-hint">optional</span></label>
							<input type="email" id="pp_visitor_email" className="pp-input" placeholder="alex@email.com" value={ form.visitor_email } onChange={ ( e ) => setForm( { ...form, visitor_email: e.target.value } ) } />
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_visitor_phone">Phone <span className="pp-label-hint">optional</span></label>
							<input type="text" id="pp_visitor_phone" className="pp-input" placeholder="+1 555 0100" value={ form.visitor_phone } onChange={ ( e ) => setForm( { ...form, visitor_phone: e.target.value } ) } />
						</div>
					</div>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_visitor_plan_id">Pass type</label>
						<select id="pp_visitor_plan_id" className="pp-input pp-input-select" required value={ form.plan_id } onChange={ ( e ) => setForm( { ...form, plan_id: e.target.value } ) }>
							<option value="">Select a pass type</option>
							{ plans.map( ( p ) => <option key={ p.id } value={ p.id }>{ p.name }</option> ) }
						</select>
					</div>
					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ registerMutation.isPending }>
							{ registerMutation.isPending ? 'Registering…' : 'Register & issue pass' }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}
