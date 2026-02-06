/**
 * Hook: useSettings — Fetches and manages plugin settings.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useCallback, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Custom hook for fetching and managing plugin settings.
 *
 * @return {Object} Settings state and controls.
 */
export function useSettings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ saved, setSaved ] = useState( false );

	const fetchSettings = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await apiFetch( {
				path: '/vmfa-cleanup/v1/settings',
			} );
			setSettings( data );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch settings.' );
		} finally {
			setLoading( false );
		}
	}, [] );

	const saveSettings = useCallback( async ( updatedSettings ) => {
		setSaving( true );
		setError( null );
		setSaved( false );

		try {
			const data = await apiFetch( {
				path: '/vmfa-cleanup/v1/settings',
				method: 'POST',
				data: updatedSettings,
			} );
			setSettings( data );
			setSaved( true );
		} catch ( err ) {
			setError( err.message || 'Failed to save settings.' );
		} finally {
			setSaving( false );
		}
	}, [] );

	const updateField = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaved( false );
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return {
		settings,
		loading,
		saving,
		error,
		saved,
		saveSettings,
		updateField,
	};
}
