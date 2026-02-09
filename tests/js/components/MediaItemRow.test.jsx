/**
 * Tests for MediaItemRow component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MediaItemRow } from '../../../src/js/components/MediaItemRow';

const baseItem = {
	id: 42,
	title: 'Test Image',
	mime_type: 'image/jpeg',
	file_size: 1048576,
	thumbnail_url: 'https://example.com/thumb.jpg',
};

describe( 'MediaItemRow', () => {
	it( 'renders item title as a link', () => {
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		const link = screen.getByText( 'Test Image' );
		expect( link.closest( 'a' ) ).toHaveAttribute(
			'href',
			'/wp-admin/post.php?post=42&action=edit'
		);
	} );

	it( 'renders fallback title when title is empty', () => {
		render(
			<MediaItemRow
				item={ { ...baseItem, title: '' } }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect( screen.getByText( '#42' ) ).toBeInTheDocument();
	} );

	it( 'renders mime type and formatted file size', () => {
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'image/jpeg' ) ).toBeInTheDocument();
		expect( screen.getByText( '1.0 MB' ) ).toBeInTheDocument();
	} );

	it( 'has a checkbox that reflects isSelected', () => {
		const { rerender } = render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		const checkbox = screen.getByRole( 'checkbox' );
		expect( checkbox ).not.toBeChecked();

		rerender(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ true }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect( checkbox ).toBeChecked();
	} );

	it( 'calls onToggle when checkbox changes', () => {
		const onToggle = vi.fn();
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ onToggle }
				onAction={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByRole( 'checkbox' ) );

		expect( onToggle ).toHaveBeenCalledOnce();
	} );

	it( 'calls onAction with flag and shows filled star after flagging', () => {
		const onAction = vi.fn();
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ onAction }
			/>
		);

		// Initially shows "Flag for review" (unflagged).
		const flagBtn = screen.getByLabelText( 'Flag for review' );
		fireEvent.click( flagBtn );

		expect( onAction ).toHaveBeenCalledWith( 'flag', [ 42 ] );

		// After flagging, button label changes to "Unflag".
		expect( screen.getByLabelText( 'Unflag' ) ).toBeInTheDocument();
	} );

	it( 'shows filled star and Unflag label when item is already flagged', () => {
		render(
			<MediaItemRow
				item={ { ...baseItem, is_flagged: true } }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect( screen.getByLabelText( 'Unflag' ) ).toBeInTheDocument();
		expect( screen.queryByLabelText( 'Flag for review' ) ).not.toBeInTheDocument();
	} );

	it( 'calls onAction with unflag when clicking a flagged item', () => {
		const onAction = vi.fn();
		render(
			<MediaItemRow
				item={ { ...baseItem, is_flagged: true } }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ onAction }
			/>
		);

		fireEvent.click( screen.getByLabelText( 'Unflag' ) );

		expect( onAction ).toHaveBeenCalledWith( 'unflag', [ 42 ] );
		// After unflagging, shows "Flag for review" again.
		expect( screen.getByLabelText( 'Flag for review' ) ).toBeInTheDocument();
	} );

	it( 'calls onAction immediately for archive', () => {
		const onAction = vi.fn();
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ onAction }
			/>
		);

		fireEvent.click( screen.getByLabelText( 'Archive' ) );

		expect( onAction ).toHaveBeenCalledWith( 'archive', [ 42 ] );
	} );

	it( 'shows confirm modal for trash action', () => {
		render(
			<MediaItemRow
				item={ baseItem }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByLabelText( 'Trash' ) );

		// ConfirmModal should appear.
		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Move to Trash' ) ).toBeInTheDocument();
	} );

	it( 'renders placeholder when no thumbnail', () => {
		render(
			<MediaItemRow
				item={ { ...baseItem, thumbnail_url: null } }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect(
			document.querySelector( '.vmfa-cleanup-item__placeholder' )
		).toBeInTheDocument();
	} );

	it( 'uses attachment_id when id is missing', () => {
		const onAction = vi.fn();
		render(
			<MediaItemRow
				item={ { attachment_id: 99, title: 'Fallback' } }
				type="unused"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ onAction }
			/>
		);

		fireEvent.click( screen.getByLabelText( 'Flag for review' ) );

		expect( onAction ).toHaveBeenCalledWith( 'flag', [ 99 ] );
	} );

	it( 'shows restore and delete buttons for trash type', () => {
		render(
			<MediaItemRow
				item={ baseItem }
				type="trash"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		expect( screen.getByLabelText( 'Restore' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Delete permanently' ) ).toBeInTheDocument();
		// Normal actions should not appear.
		expect( screen.queryByLabelText( 'Archive' ) ).not.toBeInTheDocument();
		expect( screen.queryByLabelText( 'Flag for review' ) ).not.toBeInTheDocument();
	} );

	it( 'shows confirm modal for delete permanently action', () => {
		render(
			<MediaItemRow
				item={ baseItem }
				type="trash"
				isSelected={ false }
				onToggle={ vi.fn() }
				onAction={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByLabelText( 'Delete permanently' ) );

		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Delete permanently' ) ).toBeInTheDocument();
	} );
} );
