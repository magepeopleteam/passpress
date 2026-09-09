import { useEffect, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import apiFetch from '../api.js';

export default function ScanGatePage() {
	const { data } = useQuery( { queryKey: [ 'scan-facilities' ], queryFn: () => apiFetch( { path: '/passpress/v1/scan/facilities' } ) } );

	const [ facilityId, setFacilityId ] = useState( '0' );
	const [ direction, setDirection ] = useState( 'entry' );
	const [ token, setToken ] = useState( '' );
	const [ pinNumber, setPinNumber ] = useState( '' );
	const [ pinCode, setPinCode ] = useState( '' );
	const [ result, setResult ] = useState( null );

	const tokenRef = useRef( null );

	useEffect( () => {
		tokenRef.current && tokenRef.current.focus();
	}, [] );

	const showResult = ( data ) => {
		setResult( data );
	};

	const submitToken = async ( e ) => {
		if ( 'Enter' !== e.key ) {
			return;
		}
		e.preventDefault();
		const value = token.trim();
		if ( ! value ) {
			return;
		}
		try {
			const response = await apiFetch( {
				path: '/passpress/v1/scan/validate',
				method: 'POST',
				data: { token: value, facility_id: facilityId, direction },
			} );
			showResult( response );
		} finally {
			setToken( '' );
			tokenRef.current && tokenRef.current.focus();
		}
	};

	const submitPin = async () => {
		const number = pinNumber.trim();
		const pin = pinCode.trim();
		if ( ! number || ! pin ) {
			return;
		}
		try {
			const response = await apiFetch( {
				path: '/passpress/v1/scan/pin',
				method: 'POST',
				data: { membership_number: number, pin, facility_id: facilityId, direction },
			} );
			showResult( response );
		} finally {
			setPinNumber( '' );
			setPinCode( '' );
		}
	};

	const facilities = data ? data.facilities : [];

	return (
		<div className="wrap passpress-wrap passpress-scan-gate">
			<h1>Scan Gate</h1>

			<div className="passpress-scan-panel">
				<div className="passpress-scan-controls">
					<label>
						Facility
						<select value={ facilityId } onChange={ ( e ) => setFacilityId( e.target.value ) }>
							<option value="0">— General Entrance —</option>
							{ facilities.map( ( f ) => <option key={ f.id } value={ f.id }>{ f.name }</option> ) }
						</select>
					</label>

					<label>
						Direction
						<select value={ direction } onChange={ ( e ) => setDirection( e.target.value ) }>
							<option value="entry">Entry</option>
							<option value="exit">Exit</option>
						</select>
					</label>
				</div>

				<h2>QR Scan</h2>
				<p className="description">Focus this field and scan with a USB/Bluetooth QR scanner, or paste the code and press Enter.</p>
				<p>
					<input
						ref={ tokenRef }
						type="text"
						className="regular-text"
						autoComplete="off"
						placeholder="Scan or paste pass code…"
						value={ token }
						onChange={ ( e ) => setToken( e.target.value ) }
						onKeyPress={ submitToken }
					/>
				</p>

				<h2>PIN Entry</h2>
				<p>
					<input type="text" placeholder="Membership number" value={ pinNumber } onChange={ ( e ) => setPinNumber( e.target.value ) } />
					<input type="text" placeholder="PIN" maxLength="10" value={ pinCode } onChange={ ( e ) => setPinCode( e.target.value ) } />
					<button type="button" className="button button-primary" onClick={ submitPin }>Check In</button>
				</p>

				{ result && (
					<div className={ `passpress-scan-result ${ result.allowed ? 'passpress-result-success' : 'passpress-result-error' }` } aria-live="polite">
						{ result.allowed ? (
							<>
								<strong>{ result.member_name }</strong><br />
								{ result.plan_name }<br />
								{ result.reason || 'Access granted' }
							</>
						) : (
							<>
								<strong>Access denied</strong><br />
								{ result.reason || '' }
							</>
						) }
					</div>
				) }
			</div>
		</div>
	);
}
