/**
 * Tests for useScanStatus hook.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useScanStatus } from '../../../src/js/hooks/useScanStatus.js';

describe( 'useScanStatus', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'returns idle status initially after mount fetch', async () => {
		apiFetch.mockResolvedValue( {
			status: 'idle',
			phase: '',
			processed: 0,
			total: 0,
		} );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		expect( result.current.status ).toBe( 'idle' );
		expect( result.current.phase ).toBe( '' );
		expect( result.current.progress ).toEqual( {
			processed: 0,
			total: 0,
		} );
		expect( result.current.error ).toBeNull();
	} );

	it( 'fetches status on mount', async () => {
		apiFetch.mockResolvedValue( { status: 'idle' } );

		await act( async () => {
			renderHook( () => useScanStatus() );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/scan/status',
			} )
		);
	} );

	it( 'fetches stats on mount', async () => {
		apiFetch.mockResolvedValue( { status: 'idle' } );

		await act( async () => {
			renderHook( () => useScanStatus() );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/stats',
			} )
		);
	} );

	it( 'startScan posts to scan endpoint', async () => {
		apiFetch.mockResolvedValue( { status: 'idle' } );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		apiFetch.mockResolvedValueOnce( { success: true } );

		await act( async () => {
			await result.current.startScan();
		} );

		expect( result.current.status ).toBe( 'running' );
		expect( result.current.phase ).toBe( 'indexing' );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/scan',
				method: 'POST',
			} )
		);
	} );

	it( 'startScan sets error on failure', async () => {
		apiFetch.mockResolvedValue( { status: 'idle' } );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		apiFetch.mockRejectedValueOnce( new Error( 'Network error' ) );

		await act( async () => {
			await result.current.startScan();
		} );

		expect( result.current.error ).toBe( 'Network error' );
		expect( result.current.status ).toBe( 'idle' );
	} );

	it( 'cancelScan posts to cancel endpoint', async () => {
		apiFetch.mockResolvedValue( {
			status: 'running',
			phase: 'indexing',
			processed: 5,
			total: 100,
		} );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		apiFetch.mockResolvedValueOnce( { success: true } );

		await act( async () => {
			await result.current.cancelScan();
		} );

		expect( result.current.status ).toBe( 'cancelled' );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/scan/cancel',
				method: 'POST',
			} )
		);
	} );

	it( 'resetScan posts to reset endpoint and clears state', async () => {
		apiFetch.mockResolvedValue( {
			status: 'done',
			phase: 'done',
			processed: 100,
			total: 100,
		} );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		apiFetch.mockResolvedValueOnce( { success: true } );

		await act( async () => {
			await result.current.resetScan();
		} );

		expect( result.current.status ).toBe( 'idle' );
		expect( result.current.phase ).toBe( '' );
		expect( result.current.progress ).toEqual( {
			processed: 0,
			total: 0,
		} );
		expect( result.current.stats ).toBeNull();
	} );

	it( 'sets error when status fetch fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Server down' ) );

		let result;
		await act( async () => {
			( { result } = renderHook( () => useScanStatus() ) );
		} );

		expect( result.current.error ).toBe( 'Server down' );
	} );
} );
