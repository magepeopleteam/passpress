import NavLink from './NavLink.jsx';

// Mirrors the .passpress-dashboard-kpi markup from admin/PP_Dashboard.php
// exactly (same class names) so the existing passpress-admin.css applies
// with no changes.
export default function KpiTile( { to, params, tone, icon, label, value, hint } ) {
	return (
		<NavLink to={ to } params={ params } className={ `passpress-dashboard-kpi is-${ tone }` }>
			<span className="passpress-dashboard-kpi-icon">
				<span className={ `dashicons ${ icon }` } aria-hidden="true"></span>
			</span>
			<span className="passpress-dashboard-kpi-meta">
				<span className="passpress-dashboard-kpi-label">{ label }</span>
				<strong className="passpress-dashboard-kpi-value">{ value }</strong>
				<span className="passpress-dashboard-kpi-hint">{ hint }</span>
			</span>
		</NavLink>
	);
}
