import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';

const DEFAULT_FORM = {
	title: '',
	_pp_plan_type: 'monthly',
	_pp_price: 0,
	_pp_duration_value: 1,
	_pp_duration_unit: 'month',
	_pp_max_entries_per_day: 0,
	_pp_features: '',
	_pp_entry_restriction: 'none',
	_pp_time_restriction_start: '',
	_pp_time_restriction_end: '',
	_pp_most_popular: 0,
	is_live: true,
};

function durationLabel( plan, durationUnits ) {
	if ( 'lifetime' === plan._pp_duration_unit || ! plan._pp_duration_value ) {
		return 'Lifetime';
	}
	if ( durationUnits[ plan._pp_duration_unit ] ) {
		return `${ plan._pp_duration_value } ${ durationUnits[ plan._pp_duration_unit ] }`;
	}
	return `${ plan._pp_duration_value } ${ plan._pp_duration_unit }${ plan._pp_duration_value > 1 ? 's' : '' }`;
}

function PlanCard( { plan, options, currencySymbol, onEdit } ) {
	const isLive = !! plan.is_live;
	return (
		<button
			type="button"
			className={ `passpress-plan-admin-card${ plan._pp_most_popular ? ' is-popular' : '' }${ isLive ? ' is-live' : ' is-draft' }` }
			onClick={ () => onEdit( plan ) }
		>
			<div className="passpress-plan-admin-card-top">
				<div className="passpress-plan-admin-card-badges">
					{ !! plan._pp_most_popular && <span className="passpress-plan-admin-badge is-popular">Popular</span> }
					<span className={ `passpress-plan-admin-badge ${ isLive ? 'is-live' : 'is-draft' }` }>{ isLive ? 'Live' : 'Draft' }</span>
				</div>
				<span className="passpress-plan-admin-price">{ currencySymbol }{ Number( plan._pp_price ).toFixed( 2 ) }</span>
			</div>

			<h3 className="passpress-plan-admin-title">{ plan.title }</h3>

			<dl className="passpress-plan-admin-details">
				{ options.plan_types[ plan._pp_plan_type ] && (
					<div>
						<dt>Type</dt>
						<dd>{ options.plan_types[ plan._pp_plan_type ] }</dd>
					</div>
				) }
				<div>
					<dt>Duration</dt>
					<dd>{ durationLabel( plan, options.duration_units ) }</dd>
				</div>
				{ options.entry_restrictions[ plan._pp_entry_restriction ] && (
					<div>
						<dt>Entry</dt>
						<dd>{ options.entry_restrictions[ plan._pp_entry_restriction ] }</dd>
					</div>
				) }
				{ plan._pp_max_entries_per_day > 0 && (
					<div>
						<dt>Max / day</dt>
						<dd>{ plan._pp_max_entries_per_day }</dd>
					</div>
				) }
			</dl>

			<div className="passpress-plan-admin-footer">
				<span className="passpress-plan-admin-sold">{ plan.sold } sold</span>
				<span className="passpress-plan-admin-edit">Edit plan</span>
			</div>
		</button>
	);
}

