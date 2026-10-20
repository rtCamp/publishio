/**
 * Merges the blob reports of a CI run's jobs: npx playwright merge-reports --config <this> <dir>.
 */
/**
 * External dependencies
 */
import path from 'node:path';
/**
 * Internal dependencies
 */
import { artifacts } from './env';

export default {
	testDir: path.join( artifacts, '..', 'tests', 'e2e', 'specs' ),
	reporter: [
		[
			'html',
			{ open: 'never', outputFolder: path.join( artifacts, 'report' ) },
		],
		[ 'json', { outputFile: path.join( artifacts, 'results.json' ) } ],
	],
};
