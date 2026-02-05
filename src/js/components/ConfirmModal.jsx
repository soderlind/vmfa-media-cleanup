/**
 * ConfirmModal — Confirmation dialog for destructive actions.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button } from '@wordpress/components';

/**
 * Confirmation modal component.
 *
 * @param {Object}   props           Component props.
 * @param {string}   props.action    The action to confirm (archive, trash).
 * @param {number}   props.count     Number of items affected.
 * @param {Function} props.onConfirm Confirm callback.
 * @param {Function} props.onCancel  Cancel callback.
 * @return {JSX.Element}             Confirmation modal.
 */
export function ConfirmModal( { action, count, onConfirm, onCancel } ) {
	const actionLabels = {
		trash: __( 'Move to Trash', 'vmfa-media-cleanup' ),
		archive: __( 'Archive', 'vmfa-media-cleanup' ),
	};

	const messages = {
		trash: __( 'Are you sure you want to move %d item(s) to trash? This can be undone from the WordPress trash.', 'vmfa-media-cleanup' ).replace( '%d', count ),
		archive: __( 'Are you sure you want to archive %d item(s)? They will be moved to the Archive folder.', 'vmfa-media-cleanup' ).replace( '%d', count ),
	};

	return (
		<Modal
			title={ __( 'Confirm Action', 'vmfa-media-cleanup' ) }
			onRequestClose={ onCancel }
			size="small"
		>
			<p>{ messages[ action ] || __( 'Are you sure?', 'vmfa-media-cleanup' ) }</p>

			<div className="vmfa-cleanup-confirm__actions">
				<Button variant="secondary" onClick={ onCancel }>
					{ __( 'Cancel', 'vmfa-media-cleanup' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ action === 'trash' }
					onClick={ onConfirm }
				>
					{ actionLabels[ action ] || __( 'Confirm', 'vmfa-media-cleanup' ) }
				</Button>
			</div>
		</Modal>
	);
}
