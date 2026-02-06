/**
 * Tests for useResults hook.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useResults } from '../../../src/js/hooks/useResults.js';

describe( 'useResults', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'initialises with default state', () => {
		// Prevent the mount effect from resolving during this sync check.
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		expect( result.current.type ).toBe( 'unused' );
		expect( result.current.page ).toBe( 1 );
		expect( result.current.perPage ).toBe( 20 );
		expect( result.current.results ).toEqual( [] );
		expect( result.current.selected ).toEqual( [] );
		expect( result.current.error ).toBeNull();
	} );

	it( 'accepts a custom initial type', () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults( 'oversized' ) );

		expect( result.current.type ).toBe( 'oversized' );
	} );

	it( 'fetches results on mount for non-duplicate type', async () => {
		const fakeResponse = {
			json: vi.fn().mockResolvedValue( {
				items: [
					{ id: 1, title: 'Image A' },
					{ id: 2, title: 'Image B' },
				],
				total: 2,
				page: 1,
				per_page: 20,
				total_pages: 1,
			} ),
			headers: new Headers(),
		};
		apiFetch.mockResolvedValue( fakeResponse );

		const { result } = renderHook( () => useResults( 'unused' ) );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		expect( result.current.results ).toHaveLength( 2 );
		expect( result.current.total ).toBe( 2 );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				parse: false,
			} )
		);
	} );

	it( 'fetches duplicate groups when type is duplicate', async () => {
		const groups = [
			{ hash: 'abc', primary: 1, members: [ { id: 1 }, { id: 2 } ] },
		];
		apiFetch.mockResolvedValue( groups );

		const { result } = renderHook( () => useResults( 'duplicate' ) );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		expect( result.current.duplicateGroups ).toEqual( groups );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/duplicates',
			} )
		);
	} );

	it( 'toggleSelected adds and removes ids', async () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		act( () => {
			result.current.toggleSelected( 5 );
		} );

		expect( result.current.selected ).toEqual( [ 5 ] );

		act( () => {
			result.current.toggleSelected( 10 );
		} );

		expect( result.current.selected ).toEqual( [ 5, 10 ] );

		// Toggle off.
		act( () => {
			result.current.toggleSelected( 5 );
		} );

		expect( result.current.selected ).toEqual( [ 10 ] );
	} );

	it( 'selectAll selects all result ids', async () => {
		const fakeResponse = {
			json: vi.fn().mockResolvedValue( {
				items: [
					{ id: 1, title: 'A' },
					{ id: 2, title: 'B' },
					{ id: 3, title: 'C' },
				],
				total: 3,
				page: 1,
				per_page: 20,
				total_pages: 1,
			} ),
			headers: new Headers(),
		};
		apiFetch.mockResolvedValue( fakeResponse );

		const { result } = renderHook( () => useResults() );

		await waitFor( () => {
			expect( result.current.results ).toHaveLength( 3 );
		} );

		act( () => {
			result.current.selectAll();
		} );

		expect( result.current.selected ).toEqual( [ 1, 2, 3 ] );
	} );

	it( 'clearSelection empties selected', async () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		act( () => {
			result.current.toggleSelected( 1 );
			result.current.toggleSelected( 2 );
		} );

		act( () => {
			result.current.clearSelection();
		} );

		expect( result.current.selected ).toEqual( [] );
	} );

	it( 'setType updates the type', async () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		act( () => {
			result.current.setType( 'oversized' );
		} );

		expect( result.current.type ).toBe( 'oversized' );
	} );

	it( 'setPage updates the page', async () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		act( () => {
			result.current.setPage( 3 );
		} );

		expect( result.current.page ).toBe( 3 );
	} );

	it( 'performAction calls the api and refreshes results', async () => {
		const fakeResponse = {
			json: vi.fn().mockResolvedValue( {
				items: [ { id: 1, title: 'A' } ],
				total: 1,
				page: 1,
				per_page: 20,
				total_pages: 1,
			} ),
			headers: new Headers(),
		};
		apiFetch.mockResolvedValue( fakeResponse );

		const { result } = renderHook( () => useResults( 'unused' ) );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		apiFetch.mockResolvedValueOnce( { count: 1 } ); // action response
		apiFetch.mockResolvedValueOnce( fakeResponse ); // refresh

		await act( async () => {
			await result.current.performAction( 'flag', [ 1 ] );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/actions/flag',
				method: 'POST',
			} )
		);
	} );

	it( 'performAction sets error on failure', async () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useResults() );

		apiFetch.mockRejectedValueOnce( new Error( 'Forbidden' ) );

		await act( async () => {
			try {
				await result.current.performAction( 'trash', [ 1 ] );
			} catch {
				// Expected.
			}
		} );

		expect( result.current.error ).toBe( 'Forbidden' );
	} );

	it( 'sets error when fetch fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network error' ) );

		const { result } = renderHook( () => useResults() );

		await waitFor( () => {
			expect( result.current.error ).toBe( 'Network error' );
		} );
	} );
} );
