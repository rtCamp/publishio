/**
 * INSTALL-01 to INSTALL-13: plugin installation (tests share site state and run in order).
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { markDebugLog } from '../../utils/debug-log';
import { McpClient, PROTOCOL_VERSION } from '../../utils/mcp';
import { connect } from '../../utils/oauth';
import {
	branchHeader,
	buildSourceZip,
	compareVersions,
	parseHeader,
	readFromZip,
	zipEntries,
	zipPath,
	zipPathInContainer,
} from '../../utils/plugin-zip';
import { ALREADY, wpCli, wpCliQuietly } from '../../utils/wp-cli';
import { expectLocalStatus, STATUS_PAGE } from '../../utils/status';
import { settings } from '../../config/env';
import {
	adminMain,
	adminMenu,
	collectErrors,
	evidence,
	pluginRow,
	showText,
	uploadZip,
} from './helpers';

/** Release INSTALL-10 upgrades from. Pinned so runs are reproducible; bump it after each release. */
const PREVIOUS_RELEASE = '1.1.0';

const status = () => wpCli( 'plugin list --name=publishio --field=status' );

const removePlugin = () => {
	wpCliQuietly( 'plugin deactivate publishio', ALREADY.pluginMissing );
	wpCli( 'plugin delete publishio' );
};

