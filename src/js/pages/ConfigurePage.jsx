/**
 * Configure Page Component for Media Cleanup.
 *
 * Contains all settings - size thresholds, exclusions, etc.
 * No action buttons here - just configuration.
 *
 * @package VmfaMediaCleanup
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { SettingsPanel } from '../components/SettingsPanel';

/**
 * Configure Page component.
 *
 * @return {JSX.Element} The configure page content.
 */
export function ConfigurePage() {
	return (
		<>
			<Card className="vmfo-configure-card">
				<CardHeader>
					<h3>{ __( 'Cleanup Settings', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<SettingsPanel />
				</CardBody>
			</Card>

			<Card className="vmfo-configure-card vmfo-info-card">
				<CardHeader>
					<h3>{ __( 'About Settings', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Configure size thresholds for different file types. Files exceeding these thresholds will be flagged as oversized during scans.',
							'vmfa-media-cleanup'
						) }
					</p>
					<p>
						{ __(
							'Lower thresholds will flag more files, while higher thresholds are more permissive. Consider your site\'s needs when setting these values.',
							'vmfa-media-cleanup'
						) }
					</p>
				</CardBody>
			</Card>
		</>
	);
}

export default ConfigurePage;
