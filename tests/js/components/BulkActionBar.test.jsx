/**
 * Tests for BulkActionBar component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { BulkActionBar } from '../../../src/js/components/BulkActionBar';

describe( 'BulkActionBar', () => {
	it( 'shows selected count', () => {
		render(
			<BulkActionBar
				selected={ [ 1, 2, 3 ] }
				type="unused"
				onAction={ vi.fn() }
				onClearSelection={ vi.fn() }
			/>
		);

		expect( screen.getByText( /3/ ) ).toBeInTheDocument();
		expect( screen.getByText( /selected/ ) ).toBeInTheDocument();
	} );

	it( 'shows Flag button for non-flagged types', () => {
		render(
			<BulkActionBar
				selected={ [ 1 ] }
				type="unused"
				onAction={ vi.fn() }
				onClearSelection={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Flag' ) ).toBeInTheDocument();
	} );

	it( 'shows Unflag button for flagged type', () => {
		render(
			<BulkActionBar
				selected={ [ 1 ] }
				type="flagged"
				onAction={ vi.fn() }
				onClearSelection={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Unflag' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Flag' ) ).not.toBeInTheDocument();
	} );

	it( 'calls onAction directly for flag', () => {
		const onAction = vi.fn();
		render(
			<BulkActionBar
				selected={ [ 10, 20 ] }
				type="unused"
				onAction={ onAction }
				onClearSelection={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByText( 'Flag' ) );

		expect( onAction ).toHaveBeenCalledWith( 'flag', [ 10, 20 ] );
	} );

	it( 'shows confirm modal for trash', () => {
		render(
			<BulkActionBar
				selected={ [ 1, 2 ] }
				type="unused"
				onAction={ vi.fn() }
				onClearSelection={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByText( 'Trash' ) );

		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
	} );

	it( 'shows confirm modal for archive', () => {
		render(
			<BulkActionBar
				selected={ [ 1 ] }
				type="unused"
				onAction={ vi.fn() }
				onClearSelection={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByText( 'Archive' ) );

		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
	} );

	it( 'calls onClearSelection when Clear is clicked', () => {
		const onClearSelection = vi.fn();
		render(
			<BulkActionBar
				selected={ [ 1 ] }
				type="unused"
				onAction={ vi.fn() }
				onClearSelection={ onClearSelection }
			/>
		);

		fireEvent.click( screen.getByText( 'Clear' ) );

		expect( onClearSelection ).toHaveBeenCalledOnce();
	} );
} );
