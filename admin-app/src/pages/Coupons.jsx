import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';

const DEFAULT_FORM = {
	title: '',
	_pp_discount_type: 'percent',
	_pp_discount_amount: 10,
	_pp_applicable_plans: [],
	_pp_usage_limit_total: 0,
	_pp_usage_limit_per_user: 1,
	_pp_expiry_date: '',
	_pp_active: true,
	is_live: true,
};

function toggleInArray( arr, value ) {
	const v = Number( value );
	return arr.includes( v ) ? arr.filter( ( x ) => x !== v ) : [ ...arr, v ];
}

function CouponCard( { coupon, plans, currencySymbol, onEdit } ) {
	const isLive = !! coupon.is_live;
	const active = !! coupon._pp_active;
	const isExpired = coupon._pp_expiry_date && new Date( coupon._pp_expiry_date ) < new Date( new Date().toDateString() );
	const discountLabel = 'fixed' === coupon._pp_discount_type
		? `${ currencySymbol }${ Number( coupon._pp_discount_amount ).toFixed( 2 ) }`
		: `${ parseFloat( Number( coupon._pp_discount_amount ).toFixed( 2 ) ) }%`;
	const plansLabel = coupon._pp_applicable_plans.length
		? `${ coupon._pp_applicable_plans.length } ${ 1 === coupon._pp_applicable_plans.length ? 'plan' : 'plans' }`
		: 'All plans';
	const limitLabel = coupon._pp_usage_limit_total > 0
		? `${ coupon.used } / ${ coupon._pp_usage_limit_total } used`
		: `${ coupon.used } ${ 1 === coupon.used ? 'use' : 'uses' }`;

	return (
		<button
			type="button"
			className={ `passpress-coupon-admin-card${ active && ! isExpired ? ' is-active' : ' is-inactive' }${ isLive ? ' is-live' : ' is-draft' }` }
			onClick={ () => onEdit( coupon ) }
		>
			<div className="passpress-coupon-admin-card-top">
				<div className="passpress-coupon-admin-card-badges">
					<span className={ `passpress-coupon-admin-badge ${ active && ! isExpired ? 'is-active' : 'is-inactive' }` }>
						{ isExpired ? 'Expired' : ( active ? 'Active' : 'Off' ) }
					</span>
					<span className={ `passpress-coupon-admin-badge ${ isLive ? 'is-live' : 'is-draft' }` }>{ isLive ? 'Live' : 'Draft' }</span>
					<span className="passpress-coupon-admin-badge is-type">{ 'fixed' === coupon._pp_discount_type ? 'Fixed' : 'Percent' }</span>
				</div>
				<span className="passpress-coupon-admin-discount">{ discountLabel }</span>
			</div>

			<h3 className="passpress-coupon-admin-title">{ coupon.title }</h3>

			<dl className="passpress-coupon-admin-details">
				<div>
					<dt>Applies to</dt>
					<dd>{ plansLabel }</dd>
				</div>
				<div>
					<dt>Per member</dt>
					<dd>{ coupon._pp_usage_limit_per_user > 0 ? coupon._pp_usage_limit_per_user : 'Unlimited' }</dd>
				</div>
				<div>
					<dt>Expires</dt>
					<dd>{ coupon._pp_expiry_date || 'Never' }</dd>
				</div>
			</dl>

			<div className="passpress-coupon-admin-footer">
				<span className="passpress-coupon-admin-usage">{ limitLabel }</span>
				<span className="passpress-coupon-admin-edit">Edit coupon</span>
			</div>
		</button>
	);
}

