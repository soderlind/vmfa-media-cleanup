/**
 * VMFA Media Cleanup — React entry point.
 *
 * @package VmfaMediaCleanup
 */

import { createRoot, StrictMode } from '@wordpress/element';
import { CleanupDashboard } from './components/CleanupDashboard';

import '../styles/admin.scss';

const root = document.getElementById( 'vmfa-media-cleanup-app' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<CleanupDashboard />
		</StrictMode>
	);
}
