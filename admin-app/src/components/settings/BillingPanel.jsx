import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../../api.js';
import WcGatewayCard from './WcGatewayCard.jsx';

const GATEWAYS = [
	{ key: 'paypal', label: 'PayPal', icon: 'dashicons-money-alt', gradient: 'linear-gradient(135deg,#003b7a,#0073c4)' },
	{ key: 'stripe', label: 'Credit/Debit Card (Stripe)', icon: 'dashicons-credit-card', gradient: 'linear-gradient(135deg,#4338ca,#6d5bf0)' },
	{ key: 'offline', label: 'Offline Payment', icon: 'dashicons-clipboard', gradient: 'linear-gradient(135deg,#0f5f52,#1f9c82)' },
];

function ConfirmSwitchModal( { pending, onConfirm, onCancel } ) {
	if ( ! pending ) {
		return null;
	}
	const fromLabel = 'woocommerce' === pending.from ? 'WooCommerce' : 'Custom Payment';
	const toLabel = 'woocommerce' === pending.to ? 'WooCommerce' : 'Custom Payment';
	return (
		<div className="passpress-pm-confirm-overlay" style={ { display: 'flex' } }>
			<div className="passpress-pm-confirm-box" role="alertdialog" aria-modal="true">
				<div className="passpress-pm-confirm-head">
					<span className="passpress-pm-confirm-icon dashicons dashicons-warning"></span>
					<h3 className="passpress-pm-confirm-title">Only One Payment Method Allowed</h3>
				</div>
				<div className="passpress-pm-confirm-body">
					<p>You currently have <strong>{ fromLabel }</strong> active. Only one payment method mode is supported at a time.</p>
					<p>Do you want to disable <strong>{ fromLabel }</strong> and activate <strong>{ toLabel }</strong> instead?</p>
				</div>
				<div className="passpress-pm-confirm-actions">
					<button type="button" className="passpress-pm-btn-outline" onClick={ onCancel }>Cancel</button>
					<button type="button" className="passpress-pm-btn-primary" onClick={ onConfirm }>Yes, Switch</button>
				</div>
			</div>
		</div>
	);
}

function GatewayField( { gatewayKey, form, setField } ) {
	if ( 'offline' === gatewayKey ) {
		return (
			<>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Confirmation</span>
					<span className="passpress-gw-field-control">
						<label>
							<input type="checkbox" checked={ !! form.offline_auto_confirm } onChange={ ( e ) => setField( 'offline_auto_confirm', e.target.checked ) } />
							Auto-confirm immediately (uncheck to require staff confirmation in Billing History)
						</label>
					</span>
				</label>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Instructions shown at checkout</span>
					<span className="passpress-gw-field-control">
						<textarea rows="3" value={ form.offline_instructions } onChange={ ( e ) => setField( 'offline_instructions', e.target.value ) }></textarea>
					</span>
				</label>
			</>
		);
	}
	if ( 'stripe' === gatewayKey ) {
		return (
			<>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Mode</span>
					<span className="passpress-gw-field-control">
						<select value={ form.stripe_mode } onChange={ ( e ) => setField( 'stripe_mode', e.target.value ) }>
							<option value="test">Test</option>
							<option value="live">Live</option>
						</select>
					</span>
				</label>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Publishable Key</span>
					<span className="passpress-gw-field-control">
						<input type="text" value={ form.stripe_publishable_key } onChange={ ( e ) => setField( 'stripe_publishable_key', e.target.value ) } />
					</span>
				</label>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Secret Key</span>
					<span className="passpress-gw-field-control">
						<input type="password" autoComplete="off" value={ form.stripe_secret_key } onChange={ ( e ) => setField( 'stripe_secret_key', e.target.value ) } />
					</span>
				</label>
				<label className="passpress-gw-field">
					<span className="passpress-gw-field-label">Webhook Secret</span>
					<span className="passpress-gw-field-control">
						<input type="password" autoComplete="off" value={ form.stripe_webhook_secret } onChange={ ( e ) => setField( 'stripe_webhook_secret', e.target.value ) } />
					</span>
				</label>
			</>
		);
	}
	return (
		<>
			<label className="passpress-gw-field">
				<span className="passpress-gw-field-label">Mode</span>
				<span className="passpress-gw-field-control">
					<select value={ form.paypal_mode } onChange={ ( e ) => setField( 'paypal_mode', e.target.value ) }>
						<option value="sandbox">Sandbox</option>
						<option value="live">Live</option>
					</select>
				</span>
			</label>
			<label className="passpress-gw-field">
				<span className="passpress-gw-field-label">Client ID</span>
				<span className="passpress-gw-field-control">
					<input type="text" value={ form.paypal_client_id } onChange={ ( e ) => setField( 'paypal_client_id', e.target.value ) } />
				</span>
			</label>
			<label className="passpress-gw-field">
				<span className="passpress-gw-field-label">Client Secret</span>
				<span className="passpress-gw-field-control">
					<input type="password" autoComplete="off" value={ form.paypal_client_secret } onChange={ ( e ) => setField( 'paypal_client_secret', e.target.value ) } />
				</span>
			</label>
			<label className="passpress-gw-field">
				<span className="passpress-gw-field-label">Webhook ID</span>
				<span className="passpress-gw-field-control">
					<input type="text" value={ form.paypal_webhook_id } onChange={ ( e ) => setField( 'paypal_webhook_id', e.target.value ) } />
				</span>
			</label>
		</>
	);
}

