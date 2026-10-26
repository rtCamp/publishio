/**
 * SMOKE-06: creating drafts over MCP.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { McpClient } from '../../utils/mcp';
import { connect } from '../../utils/oauth';
import { ensureUser, loginApi } from '../../utils/users';

test.describe(
	'MCP create draft',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test.afterAll( async ( { requestUtils } ) => {
			await requestUtils.deleteAllPosts();
			await requestUtils.deleteAllPages();
		} );

		for ( const [ index, postType ] of [ 'post', 'page' ].entries() ) {
			test(
				`SMOKE-06.${
					index + 1
				} create-post makes a ${ postType } draft`,
				{ tag: '@wp-update' },
				async ( { mcp, requestUtils } ) => {
					const title = `E2E ${ postType } ${ Date.now() }`;
					const res = await mcp.callTool( 'publishio/create-post', {
						title,
						post_type: postType,
					} );
					expect( res.isError, res.text ).toBe( false );
					expect( res.data.post_id ).toBeGreaterThan( 0 );
					expect( res.data.edit_url ).toContain(
						`post.php?post=${ res.data.post_id }`
					);

					const saved = await requestUtils.rest( {
						path: `/wp/v2/${ postType }s/${ res.data.post_id }`,
						params: { context: 'edit' },
					} );
					expect(
						saved.status,
						'MCP must never publish directly'
					).toBe( 'draft' );
					expect( saved.type ).toBe( postType );
					expect( saved.title.raw ).toBe( title );
				}
			);
		}

		test(
			'SMOKE-06.3 append-blocks adds valid block markup to a post',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const { data } = await mcp.callTool( 'publishio/create-post', {
					title: 'E2E append blocks',
				} );
				const markup =
					'<!-- wp:heading --><h2 class="wp-block-heading">Hello</h2><!-- /wp:heading -->' +
					'<!-- wp:paragraph --><p>World</p><!-- /wp:paragraph -->';

				const append = await mcp.callTool( 'publishio/append-blocks', {
					post_id: data.post_id,
					markup,
				} );
				expect( append.isError, append.text ).toBe( false );

				const post = await mcp.callTool( 'publishio/get-post', {
					post_id: data.post_id,
				} );
				expect( post.data.blocks.map( ( b ) => b.block_name ) ).toEqual(
					[ 'core/heading', 'core/paragraph' ]
				);
			}
		);

		test( 'SMOKE-06.4 pages refuse raw markup; posts refuse patterns', async ( {
			mcp,
		} ) => {
			const page = await mcp.callTool( 'publishio/create-post', {
				title: 'E2E page',
				post_type: 'page',
			} );
			const raw = await mcp.callTool( 'publishio/append-blocks', {
				post_id: page.data.post_id,
				markup: '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
			} );
			expect( raw.isError ).toBe( true );
			expect( raw.text ).toContain(
				'Raw block markup is not allowed for pages'
			);

			const post = await mcp.callTool( 'publishio/create-post', {
				title: 'E2E post',
			} );
			const pattern = await mcp.callTool(
				'publishio/insert-pattern-confirmed',
				{
					page_id: post.data.post_id,
					position: -1,
					pattern_name: 'twentytwentyfive/text-faqs',
					schema: [
						{ block: 'core/paragraph', fields: { content: 'x' } },
					],
				}
			);
			expect( pattern.isError ).toBe( true );
			expect( pattern.text ).toContain(
				'Patterns can be inserted here only into pages'
			);
		} );

		test( 'SMOKE-06.5 invalid input returns tool errors', async ( {
			mcp,
		} ) => {
			const badType = await mcp.callTool( 'publishio/create-post', {
				title: 'x',
				post_type: 'not_a_type',
			} );
			expect( badType.isError ).toBe( true );
			expect( badType.text ).toContain( 'Invalid post type' );

			const noTitle = await mcp.callTool( 'publishio/create-post', {} );
			expect( noTitle.isError ).toBe( true );
		} );

		test(
			'SMOKE-06.6 contributors can draft posts but not pages',
			{ tag: '@wp-update' },
			async ( { anonApi, requestUtils, playwright } ) => {
				const username = await ensureUser(
					requestUtils,
					'contributor'
				);
				const contributorApi = await loginApi( playwright, username );
				const { discovery, tokens } = await connect(
					anonApi,
					contributorApi
				);
				const mcp = new McpClient(
					anonApi,
					discovery.resource,
					tokens.access_token
				);
				await mcp.initialize();

				const post = await mcp.callTool( 'publishio/create-post', {
					title: 'Contributor post',
				} );
				expect( post.isError, post.text ).toBe( false );

				const page = await mcp.callTool( 'publishio/create-post', {
					title: 'Contributor page',
					post_type: 'page',
				} );
				expect( page.isError ).toBe( true );
				expect( page.text ).toContain(
					'You do not have permission to create this post type'
				);

				await contributorApi.dispose();
			}
		);
	}
);