export default function CouponsPage() {
	const queryClient = useQueryClient();
	const { data, isLoading } = useQuery( { queryKey: [ 'coupons' ], queryFn: () => apiFetch( { path: '/passpress/v1/coupons' } ) } );

	const [ modalOpen, setModalOpen ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ form, setForm ] = useState( DEFAULT_FORM );
	const [ error, setError ] = useState( '' );

	const invalidate = () => queryClient.invalidateQueries( { queryKey: [ 'coupons' ] } );

	const createMutation = useMutation( {
		mutationFn: ( body ) => apiFetch( { path: '/passpress/v1/coupons', method: 'POST', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );
	const updateMutation = useMutation( {
		mutationFn: ( { id, body } ) => apiFetch( { path: `/passpress/v1/coupons/${ id }`, method: 'PUT', data: body } ),
		onSuccess: () => { invalidate(); setModalOpen( false ); },
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const openCreate = () => { setEditingId( null ); setForm( DEFAULT_FORM ); setError( '' ); setModalOpen( true ); };
	const openEdit = ( coupon ) => { setEditingId( coupon.coupon_id ); setForm( { ...coupon, is_live: !! coupon.is_live, _pp_active: !! coupon._pp_active } ); setError( '' ); setModalOpen( true ); };
	const setField = ( key, value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );

	const submit = ( e ) => {
		e.preventDefault();
		setError( '' );
		const body = { ...form, title: form.title.toUpperCase() };
		if ( editingId ) {
			updateMutation.mutate( { id: editingId, body } );
		} else {
			createMutation.mutate( body );
		}
	};

	if ( isLoading || ! data ) {
		return <div className="wrap passpress-wrap passpress-coupons-page"><p>Loading…</p></div>;
	}

	const { items, plans, currency_symbol: currencySymbol } = data;
	const saving = createMutation.isPending || updateMutation.isPending;
	const amountPrefix = 'fixed' === form._pp_discount_type ? currencySymbol : '%';

	return (
		<div className="wrap passpress-wrap passpress-coupons-page">
			<div className="passpress-coupons-page-header">
				<div className="passpress-coupons-page-copy">
					<p className="passpress-coupons-page-eyebrow">Marketing</p>
					<h1>Coupons</h1>
					<p className="passpress-coupons-page-desc">{ items.length } { 1 === items.length ? 'promo code' : 'promo codes' } in your catalog</p>
				</div>
				<button type="button" className="passpress-coupons-new-btn" onClick={ openCreate }>New coupon</button>
			</div>

			{ 0 === items.length ? (
				<EmptyState
					className="passpress-coupons-empty"
					eyebrow="Get started"
					title="No coupons yet"
					desc="Create a promo code members can enter at checkout for a percentage or fixed discount."
					ctaLabel="Create a coupon"
					onCta={ openCreate }
				/>
			) : (
				<div className="passpress-coupons-grid">
					{ items.map( ( coupon ) => (
						<CouponCard key={ coupon.coupon_id } coupon={ coupon } plans={ plans } currencySymbol={ currencySymbol } onEdit={ openEdit } />
					) ) }
				</div>
			) }

			<Modal
				open={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				eyebrow={ editingId ? 'Edit' : 'Create' }
				title={ editingId ? 'Edit coupon' : 'New coupon' }
				error={ error }
				modalClassName="passpress-coupon-modal"
			>
				<form className="pp-plan-form" onSubmit={ submit }>
					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_coupon_code">Coupon code</label>
						<input
							type="text"
							id="pp_coupon_code"
							className="pp-input"
							placeholder="e.g. SUMMER20"
							required
							autoComplete="off"
							style={ { textTransform: 'uppercase' } }
							value={ form.title }
							onChange={ ( e ) => setField( 'title', e.target.value ) }
						/>
						<p className="pp-field-hint">Members enter this at checkout. Not case-sensitive; stored uppercase.</p>
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_coupon_discount_type">Discount type</label>
							<select id="pp_coupon_discount_type" className="pp-input pp-input-select" value={ form._pp_discount_type } onChange={ ( e ) => setField( '_pp_discount_type', e.target.value ) }>
								<option value="percent">Percentage</option>
								<option value="fixed">Fixed amount</option>
							</select>
						</div>
						<div className="pp-field">
							<label className="pp-label" htmlFor="pp_coupon_discount_amount">Amount</label>
							<div className="pp-input-prefix-wrap">
								<span className="pp-input-prefix">{ amountPrefix }</span>
								<input type="number" step="0.01" min="0" id="pp_coupon_discount_amount" className="pp-input" value={ form._pp_discount_amount } onChange={ ( e ) => setField( '_pp_discount_amount', e.target.value ) } />
							</div>
							<p className="pp-field-hint">Percentage (e.g. 20) or fixed currency amount, matching the type above.</p>
						</div>
					</div>

					<hr className="pp-divider" />

					<div className="pp-field">
						<span className="pp-label">Applicable plans</span>
						<p className="pp-field-hint">Leave all unchecked to allow any plan.</p>
						{ 0 === plans.length ? (
							<p className="pp-field-hint">No membership plans exist yet.</p>
						) : (
							<div className="pp-checkbox-grid">
								{ plans.map( ( plan ) => (
									<label className="pp-checkbox-box pp-checkbox-compact" key={ plan.id }>
										<input
											type="checkbox"
											checked={ form._pp_applicable_plans.map( Number ).includes( plan.id ) }
											onChange={ () => setField( '_pp_applicable_plans', toggleInArray( form._pp_applicable_plans, plan.id ) ) }
										/>
										<span>{ plan.name }</span>
									</label>
								) ) }
							</div>
						) }
					</div>

					<div className="pp-field-row">
						<div className="pp-field">
							<div className="pp-label-row">
								<label className="pp-label" htmlFor="pp_coupon_usage_total">Total usage limit</label>
								<span className="pp-label-hint">0 = unlimited</span>
							</div>
							<input type="number" min="0" id="pp_coupon_usage_total" className="pp-input" value={ form._pp_usage_limit_total } onChange={ ( e ) => setField( '_pp_usage_limit_total', e.target.value ) } />
						</div>
						<div className="pp-field">
							<div className="pp-label-row">
								<label className="pp-label" htmlFor="pp_coupon_usage_per_user">Per member limit</label>
								<span className="pp-label-hint">0 = unlimited</span>
							</div>
							<input type="number" min="0" id="pp_coupon_usage_per_user" className="pp-input" value={ form._pp_usage_limit_per_user } onChange={ ( e ) => setField( '_pp_usage_limit_per_user', e.target.value ) } />
						</div>
					</div>

					<div className="pp-field">
						<label className="pp-label" htmlFor="pp_coupon_expiry">Expiry date</label>
						<input type="date" id="pp_coupon_expiry" className="pp-input" value={ form._pp_expiry_date } onChange={ ( e ) => setField( '_pp_expiry_date', e.target.value ) } />
						<p className="pp-field-hint">Optional. Leave blank for no expiry.</p>
					</div>

					<label className="pp-checkbox-box">
						<input type="checkbox" checked={ !! form._pp_active } onChange={ ( e ) => setField( '_pp_active', e.target.checked ) } />
						<span>Coupon can be used (active)</span>
					</label>

					{ editingId && (
						<label className="pp-checkbox-box pp-coupon-status-box">
							<input type="checkbox" checked={ !! form.is_live } onChange={ ( e ) => setField( 'is_live', e.target.checked ) } />
							<span>Live (published)</span>
						</label>
					) }

					<div className="pp-modal-footer">
						<button type="button" className="pp-btn-outline passpress-modal-cancel" onClick={ () => setModalOpen( false ) }>Cancel</button>
						<button type="submit" className="pp-btn-solid" disabled={ saving }>
							{ saving ? 'Saving…' : ( editingId ? 'Save changes' : 'Create coupon' ) }
						</button>
					</div>
				</form>
			</Modal>
		</div>
	);
}
