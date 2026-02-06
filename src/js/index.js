/**
 * VMFA Media Cleanup — React entry point.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import { AddonShell } from '@vmfo/shared';
import { useScanStatus } from './hooks/useScanStatus';
import {
	OverviewPage,
	DashboardPage,
	ConfigurePage,
	ActionsPage,
	LogsPage,
} from './pages';

import '../styles/admin.scss';

/**
 * Main Media Cleanup App using AddonShell.
 *
 * @return {JSX.Element} The app component.
 */
function MediaCleanupApp() {
	const [ stats, setStats ] = useState( null );
	const [ enabled, setEnabled ] = useState( true );

	const scan = useScanStatus();

	/**
	 * Fetch media cleanup statistics.
	 */
	const fetchStats = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa-cleanup/v1/stats',
				method: 'GET',
			} );
			setStats( response );
		} catch ( err ) {
			// Ignore fetch errors.
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

	// Build KPI stats for AddonShell
	const kpiStats = stats
		? [
				{
					label: __( 'Total Media', 'vmfa-media-cleanup' ),
					value: stats.total_media?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Unused', 'vmfa-media-cleanup' ),
					value: stats.unused_count?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Duplicates', 'vmfa-media-cleanup' ),
					value: stats.duplicate_count?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Oversized', 'vmfa-media-cleanup' ),
					value: stats.oversized_count?.toLocaleString() ?? '—',
				},
		  ]
		: [];

	return (
		<AddonShell
			addonKey="media-cleanup"
			addonLabel={ __( 'Media Cleanup', 'vmfa-media-cleanup' ) }
			enabled={ enabled }
			stats={ kpiStats }
			overviewContent={ <OverviewPage /> }
			dashboardContent={ <DashboardPage /> }
			configureContent={ <ConfigurePage /> }
			actionsContent={ <ActionsPage /> }
			logsContent={ <LogsPage /> }
		/>
	);
}

/**
 * Initialize the app when DOM is ready.
 */
function initApp() {
	const root = document.getElementById( 'vmfa-media-cleanup-app' );
	if ( root ) {
		createRoot( root ).render( <MediaCleanupApp /> );
	}
}

// Run when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initApp );
} else {
	initApp();
}
