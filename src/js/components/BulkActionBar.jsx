/**
 * BulkActionBar — Toolbar for bulk actions on selected items.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { archive, trash, starEmpty, backup } from '@wordpress/icons';
import { ConfirmModal } from './ConfirmModal';

/**
 * Bulk action toolbar component.
 *
 * @param {Object}   props                  Component props.
 * @param {number[]} props.selected         Selected item IDs.
 * @param {string}   props.type             Current filter type.
 * @param {Function} props.onAction         Action callback.
 * @param {Function} props.onClearSelection Clear selection callback.
 * @return {JSX.Element}                    Bulk action bar.
 */
export function BulkActionBar( {
	selected,
	type,
	onAction,
	onClearSelection,
} ) {
	const [ confirmAction, setConfirmAction ] = useState( null );
	const count = selected.length;

	const handleAction = ( action ) => {
		if ( action === 'trash' || action === 'archive' || action === 'delete' ) {
			setConfirmAction( action );
		} else {
			onAction( action, selected );
		}
	};

	return (
		<div className="vmfa-cleanup-bulk-bar">
			<span className="vmfa-cleanup-bulk-bar__count">
				{ count }{ ' ' }
				{ __( 'selected', 'vmfa-media-cleanup' ) }
			</span>

			{ type === 'trash' ? (
				<>
					<Button
						icon={ backup }
						size="compact"
						onClick={ () => handleAction( 'restore' ) }
					>
						{ __( 'Restore', 'vmfa-media-cleanup' ) }
					</Button>

					<Button
						icon={ trash }
						size="compact"
						isDestructive
						onClick={ () => handleAction( 'delete' ) }
					>
						{ __( 'Delete permanently', 'vmfa-media-cleanup' ) }
					</Button>
				</>
			) : (
				<>
					{ type !== 'flagged' && (
						<Button
							icon={ starEmpty }
							size="compact"
							onClick={ () => handleAction( 'flag' ) }
						>
							{ __( 'Flag', 'vmfa-media-cleanup' ) }
						</Button>
					) }

					{ type === 'flagged' && (
						<Button
							size="compact"
							onClick={ () => handleAction( 'unflag' ) }
						>
							{ __( 'Unflag', 'vmfa-media-cleanup' ) }
						</Button>
					) }

					<Button
						icon={ archive }
						size="compact"
						onClick={ () => handleAction( 'archive' ) }
					>
						{ __( 'Archive', 'vmfa-media-cleanup' ) }
					</Button>

					<Button
						icon={ trash }
						size="compact"
						isDestructive
						onClick={ () => handleAction( 'trash' ) }
					>
						{ __( 'Trash', 'vmfa-media-cleanup' ) }
					</Button>
				</>
			) }

			<Button
				variant="link"
				onClick={ onClearSelection }
			>
				{ __( 'Clear', 'vmfa-media-cleanup' ) }
			</Button>

			{ confirmAction && (
				<ConfirmModal
					action={ confirmAction }
					count={ count }
					onConfirm={ () => {
						onAction( confirmAction, selected );
						setConfirmAction( null );
					} }
					onCancel={ () => setConfirmAction( null ) }
				/>
			) }
		</div>
	);
}
