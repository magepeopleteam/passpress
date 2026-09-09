import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../../api.js';

export default function GeneralPanel() {
	const queryClient = useQueryClient();
	const { data } = useQuery( { queryKey: [ 'settings-general' ], queryFn: () => apiFetch( { path: '/passpress/v1/settings/general' } ) } );
	const [ form, setForm ] = useState( null );

	useEffect( () => {
		if ( data && ! form ) {
			setForm( data );
		}
	}, [ data ] );

	const saveMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/settings/general', method: 'PUT', data: body } ),
		onSuccess: ( saved ) => {
			setForm( saved );
			queryClient.setQueryData( [ 'settings-general' ], saved );
		},
	} );

	if ( ! form ) {
		return <div className="passpress-settings-panel"><p>Loading…</p></div>;
	}

	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	return (
		<div className="passpress-settings-panel" id="passpress-panel-general">
			<header className="passpress-settings-panel-header">
				<p className="passpress-settings-panel-eyebrow">Basics</p>
				<h2>General</h2>
				<p>Currency, dates, and how passes appear to members.</p>
			</header>

			<form className="passpress-settings-form" onSubmit={ ( e ) => { e.preventDefault(); saveMutation.mutate( form ); } }>
				<section className="passpress-settings-card">
					<div className="passpress-settings-card-head">
						<h3>Currency & dates</h3>
						<p>Used on plan cards, checkout, and membership lists.</p>
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_currency_symbol">Currency symbol</label>
							<input type="text" id="pp_currency_symbol" className="pp-input pp-input-narrow" maxLength="8" value={ form.currency_symbol } onChange={ ( e ) => setField( 'currency_symbol', e.target.value ) } />
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_currency_code">Currency code</label>
							<input type="text" id="pp_currency_code" className="pp-input pp-input-narrow" maxLength="3" value={ form.currency_code } onChange={ ( e ) => setField( 'currency_code', e.target.value ) } />
							<p className="pp-field-hint">ISO 4217, e.g. usd, eur, gbp — required by Stripe/PayPal.</p>
						</div>
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_date_format">Date format</label>
						<input type="text" id="pp_date_format" className="pp-input" value={ form.date_format } onChange={ ( e ) => setField( 'date_format', e.target.value ) } />
						<p className="pp-field-hint">PHP date() format for expiry dates on My Pass and in admin lists.</p>
					</div>
				</section>

				<section className="passpress-settings-card">
					<div className="passpress-settings-card-head">
						<h3>My Pass display</h3>
						<p>How the digital pass looks when members open it.</p>
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_qr_size">QR code size (px)</label>
						<input type="number" id="pp_qr_size" className="pp-input pp-input-narrow" min="100" max="400" step="10" value={ form.qr_size } onChange={ ( e ) => setField( 'qr_size', e.target.value ) } />
						<p className="pp-field-hint">Between 100 and 400 pixels.</p>
					</div>

					<label className="pp-checkbox-box">
						<input type="checkbox" checked={ !! form.show_pin_on_pass } onChange={ ( e ) => setField( 'show_pin_on_pass', e.target.checked ) } />
						<span>Show PIN code alongside the QR on My Pass</span>
					</label>
				</section>

				<div className="passpress-settings-actions">
					<button type="submit" className="pp-btn-solid" disabled={ saveMutation.isPending }>
						{ saveMutation.isPending ? 'Saving…' : 'Save general settings' }
					</button>
					{ saveMutation.isSuccess && <span className="passpress-settings-save-note">Saved.</span> }
				</div>
			</form>
		</div>
	);
}
