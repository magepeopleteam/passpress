import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';

const DEFAULT_FORM = {
	title: '',
	_pp_class_type: 'yoga',
	_pp_instructor_id: 0,
	_pp_facility_id: 0,
	_pp_capacity: 10,
	_pp_day_of_week: 1,
	_pp_start_time: '09:00',
	_pp_end_time: '10:00',
	is_live: true,
};

function ClassCard( { item, options, instructors, facilities, onEdit } ) {
	const isLive = !! item.is_live;
	const instructor = instructors.find( ( u ) => u.id === Number( item._pp_instructor_id ) );
	const facility = facilities.find( ( f ) => f.id === Number( item._pp_facility_id ) );
	const timeLabel = item._pp_start_time && item._pp_end_time ? `${ item._pp_start_time }–${ item._pp_end_time }` : '—';
	return (
		<button type="button" className={ `passpress-class-admin-card${ isLive ? ' is-live' : ' is-draft' }` } onClick={ () => onEdit( item ) }>
			<div className="passpress-class-admin-card-top">
				<div className="passpress-class-admin-card-badges">
					<span className="passpress-class-admin-badge is-type">{ options.class_types[ item._pp_class_type ] || 'Class' }</span>
					<span className={ `passpress-class-admin-badge ${ isLive ? 'is-live' : 'is-draft' }` }>{ isLive ? 'Live' : 'Draft' }</span>
				</div>
				<span className="passpress-class-admin-day">{ options.weekdays[ item._pp_day_of_week ] || '—' }</span>
			</div>

			<h3 className="passpress-class-admin-title">{ item.title }</h3>
			<p className="passpress-class-admin-time">{ timeLabel }</p>

			<dl className="passpress-class-admin-details">
				<div>
					<dt>Instructor</dt>
					<dd>{ instructor ? instructor.name : '—' }</dd>
				</div>
				<div>
					<dt>Facility</dt>
					<dd>{ facility ? facility.name : '—' }</dd>
				</div>
				<div>
					<dt>Capacity</dt>
					<dd>{ item._pp_capacity || '—' }</dd>
				</div>
			</dl>

			<div className="passpress-class-admin-footer">
				<span className="passpress-class-admin-hint">Weekly session</span>
				<span className="passpress-class-admin-edit">Edit class</span>
			</div>
		</button>
	);
}

