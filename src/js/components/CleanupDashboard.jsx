/**
 * CleanupDashboard — Top-level component.
 *
 * @package VmfaMediaCleanup
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useScanStatus } from '../hooks/useScanStatus';
import { useResults } from '../hooks/useResults';
import { ScanProgress } from './ScanProgress';
import { ResultsPanel } from './ResultsPanel';

/**
 * Main dashboard component combining scan controls and results.
 *
 * @return {JSX.Element} Dashboard UI.
 */
export function CleanupDashboard() {
	const scan = useScanStatus();
	const results = useResults();
	const [ activeTab, setActiveTab ] = useState( 'scan' );

	const tabs = [
		{ id: 'scan', label: __( 'Scan', 'vmfa-media-cleanup' ) },
		{ id: 'results', label: __( 'Results', 'vmfa-media-cleanup' ) },
	];

	return (
		<div className="vmfa-cleanup-dashboard">
			<div className="vmfa-cleanup-dashboard__header">
				<h2>{ __( 'Media Cleanup', 'vmfa-media-cleanup' ) }</h2>
				{ scan.stats && (
					<div className="vmfa-cleanup-dashboard__stats">
						<span className="vmfa-cleanup-stat">
							{ __( 'Unused:', 'vmfa-media-cleanup' ) }{ ' ' }
							<strong>{ scan.stats.unused ?? 0 }</strong>
						</span>
						<span className="vmfa-cleanup-stat">
							{ __( 'Duplicates:', 'vmfa-media-cleanup' ) }{ ' ' }
							<strong>{ scan.stats.duplicate ?? 0 }</strong>
						</span>
						<span className="vmfa-cleanup-stat">
							{ __( 'Oversized:', 'vmfa-media-cleanup' ) }{ ' ' }
							<strong>{ scan.stats.oversized ?? 0 }</strong>
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
						onClick={ () => setActiveTab( tab.id ) }
					>
						{ tab.label }
					</button>
				) ) }
			</nav>

			<div className="vmfa-cleanup-dashboard__content" role="tabpanel">
				{ activeTab === 'scan' && <ScanProgress scan={ scan } /> }
				{ activeTab === 'results' && (
					<ResultsPanel results={ results } />
				) }
			</div>
		</div>
	);
}
