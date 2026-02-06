/**
 * Hook: useResults — Fetches and manages scan results.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useCallback, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Custom hook for fetching and managing scan results.
 *
 * @param {string} initialType Initial filter type.
 * @return {Object} Results state and controls.
 */
export function useResults( initialType = 'unused' ) {
	const [ results, setResults ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 20 );
	const [ type, setType ] = useState( initialType );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ duplicateGroups, setDuplicateGroups ] = useState( [] );

	const fetchResults = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const params = new URLSearchParams( {
				type,
				page: String( page ),
				per_page: String( perPage ),
			} );

			const data = await apiFetch( {
				path: `/vmfa-cleanup/v1/results?${ params }`,
				parse: false,
			} );

			const json = await data.json();

			setResults( json.items ?? json );
			setTotal( json.total ?? ( json.items ? json.items.length : json.length ) );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch results.' );
		} finally {
			setLoading( false );
		}
	}, [ type, page, perPage ] );

	const fetchDuplicateGroups = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await apiFetch( {
				path: '/vmfa-cleanup/v1/duplicates',
			} );
			setDuplicateGroups( data.groups ?? data );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch duplicate groups.' );
		} finally {
			setLoading( false );
		}
	}, [] );

	const performAction = useCallback(
		async ( action, ids, extra = {} ) => {
			try {
				const data = await apiFetch( {
					path: `/vmfa-cleanup/v1/actions/${ action }`,
					method: 'POST',
					data: { ids, confirm: true, ...extra },
				} );

				// Refresh results after action.
				if ( type === 'duplicate' ) {
					await fetchDuplicateGroups();
				} else {
					await fetchResults();
				}

				// Clear selection.
				setSelected( [] );

				return data;
			} catch ( err ) {
				setError( err.message || `Failed to ${ action } items.` );
				throw err;
			}
		},
		[ type, fetchResults, fetchDuplicateGroups ]
	);

	const toggleSelected = useCallback( ( id ) => {
		setSelected( ( prev ) =>
			prev.includes( id )
				? prev.filter( ( i ) => i !== id )
				: [ ...prev, id ]
		);
	}, [] );

	const selectAll = useCallback( () => {
		setSelected( results.map( ( r ) => r.id || r.attachment_id ) );
	}, [ results ] );

	const clearSelection = useCallback( () => {
		setSelected( [] );
	}, [] );

	// Fetch when type or page changes.
	useEffect( () => {
		if ( type === 'duplicate' ) {
			fetchDuplicateGroups();
		} else {
			fetchResults();
		}
	}, [ type, page, perPage ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return {
		results,
		total,
		page,
		perPage,
		type,
		loading,
		error,
		selected,
		duplicateGroups,
		setPage,
		setPerPage,
		setType,
		fetchResults,
		fetchDuplicateGroups,
		performAction,
		toggleSelected,
		selectAll,
		clearSelection,
	};
}
