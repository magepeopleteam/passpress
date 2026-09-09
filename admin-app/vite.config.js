import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds one predictably-named JS + CSS bundle straight into the plugin's
// existing assets/admin/ folder so PP_Admin::enqueue_assets() can
// wp_enqueue_script/style() it like any other plugin asset — no dev server
// assumed on the live site, the built output is committed like
// passpress-admin.js already is.
export default defineConfig({
	plugins: [ react() ],
	build: {
		outDir: '../assets/admin/build',
		emptyOutDir: true,
		rollupOptions: {
			input: 'src/main.jsx',
			output: {
				// IIFE (not the Vite default of ES modules) so PP_Admin can
				// wp_enqueue_script() this like any other plugin script,
				// with no <script type="module"> handling to wire up.
				format: 'iife',
				entryFileNames: 'admin-app.js',
				chunkFileNames: 'admin-app-[name].js',
				assetFileNames: 'admin-app.[ext]',
			},
		},
	},
} );
