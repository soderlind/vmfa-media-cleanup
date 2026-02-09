/**
 * Tests for useSettings hook.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useSettings } from '../../../src/js/hooks/useSettings.js';

const defaultSettings = {
	archive_folder_name: 'Archive',
	scan_batch_size: 100,
	content_scan_depth: 'full',
	auto_scan_on_upload: false,
	protected_attachment_ids: [],
};

describe( 'useSettings', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'starts in loading state', () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		const { result } = renderHook( () => useSettings() );

		expect( result.current.loading ).toBe( true );
		expect( result.current.settings ).toBeNull();
	} );

	it( 'fetches settings on mount', async () => {
		apiFetch.mockResolvedValue( defaultSettings );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		expect( result.current.settings ).toEqual( defaultSettings );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/vmfa-cleanup/v1/settings',
		} );
	} );

	it( 'sets error when fetch fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network error' ) );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		expect( result.current.error ).toBe( 'Network error' );
		expect( result.current.settings ).toBeNull();
	} );

	it( 'updateField updates a single field', async () => {
		apiFetch.mockResolvedValue( defaultSettings );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		act( () => {
			result.current.updateField( 'scan_batch_size', 200 );
		} );

		expect( result.current.settings.scan_batch_size ).toBe( 200 );
	} );

	it( 'updateField clears saved flag', async () => {
		apiFetch
			.mockResolvedValueOnce( defaultSettings )
			.mockResolvedValueOnce( defaultSettings );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		// Save first to set saved = true.
		await act( async () => {
			await result.current.saveSettings( defaultSettings );
		} );

		expect( result.current.saved ).toBe( true );

		// Update a field — should clear saved.
		act( () => {
			result.current.updateField( 'scan_batch_size', 50 );
		} );

		expect( result.current.saved ).toBe( false );
	} );

	it( 'saveSettings posts settings and sets saved flag', async () => {
		const updatedSettings = { ...defaultSettings, scan_batch_size: 200 };
		apiFetch
			.mockResolvedValueOnce( defaultSettings )
			.mockResolvedValueOnce( updatedSettings );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		await act( async () => {
			await result.current.saveSettings( updatedSettings );
		} );

		expect( result.current.saved ).toBe( true );
		expect( result.current.saving ).toBe( false );
		expect( result.current.settings ).toEqual( updatedSettings );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/vmfa-cleanup/v1/settings',
			method: 'POST',
			data: updatedSettings,
		} );
	} );

	it( 'sets error when save fails', async () => {
		apiFetch
			.mockResolvedValueOnce( defaultSettings )
			.mockRejectedValueOnce( new Error( 'Save failed' ) );

		const { result } = renderHook( () => useSettings() );

		await waitFor( () => {
			expect( result.current.loading ).toBe( false );
		} );

		await act( async () => {
			await result.current.saveSettings( defaultSettings );
		} );

		expect( result.current.error ).toBe( 'Save failed' );
		expect( result.current.saving ).toBe( false );
		expect( result.current.saved ).toBe( false );
	} );
} );
