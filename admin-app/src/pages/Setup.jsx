import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import apiFetch from '../api.js';
import NavLink from '../components/NavLink.jsx';
import SetupWizardModal from '../components/SetupWizardModal.jsx';

export default function SetupPage() {
	const { data } = useQuery( { queryKey: [ 'setup-summaries' ], queryFn: () => apiFetch( { path: '/passpress/v1/setup/summaries' } ) } );
	const [ modalOpen, setModalOpen ] = useState( false );

	const activeType = data ? data.active_type : '';
	const activeSummary = data && activeType ? data.summaries[ activeType ] : null;
	const activeLabel = activeSummary ? activeSummary.label : '';
	const activeIcon = activeSummary ? activeSummary.icon : 'dashicons-yes-alt';

	return (
		<div className="wrap passpress-wrap passpress-setup-page">
			<div className="passpress-setup-page-header">
				<div className="passpress-setup-page-copy">
					<p className="passpress-setup-page-eyebrow">Getting started</p>
					<h1>Setup</h1>
					<p className="passpress-setup-page-desc">Start from a business type and PassPress creates matching plans, spaces and pages for you.</p>
				</div>
				{ activeType && (
					<NavLink to="passpress" className="passpress-setup-dashboard-btn">Go to Dashboard</NavLink>
				) }
			</div>

			{ activeLabel && (
				<div className="passpress-setup-status">
					<div className="passpress-setup-status-icon">
						<span className={ `dashicons ${ activeIcon }` } aria-hidden="true"></span>
					</div>
					<div className="passpress-setup-status-copy">
						<p className="passpress-setup-status-label">Active business type</p>
						<strong>{ activeLabel }</strong>
						<span>You can add another business type as well — anything already imported stays as it is.</span>
					</div>
				</div>
			) }

			<div className="passpress-setup-cta">
				<span className="passpress-setup-cta-icon dashicons dashicons-images-alt2" aria-hidden="true"></span>
				<div className="passpress-setup-cta-copy">
					<strong>{ activeLabel ? 'Import another business type' : 'Import a starter setup' }</strong>
					<span>Two short steps: pick your business type, then confirm what gets created.</span>
				</div>
				<button type="button" className="pp-btn-solid" onClick={ () => setModalOpen( true ) }>Choose business type</button>
			</div>

			<SetupWizardModal open={ modalOpen } onClose={ () => setModalOpen( false ) } />
		</div>
	);
}
