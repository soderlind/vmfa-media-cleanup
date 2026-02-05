/**
 * Tests for ScanProgress component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ScanProgress } from '../../../src/js/components/ScanProgress';

const baseScan = {
	status: 'idle',
	phase: '',
	progress: { processed: 0, total: 0 },
	error: null,
	startScan: vi.fn(),
	cancelScan: vi.fn(),
	resetScan: vi.fn(),
};

describe( 'ScanProgress', () => {
	it( 'shows Start Scan button when idle', () => {
		render( <ScanProgress scan={ baseScan } /> );

		expect( screen.getByText( 'Start Scan' ) ).toBeInTheDocument();
	} );

	it( 'calls startScan when Start Scan is clicked', () => {
		const startScan = vi.fn();
		render(
			<ScanProgress scan={ { ...baseScan, startScan } } />
		);

		fireEvent.click( screen.getByText( 'Start Scan' ) );

		expect( startScan ).toHaveBeenCalledOnce();
	} );

	it( 'shows Cancel button when running', () => {
		render(
			<ScanProgress
				scan={ { ...baseScan, status: 'running', phase: 'indexing' } }
			/>
		);

		expect( screen.getByText( 'Cancel' ) ).toBeInTheDocument();
	} );

	it( 'calls cancelScan on Cancel click', () => {
		const cancelScan = vi.fn();
		render(
			<ScanProgress
				scan={ {
					...baseScan,
					status: 'running',
					phase: 'indexing',
					cancelScan,
				} }
			/>
		);

		fireEvent.click( screen.getByText( 'Cancel' ) );

		expect( cancelScan ).toHaveBeenCalledOnce();
	} );

	it( 'shows progress bar with correct percentage when running', () => {
		render(
			<ScanProgress
				scan={ {
					...baseScan,
					status: 'running',
					phase: 'hashing',
					progress: { processed: 50, total: 200 },
				} }
			/>
		);

		const bar = screen.getByRole( 'progressbar' );
		expect( bar ).toHaveAttribute( 'aria-valuenow', '25' );
		const detail = document.querySelector( '.vmfa-cleanup-scan__detail' );
		expect( detail.textContent ).toContain( '50' );
		expect( detail.textContent ).toContain( '200' );
	} );

	it( 'displays phase label when running', () => {
		render(
			<ScanProgress
				scan={ {
					...baseScan,
					status: 'running',
					phase: 'detecting',
					progress: { processed: 1, total: 10 },
				} }
			/>
		);

		expect(
			screen.getByText( 'Running detectors…' )
		).toBeInTheDocument();
	} );

	it( 'shows success notice when done', () => {
		render(
			<ScanProgress
				scan={ { ...baseScan, status: 'done', phase: 'done' } }
			/>
		);

		expect(
			screen.getByText( 'Scan completed successfully.' )
		).toBeInTheDocument();
	} );

	it( 'shows Re-scan button when done', () => {
		render(
			<ScanProgress
				scan={ { ...baseScan, status: 'done', phase: 'done' } }
			/>
		);

		expect( screen.getByText( 'Re-scan' ) ).toBeInTheDocument();
	} );

	it( 'shows Reset button when done', () => {
		const resetScan = vi.fn();
		render(
			<ScanProgress
				scan={ { ...baseScan, status: 'done', phase: 'done', resetScan } }
			/>
		);

		fireEvent.click( screen.getByText( 'Reset' ) );

		expect( resetScan ).toHaveBeenCalledOnce();
	} );

	it( 'shows warning notice when cancelled', () => {
		render(
			<ScanProgress
				scan={ { ...baseScan, status: 'cancelled' } }
			/>
		);

		expect(
			screen.getByText( 'Scan was cancelled.' )
		).toBeInTheDocument();
	} );

	it( 'shows error notice when error is set', () => {
		render(
			<ScanProgress
				scan={ { ...baseScan, error: 'Something failed' } }
			/>
		);

		expect( screen.getByText( 'Something failed' ) ).toBeInTheDocument();
	} );
} );
