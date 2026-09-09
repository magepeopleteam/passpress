import { usePage } from './router.jsx';
import DashboardPage from './pages/Dashboard.jsx';
import PlansPage from './pages/Plans.jsx';
import FacilitiesPage from './pages/Facilities.jsx';
import ClassSessionsPage from './pages/ClassSessions.jsx';
import CouponsPage from './pages/Coupons.jsx';
import MembershipsPage from './pages/Memberships.jsx';
import BookingsPage from './pages/Bookings.jsx';
import VisitorsPage from './pages/Visitors.jsx';
import BillingHistoryPage from './pages/BillingHistory.jsx';
import AttendancePage from './pages/Attendance.jsx';
import ReportsPage from './pages/Reports.jsx';
import ActivityLogPage from './pages/ActivityLog.jsx';
import ScanGatePage from './pages/ScanGate.jsx';
import SetupPage from './pages/Setup.jsx';
import SettingsPage from './pages/Settings.jsx';

// Screens are added here one migration phase at a time (see the plan's
// per-phase retirement list) — every slug not yet listed keeps rendering its
// legacy PHP page and never reaches this component at all.
const SCREENS = {
	passpress: DashboardPage,
	'passpress-plans': PlansPage,
	'passpress-facilities': FacilitiesPage,
	'passpress-class-sessions': ClassSessionsPage,
	'passpress-coupons': CouponsPage,
	'passpress-memberships': MembershipsPage,
	'passpress-bookings': BookingsPage,
	'passpress-visitors': VisitorsPage,
	'passpress-billing-history': BillingHistoryPage,
	'passpress-attendance': AttendancePage,
	'passpress-reports': ReportsPage,
	'passpress-activity-log': ActivityLogPage,
	'passpress-scan-gate': ScanGatePage,
	'passpress-setup': SetupPage,
	'passpress-settings': SettingsPage,
};

export default function App() {
	const page = usePage();
	const Screen = SCREENS[ page ] || DashboardPage;
	return <Screen />;
}
