/**
 * The one Playwright config for every Publishio E2E run. Do not edit per run.
 *
 * Each WordPress site (tests/e2e/config/sites.json) gets a setup project, "setup-<site>", that
 * starts its wp-env instance if needed and prepares it, and a teardown project, "teardown-<site>",
 * that stops it afterwards if setup started it. Every test project depends on its site's setup,
 * so selecting projects starts, prepares and stops only the sites they use:
 *
 *   npx playwright test --project=install                         # latest WordPress only
 *   npx playwright test --project=install --project=install-wp69  # latest + 6.9 in one report
 *   node bin/e2e.mjs install-wp69 start && node bin/e2e.mjs install-wp69 test
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
 * The project's tests (its tag, or an extra tag), narrowed to the update type when E2E_UPDATE is set.
 *
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

// Per site: setup starts it if needed and prepares it; teardown stops it after its projects.
const siteProjects = usedSites.flatMap( ( name ) => [
	{
		name: `setup-${ name }`,
		testDir: './tests/e2e/setup',
		testMatch: 'site.setup.js',
		teardown: `teardown-${ name }`,
		// Starting a cold site pulls Docker images and installs WordPress: minutes, not seconds.
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
		grep: projectGrep( project.grep ),
		retries: settings.ci ? project.retries : 0,
		timeout: project.timeout,
		dependencies: [ `setup-${ project.site }` ],
		// Shown in the HTML report, and read by the helpers to find the project's site.
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

	// WordPress shares one database per site, so tests run one at a time.
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
		// Read by the Slack step and the impact-analysis skill.
		[ 'json', { outputFile: path.join( artifacts, 'results.json' ) } ],
		...( settings.ci ? [ [ 'github' ] ] : [] ),
	],

	metadata: {
		theme: settings.theme,
		...( settings.update ? { update: settings.update } : {} ),
		...( settings.extraTags.length
			? { extraTags: settings.extraTags.join( ' ' ) }
			: {} ),
	},

	use: {
		screenshot: 'on', // Evidence for every test, attached to the report.
		trace: 'retain-on-failure',
		video: 'off',
		actionTimeout: 15_000,
		launchOptions: { slowMo: settings.slowMo },
	},

	projects: [ ...siteProjects, ...testProjects ],
} );
