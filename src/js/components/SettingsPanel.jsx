/**
 * SettingsPanel — Plugin settings UI.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useSettings } from '../hooks/useSettings';

/**
 * Format bytes to a human-readable MB value for display.
 *
 * @param {number} bytes Byte value.
 * @return {string}      Value in MB.
 */
function bytesToMB( bytes ) {
	return String( Math.round( bytes / ( 1024 * 1024 ) ) );
}

/**
 * Parse MB string input to bytes.
 *
 * @param {string} mb MB value string.
 * @return {number}   Value in bytes.
 */
function mbToBytes( mb ) {
	const num = parseInt( mb, 10 );
	return isNaN( num ) || num < 0 ? 0 : num * 1024 * 1024;
}

/**
 * Settings panel component.
 *
 * @return {JSX.Element} Settings UI.
 */
export function SettingsPanel() {
	const {
		settings,
		loading,
		saving,
		error,
		saved,
		saveSettings,
		updateField,
	} = useSettings();

	if ( loading ) {
		return (
			<div className="vmfa-cleanup-settings__loading">
				<Spinner />
			</div>
		);
	}

	if ( error && ! settings ) {
		return (
			<div className="vmfa-cleanup-settings">
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			</div>
		);
	}

	if ( ! settings ) {
		return null;
	}

	const handleSubmit = ( e ) => {
		e.preventDefault();
		saveSettings( settings );
	};

	return (
		<div className="vmfa-cleanup-settings">
			<form onSubmit={ handleSubmit }>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ saved && (
					<Notice status="success" isDismissible={ false }>
						{ __( 'Settings saved.', 'vmfa-media-cleanup' ) }
					</Notice>
				) }

				<div className="vmfa-cleanup-settings__section">
					<h3 className="vmfa-cleanup-settings__heading">
						{ __( 'Oversized Thresholds', 'vmfa-media-cleanup' ) }
					</h3>
					<p className="vmfa-cleanup-settings__description">
						{ __( 'Files exceeding these sizes will be flagged as oversized.', 'vmfa-media-cleanup' ) }
					</p>

					<div className="vmfa-cleanup-settings__grid">
						<div className="vmfa-cleanup-settings__field">
							<label htmlFor="vmfa-threshold-image">
								{ __( 'Images (MB)', 'vmfa-media-cleanup' ) }
							</label>
							<input
								id="vmfa-threshold-image"
								type="number"
								min="1"
								value={ bytesToMB( settings.oversized_threshold_image ) }
								onChange={ ( e ) =>
									updateField( 'oversized_threshold_image', mbToBytes( e.target.value ) )
								}
							/>
						</div>

						<div className="vmfa-cleanup-settings__field">
							<label htmlFor="vmfa-threshold-video">
								{ __( 'Videos (MB)', 'vmfa-media-cleanup' ) }
							</label>
							<input
								id="vmfa-threshold-video"
								type="number"
								min="1"
								value={ bytesToMB( settings.oversized_threshold_video ) }
								onChange={ ( e ) =>
									updateField( 'oversized_threshold_video', mbToBytes( e.target.value ) )
								}
							/>
						</div>

						<div className="vmfa-cleanup-settings__field">
							<label htmlFor="vmfa-threshold-audio">
								{ __( 'Audio (MB)', 'vmfa-media-cleanup' ) }
							</label>
							<input
								id="vmfa-threshold-audio"
								type="number"
								min="1"
								value={ bytesToMB( settings.oversized_threshold_audio ) }
								onChange={ ( e ) =>
									updateField( 'oversized_threshold_audio', mbToBytes( e.target.value ) )
								}
							/>
						</div>

						<div className="vmfa-cleanup-settings__field">
							<label htmlFor="vmfa-threshold-document">
								{ __( 'Documents (MB)', 'vmfa-media-cleanup' ) }
							</label>
							<input
								id="vmfa-threshold-document"
								type="number"
								min="1"
								value={ bytesToMB( settings.oversized_threshold_document ) }
								onChange={ ( e ) =>
									updateField( 'oversized_threshold_document', mbToBytes( e.target.value ) )
								}
							/>
						</div>
					</div>
				</div>

				<div className="vmfa-cleanup-settings__section">
					<h3 className="vmfa-cleanup-settings__heading">
						{ __( 'Scan Settings', 'vmfa-media-cleanup' ) }
					</h3>

					<div className="vmfa-cleanup-settings__field">
						<label htmlFor="vmfa-scan-depth">
							{ __( 'Content scan depth', 'vmfa-media-cleanup' ) }
						</label>
						<select
							id="vmfa-scan-depth"
							value={ settings.content_scan_depth }
							onChange={ ( e ) => updateField( 'content_scan_depth', e.target.value ) }
						>
							<option value="full">
								{ __( 'Full (post content + featured images)', 'vmfa-media-cleanup' ) }
							</option>
							<option value="featured_only">
								{ __( 'Featured images only', 'vmfa-media-cleanup' ) }
							</option>
							<option value="none">
								{ __( 'None (skip content scanning)', 'vmfa-media-cleanup' ) }
							</option>
						</select>
					</div>

					<div className="vmfa-cleanup-settings__field">
						<label htmlFor="vmfa-batch-size">
							{ __( 'Scan batch size', 'vmfa-media-cleanup' ) }
						</label>
						<input
							id="vmfa-batch-size"
							type="number"
							min="10"
							max="500"
							value={ settings.scan_batch_size }
							onChange={ ( e ) =>
								updateField( 'scan_batch_size', parseInt( e.target.value, 10 ) || 100 )
							}
						/>
					</div>

					<div className="vmfa-cleanup-settings__field vmfa-cleanup-settings__field--checkbox">
						<label htmlFor="vmfa-auto-scan">
							<input
								id="vmfa-auto-scan"
								type="checkbox"
								checked={ settings.auto_scan_on_upload }
								onChange={ ( e ) =>
									updateField( 'auto_scan_on_upload', e.target.checked )
								}
							/>
							{ __( 'Automatically scan new uploads', 'vmfa-media-cleanup' ) }
						</label>
					</div>
				</div>

				<div className="vmfa-cleanup-settings__section">
					<h3 className="vmfa-cleanup-settings__heading">
						{ __( 'Archive', 'vmfa-media-cleanup' ) }
					</h3>

					<div className="vmfa-cleanup-settings__field">
						<label htmlFor="vmfa-archive-folder">
							{ __( 'Archive folder name', 'vmfa-media-cleanup' ) }
						</label>
						<input
							id="vmfa-archive-folder"
							type="text"
							value={ settings.archive_folder_name }
							onChange={ ( e ) =>
								updateField( 'archive_folder_name', e.target.value )
							}
						/>
					</div>
				</div>

				<div className="vmfa-cleanup-settings__actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ saving }
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'vmfa-media-cleanup' )
							: __( 'Save Settings', 'vmfa-media-cleanup' )
						}
					</Button>
				</div>
			</form>
		</div>
	);
}
