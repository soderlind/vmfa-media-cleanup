/**
 * ResultsPanel — Display scan results with filters and bulk actions.
 *
 * @package VmfaMediaCleanup
 */

import { __ } from '@wordpress/i18n';
import { Spinner, SelectControl } from '@wordpress/components';
import { MediaItemRow } from './MediaItemRow';
import { DuplicateGroup } from './DuplicateGroup';
import { BulkActionBar } from './BulkActionBar';

/**
 * Results panel component.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.results Results state from useResults hook.
 * @return {JSX.Element}         Results UI.
 */
export function ResultsPanel( { results } ) {
	const {
		results: items,
		total,
		page,
		perPage,
		type,
		loading,
		error,
		selected,
		duplicateGroups,
		setPage,
		setType,
		performAction,
		toggleSelected,
		selectAll,
		clearSelection,
	} = results;

	const totalPages = Math.ceil( total / perPage );

	const typeOptions = [
		{ label: __( 'Unused', 'vmfa-media-cleanup' ), value: 'unused' },
		{ label: __( 'Duplicates', 'vmfa-media-cleanup' ), value: 'duplicate' },
		{ label: __( 'Oversized', 'vmfa-media-cleanup' ), value: 'oversized' },
		{ label: __( 'Flagged', 'vmfa-media-cleanup' ), value: 'flagged' },
	];

	return (
		<div className="vmfa-cleanup-results">
			<div className="vmfa-cleanup-results__toolbar">
				<SelectControl
					label={ __( 'Filter by type', 'vmfa-media-cleanup' ) }
					value={ type }
					options={ typeOptions }
					onChange={ ( value ) => {
						setType( value );
						clearSelection();
					} }
					__nextHasNoMarginBottom
				/>

				{ selected.length > 0 && (
					<BulkActionBar
						selected={ selected }
						type={ type }
						onAction={ performAction }
						onClearSelection={ clearSelection }
					/>
				) }
			</div>

			{ loading && (
				<div className="vmfa-cleanup-results__loading">
					<Spinner />
				</div>
			) }

			{ error && (
				<div className="vmfa-cleanup-results__error">
					{ error }
				</div>
			) }

			{ ! loading && ! error && type === 'duplicate' && (
				<div className="vmfa-cleanup-results__groups">
					{ duplicateGroups.length === 0 ? (
						<p className="vmfa-cleanup-results__empty">
							{ __( 'No duplicate groups found.', 'vmfa-media-cleanup' ) }
						</p>
					) : (
						duplicateGroups.map( ( group ) => (
							<DuplicateGroup
								key={ group.hash }
								group={ group }
								onAction={ performAction }
							/>
						) )
					) }
				</div>
			) }

			{ ! loading && ! error && type !== 'duplicate' && (
				<>
					{ items.length === 0 ? (
						<p className="vmfa-cleanup-results__empty">
							{ __( 'No items found.', 'vmfa-media-cleanup' ) }
						</p>
					) : (
						<div className="vmfa-cleanup-results__list">
							<div className="vmfa-cleanup-results__list-header">
								<label className="vmfa-cleanup-results__select-all">
									<input
										type="checkbox"
										checked={
											selected.length > 0 &&
											selected.length === items.length
										}
										onChange={ () =>
											selected.length === items.length
												? clearSelection()
												: selectAll()
										}
									/>
									{ __( 'Select all', 'vmfa-media-cleanup' ) }
								</label>
								<span className="vmfa-cleanup-results__total">
									{ total }{ ' ' }
									{ __( 'item(s)', 'vmfa-media-cleanup' ) }
								</span>
							</div>

							{ items.map( ( item ) => (
								<MediaItemRow
									key={ item.id || item.attachment_id }
									item={ item }
									type={ type }
									isSelected={ selected.includes(
										item.id || item.attachment_id
									) }
									onToggle={ () =>
										toggleSelected(
											item.id || item.attachment_id
										)
									}
									onAction={ performAction }
								/>
							) ) }
						</div>
					) }

					{ totalPages > 1 && (
						<div className="vmfa-cleanup-results__pagination">
							<button
								className="button"
								disabled={ page <= 1 }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __( '← Previous', 'vmfa-media-cleanup' ) }
							</button>
							<span>
								{ page } / { totalPages }
							</span>
							<button
								className="button"
								disabled={ page >= totalPages }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Next →', 'vmfa-media-cleanup' ) }
							</button>
						</div>
					) }
				</>
			) }
		</div>
	);
}
