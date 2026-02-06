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
 * @param {Object}        props           Component props.
 * @param {string}        props.action    The action to confirm (archive, trash).
 * @param {number}        props.count     Number of items affected.
 * @param {Array|null}    props.warning   Optional array of in-use items with { title, referenceCount }.
 * @param {Function}      props.onConfirm Confirm callback.
 * @param {Function}      props.onCancel  Cancel callback.
 * @return {JSX.Element}                  Confirmation modal.
 */
export function ConfirmModal( { action, count, warning = null, onConfirm, onCancel } ) {
	const actionLabels = {
		trash: __( 'Move to Trash', 'vmfa-media-cleanup' ),
		archive: __( 'Archive', 'vmfa-media-cleanup' ),
		delete: __( 'Delete permanently', 'vmfa-media-cleanup' ),
	};

	const messages = {
		/* translators: %d: number of items */
		trash: __( 'Are you sure you want to move %d item(s) to trash? This can be undone from the Trash tab.', 'vmfa-media-cleanup' ).replace( '%d', count ),
		/* translators: %d: number of items */
		archive: __( 'Are you sure you want to archive %d item(s)? They will be moved to the Archive folder.', 'vmfa-media-cleanup' ).replace( '%d', count ),
		/* translators: %d: number of items */
		delete: __( 'Are you sure you want to permanently delete %d item(s)? This cannot be undone.', 'vmfa-media-cleanup' ).replace( '%d', count ),
	};

	return (
		<Modal
			title={ __( 'Confirm Action', 'vmfa-media-cleanup' ) }
			onRequestClose={ onCancel }
			size="small"
		>
			<p>{ messages[ action ] || __( 'Are you sure?', 'vmfa-media-cleanup' ) }</p>

			{ warning && warning.length > 0 && (
				<div className="vmfa-cleanup-confirm__warning">
					<p>
						<strong>{ __( '⚠ Warning:', 'vmfa-media-cleanup' ) }</strong>{ ' ' }
						{ __( 'The following items are still used in posts. Trashing them will break those references.', 'vmfa-media-cleanup' ) }
					</p>
					<ul>
						{ warning.map( ( item, index ) => {
							/* translators: %d: number of references */
							const refs = __( '%d reference(s)', 'vmfa-media-cleanup' ).replace( '%d', item.referenceCount );
							return (
								<li key={ index }>
									{ item.title }{ ' — ' }{ refs }
								</li>
							);
						} ) }
					</ul>
				</div>
			) }

			<div className="vmfa-cleanup-confirm__actions">
				<Button variant="secondary" onClick={ onCancel }>
					{ __( 'Cancel', 'vmfa-media-cleanup' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ action === 'trash' || action === 'delete' }
					onClick={ onConfirm }
				>
					{ actionLabels[ action ] || __( 'Confirm', 'vmfa-media-cleanup' ) }
				</Button>
			</div>
		</Modal>
	);
}
