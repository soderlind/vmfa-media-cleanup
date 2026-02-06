/**
 * Tests for DuplicateGroup component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { DuplicateGroup } from '../../../src/js/components/DuplicateGroup';

const baseGroup = {
	hash: 'abc123def456',
	primary: 1,
	members: [
		{
			id: 1,
			title: 'Original',
			file_size: 2048,
			upload_date: '2025-01-01',
			thumbnail_url: 'https://example.com/1.jpg',
			reference_count: 2,
		},
		{
			id: 2,
			title: 'Copy',
			file_size: 2048,
			upload_date: '2025-06-01',
			thumbnail_url: 'https://example.com/2.jpg',
			reference_count: 0,
		},
	],
};

describe( 'DuplicateGroup', () => {
	it( 'renders truncated hash', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		expect( screen.getByText( /abc123def456/i ) ).toBeInTheDocument();
	} );

	it( 'shows member count', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		const countEl = document.querySelector( '.vmfa-cleanup-dup-group__count' );
		expect( countEl.textContent ).toContain( '2' );
		expect( countEl.textContent ).toContain( 'copies' );
	} );

	it( 'marks the primary member with a badge', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		expect( screen.getByText( 'Primary' ) ).toBeInTheDocument();
	} );

	it( 'disables set-primary button for the primary member', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		const primaryButton = screen.getByLabelText( 'Primary copy' );
		expect( primaryButton ).toBeDisabled();
	} );

	it( 'enables set-primary button for non-primary members', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		const setButton = screen.getByLabelText( 'Set as primary' );
		expect( setButton ).not.toBeDisabled();
	} );

	it( 'calls onAction with set-primary when set-primary is clicked', async () => {
		const onAction = vi.fn().mockResolvedValue( {} );
		render( <DuplicateGroup group={ baseGroup } onAction={ onAction } /> );

		fireEvent.click( screen.getByLabelText( 'Set as primary' ) );

		expect( onAction ).toHaveBeenCalledWith(
			'set-primary',
			[ 2 ],
			{ id: 2, group_ids: [ 1, 2 ] }
		);
	} );

	it( 'shows confirm modal when Trash non-primary is clicked', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		fireEvent.click( screen.getByText( 'Trash non-primary' ) );

		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
	} );

	it( 'calls onAction with trash for non-primary ids on confirm', () => {
		const onAction = vi.fn();
		render( <DuplicateGroup group={ baseGroup } onAction={ onAction } /> );

		fireEvent.click( screen.getByText( 'Trash non-primary' ) );
		fireEvent.click( screen.getByText( 'Move to Trash' ) );

		expect( onAction ).toHaveBeenCalledWith( 'trash', [ 2 ] );
	} );

	it( 'renders member thumbnails', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		const images = screen.getAllByRole( 'img' );
		expect( images ).toHaveLength( 2 );
	} );

	it( 'renders member titles as links', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		expect( screen.getByText( 'Original' ).closest( 'a' ) ).toHaveAttribute(
			'href',
			'/wp-admin/post.php?post=1&action=edit'
		);
	} );

	it( 'shows reference count for members with references', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		expect( screen.getByText( 'Used in 2 post(s)' ) ).toBeInTheDocument();
	} );

	it( 'does not show reference count for members without references', () => {
		render( <DuplicateGroup group={ baseGroup } onAction={ vi.fn() } /> );

		const refTexts = screen.getAllByText( /Used in \d+ post/ );
		expect( refTexts ).toHaveLength( 1 );
	} );

	it( 'shows in-use warning when trashing referenced duplicates', () => {
		const groupWithInUse = {
			...baseGroup,
			members: [
				{ ...baseGroup.members[ 0 ] },
				{ ...baseGroup.members[ 1 ], reference_count: 3 },
			],
		};

		render( <DuplicateGroup group={ groupWithInUse } onAction={ vi.fn() } /> );

		fireEvent.click( screen.getByText( 'Trash non-primary' ) );

		expect( screen.getByText( /still used in posts/ ) ).toBeInTheDocument();
		expect( screen.getByText( /Copy — 3 reference/ ) ).toBeInTheDocument();
	} );

} );
