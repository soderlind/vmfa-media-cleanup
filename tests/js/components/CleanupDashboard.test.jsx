/**
 * Tests for CleanupDashboard component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

// Mock the hooks before importing the component.
vi.mock( '../../../src/js/hooks/useScanStatus.js', () => ( {
	useScanStatus: vi.fn(),
} ) );

vi.mock( '../../../src/js/hooks/useResults.js', () => ( {
	useResults: vi.fn(),
} ) );

vi.mock( '../../../src/js/hooks/useSettings.js', () => ( {
	useSettings: vi.fn(),
} ) );

import { CleanupDashboard } from '../../../src/js/components/CleanupDashboard.jsx';
import { useScanStatus } from '../../../src/js/hooks/useScanStatus.js';
import { useResults } from '../../../src/js/hooks/useResults.js';
import { useSettings } from '../../../src/js/hooks/useSettings.js';

const baseScan = {
	status: 'idle',
	phase: '',
	progress: { processed: 0, total: 0 },
	error: null,
	stats: null,
	startScan: vi.fn(),
	cancelScan: vi.fn(),
	resetScan: vi.fn(),
};

const baseResultsState = {
	results: [],
	total: 0,
	page: 1,
	perPage: 20,
	type: 'unused',
	loading: false,
	error: null,
	selected: [],
	duplicateGroups: [],
	setPage: vi.fn(),
	setType: vi.fn(),
	performAction: vi.fn(),
	toggleSelected: vi.fn(),
	selectAll: vi.fn(),
	clearSelection: vi.fn(),
};

const baseSettingsState = {
	settings: null,
	loading: true,
	saving: false,
	error: null,
	saved: false,
	saveSettings: vi.fn(),
	updateField: vi.fn(),
};

describe( 'CleanupDashboard', () => {
	beforeEach( () => {
		useScanStatus.mockReturnValue( baseScan );
		useResults.mockReturnValue( baseResultsState );
		useSettings.mockReturnValue( baseSettingsState );
	} );

	it( 'renders the dashboard tabs', () => {
		render( <CleanupDashboard /> );

		expect( screen.getByRole( 'tablist' ) ).toBeInTheDocument();
	} );

	it( 'renders seven tab buttons', () => {
		render( <CleanupDashboard /> );

		expect( screen.getByText( 'Scan' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Unused' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Duplicates' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Oversized' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Flagged' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Trash' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Settings' ) ).toBeInTheDocument();
	} );

	it( 'shows scan tab as active by default', () => {
		render( <CleanupDashboard /> );

		const scanTab = screen.getByText( 'Scan' );
		expect( scanTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	it( 'switches to unused tab on click and calls setType', () => {
		const setType = vi.fn();
		const clearSelection = vi.fn();
		useResults.mockReturnValue( { ...baseResultsState, setType, clearSelection } );

		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Unused' ) );

		expect( screen.getByText( 'Unused' ) ).toHaveAttribute( 'aria-selected', 'true' );
		expect( setType ).toHaveBeenCalledWith( 'unused' );
		expect( clearSelection ).toHaveBeenCalledOnce();
	} );

	it( 'shows scan panel content when scan tab is active', () => {
		render( <CleanupDashboard /> );

		// ScanProgress renders a Start Scan button when idle.
		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'shows scan-required message when a result tab is clicked without scan', () => {
		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Unused' ) );

		expect( screen.getByText( 'Run a scan first to detect items.' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: /Start Scan/i } ) ).toBeInTheDocument();
	} );

	it( 'starts scan and navigates to scan tab when Start Scan is clicked', () => {
		const startScan = vi.fn();
		useScanStatus.mockReturnValue( { ...baseScan, startScan } );

		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Unused' ) );
		fireEvent.click( screen.getByRole( 'button', { name: /Start Scan/i } ) );

		expect( startScan ).toHaveBeenCalledOnce();
		expect( screen.getByText( 'Scan' ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	it( 'shows results panel when scan is complete', () => {
		useScanStatus.mockReturnValue( { ...baseScan, status: 'complete' } );

		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Unused' ) );

		expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Run a scan first to detect items.' ) ).not.toBeInTheDocument();
	} );

	it( 'shows trash results without requiring a scan', () => {
		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Trash' ) );

		expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Run a scan first to detect items.' ) ).not.toBeInTheDocument();
	} );

	it( 'switches to trash tab and calls setType', () => {
		const setType = vi.fn();
		const clearSelection = vi.fn();
		useResults.mockReturnValue( { ...baseResultsState, setType, clearSelection } );

		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Trash' ) );

		expect( screen.getByText( 'Trash' ) ).toHaveAttribute( 'aria-selected', 'true' );
		expect( setType ).toHaveBeenCalledWith( 'trash' );
		expect( clearSelection ).toHaveBeenCalledOnce();
	} );

	it( 'displays stats when available', () => {
		useScanStatus.mockReturnValue( {
			...baseScan,
			stats: { unused_count: 5, duplicate_count: 3, oversized_count: 1 },
		} );

		render( <CleanupDashboard /> );

		expect( screen.getByText( '5' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument();
		expect( screen.getByText( '1' ) ).toBeInTheDocument();
	} );

	it( 'does not display stats section when stats is null', () => {
		render( <CleanupDashboard /> );

		expect( screen.queryByText( 'Unused:' ) ).not.toBeInTheDocument();
	} );

	it( 'switches to settings tab and shows settings panel', () => {
		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Settings' ) );

		expect( screen.getByText( 'Settings' ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );
