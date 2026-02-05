/**
 * Tests for ConfirmModal component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ConfirmModal } from '../../../src/js/components/ConfirmModal';

describe( 'ConfirmModal', () => {
	it( 'renders with trash action text', () => {
		render(
			<ConfirmModal
				action="trash"
				count={ 3 }
				onConfirm={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect(
			screen.getByText( /move 3 item\(s\) to trash/i )
		).toBeInTheDocument();
		expect( screen.getByText( 'Move to Trash' ) ).toBeInTheDocument();
	} );

	it( 'renders with archive action text', () => {
		render(
			<ConfirmModal
				action="archive"
				count={ 1 }
				onConfirm={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect(
			screen.getByText( /archive 1 item\(s\)/i )
		).toBeInTheDocument();
		expect( screen.getByText( 'Archive' ) ).toBeInTheDocument();
	} );

	it( 'calls onConfirm when confirm button is clicked', () => {
		const onConfirm = vi.fn();
		render(
			<ConfirmModal
				action="trash"
				count={ 1 }
				onConfirm={ onConfirm }
				onCancel={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByText( 'Move to Trash' ) );

		expect( onConfirm ).toHaveBeenCalledOnce();
	} );

	it( 'calls onCancel when cancel button is clicked', () => {
		const onCancel = vi.fn();
		render(
			<ConfirmModal
				action="trash"
				count={ 1 }
				onConfirm={ vi.fn() }
				onCancel={ onCancel }
			/>
		);

		fireEvent.click( screen.getByText( 'Cancel' ) );

		expect( onCancel ).toHaveBeenCalledOnce();
	} );

	it( 'shows generic confirm for unknown action', () => {
		render(
			<ConfirmModal
				action="unknown"
				count={ 1 }
				onConfirm={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Are you sure?' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Confirm' ) ).toBeInTheDocument();
	} );
} );
