import { useEffect } from 'react';

// Mirrors the .passpress-modal-overlay/.passpress-modal/.pp-modal-header
// markup every existing modal (Plans/Facilities/Class Sessions/Coupons) uses
// in admin/*.php, so passpress-admin.css applies unchanged.
export default function Modal( { open, eyebrow, title, onClose, error, children, modalClassName = '' } ) {
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const onKeyDown = ( e ) => {
			if ( 'Escape' === e.key ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ open, onClose ] );

	if ( ! open ) {
		return null;
	}

	return (
		<div
			className="passpress-modal-overlay"
			onClick={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onClose();
				}
			} }
		>
			<div className={ `passpress-modal ${ modalClassName }` } role="dialog" aria-modal="true">
				<div className="pp-modal-header">
					<div>
						<p className="pp-modal-eyebrow">{ eyebrow }</p>
						<h2>{ title }</h2>
					</div>
					<button type="button" className="passpress-modal-close" aria-label="Close" onClick={ onClose }>
						&times;
					</button>
				</div>

				{ error && (
					<div className="passpress-modal-notice">
						<p>{ error }</p>
					</div>
				) }

				{ children }
			</div>
		</div>
	);
}
