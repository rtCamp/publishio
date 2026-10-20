/**
 * Playwright config for every Publishio E2E run; configure via env vars.
 */
/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
/**
 * Internal dependencies
 */
import { artifacts, projects, settings, site } from './tests/e2e/config/env';

const viewport = { width: 1280, height: 900 };
const browser = { ...devices[ 'Desktop Chrome' ], viewport };

/**
 * @param {string} tags The project's tag pattern, e.g. "@smoke".
 * @return {RegExp} Pattern Playwright matches against each test's title and tags.
 */
function projectGrep( tags ) {
	const any = [ tags, ...settings.extraTags ].join( '|' );
	return settings.update
		? new RegExp( `^(?=.*@${ settings.update }\\b)(?=.*(?:${ any }))` )
		: new RegExp( any );
}

const usedSites = [
	...new Set( Object.values( projects ).map( ( p ) => p.site ) ),
];

const siteProjects = usedSites.flatMap( ( name ) => [
	{
		name: `setup-${ name }`,
		testDir: './tests/e2e/setup',
		testMatch: 'site.setup.js',
		teardown: `teardown-${ name }`,
		// A cold start pulls Docker images.
		timeout: 20 * 60_000,
		metadata: { site: name },
		use: { ...browser, baseURL: site( name ).baseURL },
	},
	{
		name: `teardown-${ name }`,
		testDir: './tests/e2e/setup',
		testMatch: 'site.teardown.js',
		timeout: 5 * 60_000,
		metadata: { site: name },
	},
] );

const testProjects = Object.entries( projects ).map( ( [ name, project ] ) => {
	const s = site( project.site );
	return {
		name,
		testMatch: project.testMatch,
		testIgnore: project.testIgnore,
		grep: projectGrep( project.grep ),
		retries: settings.ci ? project.retries : 0,
		timeout: project.timeout,
		dependencies: [ `setup-${ project.site }` ],
		metadata: {
			site: project.site,
			wordpress: s.wpLabel,
			php: s.phpLabel,
			theme: settings.theme,
			...( s.plugin === 'zip'
				? { zip: path.basename( settings.zip ) }
				: {} ),
		},
		use: { ...browser, baseURL: s.baseURL, storageState: s.storageState },
	};
} );

export default defineConfig( {
	testDir: './tests/e2e/specs',
	outputDir: path.join( artifacts, 'test-results' ),

	// One database per site.
	fullyParallel: false,
	workers: 1,
	forbidOnly: settings.ci,

	expect: {
		timeout: 10_000,
	},

	reporter: [
		[ 'list' ],
		[
			'html',
			{ open: 'never', outputFolder: path.join( artifacts, 'report' ) },
		],
		[ 'json', { outputFile: path.join( artifacts, 'results.json' ) } ],
		...( settings.ci
			? [
					[ 'github' ],
					// Merged across the run's jobs into one report (e2e.yml → report job).
					[
						'blob',
						{
							outputDir: path.join( artifacts, 'blob' ),
							fileName: `${
								process.env.E2E_REPORT_NAME || 'report'
							}.zip`,
						},
					],
			  ]
			: [] ),
	],

	metadata: {
		theme: settings.theme,
		...( settings.update ? { update: settings.update } : {} ),
		...( settings.extraTags.length
			? { extraTags: settings.extraTags.join( ' ' ) }
			: {} ),
	},

	use: {
		screenshot: 'on',
		trace: 'retain-on-failure',
		video: 'off',
		actionTimeout: 15_000,
		launchOptions: { slowMo: settings.slowMo },
	},

	projects: [ ...siteProjects, ...testProjects ],
} );