test.describe(
	'1. Plugin installation',
	{ tag: [ '@install', '@plugin-update' ] },
	() => {
		test.describe.configure( { mode: 'serial' } );

		test.beforeAll( () => {
			const wp = wpCli( 'core version' );
			test.skip(
				compareVersions( wp, branchHeader().requiresWp ) < 0,
				`WordPress ${ wp } is below the plugin minimum; only INSTALL-09 runs here.`
			);
		} );

		test( 'INSTALL-01 built zip for the branch is complete', async ( {
			page,
		} ) => {
			const entries = zipEntries();
			for ( const required of [
				'publishio/publishio.php',
				'publishio/readme.txt',
				'publishio/uninstall.php',
				'publishio/vendor/autoload.php',
			] ) {
				expect( entries, `zip is missing ${ required }` ).toContain(
					required
				);
			}
			expect(
				entries.some( ( e ) => e.startsWith( 'publishio/build/' ) ),
				'zip has no build/ assets'
			).toBe( true );
			for ( const devOnly of [
				'node_modules/',
				'tests/',
				'src/',
				'.github/',
			] ) {
				expect(
					entries.some( ( e ) =>
						e.startsWith( `publishio/${ devOnly }` )
					),
					`zip ships ${ devOnly }`
				).toBe( false );
			}

			const zipHeader = parseHeader(
				readFromZip( 'publishio/publishio.php' )
			);
			expect(
				zipHeader.version,
				'zip version must match the branch'
			).toBe( branchHeader().version );
			test.info().annotations.push( {
				type: 'zip',
				description: `${ zipPath() } (v${ zipHeader.version })`,
			} );
			await showText(
				page,
				`${ zipPath() } (v${ zipHeader.version }), top-level entries`,
				[
					...new Set(
						entries.map( ( e ) =>
							e.split( '/' ).slice( 0, 2 ).join( '/' )
						)
					),
				].join( '\n' )
			);
			await evidence( page, 'INSTALL-01 zip contents' );
		} );

		test( 'INSTALL-02 source-code zip fails safely', async ( {
			admin,
			page,
			anonApi,
		} ) => {
			const source = buildSourceZip();
			await uploadZip( admin, page, source );
			await page.getByRole( 'link', { name: 'Activate Plugin' } ).click();

			await expect(
				page.getByText( /Composer autoloader was not found/ )
			).toBeVisible();
			await evidence( page, 'INSTALL-02 source zip activation' );

			// Stays active, but must load nothing without its autoloader.
			await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();
			await expect( page.getByText( /fatal error/i ) ).toHaveCount( 0 );
			expect(
				( await anonApi.get( '/' ) ).status(),
				'front end must keep working'
			).toBe( 200 );
			expect(
				(
					await anonApi.post( '/wp-json/mcp/publishio', { data: {} } )
				).status(),
				'MCP route must not load'
			).toBe( 404 );
			await page.goto( '/wp-admin/plugins.php' );
			await expect(
				page.getByRole( 'heading', { name: 'Plugins', level: 1 } )
			).toBeVisible();

			removePlugin();
		} );

		test(
			'INSTALL-03 install from WP Admin upload',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				removePlugin();
				const errors = collectErrors( page );
				const log = markDebugLog();

				await uploadZip( admin, page, zipPath() );
				await expect(
					page.getByRole( 'link', { name: 'Activate Plugin' } )
				).toBeVisible();
				await evidence( page, 'INSTALL-03 installed' );

				expect( status() ).toBe( 'inactive' );
				expect( log(), 'PHP problems from Publishio' ).toEqual( [] );
				expect( errors ).toEqual( [] );
			}
		);

		test(
			'INSTALL-05 activate without errors',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				const errors = collectErrors( page );
				const log = markDebugLog();

				await admin.visitAdminPage( 'plugins.php' );
				await pluginRow( page )
					.getByRole( 'link', { name: /^Activate/ } )
					.click();
				await expect(
					page.getByText( 'Plugin activated.' )
				).toBeVisible();
				await expect(
					page.getByText( /could not be activated|fatal error/i )
				).toHaveCount( 0 );
				await evidence( page, 'INSTALL-05 activated' );

				expect( status() ).toBe( 'active' );
				expect( log(), 'PHP problems from Publishio' ).toEqual( [] );
				expect( errors ).toEqual( [] );
			}
		);

		test(
			'INSTALL-06 Plugins screen shows the correct details',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				const header = parseHeader(
					readFromZip( 'publishio/publishio.php' )
				);
				await admin.visitAdminPage( 'plugins.php' );
				const row = pluginRow( page );

				await expect(
					row.getByText( header.name, { exact: true } )
				).toBeVisible();
				await expect(
					row.getByText( header.description )
				).toBeVisible();
				await expect(
					row.getByText( `Version ${ header.version }` )
				).toBeVisible();
				await expect(
					row.getByRole( 'link', { name: header.author } )
				).toHaveAttribute( 'href', header.authorUri );
				// "View details" if listed on WordPress.org, "Visit plugin site" otherwise.
				const site = row.getByRole( 'link', {
					name: 'Visit plugin site',
				} );
				const details = row.getByRole( 'link', {
					name: `More information about ${ header.name }`,
				} );
				await expect( site.or( details ) ).toBeVisible();
				if ( await site.isVisible() ) {
					await expect( site ).toHaveAttribute( 'href', header.uri );
				} else {
					await expect( details ).toHaveText( 'View details' );
					await expect( details ).toHaveAttribute(
						'href',
						/plugin-information&plugin=publishio/
					);
				}
				await evidence( page, 'INSTALL-06 plugins row' );

				const stableTag = readFromZip( 'publishio/readme.txt' ).match(
					/^Stable tag:\s*(\S+)/m
				)?.[ 1 ];
				expect(
					stableTag,
					'readme.txt Stable tag must match the header version'
				).toBe( header.version );
				expect( wpCli( 'plugin get publishio --field=version' ) ).toBe(
					header.version
				);

				await row.getByRole( 'link', { name: 'Settings' } ).click();
				await expect( page ).toHaveURL( /page=publishio/ );
			}
		);

		test(
			'INSTALL-07 admin menu and screens appear',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				const errors = collectErrors( page );
				// Older releases (1.1.0 and before, tested by wp-release-watch) have no Status page.
				const hasStatus = zipEntries().includes(
					'publishio/templates/status-screen.php'
				);
				if ( ! hasStatus ) {
					test.info().annotations.push( {
						type: 'note',
						description:
							'This build has no Status page; its checks are skipped.',
					} );
				}
				await admin.visitAdminPage( 'index.php' );

				const menu = adminMenu( page );
				await expect(
					menu.getByRole( 'link', { name: 'Publishio', exact: true } )
				).toBeVisible();
				await expect(
					menu.getByRole( 'link', { name: 'Guide', exact: true } )
				).toBeAttached();
				for ( const name of hasStatus
					? [ 'Connections', 'Status' ]
					: [ 'Connections' ] ) {
					await expect(
						menu.getByRole( 'link', { name, exact: true } )
					).toBeAttached();
				}

				await admin.visitAdminPage( 'admin.php', 'page=publishio' );
				await expect(
					adminMain( page ).getByRole( 'heading', {
						level: 1,
						name: 'Guide',
					} )
				).toBeVisible();
				await evidence( page, 'INSTALL-07 guide' );

				await admin.visitAdminPage(
					'admin.php',
					'page=publishio-connections'
				);
				const root = adminMain( page );
				await expect(
					root.getByRole( 'heading', {
						level: 1,
						name: 'Connections',
					} )
				).toBeVisible();
				await expect(
					root.getByText( /No AI apps have connected yet/ )
				).toBeVisible();
				await evidence( page, 'INSTALL-07 connections' );

				if ( hasStatus ) {
					await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
					await expectLocalStatus( page );
					await evidence( page, 'INSTALL-07 status' );
				}

				expect( errors ).toEqual( [] );
			}
		);

		test(
			'INSTALL-08 discovery endpoints are live after activation',
			{ tag: '@wp-update' },
			async ( { anonApi, page } ) => {
				for ( const p of [
					'/.well-known/oauth-protected-resource',
					'/.well-known/oauth-authorization-server',
				] ) {
					const res = await anonApi.get( p );
					expect( res.status(), p ).toBe( 200 );
					expect( res.headers()[ 'content-type' ], p ).toContain(
						'application/json'
					);
					await page.goto( p );
					await evidence( page, `INSTALL-08 ${ p }` );
				}
			}
		);

		test(
			'INSTALL-11 deactivate and reactivate',
			{ tag: '@wp-update' },
			async ( { admin, page, anonApi } ) => {
				const log = markDebugLog();
				await admin.visitAdminPage( 'plugins.php' );

				await pluginRow( page )
					.getByRole( 'link', { name: /^Deactivate/ } )
					.click();
				await expect(
					page.getByText( 'Plugin deactivated.' )
				).toBeVisible();
				expect( ( await anonApi.get( '/' ) ).status() ).toBe( 200 );
				expect(
					(
						await anonApi.post( '/wp-json/mcp/publishio', {
							data: {},
						} )
					).status(),
					'MCP route while inactive'
				).toBe( 404 );

				await pluginRow( page )
					.getByRole( 'link', { name: /^Activate/ } )
					.click();
				await expect(
					page.getByText( 'Plugin activated.' )
				).toBeVisible();
				expect(
					(
						await anonApi.post( '/wp-json/mcp/publishio', {
							data: {},
						} )
					).status(),
					'MCP route after reactivation'
				).toBe( 401 );
				await evidence( page, 'INSTALL-11 reactivated' );

				expect( log() ).toEqual( [] );
			}
		);

		test(
			'INSTALL-04 install with WP-CLI in the workflow environment',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				removePlugin();
				const out = wpCli(
					`plugin install ${ zipPathInContainer() } --activate`
				);

				expect( out ).toMatch( /Plugin installed successfully/ );
				expect( out ).toMatch( /Success/ );
				expect( status() ).toBe( 'active' );
				expect( wpCli( 'plugin get publishio --field=version' ) ).toBe(
					parseHeader( readFromZip( 'publishio/publishio.php' ) )
						.version
				);

				await showText( page, 'wp plugin install output', out );
				await evidence( page, 'INSTALL-04 WP-CLI output' );
				await admin.visitAdminPage( 'plugins.php' );
				await expect(
					pluginRow( page ).getByRole( 'link', {
						name: /^Deactivate/,
					} )
				).toBeVisible();
				await evidence( page, 'INSTALL-04 plugins row' );
			}
		);

		test( 'INSTALL-10 upgrade over the previous release keeps data', async ( {
			admin,
			page,
			anonApi,
			adminApi,
			requestUtils,
		} ) => {
			const previous =
				process.env.PUBLISHIO_PREVIOUS_ZIP ??
				`https://github.com/rtCamp/publishio/releases/download/${ PREVIOUS_RELEASE }/publishio.zip`;

			// Full uninstall: a plain delete keeps publishio_db_version and skips migrations.
			wpCliQuietly(
				'plugin uninstall publishio --deactivate',
				ALREADY.pluginMissing
			);
			wpCli(
				`plugin install ${ JSON.stringify( previous ) } --activate`
			);
			const previousVersion = wpCli(
				'plugin get publishio --field=version'
			);
			test.info().annotations.push( {
				type: 'upgrade',
				description: `${ previousVersion } → ${
					branchHeader().version
				}`,
			} );

			const before = await connect( anonApi, adminApi );

			await uploadZip( admin, page, zipPath(), true );
			await evidence( page, 'INSTALL-10 upgraded' );

			expect( status(), 'plugin must stay active after upgrade' ).toBe(
				'active'
			);
			expect( wpCli( 'plugin get publishio --field=version' ) ).toBe(
				branchHeader().version
			);
			await expect
				.poll( async () =>
					(
						await anonApi.get(
							'/.well-known/oauth-authorization-server'
						)
					).status()
				)
				.toBe( 200 );

			const connectionIds = async () =>
				JSON.stringify(
					(
						await requestUtils.rest( {
							path: '/publishio/v1/connections',
						} )
					).items
				);

			if ( before.discovery.cimd ) {
				await new McpClient(
					anonApi,
					before.discovery.resource,
					before.tokens.access_token
				).initialize();
				expect(
					await connectionIds(),
					'connection must survive the upgrade'
				).toContain( before.client.client_id );
			} else {
				test.info().annotations.push( {
					type: 'migration',
					description:
						'Previous release used dynamic client registration; its connections are removed by design (#129).',
				} );
				const stale = await new McpClient(
					anonApi,
					before.discovery.resource,
					before.tokens.access_token
				).send( 'initialize', {
					protocolVersion: PROTOCOL_VERSION,
					capabilities: {},
					clientInfo: { name: 'publishio-e2e', version: '1.0.0' },
				} );
				expect(
					stale.status(),
					'DCR token must be rejected after the upgrade'
				).toBe( 401 );
				expect(
					await connectionIds(),
					'DCR connection must be removed'
				).not.toContain( before.client.client_id );
			}

			const after = await connect( anonApi, adminApi );
			expect(
				after.discovery.cimd,
				'upgraded site must use client metadata documents'
			).toBe( true );
			await new McpClient(
				anonApi,
				after.discovery.resource,
				after.tokens.access_token
			).initialize();
			expect( await connectionIds() ).toContain( after.client.client_id );
		} );

		test(
			'INSTALL-12 uninstall removes plugin data',
			{ tag: '@wp-update' },
			async ( { admin, page } ) => {
				await admin.visitAdminPage( 'plugins.php' );
				await pluginRow( page )
					.getByRole( 'link', { name: /^Deactivate/ } )
					.click();
				await expect(
					page.getByText( 'Plugin deactivated.' )
				).toBeVisible();

				page.once( 'dialog', ( d ) => d.accept() );
				await pluginRow( page )
					.getByRole( 'link', { name: /^Delete/ } )
					.click();
				await expect(
					page.getByText( /was successfully deleted/ )
				).toBeVisible();
				await evidence( page, 'INSTALL-12 deleted' );

				expect( status() ).toBe( '' );
				expect(
					() => wpCli( 'option get publishio_version' ),
					'version option must be removed'
				).toThrow();
				const tables = wpCli(
					`db query "SHOW TABLES LIKE '%publishio_oauth%'" --skip-column-names`
				);
				expect( tables, 'OAuth tables must be dropped' ).toBe( '' );
				// Prefix-independent (works with any $table_prefix): transients and their timeouts.
				const transients = wpCli(
					`option list --search="*publishio_cimd_*" --format=count`
				);
				expect(
					transients,
					'client metadata cache must be removed'
				).toBe( '0' );
			}
		);

		test(
			'INSTALL-13 posts and pages created through Publishio survive deactivation and uninstall',
			{ tag: '@wp-update' },
			async ( { admin, page, anonApi, adminApi } ) => {
				// Fresh install: independent of the journey above.
				removePlugin();
				wpCli( `plugin install ${ zipPathInContainer() } --activate` );

				const { discovery, tokens } = await connect(
					anonApi,
					adminApi
				);
				const mcp = new McpClient(
					anonApi,
					discovery.resource,
					tokens.access_token
				);
				await mcp.initialize();
				const run = Date.now();

				const post = await mcp.callTool( 'publishio/create-post', {
					title: `INSTALL-13 post ${ run }`,
				} );
				expect( post.isError, post.text ).toBe( false );
				const appended = await mcp.callTool(
					'publishio/insert-blocks',
					{
						post_id: post.data.post_id,
						markup: '<!-- wp:paragraph --><p>Written through Publishio.</p><!-- /wp:paragraph -->',
					}
				);
				expect( appended.isError, appended.text ).toBe( false );

				const pageDraft = await mcp.callTool( 'publishio/create-post', {
					title: `INSTALL-13 page ${ run }`,
					post_type: 'page',
				} );
				expect( pageDraft.isError, pageDraft.text ).toBe( false );
				const pattern = (
					await mcp.callTool( 'publishio/get-patterns' )
				).data.find(
					( p ) =>
						p.name.startsWith( `${ settings.theme }/` ) &&
						p.block_types.length === 0 &&
						p.template_types.length === 0 &&
						( p.post_types.length === 0 ||
							p.post_types.includes( 'page' ) )
				);
				expect(
					pattern,
					'a page pattern in the active theme'
				).toBeTruthy();
				const schema = (
					await mcp.callTool( 'publishio/get-pattern-schema', {
						name: pattern.name,
					} )
				).data;
				const inserted = await mcp.callTool(
					'publishio/insert-pattern-confirmed',
					{
						page_id: pageDraft.data.post_id,
						position: -1,
						pattern_name: pattern.name,
						schema: schema.map( ( e ) => ( {
							block: e.block,
							fields: {},
						} ) ),
					}
				);
				expect( inserted.isError, inserted.text ).toBe( false );

				// WP-CLI, so reads work without the plugin.
				const ids = [ post.data.post_id, pageDraft.data.post_id ];
				const snapshot = () =>
					ids.map( ( id ) =>
						wpCli(
							`post get ${ id } --fields=ID,post_title,post_status,post_type,post_content --format=json`
						)
					);
				const before = snapshot();
				expect( before[ 0 ] ).toContain( 'Written through Publishio.' );
				expect( before[ 1 ] ).toContain( '<!-- wp:' );

				await admin.visitAdminPage( 'plugins.php' );
				await pluginRow( page )
					.getByRole( 'link', { name: /^Deactivate/ } )
					.click();
				await expect(
					page.getByText( 'Plugin deactivated.' )
				).toBeVisible();
				expect( snapshot(), 'content after deactivation' ).toEqual(
					before
				);

				page.once( 'dialog', ( d ) => d.accept() );
				await pluginRow( page )
					.getByRole( 'link', { name: /^Delete/ } )
					.click();
				await expect(
					page.getByText( /was successfully deleted/ )
				).toBeVisible();
				expect( status(), 'plugin is gone' ).toBe( '' );
				expect( snapshot(), 'content after uninstall' ).toEqual(
					before
				);

				await admin.visitAdminPage(
					'edit.php',
					'post_type=page&post_status=draft'
				);
				await expect(
					adminMain( page )
						.getByRole( 'row' )
						.filter( {
							has: page.getByRole( 'checkbox', {
								name: `Select INSTALL-13 page ${ run }`,
							} ),
						} )
				).toBeVisible();
				await evidence(
					page,
					'INSTALL-13 page still listed after uninstall'
				);
				await admin.visitAdminPage( 'edit.php', 'post_status=draft' );
				await expect(
					adminMain( page )
						.getByRole( 'row' )
						.filter( {
							has: page.getByRole( 'checkbox', {
								name: `Select INSTALL-13 post ${ run }`,
							} ),
						} )
				).toBeVisible();
				await evidence(
					page,
					'INSTALL-13 post still listed after uninstall'
				);
			}
		);
	}
);
