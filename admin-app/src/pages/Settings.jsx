import { useRouter } from '../router.jsx';
import NavLink from '../components/NavLink.jsx';
import GeneralPanel from '../components/settings/GeneralPanel.jsx';
import NotificationsPanel from '../components/settings/NotificationsPanel.jsx';
import BillingPanel from '../components/settings/BillingPanel.jsx';

const TABS = {
	general: { label: 'General', icon: 'dashicons-admin-generic', desc: 'Currency, dates & pass display' },
	billing: { label: 'Payment Method', icon: 'dashicons-money-alt', desc: 'Checkout gateways & renewals' },
	notifications: { label: 'Notifications', icon: 'dashicons-email-alt', desc: 'Email alerts for members' },
};

const PANELS = {
	general: GeneralPanel,
	billing: BillingPanel,
	notifications: NotificationsPanel,
};

export default function SettingsPage() {
	const { params } = useRouter();
	const tab = TABS[ params.get( 'tab' ) ] ? params.get( 'tab' ) : 'general';
	const Panel = PANELS[ tab ];

	return (
		<div className="wrap passpress-wrap passpress-settings-page">
			<div className="passpress-settings-page-header">
				<div className="passpress-settings-page-copy">
					<p className="passpress-settings-page-eyebrow">Configuration</p>
					<h1>Settings</h1>
					<p className="passpress-settings-page-desc">Tune currency, checkout, and the emails members receive.</p>
				</div>
			</div>

			<div className="passpress-settings-layout">
				<nav className="passpress-settings-sidebar" aria-label="Settings sections">
					<p className="passpress-settings-sidebar-label">Sections</p>
					<ul className="passpress-settings-nav">
						{ Object.entries( TABS ).map( ( [ slug, t ] ) => (
							<li key={ slug }>
								<NavLink
									to="passpress-settings"
									params={ { tab: slug } }
									className={ `passpress-settings-nav-link ${ slug === tab ? 'is-active' : '' }` }
								>
									<span className="passpress-settings-nav-icon">
										<span className={ `dashicons ${ t.icon }` } aria-hidden="true"></span>
									</span>
									<span className="passpress-settings-nav-text">
										<span className="passpress-settings-nav-label">{ t.label }</span>
										<span className="passpress-settings-nav-desc">{ t.desc }</span>
									</span>
								</NavLink>
							</li>
						) ) }
					</ul>
				</nav>

				<div className="passpress-settings-content">
					<Panel />
				</div>
			</div>
		</div>
	);
}
