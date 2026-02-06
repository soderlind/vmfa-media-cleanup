/**
 * StatsCard component — Displays media cleanup statistics.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Stats card component showing media cleanup statistics.
 *
 * @param {Object}  props       Component props.
 * @param {Object}  props.stats Stats data from useScanStatus.
 * @return {JSX.Element|null} Stats card or null if no stats.
 */
export function StatsCard( { stats } ) {
	if ( ! stats ) {
		return null;
	}

	return (
		<Card className="vmfa-stats-card">
			<CardBody>
				<div className="vmfa-stats-grid">
					<div className="vmfa-stats-item">
						<span className="vmfa-stats-value">
							{ stats.total_media ?? 0 }
						</span>
						<span className="vmfa-stats-label">
							{ __( 'Total Media', 'vmfa-media-cleanup' ) }
						</span>
					</div>
					<div className="vmfa-stats-item">
						<span className="vmfa-stats-value vmfa-stats-value--highlight">
							{ stats.unused_count ?? 0 }
						</span>
						<span className="vmfa-stats-label">
							{ __( 'Unused', 'vmfa-media-cleanup' ) }
						</span>
					</div>
					<div className="vmfa-stats-item">
						<span className="vmfa-stats-value">
							{ stats.duplicate_count ?? 0 }
						</span>
						<span className="vmfa-stats-label">
							{ __( 'Duplicates', 'vmfa-media-cleanup' ) }
						</span>
					</div>
					<div className="vmfa-stats-item">
						<span className="vmfa-stats-value">
							{ stats.oversized_count ?? 0 }
						</span>
						<span className="vmfa-stats-label">
							{ __( 'Oversized', 'vmfa-media-cleanup' ) }
						</span>
					</div>
				</div>
			</CardBody>
		</Card>
	);
}