export default function ClassSessionsPage() {
	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( { queryKey: [ 'class-sessions' ], queryFn: () => apiFetch( { path: '/passpress/v1/class-sessions' } ) } );

	const [ modalOpen, setModalOpen ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ form, setForm ] = useState( DEFAULT_FORM );
	const [ error, setError ] = useState( '' );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'class-sessions' ] } );

	const createMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/class-sessions', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );
	const updateMutation = useMutation( {
		mutationFn: ( { id, body } ) => apiFetch( { path: `/passpress/v1/class-sessions/${ id }`, method: 'PUT', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const openCreate = () => { setEditingId( null ); setForm( DEFAULT_FORM ); setError( '' ); setModalOpen( true ); };
	const openEdit = ( item ) => { setEditingId( item.class_id ); setForm( { ...item, is_live: !! item.is_live } ); setError( '' ); setModalOpen( true ); };
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
		return <div className="wrap passpress-wrap passpress-classes-page"><p>Loading…</p></div>;
	}

	const { items, options, instructors, facilities } = data;
	const saving = createMutation.isPending || updateMutation.isPending;

	return (
		<div className="wrap passpress-wrap passpress-classes-page">
			<div className="passpress-classes-page-header">
				<div className="passpress-classes-page-copy">
					<p className="passpress-classes-page-eyebrow">Schedule</p>
					<h1>Class Sessions</h1>
					<p className="passpress-classes-page-desc">{ items.length } { 1 === items.length ? 'weekly class' : 'weekly classes' }</p>
				</div>
				<button type="button" className="passpress-classes-new-btn" onClick={ openCreate }>New class</button>
			</div>

			{ 0 === items.length ? (
				<EmptyState
					className="passpress-classes-empty"
					eyebrow="Get started"
					title="No class sessions yet"
					desc="Add yoga, fitness, or training sessions so members can book upcoming dates."
					ctaLabel="Create a class"
					onCta={ openCreate }
				/>
			) : (
				<div className="passpress-classes-grid">
					{ items.map( ( item ) => (
						<ClassCard key={ item.class_id } item={ item } options={ options } instructors={ instructors } facilities={ facilities } onEdit={ openEdit } />
					) ) }
				</div>
			) }

			<Modal
				open={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				eyebrow={ editingId ? 'Edit' : 'Create' }
				title={ editingId ? 'Edit class session' : 'New class session' }
				error={ error }
				modalClassName="passpress-class-modal"
			>
				<form className="pp-plan-form" onSubmit={ submit }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_class_title">Class name</label>
						<input type="text" id="pp_class_title" className="pp-input" placeholder="e.g. Morning Yoga" required value={ form.title } onChange={ ( e ) => setField( 'title', e.target.value ) } />
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_class_type_field">Class type</label>
							<select id="pp_class_type_field" className="pp-input pp-input-select" value={ form._pp_class_type } onChange={ ( e ) => setField( '_pp_class_type', e.target.value ) }>
								{ Object.entries( options.class_types ).map( ( [ key, label ] ) => <option key={ key } value={ key }>{ label }</option> ) }
							</select>
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_class_capacity_field">Capacity</label>
							<input type="number" min="1" id="pp_class_capacity_field" className="pp-input" value={ form._pp_capacity } onChange={ ( e ) => setField( '_pp_capacity', e.target.value ) } />
						</div>
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_class_instructor_field">Instructor</label>
							<select id="pp_class_instructor_field" className="pp-input pp-input-select" value={ form._pp_instructor_id } onChange={ ( e ) => setField( '_pp_instructor_id', e.target.value ) }>
								<option value="0">— None —</option>
								{ instructors.map( ( user ) => <option key={ user.id } value={ user.id }>{ user.name }</option> ) }
							</select>
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_class_facility_field">Facility / room</label>
							<select id="pp_class_facility_field" className="pp-input pp-input-select" value={ form._pp_facility_id } onChange={ ( e ) => setField( '_pp_facility_id', e.target.value ) }>
								<option value="0">— None —</option>
								{ facilities.map( ( facility ) => <option key={ facility.id } value={ facility.id }>{ facility.name }</option> ) }
							</select>
						</div>
					</div>

					<hr className="pp-divider" />

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_class_day_field">Day of week</label>
						<p className="pp-field-hint">For multiple weekdays, create one class session per day.</p>
						<select id="pp_class_day_field" className="pp-input pp-input-select" value={ form._pp_day_of_week } onChange={ ( e ) => setField( '_pp_day_of_week', e.target.value ) }>
							{ Object.entries( options.weekdays ).map( ( [ dayNum, dayLabel ] ) => <option key={ dayNum } value={ dayNum }>{ dayLabel }</option> ) }
						</select>
					</div>

					<div className="pp-field">
						<label className="pp-label">Time</label>
						<div className="pp-input-group pp-input-group-time">
							<input type="time" className="pp-input" value={ form._pp_start_time } onChange={ ( e ) => setField( '_pp_start_time', e.target.value ) } />
							<span className="pp-input-group-sep">&mdash;</span>
							<input type="time" className="pp-input" value={ form._pp_end_time } onChange={ ( e ) => setField( '_pp_end_time', e.target.value ) } />
						</div>
					</div>

					{ editingId && (
						<label className="pp-checkbox-box pp-class-status-box">
							<input type="checkbox" checked={ !! form.is_live } onChange={ ( e ) => setField( 'is_live', e.target.checked ) } />
							<span>Live on site (published)</span>
						</label>
					) }

					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ saving }>
							{ saving ? 'Saving…' : ( editingId ? 'Save changes' : 'Create class' ) }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}
