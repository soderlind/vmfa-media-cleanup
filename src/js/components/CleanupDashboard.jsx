/**
 * CleanupDashboard — Top-level component.
 *
 * @package VmfaMediaCleanup
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { search } from '@wordpress/icons';
import { useScanStatus } from '../hooks/useScanStatus';
import { useResults } from '../hooks/useResults';
import { ScanProgress } from './ScanProgress';
import { ResultsPanel } from './ResultsPanel';
import { SettingsPanel } from './SettingsPanel';

/**
 * Main dashboard component combining scan controls and results.
 *
 * @return {JSX.Element} Dashboard UI.
 */
export function CleanupDashboard() {
	const scan = useScanStatus();
	const results = useResults();
	const [ activeTab, setActiveTab ] = useState( 'scan' );

	const resultTabs = [ 'unused', 'duplicate', 'oversized', 'flagged', 'trash' ];
	const scanDependentTabs = [ 'unused', 'duplicate', 'oversized', 'flagged' ];
	const isResultTab = resultTabs.includes( activeTab );
	const needsScan = scanDependentTabs.includes( activeTab ) && scan.status !== 'complete';

	const handleTabClick = ( tabId ) => {
		setActiveTab( tabId );
		if ( resultTabs.includes( tabId ) ) {
			results.setType( tabId );
			results.clearSelection();
		}
	};

	const tabs = [
		{ id: 'scan', label: __( 'Scan', 'vmfa-media-cleanup' ) },
		{ id: 'unused', label: __( 'Unused', 'vmfa-media-cleanup' ) },
		{ id: 'duplicate', label: __( 'Duplicates', 'vmfa-media-cleanup' ) },
		{ id: 'oversized', label: __( 'Oversized', 'vmfa-media-cleanup' ) },
		{ id: 'flagged', label: __( 'Flagged', 'vmfa-media-cleanup' ) },
		{ id: 'trash', label: __( 'Trash', 'vmfa-media-cleanup' ) },
		{ id: 'settings', label: __( 'Settings', 'vmfa-media-cleanup' ) },
	];

	return (
		<div className="vmfa-cleanup-dashboard">
			<div className="vmfa-cleanup-dashboard__header">
				<h2>{ __( 'Media Cleanup', 'vmfa-media-cleanup' ) }</h2>
				{ scan.stats && (
					<div className="vmfa-cleanup-dashboard__stats">
						<span className="vmfa-cleanup-stat">
							{ __( 'Unused:', 'vmfa-media-cleanup' ) }{ ' ' }
						<strong>{ scan.stats.unused_count ?? 0 }</strong>
					</span>
					<span className="vmfa-cleanup-stat">
						{ __( 'Duplicates:', 'vmfa-media-cleanup' ) }{ ' ' }
						<strong>{ scan.stats.duplicate_count ?? 0 }</strong>
					</span>
					<span className="vmfa-cleanup-stat">
						{ __( 'Oversized:', 'vmfa-media-cleanup' ) }{ ' ' }
						<strong>{ scan.stats.oversized_count ?? 0 }</strong>
						</span>
					</div>
				) }
			</div>

			<nav className="vmfa-cleanup-dashboard__tabs" role="tablist">
				{ tabs.map( ( tab ) => (
					<button
						key={ tab.id }
						role="tab"
						aria-selected={ activeTab === tab.id }
						className={ `vmfa-cleanup-tab ${
							activeTab === tab.id ? 'is-active' : ''
						}` }
						onClick={ () => handleTabClick( tab.id ) }
					>
						{ tab.label }
					</button>
				) ) }
			</nav>

			<div className="vmfa-cleanup-dashboard__content" role="tabpanel">
				{ activeTab === 'scan' && <ScanProgress scan={ scan } /> }
				{ isResultTab && needsScan && (
					<div className="vmfa-cleanup-results__empty-state">
						<p>{ __( 'Run a scan first to detect items.', 'vmfa-media-cleanup' ) }</p>
						<Button
							variant="primary"
							icon={ search }
							onClick={ () => {
								scan.startScan();
								setActiveTab( 'scan' );
							} }
						>
							{ __( 'Start Scan', 'vmfa-media-cleanup' ) }
						</Button>
					</div>
				) }
				{ isResultTab && ! needsScan && (
					<ResultsPanel results={ results } />
				) }
				{ activeTab === 'settings' && <SettingsPanel /> }
			</div>
		</div>
	);
}
