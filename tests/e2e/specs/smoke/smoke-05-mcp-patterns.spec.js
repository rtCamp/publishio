/**
 * SMOKE-05: MCP server handshake, tool listing and pattern discovery.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { McpClient, tool } from '../../utils/mcp';
import { settings } from '../../config/env';

const CORE_TOOLS = [
	'publishio/get-patterns',
	'publishio/get-pattern',
	'publishio/get-pattern-schema',
	'publishio/create-post',
	'publishio/get-post',
	'publishio/insert-blocks',
	'publishio/insert-pattern-confirmed',
].map( tool );

test.describe( 'MCP server', { tag: [ '@smoke', '@plugin-update' ] }, () => {
	test(
		'SMOKE-05.1 initialize returns server info and capabilities',
		{ tag: '@wp-update' },
		async ( { anonApi, connection } ) => {
			const client = new McpClient(
				anonApi,
				connection.discovery.resource,
				connection.tokens.access_token
			);
			const result = await client.initialize();

			expect( result.protocolVersion ).toBeTruthy();
			expect( result.serverInfo.name ).toContain( 'Publishio' );
			expect( result.capabilities.tools ).toBeDefined();
		}
	);

	test(
		'SMOKE-05.2 lists the Publishio tools',
		{ tag: '@wp-update' },
		async ( { mcp } ) => {
			const names = ( await mcp.listTools() ).map( ( t ) => t.name );
			for ( const name of CORE_TOOLS ) {
				expect( names, `missing tool ${ name }` ).toContain( name );
			}
		}
	);

	test(
		'SMOKE-05.3 get-patterns returns the active theme patterns with metadata',
		{ tag: '@wp-update' },
		async ( { mcp } ) => {
			const { isError, data } = await mcp.callTool(
				'publishio/get-patterns'
			);
			expect( isError ).toBe( false );
			expect( Array.isArray( data ) ).toBe( true );

			const themePatterns = data.filter( ( p ) =>
				p.name.startsWith( `${ settings.theme }/` )
			);
			expect( themePatterns.length ).toBeGreaterThan( 0 );
			for ( const p of data ) {
				expect( p.name, JSON.stringify( p ) ).toMatch( /^[^/]+\/.+/ );
				expect( p.title, p.name ).toBeTruthy();
				expect( Array.isArray( p.categories ) ).toBe( true );
				expect(
					p,
					'pattern content must not be sent in the list'
				).not.toHaveProperty( 'content' );
			}
		}
	);

	test(
		'SMOKE-05.4 get-patterns filters by keyword and category',
		{ tag: '@wp-update' },
		async ( { mcp } ) => {
			const all = ( await mcp.callTool( 'publishio/get-patterns' ) ).data;
			const withCategory = all.find( ( p ) => p.categories.length > 0 );
			test.skip(
				! withCategory,
				'No categorised patterns on this theme.'
			);

			const category = withCategory.categories[ 0 ];
			const byCategory = (
				await mcp.callTool( 'publishio/get-patterns', {
					category,
				} )
			).data;
			expect( byCategory.length ).toBeGreaterThan( 0 );
			expect(
				byCategory.every( ( p ) => p.categories.includes( category ) )
			).toBe( true );

			const keyword = withCategory.title.split( ' ' )[ 0 ].toLowerCase();
			const byKeyword = (
				await mcp.callTool( 'publishio/get-patterns', { keyword } )
			).data;
			expect( byKeyword.map( ( p ) => p.name ) ).toContain(
				withCategory.name
			);
		}
	);

	test(
		'SMOKE-05.5 get-pattern and get-pattern-schema return content for a known pattern',
		{ tag: '@wp-update' },
		async ( { mcp } ) => {
			const all = ( await mcp.callTool( 'publishio/get-patterns' ) ).data;
			const name = all.find( ( p ) =>
				p.name.startsWith( `${ settings.theme }/` )
			).name;

			const pattern = await mcp.callTool( 'publishio/get-pattern', {
				name,
			} );
			expect( pattern.isError ).toBe( false );
			expect( pattern.data.content ).toContain( '<!-- wp:' );

			const schema = await mcp.callTool( 'publishio/get-pattern-schema', {
				name,
			} );
			expect( schema.isError ).toBe( false );
			expect( Array.isArray( schema.data ) ).toBe( true );
		}
	);

	test( 'SMOKE-05.6 unknown pattern returns a tool error, not a crash', async ( {
		mcp,
	} ) => {
		const res = await mcp.callTool( 'publishio/get-pattern-schema', {
			name: 'nope/does-not-exist',
		} );
		expect( res.isError ).toBe( true );
		expect( res.text ).toMatch( /No pattern found/ );
	} );

	test( 'SMOKE-05.7 requests without a session are rejected', async ( {
		anonApi,
		connection,
	} ) => {
		const res = await anonApi.post( connection.discovery.resource, {
			headers: {
				Authorization: `Bearer ${ connection.tokens.access_token }`,
				'Content-Type': 'application/json',
			},
			data: { jsonrpc: '2.0', id: 1, method: 'tools/list' },
		} );
		const body = await res.json();
		expect( JSON.stringify( body ) ).toContain( 'Mcp-Session-Id' );
	} );
} );
