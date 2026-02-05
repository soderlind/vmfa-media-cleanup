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

import { CleanupDashboard } from '../../../src/js/components/CleanupDashboard.jsx';
import { useScanStatus } from '../../../src/js/hooks/useScanStatus.js';
import { useResults } from '../../../src/js/hooks/useResults.js';

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

describe( 'CleanupDashboard', () => {
	beforeEach( () => {
		useScanStatus.mockReturnValue( baseScan );
		useResults.mockReturnValue( baseResultsState );
	} );

	it( 'renders the dashboard heading', () => {
		render( <CleanupDashboard /> );

		expect( screen.getByText( 'Media Cleanup' ) ).toBeInTheDocument();
	} );

	it( 'renders two tab buttons', () => {
		render( <CleanupDashboard /> );

		expect( screen.getByText( 'Scan' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Results' ) ).toBeInTheDocument();
	} );

	it( 'shows scan tab as active by default', () => {
		render( <CleanupDashboard /> );

		const scanTab = screen.getByText( 'Scan' );
		expect( scanTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	it( 'switches to results tab on click', () => {
		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Results' ) );

		const resultsTab = screen.getByText( 'Results' );
		expect( resultsTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	it( 'shows scan panel content when scan tab is active', () => {
		render( <CleanupDashboard /> );

		// ScanProgress renders a Start Scan button when idle.
		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'shows results panel content when results tab is active', () => {
		render( <CleanupDashboard /> );

		fireEvent.click( screen.getByText( 'Results' ) );

		// ResultsPanel renders the type filter.
		expect( screen.getByRole( 'combobox' ) ).toBeInTheDocument();
	} );

	it( 'displays stats when available', () => {
		useScanStatus.mockReturnValue( {
			...baseScan,
			stats: { unused: 5, duplicate: 3, oversized: 1 },
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
} );
