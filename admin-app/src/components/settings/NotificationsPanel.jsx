import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../../api.js';
import NavLink from '../NavLink.jsx';

export default function NotificationsPanel() {
	const queryClient = useQueryClient();
	const { data } = useQuery( { queryKey: [ 'settings-notifications' ], queryFn: () => apiFetch( { path: '/passpress/v1/settings/notifications' } ) } );
	const [ form, setForm ] = useState( null );

	useEffect( () => {
		if ( data && ! form ) {
			setForm( data );
		}
	}, [ data ] );

	const saveMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/settings/notifications', method: 'PUT', data: body } ),
		onSuccess: ( saved ) => {
			setForm( saved );
			queryClient.setQueryData( [ 'settings-notifications' ], saved );
		},
	} );

	if ( ! form ) {
		return <div className="passpress-settings-panel"><p>Loading…</p></div>;
	}

	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	return (
		<div className="passpress-settings-panel" id="passpress-panel-notifications">
			<header className="passpress-settings-panel-header">
				<p className="passpress-settings-panel-eyebrow">Email</p>
				<h2>Notifications</h2>
				<p>
					All notifications are sent by email only. Renewal reminder timing is under{ ' ' }
					<NavLink to="passpress-settings" params={ { tab: 'billing' } }>Payment Method</NavLink>.
				</p>
			</header>

			<form className="passpress-settings-form" onSubmit={ ( e ) => { e.preventDefault(); saveMutation.mutate( form ); } }>
				<section className="passpress-settings-card">
					<div className="passpress-settings-card-head">
						<h3>Member emails</h3>
						<p>Turn each message on or off. Off emails are never sent.</p>
					</div>

					<label className="passpress-settings-toggle-row">
						<span className="passpress-settings-toggle-copy">
							<span className="passpress-settings-toggle-title">Welcome email</span>
							<span className="passpress-settings-toggle-desc">Sent when a new membership or visitor pass is issued.</span>
						</span>
						<span className="passpress-pm-switch">
							<input type="checkbox" checked={ !! form.welcome_enabled } onChange={ ( e ) => setField( 'welcome_enabled', e.target.checked ) } />
							<span className="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<label className="passpress-settings-toggle-row">
						<span className="passpress-settings-toggle-copy">
							<span className="passpress-settings-toggle-title">Booking reminder</span>
							<span className="passpress-settings-toggle-desc">Reminder before an upcoming facility or class booking.</span>
						</span>
						<span className="passpress-pm-switch">
							<input type="checkbox" checked={ !! form.booking_reminder_enabled } onChange={ ( e ) => setField( 'booking_reminder_enabled', e.target.checked ) } />
							<span className="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<div className="pp-field passpress-settings-nested-field">
						<div className="pp-label-row">
							<label className="pp-label" htmlFor="pp_booking_reminder_days">Days before the booking</label>
						</div>
						<input type="number" id="pp_booking_reminder_days" className="pp-input pp-input-narrow" min="1" max="14" value={ form.booking_reminder_days } onChange={ ( e ) => setField( 'booking_reminder_days', e.target.value ) } />
					</div>

					<label className="passpress-settings-toggle-row">
						<span className="passpress-settings-toggle-copy">
							<span className="passpress-settings-toggle-title">Payment failed</span>
							<span className="passpress-settings-toggle-desc">Sent when a checkout payment attempt fails.</span>
						</span>
						<span className="passpress-pm-switch">
							<input type="checkbox" checked={ !! form.payment_failed_enabled } onChange={ ( e ) => setField( 'payment_failed_enabled', e.target.checked ) } />
							<span className="passpress-pm-switch-slider"></span>
						</span>
					</label>

					<label className="passpress-settings-toggle-row">
						<span className="passpress-settings-toggle-copy">
							<span className="passpress-settings-toggle-title">Birthday greeting</span>
							<span className="passpress-settings-toggle-desc">Sent on a member's birthday from the date saved on My Pass.</span>
						</span>
						<span className="passpress-pm-switch">
							<input type="checkbox" checked={ !! form.birthday_enabled } onChange={ ( e ) => setField( 'birthday_enabled', e.target.checked ) } />
							<span className="passpress-pm-switch-slider"></span>
						</span>
					</label>
				</section>

				<div className="passpress-settings-actions">
					<button type="submit" className="pp-btn-solid" disabled={ saveMutation.isPending }>
						{ saveMutation.isPending ? 'Saving…' : 'Save notification settings' }
					</button>
					{ saveMutation.isSuccess && <span className="passpress-settings-save-note">Saved.</span> }
				</div>
			</form>
		</div>
	);
}
