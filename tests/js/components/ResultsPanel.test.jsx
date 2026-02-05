/**
 * Tests for ResultsPanel component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ResultsPanel } from '../../../src/js/components/ResultsPanel';

const baseResults = {
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

describe( 'ResultsPanel', () => {
	it( 'shows empty message when no results', () => {
		render( <ResultsPanel results={ baseResults } /> );

		expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
	} );

	it( 'shows spinner when loading', () => {
		render(
			<ResultsPanel results={ { ...baseResults, loading: true } } />
		);

		expect( screen.getByTestId( 'spinner' ) ).toBeInTheDocument();
	} );

	it( 'shows error message', () => {
		render(
			<ResultsPanel
				results={ { ...baseResults, error: 'Fetch failed' } }
			/>
		);

		expect( screen.getByText( 'Fetch failed' ) ).toBeInTheDocument();
	} );

	it( 'renders media items when results exist', () => {
		const items = [
			{ id: 1, title: 'Image A', mime_type: 'image/png' },
			{ id: 2, title: 'Image B', mime_type: 'image/jpeg' },
		];

		render(
			<ResultsPanel
				results={ { ...baseResults, results: items, total: 2 } }
			/>
		);

		expect( screen.getByText( 'Image A' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Image B' ) ).toBeInTheDocument();
	} );

	it( 'shows total count', () => {
		const items = [ { id: 1, title: 'A' } ];

		render(
			<ResultsPanel
				results={ { ...baseResults, results: items, total: 1 } }
			/>
		);

		expect( screen.getByText( /1/ ) ).toBeInTheDocument();
		expect( screen.getByText( /item\(s\)/ ) ).toBeInTheDocument();
	} );

	it( 'renders type filter select', () => {
		render( <ResultsPanel results={ baseResults } /> );

		const select = screen.getByRole( 'combobox' );
		expect( select ).toBeInTheDocument();
		expect( select.value ).toBe( 'unused' );
	} );

	it( 'calls setType and clearSelection when filter changes', () => {
		const setType = vi.fn();
		const clearSelection = vi.fn();

		render(
			<ResultsPanel
				results={ { ...baseResults, setType, clearSelection } }
			/>
		);

		fireEvent.change( screen.getByRole( 'combobox' ), {
			target: { value: 'oversized' },
		} );

		expect( setType ).toHaveBeenCalledWith( 'oversized' );
		expect( clearSelection ).toHaveBeenCalledOnce();
	} );

	it( 'shows duplicate groups empty message for duplicate type', () => {
		render(
			<ResultsPanel
				results={ {
					...baseResults,
					type: 'duplicate',
					duplicateGroups: [],
				} }
			/>
		);

		expect(
			screen.getByText( 'No duplicate groups found.' )
		).toBeInTheDocument();
	} );

	it( 'shows pagination when more than one page', () => {
		const items = [ { id: 1, title: 'A' } ];

		render(
			<ResultsPanel
				results={ {
					...baseResults,
					results: items,
					total: 50,
					perPage: 20,
					page: 1,
				} }
			/>
		);

		expect( screen.getByText( '1 / 3' ) ).toBeInTheDocument();
		expect( screen.getByText( '← Previous' ) ).toBeDisabled();
		expect( screen.getByText( 'Next →' ) ).not.toBeDisabled();
	} );

	it( 'calls setPage on pagination click', () => {
		const setPage = vi.fn();
		const items = [ { id: 1, title: 'A' } ];

		render(
			<ResultsPanel
				results={ {
					...baseResults,
					results: items,
					total: 50,
					perPage: 20,
					page: 1,
					setPage,
				} }
			/>
		);

		fireEvent.click( screen.getByText( 'Next →' ) );

		expect( setPage ).toHaveBeenCalledWith( 2 );
	} );

	it( 'shows select-all checkbox', () => {
		const items = [
			{ id: 1, title: 'A' },
			{ id: 2, title: 'B' },
		];

		render(
			<ResultsPanel
				results={ { ...baseResults, results: items, total: 2 } }
			/>
		);

		expect( screen.getByText( 'Select all' ) ).toBeInTheDocument();
	} );

	it( 'shows bulk action bar when items are selected', () => {
		const items = [ { id: 1, title: 'A' } ];

		render(
			<ResultsPanel
				results={ {
					...baseResults,
					results: items,
					total: 1,
					selected: [ 1 ],
				} }
			/>
		);

		expect( screen.getByText( /selected/ ) ).toBeInTheDocument();
	} );
} );
