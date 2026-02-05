/**
 * MediaItemRow — Single media item in results list.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { archive, trash, starEmpty } from '@wordpress/icons';
import { ConfirmModal } from './ConfirmModal';

/**
 * Format bytes to human-readable size.
 *
 * @param {number} bytes File size in bytes.
 * @return {string} Formatted size.
 */
function formatSize( bytes ) {
	if ( ! bytes ) return '—';
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let i = 0;
	let size = bytes;
	while ( size >= 1024 && i < units.length - 1 ) {
		size /= 1024;
		i++;
	}
	return `${ size.toFixed( 1 ) } ${ units[ i ] }`;
}

/**
 * Media item row component.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.item       Media item data.
 * @param {string}   props.type       Issue type.
 * @param {boolean}  props.isSelected Whether item is selected.
 * @param {Function} props.onToggle   Toggle selection callback.
 * @param {Function} props.onAction   Action callback.
 * @return {JSX.Element}              Media item row.
 */
export function MediaItemRow( {
	item,
	type,
	isSelected,
	onToggle,
	onAction,
} ) {
	const [ confirmAction, setConfirmAction ] = useState( null );
	const id = item.id || item.attachment_id;

	const handleAction = ( action ) => {
		if ( action === 'trash' ) {
			setConfirmAction( action );
		} else {
			onAction( action, [ id ] );
		}
	};

	return (
		<div
			className={ `vmfa-cleanup-item ${
				isSelected ? 'is-selected' : ''
			}` }
		>
			<div className="vmfa-cleanup-item__checkbox">
				<input
					type="checkbox"
					checked={ isSelected }
					onChange={ onToggle }
					aria-label={ __( 'Select item', 'vmfa-media-cleanup' ) }
				/>
			</div>

			<div className="vmfa-cleanup-item__thumbnail">
				{ item.thumbnail_url ? (
					<img
						src={ item.thumbnail_url }
						alt={ item.title || '' }
						loading="lazy"
					/>
				) : (
					<div className="vmfa-cleanup-item__placeholder" />
				) }
			</div>

			<div className="vmfa-cleanup-item__info">
				<div className="vmfa-cleanup-item__title">
					<a
						href={ `${ window.vmfaMediaCleanup?.adminUrl || '/wp-admin/' }post.php?post=${ id }&action=edit` }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ item.title || `#${ id }` }
					</a>
				</div>
				<div className="vmfa-cleanup-item__meta">
					{ item.mime_type && (
						<span>{ item.mime_type }</span>
					) }
					{ item.file_size > 0 && (
						<span>{ formatSize( item.file_size ) }</span>
					) }
					{ item.dimensions && (
						<span>{ item.dimensions }</span>
					) }
					{ type === 'oversized' && item.over_by > 0 && (
						<span className="vmfa-cleanup-item__over">
							{ __( 'Over by:', 'vmfa-media-cleanup' ) }{ ' ' }
							{ formatSize( item.over_by ) }
						</span>
					) }
				</div>
			</div>

			<div className="vmfa-cleanup-item__actions">
				<Button
					icon={ starEmpty }
					label={ __( 'Flag for review', 'vmfa-media-cleanup' ) }
					size="small"
					onClick={ () => handleAction( 'flag' ) }
				/>
				<Button
					icon={ archive }
					label={ __( 'Archive', 'vmfa-media-cleanup' ) }
					size="small"
					onClick={ () => handleAction( 'archive' ) }
				/>
				<Button
					icon={ trash }
					label={ __( 'Trash', 'vmfa-media-cleanup' ) }
					size="small"
					isDestructive
					onClick={ () => handleAction( 'trash' ) }
				/>
			</div>

			{ confirmAction && (
				<ConfirmModal
					action={ confirmAction }
					count={ 1 }
					onConfirm={ () => {
						onAction( confirmAction, [ id ] );
						setConfirmAction( null );
					} }
					onCancel={ () => setConfirmAction( null ) }
				/>
			) }
		</div>
	);
}