export default function BillingPanel() {
	const queryClient = useQueryClient();
	const { data } = useQuery( { queryKey: [ 'settings-billing' ], queryFn: () => apiFetch( { path: '/passpress/v1/settings/billing' } ) } );
	const [ form, setForm ] = useState( null );
	const [ openGw, setOpenGw ] = useState( '' );
	const [ pendingSwitch, setPendingSwitch ] = useState( null );
	const [ wcAccordion, setWcAccordion ] = useState( { gateways: false, additional: false } );
	const [ installStatus, setInstallStatus ] = useState( '' );

	useEffect( () => {
		if ( data && ! form ) {
			setForm( data.settings );
		}
	}, [ data ] );

	const { data: wcGateways } = useQuery( {
		queryKey: [ 'wc-gateways' ],
		queryFn: () => apiFetch( { path: '/passpress/v1/settings/billing/wc-gateways' } ),
		enabled: !! ( data && data.wc_active ),
	} );

	const saveMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/settings/billing', method: 'PUT', data: body } ),
		onSuccess: ( saved ) => {
			setForm( saved );
			queryClient.setQueryData( [ 'settings-billing' ], ( prev ) => ( prev ? { ...prev, settings: saved } : prev ) );
		},
	} );

	const installMutation = useMutation( {
		mutationFn: () => apiFetch( { path: '/passpress/v1/settings/wc-install', method: 'POST' } ),
		onSuccess: () => { window.location.reload(); },
		onError: ( err ) => setInstallStatus( err.message || 'Something went wrong.' ),
	} );

	if ( ! form || ! data ) {
		return <div className="passpress-settings-panel"><p>Loading…</p></div>;
	}

	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	const requestSwitch = ( to ) => {
		const from = form.payment_method_type;
		const isCrossover = ( 'woocommerce' === to && 'native' === from ) || ( 'native' === to && 'woocommerce' === from );
		if ( ! isCrossover ) {
			setField( 'payment_method_type', to );
			return;
		}
		setPendingSwitch( { from, to } );
	};

	const wcActive = data.wc_active;
	const isNative = 'native' === form.payment_method_type;

	return (
		<div className="passpress-settings-panel" id="passpress-panel-billing">
			<header className="passpress-settings-panel-header">
				<p className="passpress-settings-panel-eyebrow">Checkout</p>
				<h2>Payment Method</h2>
				<p>Choose WooCommerce checkout or PassPress custom payment (Offline, Stripe, PayPal). Memberships and renewals are always managed by PassPress.</p>
			</header>

			<form id="passpress-payment-method-form" onSubmit={ ( e ) => { e.preventDefault(); saveMutation.mutate( form ); } }>
				<div className="passpress-pm-toggle">
					<button type="button" className={ `passpress-pm-toggle-btn${ ! isNative ? ' is-active' : '' }` } onClick={ () => requestSwitch( 'woocommerce' ) }>WooCommerce</button>
					<button type="button" className={ `passpress-pm-toggle-btn${ isNative ? ' is-active' : '' }` } onClick={ () => requestSwitch( 'native' ) }>Custom Payment</button>
				</div>

				<ConfirmSwitchModal
					pending={ pendingSwitch }
					onConfirm={ () => { setField( 'payment_method_type', pendingSwitch.to ); setPendingSwitch( null ); } }
					onCancel={ () => setPendingSwitch( null ) }
				/>

				<div className="passpress-pm-panel" data-panel="woocommerce" style={ { display: isNative ? 'none' : undefined } }>
					{ ! wcActive ? (
						<div className="passpress-pm-notice passpress-pm-notice-warning">
							<strong>WooCommerce is not activated</strong>
							<p>To use WooCommerce as your payment method, install and activate WooCommerce. PassPress will still create and renew memberships when shop orders complete.</p>
							<button type="button" className="passpress-pm-btn-primary" disabled={ installMutation.isPending } onClick={ () => { setInstallStatus( 'Please wait…' ); installMutation.mutate(); } }>
								{ installMutation.isPending ? 'Please wait…' : ( 2 === data.wc_status ? 'Activate Now' : 'Install & Activate Now' ) }
							</button>
							<span className="passpress-pm-status-text">{ installStatus }</span>
						</div>
					) : (
						<>
							<div className="passpress-pm-toggle-row">
								<div>
									<strong>Enable WooCommerce Payment</strong>
									<p className="description">If enabled, members buy plans through the WooCommerce cart/checkout. Completed orders issue or renew PassPress memberships.</p>
								</div>
								<label className="passpress-pm-switch">
									<input type="checkbox" checked={ ! isNative } onChange={ ( e ) => requestSwitch( e.target.checked ? 'woocommerce' : 'native' ) } />
									<span className="passpress-pm-switch-slider"></span>
								</label>
							</div>

							<div className="passpress-pm-accordion passpress-wc-dependent" style={ { display: isNative ? 'none' : undefined } }>
								<button type="button" className="passpress-pm-accordion-header" onClick={ () => setWcAccordion( ( a ) => ( { ...a, gateways: ! a.gateways } ) ) }>
									WooCommerce Payment Methods
									<span className="dashicons dashicons-arrow-down-alt2"></span>
								</button>
								{ wcAccordion.gateways && (
									<div className="passpress-pm-accordion-body">
										<p>
											<a className="passpress-pm-btn-outline" href="/wp-admin/admin.php?page=wc-settings&tab=checkout" target="_blank" rel="noopener noreferrer">
												Open in WooCommerce <span className="dashicons dashicons-external"></span>
											</a>
										</p>
										{ ! wcGateways ? <p>Loading…</p> : wcGateways.items.map( ( g ) => (
											<WcGatewayCard key={ g.id } gateway={ g } />
										) ) }
									</div>
								) }
							</div>

							<div className="passpress-pm-accordion passpress-wc-dependent" style={ { display: isNative ? 'none' : undefined } }>
								<button type="button" className="passpress-pm-accordion-header" onClick={ () => setWcAccordion( ( a ) => ( { ...a, additional: ! a.additional } ) ) }>
									Additional Settings
									<span className="dashicons dashicons-arrow-down-alt2"></span>
								</button>
								{ wcAccordion.additional && (
									<div className="passpress-pm-accordion-body">
										<div className="passpress-pm-settings-row">
											<div>
												<strong>After Adding to Cart, Redirect to</strong>
												<p className="description">Where members go after clicking Buy on a membership plan.</p>
											</div>
											<select value={ form.wc_add_to_cart_redirect } onChange={ ( e ) => setField( 'wc_add_to_cart_redirect', e.target.value ) }>
												<option value="checkout">Checkout</option>
												<option value="cart">Cart</option>
											</select>
										</div>
										<div className="passpress-pm-settings-row">
											<div>
												<strong>Require Account Login</strong>
												<p className="description">Memberships need a WordPress user account. Guest checkout cannot receive a pass.</p>
											</div>
											<label>
												<input type="checkbox" checked={ !! form.wc_require_login } onChange={ ( e ) => setField( 'wc_require_login', e.target.checked ) } />
												Require login before buying a plan via shop
											</label>
										</div>
									</div>
								) }
							</div>
						</>
					) }
				</div>

				<div className="passpress-pm-panel" data-panel="native" style={ { display: isNative ? undefined : 'none' } }>
					<div className="passpress-pm-toggle-row">
						<div>
							<strong>Enable Custom Payment Method</strong>
							<p className="description">Use PassPress native checkout with Offline, Stripe, and/or PayPal. No WooCommerce Subscriptions plugin required.</p>
						</div>
						<label className="passpress-pm-switch">
							<input type="checkbox" checked={ isNative } onChange={ ( e ) => requestSwitch( e.target.checked ? 'native' : 'woocommerce' ) } />
							<span className="passpress-pm-switch-slider"></span>
						</label>
					</div>

					<div className={ `passpress-native-dependent${ isNative ? '' : ' is-locked' }` }>
						{ GATEWAYS.map( ( gw ) => {
							const enabled = !! form[ `${ gw.key }_enabled` ];
							return (
								<div key={ gw.key }>
									<div className="passpress-gateway-card" style={ { background: gw.gradient } }>
										<div className="passpress-gateway-row">
											<span className={ `passpress-gateway-icon dashicons ${ gw.icon }` }></span>
											<span className="passpress-gateway-name">{ gw.label }</span>
											<span className={ `passpress-gateway-status${ enabled ? ' is-enabled' : '' }` }>{ enabled ? 'Enabled' : 'Disabled' }</span>
											<button type="button" className="passpress-gateway-configure" onClick={ () => setOpenGw( openGw === gw.key ? '' : gw.key ) }>Configure</button>
										</div>
									</div>
									{ openGw === gw.key && (
										<div className="passpress-gw-panel">
											<label className="passpress-gw-enable-label">
												<span className="passpress-pm-switch">
													<input type="checkbox" checked={ enabled } onChange={ ( e ) => setField( `${ gw.key }_enabled`, e.target.checked ) } />
													<span className="passpress-pm-switch-slider"></span>
												</span>
												Enable this payment method
											</label>
											<GatewayField gatewayKey={ gw.key } form={ form } setField={ setField } />
											{ 'stripe' === gw.key && (
												<p className="description">
													Webhook URL (checkout.session.completed): <code>{ data.webhook_urls.stripe }</code>
												</p>
											) }
											{ 'paypal' === gw.key && (
												<p className="description">
													Webhook URL (PAYMENT.CAPTURE.COMPLETED): <code>{ data.webhook_urls.paypal }</code>
												</p>
											) }
										</div>
									) }
								</div>
							);
						} ) }

						<div className="passpress-pm-confirmation-row">
							<div>
								<strong>Renewal Reminders</strong>
								<p className="description">Email members this many days before their membership expires.</p>
							</div>
							<input type="number" className="small-text" min="1" max="60" value={ form.renewal_reminder_days } onChange={ ( e ) => setField( 'renewal_reminder_days', e.target.value ) } />
						</div>
					</div>
				</div>

				<p className="submit">
					<button type="submit" className="button button-primary" disabled={ saveMutation.isPending }>
						{ saveMutation.isPending ? 'Saving…' : 'Save Payment Settings' }
					</button>
					{ saveMutation.isSuccess && <span className="passpress-settings-save-note">Saved.</span> }
				</p>
			</form>
		</div>
	);
}
