/**
 * COMPAT-01: which patterns the AI can discover over MCP.
 *
 * get-patterns lists the patterns registered with WordPress (WP_Block_Patterns_Registry): the
 * active theme's, plus any core or plugin ones. It does not list user-defined patterns (synced or
 * unsynced, saved as wp_block posts) or WordPress.org pattern-directory entries, which the
 * inserter also shows. Oracle: the editor's own REST endpoints. Run per theme with E2E_THEME.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { wpCli } from '../../utils/wp-cli';

/** Inserter sources that come from the WordPress.org pattern directory, not the site. */
const DIRECTORY = [
	'pattern-directory/core',
	'pattern-directory/featured',
	'pattern-directory/theme',
];

test.describe(
	'Pattern discovery',
	{ tag: [ '@compat', '@plugin-update' ] },
	() => {
		test(
			'COMPAT-01 theme-registered patterns are discoverable over MCP',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
				const editorPatterns = await requestUtils.rest( {
					path: '/wp/v2/block-patterns/patterns',
				} );
				const mcpNames = (
					await mcp.callTool( 'publishio/get-patterns' )
				).data.map( ( p ) => p.name );

				// The theme's patterns are named <theme slug>/<pattern>.
				const theme = wpCli( 'option get stylesheet' );
				const themePatterns = editorPatterns
					.map( ( p ) => p.name )
					.filter( ( n ) => n.startsWith( `${ theme }/` ) );
				expect(
					themePatterns.length,
					`patterns registered by ${ theme }`
				).toBeGreaterThan( 0 );
				expect(
					themePatterns.filter( ( n ) => ! mcpNames.includes( n ) ),
					`${ theme } patterns in the inserter but not over MCP`
				).toEqual( [] );

				// Every other registered pattern (core, plugins) is listed too.
				const registered = editorPatterns
					.filter( ( p ) => ! DIRECTORY.includes( p.source ?? '' ) )
					.map( ( p ) => p.name );
				expect(
					registered.filter( ( n ) => ! mcpNames.includes( n ) ),
					'registered patterns in the inserter but not over MCP'
				).toEqual( [] );

				// Pattern-directory entries (loaded only when the site can reach WordPress.org) are
				// inserter-only.
				const directory = editorPatterns
					.filter( ( p ) => DIRECTORY.includes( p.source ?? '' ) )
					.map( ( p ) => p.name );
				expect(
					directory.filter( ( n ) => mcpNames.includes( n ) ),
					'pattern-directory entries listed over MCP'
				).toEqual( [] );
				test.info().annotations.push( {
					type: 'patterns',
					description: `${ themePatterns.length } from ${ theme }, ${
						registered.length - themePatterns.length
					} core/plugin, ${
						directory.length
					} directory (not listed)`,
				} );
			}
		);

		test(
			'COMPAT-01 user-defined patterns (synced and unsynced) are not listed',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
				// Not supported by design: get-patterns reads the registry only, so patterns users
				// save in the editor (wp_block posts) are not offered to the AI. #119 tracks adding
				// them; if that lands, this test must change.
				test.info().annotations.push( {
					type: 'issue',
					description:
						'https://github.com/rtCamp/publishio/issues/119',
				} );
				const content =
					'<!-- wp:paragraph --><p>E2E user pattern</p><!-- /wp:paragraph -->';
				const created = [];
				try {
					for ( const [ title, sync ] of [
						// Synced is the default: no sync status at all.
						[ 'E2E Synced Pattern', {} ],
						[
							'E2E Unsynced Pattern',
							{ wp_pattern_sync_status: 'unsynced' },
						],
					] ) {
						created.push(
							await requestUtils.rest( {
								method: 'POST',
								path: '/wp/v2/blocks',
								data: {
									title,
									content,
									status: 'publish',
									meta: sync,
								},
							} )
						);
					}
					// The inserter offers them...
					const inEditor = (
						await requestUtils.rest( {
							path: '/wp/v2/blocks',
							params: { per_page: 100 },
						} )
					).map( ( p ) => p.title.raw ?? p.title.rendered );
					expect( inEditor ).toEqual(
						expect.arrayContaining( [
							'E2E Synced Pattern',
							'E2E Unsynced Pattern',
						] )
					);

					// ...get-patterns does not.
					const titles = (
						await mcp.callTool( 'publishio/get-patterns' )
					).data.map( ( p ) => p.title );
					expect( titles ).not.toContain( 'E2E Synced Pattern' );
					expect( titles ).not.toContain( 'E2E Unsynced Pattern' );
				} finally {
					for ( const { id } of created ) {
						await requestUtils.rest( {
							method: 'DELETE',
							path: `/wp/v2/blocks/${ id }`,
							params: { force: true },
						} );
					}
				}
			}
		);

		test(
			'COMPAT-01 patterns without descriptions are still listed',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const data = ( await mcp.callTool( 'publishio/get-patterns' ) )
					.data;
				const undocumented = data.filter( ( p ) => ! p.description );
				// Not a failure: reported so theme authors can improve metadata (see README guidance).
				test.info().annotations.push( {
					type: 'patterns-without-description',
					description: `${ undocumented.length } of ${ data.length }`,
				} );
				expect( data.length ).toBeGreaterThan( 0 );
			}
		);
	}
);
