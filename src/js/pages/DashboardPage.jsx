/**
 * Dashboard Page Component for Media Cleanup.
 *
 * Displays scan results and detection results.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useCallback } from '@wordpress/element';
import { Card, CardBody, CardHeader, Button, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useScanStatus } from '../hooks/useScanStatus';
import { useResults } from '../hooks/useResults';
import { ScanProgress } from '../components/ScanProgress';
import { ResultsPanel } from '../components/ResultsPanel';

/**
 * Dashboard Page component.
 *
 * @return {JSX.Element} The dashboard page content.
 */
export function DashboardPage() {
	const scan = useScanStatus();
	const results = useResults();
	const [ activeResultTab, setActiveResultTab ] = useState( 'unused' );

	const isRunning = scan.status === 'running';
	const isComplete = scan.status === 'complete';
	const isIdle = scan.status === 'idle';

	const handleResultTabSelect = ( tabName ) => {
		setActiveResultTab( tabName );
		results.setType( tabName );
		results.clearSelection();
	};

	const resultTabs = [
		{
			name: 'unused',
			title: __( 'Unused', 'vmfa-media-cleanup' ),
		},
		{
			name: 'duplicate',
			title: __( 'Duplicates', 'vmfa-media-cleanup' ),
		},
		{
			name: 'oversized',
			title: __( 'Oversized', 'vmfa-media-cleanup' ),
		},
		{
			name: 'flagged',
			title: __( 'Flagged', 'vmfa-media-cleanup' ),
		},
		{
			name: 'trash',
			title: __( 'Trash', 'vmfa-media-cleanup' ),
		},
	];

	return (
		<>
			{ /* Scan Status Card */ }
			<Card className="vmfo-dashboard-card">
				<CardHeader>
					<h3>{ __( 'Scan Status', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					{ isIdle && (
						<div className="vmfo-status-idle">
							<p>
								{ __(
									'No scan has been run yet. Go to the Actions tab to start a scan.',
									'vmfa-media-cleanup'
								) }
							</p>
						</div>
					) }

					{ ( isRunning || isComplete || scan.status === 'cancelled' ) && (
						<ScanProgress scan={ scan } />
					) }
				</CardBody>
			</Card>

			{ /* Results Panel */ }
			{ isComplete && (
				<Card className="vmfo-dashboard-card vmfo-results-card">
					<CardHeader>
						<h3>{ __( 'Scan Results', 'vmfa-media-cleanup' ) }</h3>
					</CardHeader>
					<CardBody>
						<TabPanel
							className="vmfo-results-tabs"
							activeClass="is-active"
							tabs={ resultTabs }
							onSelect={ handleResultTabSelect }
						>
							{ () => <ResultsPanel results={ results } /> }
						</TabPanel>
					</CardBody>
				</Card>
			) }

			{ /* Info Card */ }
			<Card className="vmfo-dashboard-card vmfo-info-card">
				<CardHeader>
					<h3>{ __( 'Understanding Results', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<dl className="vmfo-info-list">
						<dt>{ __( 'Unused', 'vmfa-media-cleanup' ) }</dt>
						<dd>
							{ __(
								'Media files not referenced in posts, pages, widgets, or theme options.',
								'vmfa-media-cleanup'
							) }
						</dd>
						<dt>{ __( 'Duplicates', 'vmfa-media-cleanup' ) }</dt>
						<dd>
							{ __(
								'Files with identical content (same SHA-256 hash).',
								'vmfa-media-cleanup'
							) }
						</dd>
						<dt>{ __( 'Oversized', 'vmfa-media-cleanup' ) }</dt>
						<dd>
							{ __(
								'Files exceeding the configured size thresholds.',
								'vmfa-media-cleanup'
							) }
						</dd>
						<dt>{ __( 'Flagged', 'vmfa-media-cleanup' ) }</dt>
						<dd>
							{ __(
								'Items manually flagged for review.',
								'vmfa-media-cleanup'
							) }
						</dd>
						<dt>{ __( 'Trash', 'vmfa-media-cleanup' ) }</dt>
						<dd>
							{ __(
								'Items moved to trash, can be restored or permanently deleted.',
								'vmfa-media-cleanup'
							) }
						</dd>
					</dl>
				</CardBody>
			</Card>
		</>
	);
}

export default DashboardPage;
