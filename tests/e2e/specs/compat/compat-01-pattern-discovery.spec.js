/**
 * COMPAT-01: pattern discovery over MCP.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { wpCli } from '../../utils/wp-cli';

// Inserter sources from the WordPress.org pattern directory.
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
			'COMPAT-01.1 theme-registered patterns are discoverable over MCP',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
				const editorPatterns = await requestUtils.rest( {
					path: '/wp/v2/block-patterns/patterns',
				} );
				const mcpNames = (
					await mcp.callTool( 'publishio/get-patterns' )
				).data.map( ( p ) => p.name );

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

				const registered = editorPatterns
					.filter( ( p ) => ! DIRECTORY.includes( p.source ?? '' ) )
					.map( ( p ) => p.name );
				expect(
					registered.filter( ( n ) => ! mcpNames.includes( n ) ),
					'registered patterns in the inserter but not over MCP'
				).toEqual( [] );

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
			'COMPAT-01.2 user-defined patterns (synced and unsynced) are not listed',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
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
						// Synced is the default (no sync status).
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
			'COMPAT-01.3 patterns without descriptions are still listed',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
				// A pattern registered with no description, via a test-only mu-plugin.
				const name = 'e2e/no-description';
				const muPlugin = 'e2e-no-description-pattern.php';
				const php = `<?php add_action( 'init', static function () { register_block_pattern( '${ name }', [ 'title' => 'E2E no description', 'categories' => [ 'text' ], 'content' => '<!-- wp:paragraph --><p>E2E</p><!-- /wp:paragraph -->' ] ); } );`;
				wpCli(
					`eval 'wp_mkdir_p( WPMU_PLUGIN_DIR ); file_put_contents( WPMU_PLUGIN_DIR . "/${ muPlugin }", base64_decode( "${ Buffer.from(
						php
					).toString( 'base64' ) }" ) );'`
				);
				try {
					const data = (
						await mcp.callTool( 'publishio/get-patterns' )
					).data;
					const own = data.find( ( p ) => p.name === name );
					expect( own, `${ name } is listed` ).toBeTruthy();
					expect( own.description ).toBe( '' );

					// Every registered pattern the editor offers without a description is listed too.
					const names = data.map( ( p ) => p.name );
					const undocumented = (
						await requestUtils.rest( {
							path: '/wp/v2/block-patterns/patterns',
						} )
					)
						.filter(
							( p ) =>
								! p.description &&
								! DIRECTORY.includes( p.source ?? '' )
						)
						.map( ( p ) => p.name );
					expect( undocumented ).toContain( name );
					expect(
						undocumented.filter( ( n ) => ! names.includes( n ) ),
						'description-less patterns missing over MCP'
					).toEqual( [] );
					test.info().annotations.push( {
						type: 'patterns-without-description',
						description: `${ undocumented.length } of ${ data.length }`,
					} );
				} finally {
					wpCli(
						`eval 'wp_delete_file( WPMU_PLUGIN_DIR . "/${ muPlugin }" );'`
					);
				}
			}
		);
	}
);
