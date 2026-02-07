/**
 * CleanupDashboard — Top-level component.
 *
 * Tab navigation is handled by PHP (nav-tab-wrapper).
 * This component renders the content for the active subtab.
 *
 * @package VmfaMediaCleanup
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { search } from '@wordpress/icons';
import { useScanStatus } from '../hooks/useScanStatus';
import { useResults } from '../hooks/useResults';
import { ScanProgress } from './ScanProgress';
import { ResultsPanel } from './ResultsPanel';
import { SettingsPanel } from './SettingsPanel';
import { StatsCard } from './StatsCard';

/**
 * Main dashboard component - renders content for the active subtab.
 *
 * @return {JSX.Element} Dashboard UI.
 */
export function CleanupDashboard() {
	const scan = useScanStatus();
	const results = useResults();

	// Get active subtab from PHP (via localized script).
	const activeTab = window.vmfaMediaCleanup?.activeSubtab || 'scan';

	const resultTabs = [ 'unused', 'duplicate', 'oversized', 'flagged', 'trash' ];
	const scanDependentTabs = [ 'unused', 'duplicate', 'oversized', 'flagged' ];
	const isResultTab = resultTabs.includes( activeTab );
	const needsScan =
		scanDependentTabs.includes( activeTab ) && scan.status !== 'complete';

	// Set results type when tab changes.
	useEffect( () => {
		if ( resultTabs.includes( activeTab ) ) {
			results.setType( activeTab );
			results.clearSelection();
		}
	}, [ activeTab ] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Navigate to the scan subtab.
	 */
	const goToScanTab = () => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'subtab', 'scan' );
		window.location.href = url.toString();
	};

	return (
		<div className="vmfa-cleanup-dashboard">
			<StatsCard stats={ scan.stats } />

			<div className="vmfa-cleanup-dashboard__content" role="tabpanel">
				{ activeTab === 'scan' && <ScanProgress scan={ scan } /> }
				{ isResultTab && needsScan && (
					<div className="vmfa-cleanup-results__empty-state">
						<p>
							{ __(
								'Run a scan first to detect items.',
								'vmfa-media-cleanup'
							) }
						</p>
						<Button
							variant="primary"
							icon={ search }
							onClick={ () => {
								scan.startScan();
								goToScanTab();
							} }
						>
							{ __( 'Start Scan', 'vmfa-media-cleanup' ) }
						</Button>
					</div>
				) }
				{ isResultTab && ! needsScan && <ResultsPanel results={ results } /> }
				{ activeTab === 'settings' && <SettingsPanel /> }
			</div>
		</div>
	);
}
