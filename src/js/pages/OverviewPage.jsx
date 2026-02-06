/**
 * Overview Page Component for Media Cleanup.
 *
 * Displays add-on description, KPI stats, and feature overview.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { StatsCard } from '@vmfo/shared';

/**
 * Overview Page component.
 *
 * @return {JSX.Element} The overview page content.
 */
export function OverviewPage() {
	const [ stats, setStats ] = useState( null );
	const [ loading, setLoading ] = useState( true );

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
			// Ignore fetch errors; stats may still show loading.
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

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
		: [
				{
					label: __( 'Total Media', 'vmfa-media-cleanup' ),
					isLoading: loading,
				},
				{
					label: __( 'Unused', 'vmfa-media-cleanup' ),
					isLoading: loading,
				},
				{
					label: __( 'Duplicates', 'vmfa-media-cleanup' ),
					isLoading: loading,
				},
				{
					label: __( 'Oversized', 'vmfa-media-cleanup' ),
					isLoading: loading,
				},
		  ];

	return (
		<>
			<StatsCard stats={ kpiStats } />

			<Card className="vmfo-overview-card">
				<CardHeader>
					<h3>{ __( 'About Media Cleanup', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Media Cleanup helps you identify and manage unused, duplicate, and oversized media files in your WordPress library. Keep your media library clean and optimized.',
							'vmfa-media-cleanup'
						) }
					</p>
				</CardBody>
			</Card>

			<Card className="vmfo-overview-card">
				<CardHeader>
					<h3>{ __( 'Features', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<ul className="vmfo-feature-list">
						<li>
							<strong>{ __( 'Unused Detection', 'vmfa-media-cleanup' ) }</strong>
							<p>{ __( 'Find media files not used in posts, pages, or site options.', 'vmfa-media-cleanup' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Duplicate Finder', 'vmfa-media-cleanup' ) }</strong>
							<p>{ __( 'Identify duplicate files by content hash to save storage space.', 'vmfa-media-cleanup' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Oversized Files', 'vmfa-media-cleanup' ) }</strong>
							<p>{ __( 'Flag images, videos, and documents that exceed size thresholds.', 'vmfa-media-cleanup' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Safe Deletion', 'vmfa-media-cleanup' ) }</strong>
							<p>{ __( 'Move files to trash for review before permanent deletion.', 'vmfa-media-cleanup' ) }</p>
						</li>
					</ul>
				</CardBody>
			</Card>

			<Card className="vmfo-overview-card">
				<CardHeader>
					<h3>{ __( 'Getting Started', 'vmfa-media-cleanup' ) }</h3>
				</CardHeader>
				<CardBody>
					<ol>
						<li>{ __( 'Go to the Configure tab to set size thresholds', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Run a scan from the Actions tab', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Review results in the Dashboard tab', 'vmfa-media-cleanup' ) }</li>
						<li>{ __( 'Take action on flagged items', 'vmfa-media-cleanup' ) }</li>
					</ol>
				</CardBody>
			</Card>
		</>
	);
}

export default OverviewPage;
