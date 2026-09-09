import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';

const DEFAULT_FORM = {
	title: '',
	_pp_facility_type: 'gym',
	_pp_capacity: 10,
	_pp_booking_required: false,
	_pp_slot_duration: 60,
	_pp_buffer_minutes: 0,
	_pp_open_time: '09:00',
	_pp_close_time: '21:00',
	_pp_days_open: [ 0, 1, 2, 3, 4, 5, 6 ],
	_pp_cancellation_lead_hours: 2,
	_pp_staff_ids: [],
	is_live: true,
};

function daysLabel( days, weekdays ) {
	const clean = Array.from( new Set( days.map( Number ) ) ).sort( ( a, b ) => a - b );
	if ( 7 === clean.length ) {
		return 'Daily';
	}
	if ( ! clean.length ) {
		return '—';
	}
	return clean.map( ( d ) => ( weekdays[ d ] || '' ).slice( 0, 3 ) ).join( ', ' );
}

function toggleInArray( arr, value ) {
	const v = Number( value );
	return arr.includes( v ) ? arr.filter( ( x ) => x !== v ) : [ ...arr, v ];
}

function FacilityCard( { facility, options, onEdit } ) {
	const isLive = !! facility.is_live;
	const bookable = !! facility._pp_booking_required;
	const hours = facility._pp_open_time && facility._pp_close_time ? `${ facility._pp_open_time }–${ facility._pp_close_time }` : '—';
	return (
		<button
			type="button"
			className={ `passpress-facility-admin-card${ isLive ? ' is-live' : ' is-draft' }${ bookable ? ' is-bookable' : '' }` }
			onClick={ () => onEdit( facility ) }
		>
			<div className="passpress-facility-admin-card-top">
				<div className="passpress-facility-admin-card-badges">
					<span className="passpress-facility-admin-badge is-type">{ options.facility_types[ facility._pp_facility_type ] || 'Facility' }</span>
					<span className={ `passpress-facility-admin-badge ${ isLive ? 'is-live' : 'is-draft' }` }>{ isLive ? 'Live' : 'Draft' }</span>
					{ bookable && <span className="passpress-facility-admin-badge is-bookable">Bookable</span> }
				</div>
			</div>

			<h3 className="passpress-facility-admin-title">{ facility.title }</h3>

			<dl className="passpress-facility-admin-details">
				<div>
					<dt>Capacity</dt>
					<dd>{ facility._pp_capacity || '—' }</dd>
				</div>
				<div>
					<dt>Hours</dt>
					<dd>{ hours }</dd>
				</div>
				<div>
					<dt>Days</dt>
					<dd>{ daysLabel( facility._pp_days_open, options.weekdays ) }</dd>
				</div>
				{ facility._pp_slot_duration > 0 && (
					<div>
						<dt>Slot</dt>
						<dd>{ facility._pp_slot_duration } min</dd>
					</div>
				) }
			</dl>

			<div className="passpress-facility-admin-footer">
				<span className="passpress-facility-admin-hint">{ bookable ? 'Shows on booking calendar' : 'Walk-in / open access' }</span>
				<span className="passpress-facility-admin-edit">Edit facility</span>
			</div>
		</button>
	);
}

