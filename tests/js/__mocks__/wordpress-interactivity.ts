/**
 * Mock for @wordpress/interactivity module.
 *
 * Provides lightweight mock implementations for the WordPress Interactivity API
 * that can be used in unit tests.
 */

/**
 * Mock store function that captures the namespace and configuration.
 */
export const store = jest.fn(
	( _namespace: string, config: unknown ) => config
);

/**
 * Mock getContext function that returns an empty context object by default.
 * Can be mocked per-test to return specific context values.
 */
export const getContext = jest.fn( () => ( {} ) );

/**
 * Mock getElement function that returns an element with null ref by default.
 * Can be mocked per-test to return specific element references.
 */
export const getElement = jest.fn( () => ( { ref: null } ) );

/**
 * Mock withScope function that simply executes the callback.
 */
export const withScope = jest.fn( ( callback: () => void ) => callback() );

/**
 * Mock directives object for custom directives.
 */
export const directives = {};

/**
 * Mock h function for hyperscript (if used).
 */
export const h = jest.fn();
