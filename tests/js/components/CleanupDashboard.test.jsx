/**
 * Tests for CleanupDashboard component.
 *
 * Tab navigation is now handled by PHP (nav-tab-wrapper).
 * The component reads activeSubtab from window.vmfaMediaCleanup.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
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

/**
 * Helper to set the active subtab via window global.
 *
 * @param {string} subtab - The subtab to activate.
 */
function setActiveSubtab( subtab ) {
	window.vmfaMediaCleanup = { activeSubtab: subtab };
}

describe( 'CleanupDashboard', () => {
	beforeEach( () => {
		useScanStatus.mockReturnValue( baseScan );
		useResults.mockReturnValue( baseResultsState );
		useSettings.mockReturnValue( baseSettingsState );
		// Default to scan tab.
		setActiveSubtab( 'scan' );
	} );

	afterEach( () => {
		delete window.vmfaMediaCleanup;
	} );

	it( 'renders the dashboard with tabpanel role', () => {
		render( <CleanupDashboard /> );

		expect( screen.getByRole( 'tabpanel' ) ).toBeInTheDocument();
	} );

	it( 'renders scan content by default', () => {
		render( <CleanupDashboard /> );

		// ScanProgress renders a Start Scan button when idle.
		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'defaults to scan tab when no activeSubtab is set', () => {
		delete window.vmfaMediaCleanup;

		render( <CleanupDashboard /> );

		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'calls setType and clearSelection when unused tab is active', () => {
		const setType = vi.fn();
		const clearSelection = vi.fn();
		useResults.mockReturnValue( { ...baseResultsState, setType, clearSelection } );
		setActiveSubtab( 'unused' );

		render( <CleanupDashboard /> );

		expect( setType ).toHaveBeenCalledWith( 'unused' );
		expect( clearSelection ).toHaveBeenCalledOnce();
	} );

	it( 'shows scan panel content when scan tab is active', () => {
		setActiveSubtab( 'scan' );

		render( <CleanupDashboard /> );

		// ScanProgress renders a Start Scan button when idle.
		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'shows scan-required message when unused tab is active without scan', () => {
		setActiveSubtab( 'unused' );

		render( <CleanupDashboard /> );

		expect( screen.getByText( 'Run a scan first to detect items.' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: /Start Scan/i } ) ).toBeInTheDocument();
	} );

	it( 'calls startScan when Start Scan button is clicked on result tab', () => {
		const startScan = vi.fn();
		useScanStatus.mockReturnValue( { ...baseScan, startScan } );
		setActiveSubtab( 'unused' );

		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByRole( 'button', { name: /Start Scan/i } ) );

		expect( startScan ).toHaveBeenCalledOnce();
	} );

	it( 'shows results panel when scan is complete on unused tab', () => {
		useScanStatus.mockReturnValue( { ...baseScan, status: 'complete' } );
		setActiveSubtab( 'unused' );

		render( <CleanupDashboard /> );

		expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Run a scan first to detect items.' ) ).not.toBeInTheDocument();
	} );

	it( 'shows trash results without requiring a scan', () => {
		setActiveSubtab( 'trash' );

		render( <CleanupDashboard /> );

		expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Run a scan first to detect items.' ) ).not.toBeInTheDocument();
	} );

	it( 'calls setType with trash when trash tab is active', () => {
		const setType = vi.fn();
		const clearSelection = vi.fn();
		useResults.mockReturnValue( { ...baseResultsState, setType, clearSelection } );
		setActiveSubtab( 'trash' );

		render( <CleanupDashboard /> );

		expect( setType ).toHaveBeenCalledWith( 'trash' );
		expect( clearSelection ).toHaveBeenCalledOnce();
	} );

	it( 'displays stats when available', () => {
		useScanStatus.mockReturnValue( {
			...baseScan,
			stats: { unused_count: 5, duplicate_count: 3 },
		} );

		render( <CleanupDashboard /> );

		expect( screen.getByText( '5' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument();
	} );

	it( 'does not display stats section when stats is null', () => {
		render( <CleanupDashboard /> );

		expect( screen.queryByText( 'Unused:' ) ).not.toBeInTheDocument();
	} );

	it( 'shows settings panel when settings tab is active', () => {
		setActiveSubtab( 'settings' );

		render( <CleanupDashboard /> );

		// SettingsPanel shows loading state initially.
		expect( screen.getByRole( 'tabpanel' ) ).toBeInTheDocument();
	} );
} );
