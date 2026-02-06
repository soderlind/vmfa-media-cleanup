/**
 * ScanProgress — Scan controls and progress indicator.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { search, cancelCircleFilled, reset } from '@wordpress/icons';

/**
 * Scan progress component.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.scan Scan state from useScanStatus hook.
 * @return {JSX.Element}      Progress UI.
 */
export function ScanProgress( { scan } ) {
	const {
		status,
		phase,
		progress,
		error,
		startScan,
		cancelScan,
		resetScan,
	} = scan;

	const isRunning = status === 'running';
	const isDone = status === 'complete';
	const percent =
		progress.total > 0
			? Math.round( ( progress.processed / progress.total ) * 100 )
			: 0;

	const phaseLabels = {
		indexing: __( 'Building reference index…', 'vmfa-media-cleanup' ),
		hashing: __( 'Computing file hashes…', 'vmfa-media-cleanup' ),
		detecting: __( 'Running detectors…', 'vmfa-media-cleanup' ),
		done: __( 'Scan complete', 'vmfa-media-cleanup' ),
	};

	return (
		<div className="vmfa-cleanup-scan">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ isDone && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Scan completed successfully.', 'vmfa-media-cleanup' ) }
				</Notice>
			) }

			{ status === 'cancelled' && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Scan was cancelled.', 'vmfa-media-cleanup' ) }
				</Notice>
			) }

			<div className="vmfa-cleanup-scan__controls">
				{ ! isRunning && (
					<Button
						variant="primary"
						icon={ search }
						onClick={ startScan }
					>
						{ isDone
							? __( 'Re-scan', 'vmfa-media-cleanup' )
							: __( 'Start Scan', 'vmfa-media-cleanup' ) }
					</Button>
				) }

				{ isRunning && (
					<Button
						variant="secondary"
						icon={ cancelCircleFilled }
						isDestructive
						onClick={ cancelScan }
					>
						{ __( 'Cancel', 'vmfa-media-cleanup' ) }
					</Button>
				) }

				{ ( isDone || status === 'cancelled' ) && (
					<Button
						variant="tertiary"
						icon={ reset }
						onClick={ resetScan }
					>
						{ __( 'Reset', 'vmfa-media-cleanup' ) }
					</Button>
				) }
			</div>

			{ isRunning && (
				<div className="vmfa-cleanup-scan__progress">
					<div className="vmfa-cleanup-scan__phase">
						{ phaseLabels[ phase ] || phase }
					</div>

					<div className="vmfa-cleanup-scan__bar-wrapper">
						<div
							className="vmfa-cleanup-scan__bar"
							role="progressbar"
							aria-valuenow={ percent }
							aria-valuemin={ 0 }
							aria-valuemax={ 100 }
							style={ { width: `${ percent }%` } }
						/>
					</div>

					<div className="vmfa-cleanup-scan__detail">
						{ progress.processed } / { progress.total }
						{ ' ' }
						({ percent }%)
					</div>
				</div>
			) }
		</div>
	);
}