export default function PlansPage() {
	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( { queryKey: [ 'plans' ], queryFn: () => apiFetch( { path: '/passpress/v1/plans' } ) } );

	const [ modalOpen, setModalOpen ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ form, setForm ] = useState( DEFAULT_FORM );
	const [ error, setError ] = useState( '' );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'plans' ] } );

	const createMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/plans', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const updateMutation = useMutation( {
		mutationFn: ( { id, body } ) => apiFetch( { path: `/passpress/v1/plans/${ id }`, method: 'PUT', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const openCreate = () => {
		setEditingId( null );
		setForm( DEFAULT_FORM );
		setError( '' );
		setModalOpen( true );
	};

	const openEdit = ( plan ) => {
		setEditingId( plan.plan_id );
		setForm( { ...plan, is_live: !! plan.is_live } );
		setError( '' );
		setModalOpen( true );
	};

	const submit = ( e ) => {
		e.preventDefault();
		setError( '' );
		if ( editingId ) {
			updateMutation.mutate( { id: editingId, body: form } );
		} else {
			createMutation.mutate( form );
		}
	};

	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-plans-page"><p>Loading…</p></div>;
	}

	const { items, options, currency_symbol: currencySymbol } = data;
	const saving = createMutation.isPending || updateMutation.isPending;

	return (
		<div className="wrap passpress-wrap passpress-plans-page">
			<div className="passpress-plans-page-header">
				<div className="passpress-plans-page-copy">
					<p className="passpress-plans-page-eyebrow">Catalog</p>
					<h1>Membership Plans</h1>
					<p className="passpress-plans-page-desc">{ items.length } { 1 === items.length ? 'plan' : 'plans' } in your catalog</p>
				</div>
				<button type="button" className="passpress-plans-new-btn" onClick={ openCreate }>New plan</button>
			</div>

			{ 0 === items.length ? (
				<EmptyState
					className="passpress-plans-empty"
					eyebrow="Get started"
					title="No membership plans yet"
					desc="Create your first plan to sell passes on the site and at the front desk."
					ctaLabel="Create a plan"
					onCta={ openCreate }
				/>
			) : (
				<div className="passpress-plans-grid">
					{ items.map( ( plan ) => (
						<PlanCard key={ plan.plan_id } plan={ plan } options={ options } currencySymbol={ currencySymbol } onEdit={ openEdit } />
					) ) }
				</div>
			) }

			<Modal
				open={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				eyebrow={ editingId ? 'Edit' : 'Create' }
				title={ editingId ? 'Edit membership plan' : 'New membership plan' }
				error={ error }
				modalClassName="passpress-plan-modal"
			>
				<form className="pp-plan-form" onSubmit={ submit }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_new_plan_title">Plan name</label>
						<input
							type="text"
							id="pp_new_plan_title"
							className="pp-input"
							placeholder="e.g. Gold Annual Membership"
							required
							value={ form.title }
							onChange={ ( e ) => setField( 'title', e.target.value ) }
						/>
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_new_plan_type">Plan type</label>
						<select id="pp_new_plan_type" className="pp-input pp-input-select" value={ form._pp_plan_type } onChange={ ( e ) => setField( '_pp_plan_type', e.target.value ) }>
							{ Object.entries( options.plan_types ).map( ( [ key, label ] ) => <option key={ key } value={ key }>{ label }</option> ) }
						</select>
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_new_plan_price">Price</label>
							<div className="pp-input-prefix-wrap">
								<span className="pp-input-prefix">{ currencySymbol }</span>
								<input type="number" step="0.01" min="0" id="pp_new_plan_price" className="pp-input" value={ form._pp_price } onChange={ ( e ) => setField( '_pp_price', e.target.value ) } />
							</div>
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_new_plan_duration_value">Duration</label>
							<div className="pp-input-group">
								<input type="number" min="0" id="pp_new_plan_duration_value" className="pp-input pp-input-narrow" value={ form._pp_duration_value } onChange={ ( e ) => setField( '_pp_duration_value', e.target.value ) } />
								<select id="pp_new_plan_duration_unit" className="pp-input pp-input-select" value={ form._pp_duration_unit } onChange={ ( e ) => setField( '_pp_duration_unit', e.target.value ) }>
									{ Object.entries( options.duration_units ).map( ( [ key, label ] ) => <option key={ key } value={ key }>{ label }</option> ) }
								</select>
							</div>
						</div>
					</div>

					<div className="pp-field">
						<div className="pp-label-row">
							<label className="pp-label" htmlFor="pp_new_plan_max_per_day">Max entries / day</label>
							<span className="pp-label-hint">0 = unlimited</span>
						</div>
						<input type="number" min="0" id="pp_new_plan_max_per_day" className="pp-input pp-input-narrow" value={ form._pp_max_entries_per_day } onChange={ ( e ) => setField( '_pp_max_entries_per_day', e.target.value ) } />
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_new_plan_features">Features</label>
						<textarea id="pp_new_plan_features" rows="4" className="pp-input" placeholder={ 'One per line, e.g.\nFull facility access\nValid until midnight\nInstant QR by email' } value={ form._pp_features } onChange={ ( e ) => setField( '_pp_features', e.target.value ) }></textarea>
					</div>

					<details className="pp-advanced" open={ 'none' !== form._pp_entry_restriction || !! form._pp_most_popular }>
						<summary className="pp-advanced-summary">
							<span className="pp-advanced-title">Advanced rules</span>
							<span className="pp-advanced-hint">Entry restrictions, time window, badge</span>
						</summary>
						<div className="pp-advanced-body">
							<div className="pp-field">
								<label className="pp-label" htmlFor="pp_new_plan_restriction">Entry restriction</label>
								<select id="pp_new_plan_restriction" className="pp-input pp-input-select" value={ form._pp_entry_restriction } onChange={ ( e ) => setField( '_pp_entry_restriction', e.target.value ) }>
									{ Object.entries( options.entry_restrictions ).map( ( [ key, label ] ) => <option key={ key } value={ key }>{ label }</option> ) }
								</select>
							</div>
							<div className="pp-field">
								<label className="pp-label" htmlFor="pp_new_plan_time_start">Time window</label>
								<p className="pp-field-hint">Used when entry is time restricted.</p>
								<div className="pp-input-group pp-input-group-time">
									<input type="time" id="pp_new_plan_time_start" className="pp-input" value={ form._pp_time_restriction_start } onChange={ ( e ) => setField( '_pp_time_restriction_start', e.target.value ) } />
									<span className="pp-input-group-sep">&mdash;</span>
									<input type="time" id="pp_new_plan_time_end" className="pp-input" value={ form._pp_time_restriction_end } onChange={ ( e ) => setField( '_pp_time_restriction_end', e.target.value ) } />
								</div>
							</div>
							<label className="pp-checkbox-box">
								<input type="checkbox" checked={ !! form._pp_most_popular } onChange={ ( e ) => setField( '_pp_most_popular', e.target.checked ) } />
								<span>Highlight with a "Most Popular" badge</span>
							</label>
						</div>
					</details>

					{ editingId && (
						<label className="pp-checkbox-box pp-plan-status-box">
							<input type="checkbox" checked={ !! form.is_live } onChange={ ( e ) => setField( 'is_live', e.target.checked ) } />
							<span>Live on site (published)</span>
						</label>
					) }

					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ saving }>
							{ saving ? 'Saving…' : ( editingId ? 'Save changes' : 'Create plan' ) }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}
