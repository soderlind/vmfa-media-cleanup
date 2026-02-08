/**
 * DuplicateGroup — Grouped display of duplicate media items.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { starFilled, starEmpty, trash, info } from '@wordpress/icons';
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
 * Duplicate group component.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.group    Group data with hash, primary, members.
 * @param {Function} props.onAction Action callback.
 * @return {JSX.Element}            Duplicate group UI.
 */
export function DuplicateGroup( { group, onAction } ) {
	const [ confirmTrash, setConfirmTrash ] = useState( null );
	const getId = ( m ) => m.id ?? m.attachment_id;
	const groupIds = group.members.map( getId );
	const primaryId = group.primary ?? group.members.find( ( m ) => m.is_primary )?.attachment_id ?? groupIds[ 0 ];

	const handleSetPrimary = async ( id ) => {
		try {
			await onAction( 'set-primary', [ id ], {
				id,
				group_ids: groupIds,
			} );
		} catch {
			// Error handled by parent.
		}
	};

	const handleTrashNonPrimary = () => {
		const nonPrimary = group.members
			.filter( ( m ) => getId( m ) !== primaryId );
		const ids = nonPrimary.map( getId );

		if ( ids.length === 0 ) {
			return;
		}

		const inUse = nonPrimary.filter(
			( m ) => ( m.reference_count ?? 0 ) > 0
		);

		setConfirmTrash( {
			ids,
			inUseItems: inUse.map( ( m ) => ( {
				title: m.title || `#${ getId( m ) }`,
				referenceCount: m.reference_count,
			} ) ),
		} );
	};

	return (
		<div className="vmfa-cleanup-dup-group">
			<div className="vmfa-cleanup-dup-group__header">
				<span className="vmfa-cleanup-dup-group__hash">
					{ __( 'Hash:', 'vmfa-media-cleanup' ) }{ ' ' }
					{ group.hash?.substring( 0, 12 ) }…
				</span>
				<span className="vmfa-cleanup-dup-group__count">
					{ group.members.length }{ ' ' }
					{ __( 'copies', 'vmfa-media-cleanup' ) }
				</span>
				<Button
					variant="secondary"
					isDestructive
					icon={ trash }
					size="small"
					onClick={ handleTrashNonPrimary }
				>
					{ __( 'Trash non-primary', 'vmfa-media-cleanup' ) }
				</Button>
			</div>

			<div className="vmfa-cleanup-dup-group__members">
				{ group.members.map( ( member ) => {
					const memberId = getId( member );
					const isPrimary = memberId === primaryId;
					const isTrashed = !! member.is_trashed;

					return (
						<div
							key={ memberId }
							className={ `vmfa-cleanup-dup-member ${
								isPrimary ? 'is-primary' : ''
							}${ isTrashed ? ' is-trashed' : '' }` }
						>
							<div className="vmfa-cleanup-dup-member__thumb">
								{ member.thumbnail_url ? (
									<img
										src={ member.thumbnail_url }
										alt={ member.title || '' }
										loading="lazy"
									/>
								) : (
									<div className="vmfa-cleanup-item__placeholder" />
								) }
							</div>

							<div className="vmfa-cleanup-dup-member__info">
								<div className="vmfa-cleanup-dup-member__title">
									<a
											href={ `${ window.vmfaMediaCleanup?.adminUrl || '/wp-admin/' }post.php?post=${ memberId }&action=edit` }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ member.title || `#${ memberId }` }
									</a>
									{ isPrimary && (
										<span className="vmfa-cleanup-dup-member__badge">
											{ __( 'Primary', 'vmfa-media-cleanup' ) }
										</span>
									) }
									{ isTrashed && (
										<span className="vmfa-cleanup-dup-member__badge vmfa-cleanup-dup-member__badge--trashed">
											{ __( 'Trashed', 'vmfa-media-cleanup' ) }
										</span>
									) }
								</div>
								<div className="vmfa-cleanup-dup-member__meta">
									{ member.file_size > 0 && (
										<span>
											{ formatSize( member.file_size ) }
										</span>
									) }
									{ member.upload_date && (
										<span>{ member.upload_date }</span>
										) }									{ ( member.reference_count ?? 0 ) > 0 && (() => {
									/* translators: %d: number of posts referencing this media */
									const usedIn = __( 'Used in %d post(s)', 'vmfa-media-cleanup' ).replace( '%d', member.reference_count );
									return (
										<span className="vmfa-cleanup-dup-member__refs">
											{ usedIn }
										</span>
									);
								})() }								</div>
							</div>

							<div className="vmfa-cleanup-dup-member__actions">
								<Button
									icon={
										isPrimary ? starFilled : starEmpty
									}
									label={
										isPrimary
											? __( 'Primary copy', 'vmfa-media-cleanup' )
											: __( 'Set as primary', 'vmfa-media-cleanup' )
									}
									size="small"
									disabled={ isPrimary }
									onClick={ () =>
										handleSetPrimary( memberId )
									}
								/>
							</div>
						</div>
					);
				} ) }
			</div>

			{ confirmTrash && (
				<ConfirmModal
					action="trash"
					count={ confirmTrash.ids.length }
					warning={
						confirmTrash.inUseItems.length > 0
							? confirmTrash.inUseItems
							: null
					}
					onConfirm={ () => {
						onAction( 'trash', confirmTrash.ids );
						setConfirmTrash( null );
					} }
					onCancel={ () => setConfirmTrash( null ) }
				/>
			) }
		</div>
	);
}
