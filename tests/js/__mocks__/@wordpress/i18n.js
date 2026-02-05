/**
 * Mock for @wordpress/i18n — pass-through.
 */

export const __ = ( text ) => text;
export const _x = ( text ) => text;
export const _n = ( single, plural, count ) =>
	count === 1 ? single : plural;
export const sprintf = ( fmt, ...args ) => {
	let i = 0;
	return fmt.replace( /%[sd]/g, () => args[ i++ ] );
};
