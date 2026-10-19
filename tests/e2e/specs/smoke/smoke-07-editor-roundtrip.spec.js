/**
 * SMOKE-07: MCP-built content round-trips through the block editor.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { BLOCK_WARNING } from '../../utils/editor';
import { settings } from '../../config/env';
/**
 * Names of blocks the editor marks invalid, at any depth.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 */
async function invalidBlocks( page ) {
	return page.evaluate( () => {
		const walk = ( blocks ) =>
			blocks.flatMap( ( b ) => [ b, ...walk( b.innerBlocks ?? [] ) ] );
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		return walk( blocks )
			.filter( ( b ) => b.isValid === false )
			.map( ( b ) => b.name );
	} );
}

/**
 * Pick a theme pattern for page content and fill its schema.
 *
 * @param {import('./utils/mcp').McpClient} mcp    Initialized MCP client.
 * @param {number}                          pageId ID of the page to build.
 */
async function buildPageFromPattern( mcp, pageId ) {
	const patterns = (
		await mcp.callTool( 'publishio/get-patterns' )
	).data.filter(
		( p ) =>
			p.name.startsWith( `${ settings.theme }/` ) &&
			p.block_types.length === 0 &&
			p.template_types.length === 0 &&
			( p.post_types.length === 0 || p.post_types.includes( 'page' ) )
	);

	for ( const p of patterns ) {
		// Skip nested wp:pattern blocks: the editor expands them, so markup never round-trips.
		const full = (
			await mcp.callTool( 'publishio/get-pattern', { name: p.name } )
		).data;
		if ( ! full?.content || full.content.includes( '<!-- wp:pattern ' ) ) {
			continue;
		}
		const schema = (
			await mcp.callTool( 'publishio/get-pattern-schema', {
				name: p.name,
			} )
		).data;
		if (
			! Array.isArray( schema ) ||
			! schema.some( ( e ) => 'content' in e.fields )
		) {
			continue;
		}
		const filled = schema.map( ( entry, i ) => ( {
			...entry,
			fields: Object.fromEntries(
				Object.entries( entry.fields ).map( ( [ k, v ] ) => [
					k,
					k === 'content' ? `E2E text ${ i }` : v,
				] )
			),
		} ) );
		const res = await mcp.callTool( 'publishio/insert-pattern-confirmed', {
			page_id: pageId,
			position: -1,
			pattern_name: p.name,
			schema: filled,
		} );
		expect( res.isError, `${ p.name }: ${ res.text }` ).toBe( false );
		return p.name;
	}
	throw new Error( `No fillable ${ settings.theme } pattern found.` );
}

test.describe(
	'Editor round-trip',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test.afterAll( async ( { requestUtils } ) => {
			await requestUtils.deleteAllPosts();
			await requestUtils.deleteAllPages();
		} );

		test(
			'SMOKE-07 MCP-built post opens without block warnings and round-trips',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page, requestUtils } ) => {
				const { data } = await mcp.callTool( 'publishio/create-post', {
					title: 'E2E round-trip post',
				} );
				const appended = await mcp.callTool(
					'publishio/append-blocks',
					{
						post_id: data.post_id,
						markup: [
							'<!-- wp:heading --><h2 class="wp-block-heading">Section</h2><!-- /wp:heading -->',
							'<!-- wp:paragraph --><p>Body with <strong>bold</strong> and <a href="https://example.com">a link</a>.</p><!-- /wp:paragraph -->',
							'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
							'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quote</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
						].join( '\n\n' ),
					}
				);
				expect( appended.isError, appended.text ).toBe( false );

				await assertRoundTrip( {
					admin,
					editor,
					page,
					requestUtils,
					id: data.post_id,
					type: 'posts',
				} );
			}
		);

		test(
			'SMOKE-07 MCP-built page from a theme pattern opens without block warnings',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page, requestUtils } ) => {
				const { data } = await mcp.callTool( 'publishio/create-post', {
					title: 'E2E round-trip page',
					post_type: 'page',
				} );
				const patternName = await buildPageFromPattern(
					mcp,
					data.post_id
				);
				test.info().annotations.push( {
					type: 'pattern',
					description: patternName,
				} );

				await assertRoundTrip( {
					admin,
					editor,
					page,
					requestUtils,
					id: data.post_id,
					type: 'pages',
				} );
			}
		);
	}
);

async function assertRoundTrip( {
	admin,
	editor,
	page,
	requestUtils,
	id,
	type,
} ) {
	const before = await requestUtils.rest( {
		path: `/wp/v2/${ type }/${ id }`,
		params: { context: 'edit' },
	} );

	await admin.editPost( id );
	await expect( editor.canvas.locator( BLOCK_WARNING ) ).toHaveCount( 0 );
	expect( await invalidBlocks( page ) ).toEqual( [] );

	// Trivial edit so Save is enabled.
	await editor.canvas.getByRole( 'textbox', { name: 'Add title' } ).click();
	await page.keyboard.press( 'End' );
	await page.keyboard.type( ' (saved)' );
	await editor.saveDraft();

	const after = await requestUtils.rest( {
		path: `/wp/v2/${ type }/${ id }`,
		params: { context: 'edit' },
	} );
	expect( after.status ).toBe( 'draft' );

	// The editor drops whitespace between tags when it re-serialises.
	const norm = ( s ) =>
		s.replace( /\s+/g, ' ' ).replace( />\s+</g, '><' ).trim();
	expect(
		norm( after.content.raw ),
		'editor re-serialised the content differently'
	).toBe( norm( before.content.raw ) );

	await page.reload();
	await expect( editor.canvas.locator( BLOCK_WARNING ) ).toHaveCount( 0 );
}
