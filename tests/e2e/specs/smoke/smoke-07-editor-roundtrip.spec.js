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
 * Insert a theme pattern into a page, then rewrite its first heading with replace-block.
 *
 * Patterns for template parts, queries or post content are skipped, since they don't belong in page content.
 *
 * @param {import('./utils/mcp').McpClient} mcp    Initialized MCP client.
 * @param {number}                          pageId ID of the page to build.
 */
async function buildPageFromPattern( mcp, pageId ) {
	const patterns = (
		await mcp.callTool( 'publishio/list-patterns', { post_type: 'page' } )
	).data.patterns.filter( ( p ) =>
		p.name.startsWith( `${ settings.theme }/` )
	);

	for ( const p of patterns ) {
		const full = (
			await mcp.callTool( 'publishio/get-pattern', { name: p.name } )
		).data;
		const skip = [
			'core/template-part',
			'core/post-content',
			'core/query',
		].some( ( name ) => full?.blocks_used.includes( name ) );
		const heading = full?.outline.rows.find( ( row ) =>
			/(^|> )\d+:heading$/.test( row.path )
		);
		if ( ! full || skip || ! heading ) {
			continue;
		}

		const inserted = await mcp.callTool( 'publishio/insert-pattern', {
			post_id: pageId,
			pattern: p.name,
		} );
		expect( inserted.isError, `${ p.name }: ${ inserted.text }` ).toBe(
			false
		);

		const path = inserted.data.outline.rows.find( ( row ) =>
			/(^|> )\d+:heading$/.test( row.path )
		).path;
		const replaced = await mcp.callTool( 'publishio/replace-block', {
			post_id: pageId,
			path,
			markup: '<!-- wp:heading --><h2 class="wp-block-heading">E2E heading</h2><!-- /wp:heading -->',
		} );
		expect( replaced.isError, replaced.text ).toBe( false );
		return p.name;
	}
	throw new Error(
		`No ${ settings.theme } page pattern with a heading found.`
	);
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
			'SMOKE-07.1 a post written by every block-edit tool opens without block warnings and round-trips',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page, requestUtils } ) => {
				const { data } = await mcp.callTool( 'publishio/create-post', {
					title: 'E2E round-trip post',
				} );
				const appended = await mcp.callTool(
					'publishio/insert-blocks',
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

				const edits = [
					[
						'publishio/duplicate-block',
						{ path: '1:paragraph', count: 1 },
					],
					[
						'publishio/move-block',
						{
							path: '2:paragraph',
							target: '0:heading',
							placement: 'before',
						},
					],
					[
						'publishio/replace-block',
						{
							path: '0:paragraph',
							markup: '<!-- wp:paragraph --><p>Replaced <em>text</em>.</p><!-- /wp:paragraph -->',
						},
					],
					[
						'publishio/insert-blocks',
						{
							target: '4:quote',
							placement: 'end',
							markup: '<!-- wp:paragraph --><p>Cited</p><!-- /wp:paragraph -->',
						},
					],
					[ 'publishio/delete-blocks', { paths: [ '3:list' ] } ],
				];
				for ( const [ name, args ] of edits ) {
					const res = await mcp.callTool( name, {
						post_id: data.post_id,
						...args,
					} );
					expect( res.isError, `${ name }: ${ res.text }` ).toBe(
						false
					);
				}

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
			'SMOKE-07.2 a page built with insert-pattern and replace-block opens without block warnings',
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

		test(
			'SMOKE-07.3 a synced pattern with override values opens without block warnings',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page, requestUtils } ) => {
				const pattern = await requestUtils.rest( {
					method: 'POST',
					path: '/wp/v2/blocks',
					data: {
						title: 'E2E footer CTA',
						status: 'publish',
						content:
							'<!-- wp:heading {"metadata":{"name":"CTA heading","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Ready?</h2><!-- /wp:heading -->',
					},
				} );
				const { data } = await mcp.callTool( 'publishio/create-post', {
					title: 'E2E synced pattern post',
				} );

				const inserted = await mcp.callTool(
					'publishio/insert-pattern',
					{
						post_id: data.post_id,
						pattern: `core/block/${ pattern.id }`,
					}
				);
				expect( inserted.isError, inserted.text ).toBe( false );
				expect( inserted.data.changed ).toEqual( [ '0:block' ] );

				const unknown = await mcp.callTool( 'publishio/replace-block', {
					post_id: data.post_id,
					path: '0:block',
					markup: `<!-- wp:block {"ref":${ pattern.id },"content":{"Nope":{"content":"x"}}} /-->`,
				} );
				expect( unknown.text ).toMatch( /^unknown_override: / );

				const replaced = await mcp.callTool(
					'publishio/replace-block',
					{
						post_id: data.post_id,
						path: '0:block',
						markup: `<!-- wp:block {"ref":${ pattern.id },"content":{"CTA heading":{"content":"Let's talk"}}} /-->`,
					}
				);
				expect( replaced.isError, replaced.text ).toBe( false );

				await assertRoundTrip( {
					admin,
					editor,
					page,
					requestUtils,
					id: data.post_id,
					type: 'posts',
				} );
				await expect(
					editor.canvas.getByText( "Let's talk" )
				).toBeVisible();

				await requestUtils.rest( {
					method: 'DELETE',
					path: `/wp/v2/blocks/${ pattern.id }`,
					params: { force: true },
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
