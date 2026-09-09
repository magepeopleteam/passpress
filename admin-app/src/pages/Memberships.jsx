import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import { useRouter } from '../router.jsx';

const STAT_TILES = [
	{ key: 'active', label: 'Active', icon: 'shield' },
	{ key: 'frozen', label: 'Frozen', icon: 'controls-pause' },
	{ key: 'suspended', label: 'Suspended', icon: 'warning' },
	{ key: 'expired', label: 'Expired', icon: 'calendar-alt' },
	{ key: 'cancelled', label: 'Cancelled', icon: 'dismiss' },
];

const QUICK_TABS = [
	{ label: 'All Members', type: 'reset' },
	{ label: 'Corporate', type: 'plan_scope', value: 'corporate' },
	{ label: 'Individual', type: 'plan_scope', value: 'individual' },
	{ label: 'Active', type: 'status', value: 'active' },
	{ label: 'Frozen', type: 'status', value: 'frozen' },
	{ label: 'Suspended', type: 'status', value: 'suspended' },
	{ label: 'Cancelled', type: 'status', value: 'cancelled' },
];

const ACTIONS = [
	{ key: 'renew', label: 'Renew' },
	{ key: 'freeze', label: 'Freeze' },
	{ key: 'suspend', label: 'Suspend' },
	{ key: 'reactivate', label: 'Reactivate' },
	{ key: 'cancel', label: 'Cancel' },
];