export default function FacilitiesPage() {
	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( { queryKey: [ 'facilities' ], queryFn: () => apiFetch( { path: '/passpress/v1/facilities' } ) } );

	const [ modalOpen, setModalOpen ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ form, setForm ] = useState( DEFAULT_FORM );
	const [ error, setError ] = useState( '' );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'facilities' ] } );

	const createMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/facilities', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );
	const updateMutation = useMutation( {
		mutationFn: ( { id, body } ) => apiFetch( { path: `/passpress/v1/facilities/${ id }`, method: 'PUT', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const openCreate = () => { setEditingId( null ); setForm( DEFAULT_FORM ); setError( '' ); setModalOpen( true ); };
	const openEdit = ( facility ) => { setEditingId( facility.facility_id ); setForm( { ...facility, is_live: !! facility.is_live, _pp_booking_required: !! facility._pp_booking_required } ); setError( '' ); setModalOpen( true ); };
	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	const submit = ( e ) => {
		e.preventDefault();
		setError( '' );
		if ( editingId ) {
			updateMutation.mutate( { id: editingId, body: form } );
		} else {
			createMutation.mutate( form );
		}
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-facilities-page"><p>Loading…</p></div>;
	}

	const { items, options, staff } = data;
	const saving = createMutation.isPending || updateMutation.isPending;

	return (
		<div className="wrap passpress-wrap passpress-facilities-page">
			<div className="passpress-facilities-page-header">
				<div className="passpress-facilities-page-copy">
					<p className="passpress-facilities-page-eyebrow">Spaces</p>
					<h1>Facilities</h1>
					<p className="passpress-facilities-page-desc">{ items.length } { 1 === items.length ? 'facility' : 'facilities' } configured</p>
				</div>
				<button type="button" className="passpress-facilities-new-btn" onClick={ openCreate }>New facility</button>
			</div>

			{ 0 === items.length ? (
				<EmptyState
					className="passpress-facilities-empty"
					eyebrow="Get started"
					title="No facilities yet"
					desc="Add a gym, court, pool, or room so members can book and check in."
					ctaLabel="Create a facility"
					onCta={ openCreate }
				/>
			) : (
				<div className="passpress-facilities-grid">
					{ items.map( ( facility ) => (
						<FacilityCard key={ facility.facility_id } facility={ facility } options={ options } onEdit={ openEdit } />
					) ) }
				</div>
			) }

			<Modal
				open={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				eyebrow={ editingId ? 'Edit' : 'Create' }
				title={ editingId ? 'Edit facility' : 'New facility' }
				error={ error }
				modalClassName="passpress-facility-modal"
			>
				<form className="pp-plan-form" onSubmit={ submit }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_facility_title">Facility name</label>
						<input type="text" id="pp_facility_title" className="pp-input" placeholder="e.g. Main Gym Floor" required value={ form.title } onChange={ ( e ) => setField( 'title', e.target.value ) } />
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_facility_type_field">Type</label>
							<select id="pp_facility_type_field" className="pp-input pp-input-select" value={ form._pp_facility_type } onChange={ ( e ) => setField( '_pp_facility_type', e.target.value ) }>
								{ Object.entries( options.facility_types ).map( ( [ key, label ] ) => <option key={ key } value={ key }>{ label }</option> ) }
							</select>
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_facility_capacity">Capacity</label>
							<input type="number" min="0" id="pp_facility_capacity" className="pp-input" value={ form._pp_capacity } onChange={ ( e ) => setField( '_pp_capacity', e.target.value ) } />
						</div>
					</div>

					<label className="pp-checkbox-box">
						<input type="checkbox" checked={ !! form._pp_booking_required } onChange={ ( e ) => setField( '_pp_booking_required', e.target.checked ) } />
						<span>Requires booking (show calendar on the front end)</span>
					</label>

					<hr className="pp-divider" />

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_facility_slot_duration">Slot duration (min)</label>
							<input type="number" min="5" step="5" id="pp_facility_slot_duration" className="pp-input" value={ form._pp_slot_duration } onChange={ ( e ) => setField( '_pp_slot_duration', e.target.value ) } />
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_facility_buffer">Buffer (min)</label>
							<input type="number" min="0" step="5" id="pp_facility_buffer" className="pp-input" value={ form._pp_buffer_minutes } onChange={ ( e ) => setField( '_pp_buffer_minutes', e.target.value ) } />
						</div>
					</div>

					<div className="pp-field">
						<label className="pp-label">Open hours</label>
						<div className="pp-input-group pp-input-group-time">
							<input type="time" className="pp-input" value={ form._pp_open_time } onChange={ ( e ) => setField( '_pp_open_time', e.target.value ) } />
							<span className="pp-input-group-sep">&mdash;</span>
							<input type="time" className="pp-input" value={ form._pp_close_time } onChange={ ( e ) => setField( '_pp_close_time', e.target.value ) } />
						</div>
					</div>

					<div className="pp-field">
						<span className="pp-label">Open days</span>
						<div className="pp-days-grid">
							{ Object.entries( options.weekdays ).map( ( [ dayNum, dayLabel ] ) => (
								<label className="pp-day-chip" key={ dayNum }>
									<input
										type="checkbox"
										checked={ form._pp_days_open.map( Number ).includes( Number( dayNum ) ) }
										onChange={ () => setField( '_pp_days_open', toggleInArray( form._pp_days_open, dayNum ) ) }
									/>
									<span>{ dayLabel.slice( 0, 3 ) }</span>
								</label>
							) ) }
						</div>
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_facility_cancel_hours">Cancellation lead (hours)</label>
						<input type="number" min="0" id="pp_facility_cancel_hours" className="pp-input pp-input-narrow" value={ form._pp_cancellation_lead_hours } onChange={ ( e ) => setField( '_pp_cancellation_lead_hours', e.target.value ) } />
					</div>

					{ staff.length > 0 && (
						<div className="pp-field">
							<span className="pp-label">Assigned staff</span>
							<div className="pp-staff-grid">
								{ staff.map( ( user ) => (
									<label className="pp-staff-chip" key={ user.id }>
										<input
											type="checkbox"
											checked={ form._pp_staff_ids.map( Number ).includes( user.id ) }
											onChange={ () => setField( '_pp_staff_ids', toggleInArray( form._pp_staff_ids, user.id ) ) }
										/>
										<span>{ user.name }</span>
									</label>
								) ) }
							</div>
						</div>
					) }

					{ editingId && (
						<label className="pp-checkbox-box pp-facility-status-box">
							<input type="checkbox" checked={ !! form.is_live } onChange={ ( e ) => setField( 'is_live', e.target.checked ) } />
							<span>Live on site (published)</span>
						</label>
					) }

					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ saving }>
							{ saving ? 'Saving…' : ( editingId ? 'Save changes' : 'Create facility' ) }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}
