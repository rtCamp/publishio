/**
 * Jest configuration for Publish with AI.
 *
 * Extends @wordpress/scripts default configuration with:
 * - Custom test setup for WordPress mocks
 * - Module path aliases for cleaner imports
 * - Coverage thresholds to maintain code quality
 *
 * @see https://jestjs.io/docs/configuration
 */

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,

	// Display name for clarity in multi-project setups
	displayName: 'rtcamp-publish-with-ai',

	// Root directory for tests
	rootDir: '.',

	// Test setup files run after Jest environment is set up
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/js/setup.ts',
	],

	// Module resolution aliases
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		// Path alias for src directory
		'^@/(.*)$': '<rootDir>/src/$1',
	},

	// Directories to ignore when searching for tests
	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
		'/build-apps/',
		'/vendor/',
		'/tests/e2e/',
		'/tests/php/',
		'/.claude/',
	],

	// Directories to ignore for module resolution (prevents Haste collisions)
	modulePathIgnorePatterns: [ '/.claude/' ],

	// Test match patterns
	testMatch: [
		'**/__tests__/**/*.{js,jsx,ts,tsx}',
		'**/*.{test,spec}.{js,jsx,ts,tsx}',
	],

	// Files to include in coverage reports
	collectCoverageFrom: [
		'src/**/*.{ts,tsx}',
		// Exclude type definition files
		'!src/**/*.d.ts',
		// Exclude barrel exports
		'!src/**/index.ts',
		// Exclude WordPress block JSON schemas
		'!src/**/*.json',
		// Exclude style files
		'!src/**/*.scss',
		'!src/**/*.css',
	],

	// Coverage output directory
	coverageDirectory: 'tests/_output/js-coverage',

	// Coverage thresholds - start at 0% to allow gradual adoption
	coverageThreshold: {
		global: {
			branches: 0,
			functions: 0,
			lines: 0,
			statements: 0,
		},
	},

	// Coverage reporters for different outputs
	coverageReporters: [ 'text', 'text-summary', 'lcov', 'html' ],

	// Verbose output for CI environments
	verbose: process.env.CI === 'true',

	// Timeout for slow tests (useful for integration tests)
	testTimeout: 10000,

	// Watch plugins for better DX
	watchPlugins: [
		'jest-watch-typeahead/filename',
		'jest-watch-typeahead/testname',
	],
};
