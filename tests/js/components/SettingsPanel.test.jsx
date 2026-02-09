/**
 * Tests for SettingsPanel component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import { SettingsPanel } from '../../../src/js/components/SettingsPanel.jsx';

const defaultSettings = {
	oversized_threshold_image: 2097152,
	oversized_threshold_video: 104857600,
	oversized_threshold_audio: 20971520,
	oversized_threshold_document: 10485760,
	archive_folder_name: 'Archive',
	scan_batch_size: 100,
	content_scan_depth: 'full',
	auto_scan_on_upload: false,
	protected_attachment_ids: [],
};

describe( 'SettingsPanel', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		apiFetch.mockResolvedValue( defaultSettings );
	} );

	it( 'shows spinner while loading', () => {
		// Never resolve so it stays in loading state.
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		render( <SettingsPanel /> );

		expect( screen.getByTestId( 'spinner' ) ).toBeInTheDocument();
	} );

	it( 'renders settings form after loading', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Images (MB)' ) ).toBeInTheDocument();
		} );

		expect( screen.getByLabelText( 'Videos (MB)' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Audio (MB)' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Documents (MB)' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Content scan depth' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Scan batch size' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( /Automatically scan new uploads/ ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Archive folder name' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Save Settings' ) ).toBeInTheDocument();
	} );

	it( 'displays threshold values in MB', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Images (MB)' ) ).toHaveValue( 2 );
		} );

		expect( screen.getByLabelText( 'Videos (MB)' ) ).toHaveValue( 100 );
		expect( screen.getByLabelText( 'Audio (MB)' ) ).toHaveValue( 20 );
		expect( screen.getByLabelText( 'Documents (MB)' ) ).toHaveValue( 10 );
	} );

	it( 'displays scan settings values', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Scan batch size' ) ).toHaveValue( 100 );
		} );

		expect( screen.getByLabelText( 'Content scan depth' ) ).toHaveValue( 'full' );
		expect( screen.getByLabelText( /Automatically scan new uploads/ ) ).not.toBeChecked();
	} );

	it( 'displays archive folder name', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Archive folder name' ) ).toHaveValue( 'Archive' );
		} );
	} );

	it( 'updates threshold field on change', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Images (MB)' ) ).toBeInTheDocument();
		} );

		fireEvent.change( screen.getByLabelText( 'Images (MB)' ), {
			target: { value: '5' },
		} );

		expect( screen.getByLabelText( 'Images (MB)' ) ).toHaveValue( 5 );
	} );

	it( 'updates content scan depth on change', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Content scan depth' ) ).toBeInTheDocument();
		} );

		fireEvent.change( screen.getByLabelText( 'Content scan depth' ), {
			target: { value: 'featured_only' },
		} );

		expect( screen.getByLabelText( 'Content scan depth' ) ).toHaveValue( 'featured_only' );
	} );

	it( 'toggles auto scan checkbox', async () => {
		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByLabelText( /Automatically scan new uploads/ ) ).toBeInTheDocument();
		} );

		fireEvent.click( screen.getByLabelText( /Automatically scan new uploads/ ) );

		expect( screen.getByLabelText( /Automatically scan new uploads/ ) ).toBeChecked();
	} );

	it( 'saves settings on form submit', async () => {
		apiFetch
			.mockResolvedValueOnce( defaultSettings )
			.mockResolvedValueOnce( defaultSettings );

		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'Save Settings' ) ).toBeInTheDocument();
		} );

		fireEvent.click( screen.getByText( 'Save Settings' ) );

		await waitFor( () => {
			expect( screen.getByText( 'Settings saved.' ) ).toBeInTheDocument();
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/vmfa-cleanup/v1/settings',
				method: 'POST',
			} )
		);
	} );

	it( 'shows error notice when fetch fails', async () => {
		apiFetch.mockRejectedValueOnce( new Error( 'Network error' ) );

		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'Network error' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows error notice when save fails', async () => {
		apiFetch
			.mockResolvedValueOnce( defaultSettings )
			.mockRejectedValueOnce( new Error( 'Save failed' ) );

		render( <SettingsPanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'Save Settings' ) ).toBeInTheDocument();
		} );

		fireEvent.click( screen.getByText( 'Save Settings' ) );

		await waitFor( () => {
			expect( screen.getByText( 'Save failed' ) ).toBeInTheDocument();
		} );
	} );
} );
