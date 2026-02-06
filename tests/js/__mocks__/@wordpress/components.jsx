/**
 * Mock for @wordpress/components — lightweight stubs.
 */

import React from 'react';

export const Button = ( { children, onClick, label, ...rest } ) => (
	<button onClick={ onClick } aria-label={ label } { ...rest }>
		{ children }
	</button>
);

export const Notice = ( { children, status } ) => (
	<div data-testid="notice" data-status={ status }>
		{ children }
	</div>
);

export const Spinner = () => <div data-testid="spinner" />;

export const SelectControl = ( { label, value, options, onChange } ) => (
	<select
		aria-label={ label }
		value={ value }
		onChange={ ( e ) => onChange( e.target.value ) }
	>
		{ options.map( ( opt ) => (
			<option key={ opt.value } value={ opt.value }>
				{ opt.label }
			</option>
		) ) }
	</select>
);

export const Modal = ( { title, children, onRequestClose } ) => (
	<div data-testid="modal" role="dialog" aria-label={ title }>
		<button data-testid="modal-close" onClick={ onRequestClose } />
		{ children }
	</div>
);

export const ExternalLink = ( { href, children, ...rest } ) => (
	<a href={ href } target="_blank" rel="noopener noreferrer" { ...rest }>
		{ children }
	</a>
);

export const Card = ( { children, className } ) => (
	<div data-testid="card" className={ className }>
		{ children }
	</div>
);

export const CardBody = ( { children } ) => (
	<div data-testid="card-body">{ children }</div>
);
