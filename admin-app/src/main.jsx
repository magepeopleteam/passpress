import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import './styles/tokens.css';
import './styles/shell.css';
import { RouterProvider } from './router.jsx';
import Shell from './components/Shell.jsx';
import App from './App.jsx';

const queryClient = new QueryClient( {
	defaultOptions: {
		queries: {
			refetchOnWindowFocus: false,
			retry: 1,
		},
	},
} );

const container = document.getElementById( 'passpress-root' );

if ( container ) {
	createRoot( container ).render(
		<QueryClientProvider client={ queryClient }>
			<RouterProvider>
				<Shell>
					<App />
				</Shell>
			</RouterProvider>
		</QueryClientProvider>
	);
}
