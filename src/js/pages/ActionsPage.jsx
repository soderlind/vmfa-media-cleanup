/**
 * Actions Page Component for Media Cleanup.
 *
 * Contains action buttons - start scan, cancel, reset.
 * No settings here - just actions.
 *
 * @package VmfaMediaCleanup
 */

import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { search, cancelCircleFilled, reset } from '@wordpress/icons';

import { useScanStatus } from '../hooks/useScanStatus';

/**
 * Actions Page component.
 *
 * @return {JSX.Element} The actions page content.
 */
export function ActionsPage() {
	const scan = useScanStatus();
	const [ notice, setNotice ] = useState( null );

	const isRunning = scan.status === 'running';
	const isComplete = scan.status === 'complete';
	const isCancelled = scan.status === 'cancelled';

	/**
	 * Handle scan start.
	 */
	const handleStartScan = async () => {
		try {
			setNotice( null );
			await scan.startScan();
			setNotice( {
				type: 'success',
				message: __(
					'Scan started. Progress will be shown in the Dashboard tab.',
					'vmfa-media-cleanup'
				),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message:
					err.message ||
					__( 'Failed to start scan.', 'vmfa-media-cleanup' ),
			} );
		}
	};

	/**
	 * Handle scan cancel.
	 */
	const handleCancelScan = async () => {
		try {
			await scan.cancelScan();
			setNotice( {
				type: 'warning',
				message: __( 'Scan cancelled.', 'vmfa-media-cleanup' ),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message:
					err.message ||
					__( 'Failed to cancel scan.', 'vmfa-media-cleanup' ),
			} );
		}
	};

	/**
	 * Handle reset.
	 */
	const handleReset = async () => {
		try {
			await scan.resetScan();
			setNotice( {
				type: 'info',
				message: __( 'Scan data reset.', 'vmfa-media-cleanup' ),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message:
					err.message ||
					__( 'Failed to reset scan.', 'vmfa-media-cleanup' ),
			} );
		}
	};

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ /* Scan Actions */ }
			<Card className="vmfo-actions-card">
				<CardHeader>
					<h3>{ __( 'Scan Actions', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<p className="vmfo-actions-description">
						{ __(
							'Run a scan to detect unused, duplicate, and oversized media files in your library.',
							'vmfa-media-cleanup'
						) }
					</p>

					<div className="vmfo-actions-buttons">
						{ ! isRunning && (
							<Button
								variant="primary"
								icon={ search }
								onClick={ handleStartScan }
							>
								{ isComplete
									? __( 'Re-scan Media', 'vmfa-media-cleanup' )
									: __( 'Start Scan', 'vmfa-media-cleanup' ) }
							</Button>
						) }

						{ isRunning && (
							<Button
								variant="secondary"
								icon={ cancelCircleFilled }
								isDestructive
								onClick={ handleCancelScan }
							>
								{ __( 'Cancel Scan', 'vmfa-media-cleanup' ) }
							</Button>
						) }

						{ ( isComplete || isCancelled ) && (
							<Button
								variant="tertiary"
								icon={ reset }
								onClick={ handleReset }
							>
								{ __( 'Reset', 'vmfa-media-cleanup' ) }
							</Button>
						) }
					</div>
				</CardBody>
			</Card>

			{ /* Scan Info */ }
			<Card className="vmfo-actions-card vmfo-info-card">
				<CardHeader>
					<h3>{ __( 'What the Scan Does', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<ol>
						<li>
							{ __(
								'Indexes all references to media files across your site content',
								'vmfa-media-cleanup'
							) }
						</li>
						<li>
							{ __(
								'Computes file hashes to identify duplicate files',
								'vmfa-media-cleanup'
							) }
						</li>
						<li>
							{ __(
								'Checks file sizes against configured thresholds',
								'vmfa-media-cleanup'
							) }
						</li>
						<li>
							{ __(
								'Generates a report of items requiring attention',
								'vmfa-media-cleanup'
							) }
						</li>
					</ol>
					<p>
						<strong>{ __( 'Note:', 'vmfa-media-cleanup' ) }</strong>{ ' ' }
						{ __(
							'Scanning does not modify or delete any files. You can safely run scans at any time.',
							'vmfa-media-cleanup'
						) }
					</p>
				</CardBody>
			</Card>

			{ /* Bulk Actions */ }
			<Card className="vmfo-actions-card">
				<CardHeader>
					<h3>{ __( 'Bulk Operations', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'After running a scan, you can perform bulk operations on detected items from the Dashboard tab. Select items and use the bulk action bar to:',
							'vmfa-media-cleanup'
						) }
					</p>
					<ul>
						<li>{ __( 'Move items to trash', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Restore items from trash', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Permanently delete items', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Flag items for later review', 'vmfa-media-cleanup' ) }</li>
					</ul>
				</CardBody>
			</Card>
		</>
	);
}

export default ActionsPage;
