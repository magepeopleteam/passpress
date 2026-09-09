import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../../api.js';

export default function WcGatewayCard( { gateway } ) {
	const queryClient = useQueryClient();
	const [ open, setOpen ] = useState( false );
	const [ enabled, setEnabled ] = useState( gateway.enabled );
	const [ saveStatus, setSaveStatus ] = useState( '' );
	const formRef = useRef( null );

	const { data: configureData } = useQuery( {
		queryKey: [ 'wc-gateway-configure', gateway.id ],
		queryFn: () => apiFetch( { path: `/passpress/v1/settings/billing/wc-gateways/${ gateway.id }/configure` } ),
		enabled: open,
	} );

	const toggleMutation = useMutation( {
		mutationFn: ( nextEnabled ) => apiFetch( { path: `/passpress/v1/settings/billing/wc-gateways/${ gateway.id }/toggle`, method: 'POST', data: { enabled: nextEnabled } } ),
		onError: () => setEnabled( ( e ) => ! e ),
	} );

	const saveMutation = useMutation( {
		mutationFn: ( fields ) => apiFetch( { path: `/passpress/v1/settings/billing/wc-gateways/${ gateway.id }/configure`, method: 'POST', data: { fields, enabled } } ),
		onSuccess: () => {
			setSaveStatus( 'Saved.' );
			queryClient.invalidateQueries( { queryKey: [ 'wc-gateway-configure', gateway.id ] } );
		},
		onError: ( err ) => setSaveStatus( err.message || 'Something went wrong.' ),
	} );

	const onToggle = ( e ) => {
		const next = e.target.checked;
		setEnabled( next );
		toggleMutation.mutate( next );
	};

	const onSave = () => {
		if ( ! formRef.current ) {
			return;
		}
		setSaveStatus( 'Saving…' );
		const fields = {};
		new FormData( formRef.current ).forEach( ( value, key ) => { fields[ key ] = value; } );
		saveMutation.mutate( fields );
	};

	return (
		<>
			<div className="passpress-wc-gateway-card">
				<label className="passpress-pm-switch">
					<input type="checkbox" checked={ enabled } onChange={ onToggle } />
					<span className="passpress-pm-switch-slider"></span>
				</label>
				<span className="passpress-wc-gateway-name">{ gateway.title }</span>
				<span className={ `passpress-wc-gateway-status${ enabled ? ' is-enabled' : '' }` }>{ enabled ? 'ENABLED' : 'DISABLED' }</span>
				<button type="button" className="passpress-pm-btn-outline passpress-gateway-configure" onClick={ () => setOpen( ( o ) => ! o ) }>Configure</button>
				<div className="passpress-wc-gateway-desc" dangerouslySetInnerHTML={ { __html: gateway.description } }></div>
			</div>
			{ open && (
				<div className="passpress-wc-gw-panel">
					{ ! configureData ? (
						<p>Loading…</p>
					) : (
						<form ref={ formRef } onSubmit={ ( e ) => e.preventDefault() }>
							<table className="form-table" dangerouslySetInnerHTML={ { __html: configureData.html } }></table>
							<p>
								<button type="button" className="passpress-pm-btn-primary passpress-wc-gw-save" disabled={ saveMutation.isPending } onClick={ onSave }>Save Changes</button>
								<span className="passpress-wc-gw-save-status passpress-pm-status-text">{ saveStatus }</span>
							</p>
						</form>
					) }
				</div>
			) }
		</>
	);
}
