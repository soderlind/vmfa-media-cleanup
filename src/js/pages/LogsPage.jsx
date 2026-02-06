/**
 * Logs Page Component for Media Cleanup.
 *
 * Placeholder for future activity logs feature.
 *
 * @package VmfaMediaCleanup
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Logs Page component.
 *
 * @return {JSX.Element} The logs page content.
 */
export function LogsPage() {
	return (
		<Card className="vmfo-logs-card">
			<CardHeader>
				<h3>{ __( 'Activity Logs', 'vmfa-media-cleanup' ) }</h3>
			</CardHeader>
			<CardBody>
				<div className="vmfo-addon-shell__empty-state">
					<p>
						{ __(
							'Activity logging is coming in a future update. This will show a history of scan operations, cleanup actions, and file deletions.',
							'vmfa-media-cleanup'
						) }
					</p>
				</div>
			</CardBody>
		</Card>
	);
}

export default LogsPage;