export default function MembershipsPage() {
	const { params, navigate } = useRouter();
	const status = params.get( 'status' ) || '';
	const planScope = params.get( 'plan_scope' ) || '';
	const search = params.get( 's' ) || '';
	const paged = Number( params.get( 'paged' ) || 1 );

	const [ searchInput, setSearchInput ] = useState( search );
	const [ modalOpen, setModalOpen ] = useState( false );
	const [ form, setForm ] = useState( { user_id: '', plan_id: '' } );
	const [ error, setError ] = useState( '' );

	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( {
		queryKey: [ 'memberships', { status, planScope, search, paged } ],
		queryFn: () => apiFetch( { path: `/passpress/v1/memberships?status=${ status }&plan_scope=${ planScope }&s=${ encodeURIComponent( search ) }&paged=${ paged }` } ),
	} );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'memberships' ] } );

	const issueMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/memberships', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const actionMutation = useMutation( {
		mutationFn: ( { id, action } ) => apiFetch( { path: `/passpress/v1/memberships/${ id }/${ action }`, method: 'POST' } ),
		onSuccess: () => invalidate(),
	} );

	const goto = ( next ) => navigate( { ...next, paged: null } );

	const onStatTileClick = ( key ) => {
		goto( { status: status === key ? '' : key } );
	};

	const onTabClick = ( tab ) => {
		if ( 'reset' === tab.type ) {
			goto( { status: '', plan_scope: '' } );
		} else if ( 'plan_scope' === tab.type ) {
			goto( { plan_scope: planScope === tab.value ? '' : tab.value } );
		} else {
			goto( { status: status === tab.value ? '' : tab.value } );
		}
	};

	const submitSearch = ( e ) => {
		e.preventDefault();
		goto( { s: searchInput } );
	};

	const submitIssue = ( e ) => {
		e.preventDefault();
		setError( '' );
		issueMutation.mutate( form );
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-members-page"><p>Loading…</p></div>;
	}

	const { items, total, per_page: perPage, counts, plans, users } = data;
	const totalPages = Math.ceil( total / Math.max( 1, perPage ) );
	const first = total ? ( paged - 1 ) * perPage + 1 : 0;
	const last = Math.min( total, paged * perPage );

	return (
		<div className="wrap passpress-wrap passpress-members-page">
			<div className="passpress-members-page-header">
				<div className="passpress-members-page-copy">
					<p className="passpress-members-page-eyebrow">Directory</p>
					<h1>Members</h1>
					<p className="passpress-members-page-desc">Filter, search, and manage memberships from one place.</p>
				</div>
				<button type="button" className="passpress-members-issue-btn" onClick={ () => { setForm( { user_id: '', plan_id: '' } ); setError( '' ); setModalOpen( true ); } }>
					Issue membership
				</button>
			</div>

			<div className="passpress-stat-row">
				{ STAT_TILES.map( ( tile ) => (
					<button
						type="button"
						key={ tile.key }
						className={ `passpress-stat-tile passpress-stat-tile-${ tile.key }${ status === tile.key ? ' is-active' : '' }` }
						onClick={ () => onStatTileClick( tile.key ) }
					>
						<div className="passpress-stat-tile-text">
							<span className="passpress-stat-tile-label">{ tile.label }</span>
							<span className="passpress-stat-tile-number">{ counts[ tile.key ] }</span>
						</div>
						<span className="passpress-stat-tile-icon" aria-hidden="true"><span className={ `dashicons dashicons-${ tile.icon }` }></span></span>
					</button>
				) ) }
			</div>

			<div className="passpress-members-toolbar">
				<div className="passpress-members-toolbar-top">
					<div className="passpress-scope-tabs">
						{ QUICK_TABS.map( ( tab ) => {
							const isActive = 'reset' === tab.type
								? ( '' === status && '' === planScope )
								: 'plan_scope' === tab.type ? planScope === tab.value : status === tab.value;
							return (
								<button type="button" key={ tab.label } className={ `passpress-scope-tab${ isActive ? ' is-active' : '' }` } onClick={ () => onTabClick( tab ) }>
									{ tab.label }
								</button>
							);
						} ) }
					</div>

					<form className="passpress-members-search-form" onSubmit={ submitSearch }>
						<input type="search" className="passpress-members-search" placeholder="Search membership #…" value={ searchInput } onChange={ ( e ) => setSearchInput( e.target.value ) } />
						<button type="submit" className="passpress-members-search-btn">Search</button>
					</form>
				</div>
			</div>

			<div className="passpress-members-table-wrap">
				<table className="passpress-members-table">
					<thead>
						<tr>
							<th>Membership #</th>
							<th>Member</th>
							<th>Plan</th>
							<th>Status</th>
							<th>Validity</th>
							<th>PIN</th>
							<th><span className="screen-reader-text">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						{ 0 === items.length ? (
							<tr><td colSpan="7">No members found.</td></tr>
						) : items.map( ( m ) => (
							<tr key={ m.id }>
								<td><code className="passpress-membership-code">{ m.membership_number }</code></td>
								<td>
									<div className="passpress-member-cell">
										<span className={ `passpress-member-avatar passpress-avatar-color-${ m.id % 5 }` }>{ initials( m.member_name ) }</span>
										<span className="passpress-member-info">
											<strong>{ m.member_name }</strong>
											{ m.member_email && <span className="passpress-member-email">{ m.member_email }</span> }
										</span>
									</div>
								</td>
								<td><span className="passpress-plan-pill">{ m.plan_title }</span></td>
								<td><span className={ `passpress-status-pill passpress-status-pill-${ m.status }` }><span className="passpress-status-dot"></span>{ m.status_label }</span></td>
								<td>
									<div className="passpress-validity">
										<strong>{ m.start_date_label }</strong>
										<span className={ m.is_expiring ? 'passpress-expiring-soon' : 'passpress-validity-until' }>{ m.expiring_label }</span>
									</div>
								</td>
								<td><code className="passpress-pin-code">{ m.pin_code }</code></td>
								<td className="passpress-row-menu-cell">
									<details className="passpress-row-menu">
										<summary aria-label="Actions">&#8942;</summary>
										<div className="passpress-row-menu-panel">
											{ ACTIONS.map( ( a ) => (
												<a
													href="#"
													key={ a.key }
													onClick={ ( e ) => { e.preventDefault(); actionMutation.mutate( { id: m.id, action: a.key } ); } }
												>
													{ a.label }
												</a>
											) ) }
										</div>
									</details>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="passpress-members-footer">
				<p className="passpress-members-count">
					Showing <strong>{ first }</strong>-{ last } of <strong>{ total }</strong> members
				</p>
				{ totalPages > 1 && (
					<div className="passpress-members-pagination">
						{ Array.from( { length: totalPages }, ( _, i ) => i + 1 ).map( ( page ) => (
							<button
								type="button"
								key={ page }
								className={ page === paged ? 'current' : '' }
								onClick={ () => navigate( { paged: page } ) }
							>
								{ page }
							</button>
						) ) }
					</div>
				) }
			</div>

			<Modal open={ modalOpen } onClose={ () => setModalOpen( false ) } eyebrow="Front desk" title="Issue membership" error={ error } modalClassName="passpress-issue-modal">
				<form className="pp-plan-form" onSubmit={ submitIssue }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_user_id">Member</label>
						<select id="pp_user_id" className="pp-input pp-input-select" required value={ form.user_id } onChange={ ( e ) => setForm( { ...form, user_id: e.target.value } ) }>
							<option value="">Select a member</option>
							{ users.map( ( u ) => <option key={ u.id } value={ u.id }>{ u.name }</option> ) }
						</select>
					</div>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_plan_id">Plan</label>
						<select id="pp_plan_id" className="pp-input pp-input-select" required value={ form.plan_id } onChange={ ( e ) => setForm( { ...form, plan_id: e.target.value } ) }>
							<option value="">Select a plan</option>
							{ plans.map( ( p ) => <option key={ p.id } value={ p.id }>{ p.name }</option> ) }
						</select>
					</div>
					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ issueMutation.isPending }>
							{ issueMutation.isPending ? 'Issuing…' : 'Issue membership' }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}

function initials( name ) {
	const words = String( name || '' ).trim().split( /\s+/ );
	if ( words.length >= 2 ) {
		return ( words[ 0 ][ 0 ] + words[ words.length - 1 ][ 0 ] ).toUpperCase();
	}
	return ( name || '' ).slice( 0, 2 ).toUpperCase();
}
