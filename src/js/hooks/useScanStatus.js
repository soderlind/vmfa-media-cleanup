/**
 * Hook: useScanStatus — Polls scan progress from REST API.
 *
 * @package VmfaMediaCleanup
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const POLL_INTERVAL = 2000;

/**
 * Custom hook for tracking scan progress.
 *
 * @return {Object} Scan state and controls.
 */
export function useScanStatus() {
	const [ status, setStatus ] = useState( 'idle' );
	const [ phase, setPhase ] = useState( '' );
	const [ progress, setProgress ] = useState( { processed: 0, total: 0 } );
	const [ error, setError ] = useState( null );
	const [ stats, setStats ] = useState( null );
	const intervalRef = useRef( null );

	const fetchStatus = useCallback( async () => {
		try {
			const data = await apiFetch( {
				path: '/vmfa-cleanup/v1/scan/status',
			} );

			setStatus( data.status );
			setPhase( data.phase || '' );
			setProgress( {
				processed: data.processed || 0,
				total: data.total || 0,
			} );

			// Stop polling when scan is complete or idle.
			if ( data.status === 'complete' || data.status === 'idle' ) {
				stopPolling();
				fetchStats();
			}
		} catch ( err ) {
			setError( err.message || 'Failed to fetch scan status.' );
			stopPolling();
		}
	}, [] );

	const fetchStats = useCallback( async () => {
		try {
			const data = await apiFetch( {
				path: '/vmfa-cleanup/v1/stats',
			} );
			setStats( data );
		} catch {
			// Stats are non-critical.
		}
	}, [] );

	const startPolling = useCallback( () => {
		stopPolling();
		intervalRef.current = setInterval( fetchStatus, POLL_INTERVAL );
	}, [ fetchStatus ] );

	const stopPolling = useCallback( () => {
		if ( intervalRef.current ) {
			clearInterval( intervalRef.current );
			intervalRef.current = null;
		}
	}, [] );

	const startScan = useCallback( async () => {
		setError( null );
		setStatus( 'running' );
		setPhase( 'indexing' );
		setProgress( { processed: 0, total: 0 } );

		try {
			await apiFetch( {
				path: '/vmfa-cleanup/v1/scan',
				method: 'POST',
			} );
			startPolling();
		} catch ( err ) {
			setError( err.message || 'Failed to start scan.' );
			setStatus( 'idle' );
		}
	}, [ startPolling ] );

	const cancelScan = useCallback( async () => {
		try {
			await apiFetch( {
				path: '/vmfa-cleanup/v1/scan/cancel',
				method: 'POST',
			} );
			stopPolling();
			setStatus( 'cancelled' );
		} catch ( err ) {
			setError( err.message || 'Failed to cancel scan.' );
		}
	}, [ stopPolling ] );

	const resetScan = useCallback( async () => {
		try {
			await apiFetch( {
				path: '/vmfa-cleanup/v1/scan/reset',
				method: 'POST',
			} );
			setStatus( 'idle' );
			setPhase( '' );
			setProgress( { processed: 0, total: 0 } );
			setStats( null );
		} catch ( err ) {
			setError( err.message || 'Failed to reset scan.' );
		}
	}, [] );

	// Initial fetch.
	useEffect( () => {
		fetchStatus();
		fetchStats();

		return () => stopPolling();
	}, [] );

	// If on mount status is "running", start polling.
	useEffect( () => {
		if ( status === 'running' ) {
			startPolling();
		}
	}, [ status === 'running' ] );

	return {
		status,
		phase,
		progress,
		error,
		stats,
		startScan,
		cancelScan,
		resetScan,
	};
}
