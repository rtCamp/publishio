/**
 * SKILL: skills/publishio/SKILL.md checked against the plugin.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { BLOCK_WARNING } from '../../utils/editor';
import { wpCli } from '../../utils/wp-cli';
import { collectErrors, evidence } from '../install/helpers';
import { currentSite } from '../../config/env';
import { markDebugLog } from '../../utils/debug-log';
import { STATUS_PAGE } from '../../utils/status';
import { requiresTwentyTwentyFive } from '../../utils/theme';

const PARAGRAPH = ( text ) =>
	`<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`;

// The plugin inside the wp-env containers (mounted sites only).
const PLUGIN_DIR = 'wp-content/plugins/publishio';

/**
 * Create a draft through Publishio and return its ID.
 *
 * @param {import('../../utils/mcp').McpClient} mcp      MCP client.
 * @param {string}                              title    Title.
 * @param {'post'|'page'}                       postType Post type.
 * @return {Promise<number>} The new post ID.
 */
async function draft( mcp, title, postType = 'post' ) {
	const res = await mcp.callTool( 'publishio/create-post', {
		title,
		post_type: postType,
	} );
	expect( res.isError, res.text ).toBe( false );
	return res.data.post_id;
}

const quietly = ( cmd ) => {
	try {
		wpCli( cmd );
	} catch {}
};

test.describe(
	'Skill workflow (SKILL.md)',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test( 'SKILL-01 everything stays a draft: no tool accepts a status or content override', async ( {
			mcp,
		} ) => {
			const publish = await mcp.callTool( 'publishio/create-post', {
				title: 'Should not publish',
				status: 'publish',
			} );
			expect( publish.isError ).toBe( true );
			expect( publish.text ).toContain( 'status' );

			const id = await draft( mcp, 'SKILL-01 draft' );
			for ( const field of [ 'content', 'status' ] ) {
				const res = await mcp.callTool( 'publishio/update-post', {
					post_id: id,
					[ field ]: field === 'status' ? 'publish' : 'Replaced',
				} );
				expect( res.isError, field ).toBe( true );
				expect( res.text ).toContain( field );
			}

			const saved = (
				await mcp.callTool( 'publishio/get-post', { post_id: id } )
			).data;
			expect( saved.status ).toBe( 'draft' );
			expect( saved.content ).toBe( '' );
		} );

		test(
			'SKILL-02 posts take block markup by position; pages refuse it; delete-block-at works on both',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				requiresTwentyTwentyFive();
				const postId = await draft( mcp, 'SKILL-02 post' );
				await mcp.callTool( 'publishio/append-blocks', {
					post_id: postId,
					markup: PARAGRAPH( 'First' ) + PARAGRAPH( 'Third' ),
				} );

				const inserted = await mcp.callTool(
					'publishio/insert-blocks-at',
					{
						post_id: postId,
						position: 1,
						markup: PARAGRAPH( 'Second' ),
					}
				);
				expect( inserted.isError, inserted.text ).toBe( false );
				let content = (
					await mcp.callTool( 'publishio/get-post', {
						post_id: postId,
					} )
				).data.content;
				expect( content.indexOf( 'First' ) ).toBeLessThan(
					content.indexOf( 'Second' )
				);
				expect( content.indexOf( 'Second' ) ).toBeLessThan(
					content.indexOf( 'Third' )
				);

				const deleted = await mcp.callTool(
					'publishio/delete-block-at',
					{
						post_id: postId,
						position: 0,
					}
				);
				expect( deleted.isError, deleted.text ).toBe( false );
				content = (
					await mcp.callTool( 'publishio/get-post', {
						post_id: postId,
					} )
				).data.content;
				expect( content ).not.toContain( 'First' );
				expect( content ).toContain( 'Second' );

				const outOfRange = await mcp.callTool(
					'publishio/delete-block-at',
					{
						post_id: postId,
						position: 9,
					}
				);
				expect( outOfRange.isError ).toBe( true );
				expect( outOfRange.text ).toMatch( /out of range/ );

				const pageId = await draft( mcp, 'SKILL-02 page', 'page' );
				const pageInsert = await mcp.callTool(
					'publishio/insert-blocks-at',
					{
						post_id: pageId,
						position: 0,
						markup: PARAGRAPH( 'Not on pages' ),
					}
				);
				expect( pageInsert.isError ).toBe( true );
				expect( pageInsert.text ).toContain(
					'Raw block markup is not allowed for pages'
				);

				await mcp.callTool( 'publishio/insert-pattern-confirmed', {
					page_id: pageId,
					position: -1,
					pattern_name: 'twentytwentyfive/cta-centered-heading',
					schema: [
						{
							block: 'core/heading',
							fields: { content: 'Remove me' },
						},
					],
				} );
				const pageDelete = await mcp.callTool(
					'publishio/delete-block-at',
					{
						post_id: pageId,
						position: 0,
					}
				);
				expect( pageDelete.isError, pageDelete.text ).toBe( false );
				expect(
					(
						await mcp.callTool( 'publishio/get-post', {
							post_id: pageId,
						} )
					).data.content
				).not.toContain( 'Remove me' );
			}
		);

		test(
			'SKILL-03 preview before insert: render-pattern shows the filled pattern; preview-pattern leaves the page unchanged',
			{ tag: '@wp-update' },
			async ( { mcp, page } ) => {
				requiresTwentyTwentyFive();
				const name = 'twentytwentyfive/cta-centered-heading';
				const schema = (
					await mcp.callTool( 'publishio/get-pattern-schema', {
						name,
					} )
				).data;
				const filled = schema.map( ( entry ) =>
					entry.block === 'core/heading'
						? {
								block: entry.block,
								fields: { content: 'Preview headline' },
						  }
						: { block: entry.block, fields: {} }
				);

				const rendered = await mcp.callTool(
					'publishio/render-pattern',
					{
						pattern_name: name,
						schema: filled,
					}
				);
				expect( rendered.isError, rendered.text ).toBe( false );
				expect( rendered.data.preview_html ).toMatch(
					/^<!DOCTYPE html>/i
				);
				expect( rendered.data.preview_html ).toContain(
					'Preview headline'
				);
				await page.setContent( rendered.data.preview_html );
				await expect(
					page.getByRole( 'heading', { name: 'Preview headline' } )
				).toBeVisible();
				await evidence( page, 'SKILL-03 render-pattern preview' );

				const pageId = await draft( mcp, 'SKILL-03 page', 'page' );
				const preview = await mcp.callTool(
					'publishio/preview-pattern',
					{
						page_id: pageId,
						position: 0,
						pattern_name: name,
						schema: filled,
					}
				);
				expect( preview.isError, preview.text ).toBe( false );
				expect( preview.data ).toMatchObject( {
					page_id: pageId,
					position: 0,
					pattern_name: name,
				} );
				expect(
					(
						await mcp.callTool( 'publishio/get-post', {
							post_id: pageId,
						} )
					).data.content
				).toBe( '' );
			}
		);

		test( 'SKILL-04 landing pages: append a section through the WordPress REST API, as the skill says', async ( {
			mcp,
			anonApi,
			connection,
		} ) => {
			test.fixme(
				true,
				'SKILL.md step 8 tells clients to write page content with POST /wp/v2/pages/{id}, but the ' +
					'Publishio token is only accepted on the MCP endpoint (401). insert-pattern-confirmed works.'
			);
			const pageId = await draft( mcp, 'SKILL-04 page', 'page' );
			const current = (
				await mcp.callTool( 'publishio/get-post', { post_id: pageId } )
			).data.content;
			const res = await anonApi.post(
				`/wp-json/wp/v2/pages/${ pageId }`,
				{
					headers: {
						Authorization: `Bearer ${ connection.tokens.access_token }`,
					},
					data: {
						content: current + PARAGRAPH( 'Appended over REST' ),
					},
				}
			);
			expect( res.status(), await res.text() ).toBe( 200 );
		} );

		test( 'SKILL-05 repeating items: removing an entry shows one item fewer', async ( {
			mcp,
		} ) => {
			requiresTwentyTwentyFive();
			test.fixme(
				true,
				'SKILL.md: "You may reduce count by removing entries." Schemas are a flat list applied by ' +
					'position, so removing an entry shifts the text instead of removing the item.'
			);
			const name = 'twentytwentyfive/services-3-col';
			const schema = (
				await mcp.callTool( 'publishio/get-pattern-schema', { name } )
			).data;
			const images = schema.filter(
				( e ) => e.block === 'core/image'
			).length;
			expect( images ).toBe( 3 );

			// Drop the third service (the last three entries).
			const pageId = await draft( mcp, 'SKILL-05 page', 'page' );
			const res = await mcp.callTool(
				'publishio/insert-pattern-confirmed',
				{
					page_id: pageId,
					position: -1,
					pattern_name: name,
					schema: schema.slice( 0, -3 ),
				}
			);
			expect( res.isError, res.text ).toBe( false );
			const content = (
				await mcp.callTool( 'publishio/get-post', { post_id: pageId } )
			).data.content;
			expect( content.match( /<!-- wp:image/g ) ?? [] ).toHaveLength(
				images - 1
			);
		} );

		test(
			'SKILL-06 media: find a library image, use it as featured image and in image and cover blocks',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page } ) => {
				requiresTwentyTwentyFive();
				const keyword = `skill06${ Date.now() }`;
				const alt = 'Publishio icon';
				const attachmentId = Number(
					wpCli(
						`media import ${ PLUGIN_DIR }/.wordpress-org/icon-256x256.png --title=${ keyword } --alt="${ alt }" --porcelain`
					)
				);
				expect( attachmentId ).toBeGreaterThan( 0 );
				test.info().attach( 'attachment', {
					body: String( attachmentId ),
				} );

				try {
					const image =
						await test.step( 'search-attachments finds it', async () => {
							const found = await mcp.callTool(
								'publishio/search-attachments',
								{ search: keyword, mime_type: 'image' }
							);
							expect( found.isError, found.text ).toBe( false );
							expect( found.data ).toHaveLength( 1 );
							expect( found.data[ 0 ] ).toMatchObject( {
								id: attachmentId,
								title: keyword,
								alt,
								mime_type: 'image/png',
							} );
							expect( found.data[ 0 ].url ).toMatch( /\.png$/ );

							const none = await mcp.callTool(
								'publishio/search-attachments',
								{ search: `${ keyword }-no-such-image` }
							);
							expect( none.isError, none.text ).toBe( false );
							expect( none.data ).toEqual( [] );
							const notVideo = await mcp.callTool(
								'publishio/search-attachments',
								{ search: keyword, mime_type: 'video' }
							);
							expect( notVideo.data ).toEqual( [] );
							return found.data[ 0 ];
						} );

					const postId = await draft( mcp, 'SKILL-06 post' );
					await test.step( 'set-featured-image uses it', async () => {
						const featured = await mcp.callTool(
							'publishio/set-featured-image',
							{ post_id: postId, attachment_id: attachmentId }
						);
						expect( featured.isError, featured.text ).toBe( false );
						expect(
							(
								await mcp.callTool( 'publishio/get-post', {
									post_id: postId,
								} )
							).data.featured_image?.id
						).toBe( attachmentId );

						const missing = await mcp.callTool(
							'publishio/set-featured-image',
							{ post_id: postId, attachment_id: 999999 }
						);
						expect( missing.isError ).toBe( true );
						expect( missing.text ).toContain(
							'Attachment not found'
						);
					} );

					await test.step( 'an image block in a post uses it', async () => {
						const appended = await mcp.callTool(
							'publishio/append-blocks',
							{
								post_id: postId,
								markup: `<!-- wp:image {"id":${ attachmentId },"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="${ image.url }" alt="${ alt }" class="wp-image-${ attachmentId }"/></figure><!-- /wp:image -->`,
							}
						);
						expect( appended.isError, appended.text ).toBe( false );
						await admin.visitAdminPage(
							'post.php',
							`post=${ postId }&action=edit`
						);
						await expect(
							editor.canvas.getByRole( 'img', { name: alt } )
						).toBeVisible();
						await expect(
							editor.canvas.locator( BLOCK_WARNING )
						).toHaveCount( 0 );
						await evidence(
							page,
							'SKILL-06 post with image block'
						);
					} );

					// Fill image fields in schema order: writing url drops the wp-image-<id> class.
					const fillMedia = async ( name, blockName ) => {
						const schema = (
							await mcp.callTool(
								'publishio/get-pattern-schema',
								{
									name,
								}
							)
						).data;
						expect(
							schema.map( ( e ) => e.block ),
							`${ name } has a ${ blockName }`
						).toContain( blockName );
						return schema.map( ( entry ) =>
							entry.block === blockName
								? {
										block: entry.block,
										fields: Object.fromEntries(
											Object.keys( entry.fields ).map(
												( key ) => [
													key,
													{
														url: image.url,
														alt,
														id: String(
															attachmentId
														),
													}[ key ] ??
														entry.fields[ key ],
												]
											)
										),
								  }
								: { block: entry.block, fields: {} }
						);
					};

					const pageId = await draft( mcp, 'SKILL-06 page', 'page' );
					for ( const [ name, blockName ] of [
						[ 'twentytwentyfive/banner-intro-image', 'core/image' ],
						[
							'twentytwentyfive/hero-full-width-image',
							'core/cover',
						],
					] ) {
						await test.step( `${ blockName } from ${ name } uses it`, async () => {
							const inserted = await mcp.callTool(
								'publishio/insert-pattern-confirmed',
								{
									page_id: pageId,
									position: -1,
									pattern_name: name,
									schema: await fillMedia( name, blockName ),
								}
							);
							expect( inserted.isError, inserted.text ).toBe(
								false
							);
						} );
					}

					await test.step( 'the page holds both blocks and opens cleanly', async () => {
						const content = (
							await mcp.callTool( 'publishio/get-post', {
								post_id: pageId,
							} )
						).data.content;
						expect( content ).toMatch(
							new RegExp(
								`<!-- wp:image \\{[^\\n]*?"id":${ attachmentId }[,}]`
							)
						);
						expect( content ).toMatch(
							new RegExp(
								`<!-- wp:cover \\{[^\\n]*?"id":${ attachmentId }[,}]`
							)
						);
						expect(
							content.split( image.url ).length - 1,
							'image URL in the image and cover blocks'
						).toBeGreaterThanOrEqual( 2 );

						await admin.visitAdminPage(
							'post.php',
							`post=${ pageId }&action=edit`
						);
						await expect(
							editor.canvas.getByRole( 'img', { name: alt } )
						).toHaveCount( 2 );
						await expect(
							editor.canvas.locator( BLOCK_WARNING )
						).toHaveCount( 0 );
						await evidence(
							page,
							'SKILL-06 page with image and cover blocks'
						);
					} );
				} finally {
					quietly( `post delete ${ attachmentId } --force` );
				}
			}
		);

		test(
			'SKILL-07 taxonomies: list, assign and read back terms; unknown terms are refused',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				quietly(
					'term create category "SKILL News" --slug=skill-news'
				);
				quietly( 'term create post_tag "SKILL Tag" --slug=skill-tag' );

				const categories = await mcp.callTool(
					'publishio/get-taxonomy-terms',
					{
						taxonomy: 'category',
					}
				);
				expect( categories.isError, categories.text ).toBe( false );
				expect(
					categories.data.terms.map( ( t ) => t.slug )
				).toContain( 'skill-news' );
				const tags = await mcp.callTool(
					'publishio/get-taxonomy-terms',
					{
						taxonomy: 'post_tag',
					}
				);
				expect( tags.data.terms.map( ( t ) => t.slug ) ).toContain(
					'skill-tag'
				);

				const postId = await draft( mcp, 'SKILL-07 post' );
				for ( const [ taxonomy, slug ] of [
					[ 'category', 'skill-news' ],
					[ 'post_tag', 'skill-tag' ],
				] ) {
					const set = await mcp.callTool(
						'publishio/set-post-terms',
						{
							post_id: postId,
							taxonomy,
							terms: [ slug ],
						}
					);
					expect( set.isError, set.text ).toBe( false );
				}
				const assigned = (
					await mcp.callTool( 'publishio/get-post-terms', {
						post_id: postId,
					} )
				).data.taxonomies;
				expect( assigned.category.map( ( t ) => t.slug ) ).toEqual( [
					'skill-news',
				] );
				expect( assigned.post_tag.map( ( t ) => t.slug ) ).toEqual( [
					'skill-tag',
				] );

				const unknown = await mcp.callTool(
					'publishio/set-post-terms',
					{
						post_id: postId,
						taxonomy: 'post_tag',
						terms: [ 'skill-made-up' ],
					}
				);
				expect( unknown.isError ).toBe( true );
				expect( unknown.text ).toContain( 'not found' );
			}
		);

		test( 'SKILL-08 new terms: create a tag through the WordPress REST API, as the skill says', async ( {
			anonApi,
			connection,
		} ) => {
			test.fixme(
				true,
				'SKILL.md: "if confirmed, create via the WordPress REST API". The Publishio token is only ' +
					'accepted on the MCP endpoint, so this is refused (401) and no MCP tool creates terms.'
			);
			const res = await anonApi.post( '/wp-json/wp/v2/tags', {
				headers: {
					Authorization: `Bearer ${ connection.tokens.access_token }`,
				},
				data: { name: `SKILL confirmed ${ Date.now() }` },
			} );
			expect( res.status(), await res.text() ).toBe( 201 );
		} );

		test(
			'SKILL-09 search-posts finds existing posts and pages with their links',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const postId = await draft( mcp, 'SKILL-09 searchable post' );
				const pageId = await draft(
					mcp,
					'SKILL-09 searchable page',
					'page'
				);

				const all = await mcp.callTool( 'publishio/search-posts', {
					search: 'SKILL-09 searchable',
					post_type: 'any',
				} );
				expect( all.isError, all.text ).toBe( false );
				const ids = all.data.map( ( p ) => p.post_id );
				expect( ids ).toEqual( expect.arrayContaining( [ postId ] ) );

				const pages = await mcp.callTool( 'publishio/search-posts', {
					search: 'SKILL-09 searchable',
					post_type: 'page',
				} );
				expect( pages.data.map( ( p ) => p.post_id ) ).toEqual( [
					pageId,
				] );
				expect( pages.data[ 0 ] ).toMatchObject( {
					post_type: 'page',
					status: 'draft',
				} );
				expect( pages.data[ 0 ].edit_url ).toContain(
					`post=${ pageId }`
				);
			}
		);

		test(
			'SKILL-10 SEO without Yoast: the plugin works without errors, slug and excerpt are set, the Yoast tools fail cleanly',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page } ) => {
				// Yoast mapped in wp-env config can't be deleted; inactive is enough there.
				quietly( 'plugin deactivate wordpress-seo' );
				const mapped = fs
					.readFileSync( currentSite().wpEnvConfig, 'utf8' )
					.includes( 'wordpress-seo' );
				if ( ! mapped ) {
					quietly( 'plugin delete wordpress-seo' );
				}
				test.info().annotations.push( {
					type: 'Yoast SEO',
					description: mapped
						? 'installed (mapped by wp-env), inactive'
						: 'not installed',
				} );
				expect(
					wpCli( 'plugin list --status=active --field=name' )
				).not.toContain( 'wordpress-seo' );
				const newLogProblems = markDebugLog();
				const errors = collectErrors( page );

				await test.step( 'MCP tools load', async () => {
					const tools = ( await mcp.listTools() ).map(
						( t ) => t.name
					);
					expect( tools ).toEqual(
						expect.arrayContaining( [
							'publishio-create-post',
							'publishio-update-post',
							'publishio-get-yoast-meta',
							'publishio-set-yoast-meta',
						] )
					);
				} );

				const postId = await draft( mcp, 'SKILL-10 post' );
				await test.step( 'slug and excerpt are saved with update-post', async () => {
					const updated = await mcp.callTool(
						'publishio/update-post',
						{
							post_id: postId,
							slug: 'skill-10-seo',
							excerpt: 'A short meta description for SKILL-10.',
						}
					);
					expect( updated.isError, updated.text ).toBe( false );
					const saved = (
						await mcp.callTool( 'publishio/get-post', {
							post_id: postId,
						} )
					).data;
					expect( saved.slug ).toBe( 'skill-10-seo' );
					expect( saved.excerpt ).toContain(
						'A short meta description'
					);
				} );

				await test.step( 'the Yoast tools return a clear error', async () => {
					const probe = await mcp.callTool(
						'publishio/get-yoast-meta',
						{
							post_id: postId,
						}
					);
					expect( probe.isError ).toBe( true );
					expect( probe.text ).toContain(
						'Yoast SEO is not installed or active'
					);
					const set = await mcp.callTool(
						'publishio/set-yoast-meta',
						{
							post_id: postId,
							seo_title: 'SKILL-10 SEO title',
						}
					);
					expect( set.isError ).toBe( true );
					expect( set.text ).toContain(
						'Yoast SEO is not installed or active'
					);
				} );

				await test.step( 'admin screens and the editor load without errors', async () => {
					await admin.visitAdminPage( 'admin.php', 'page=publishio' );
					await expect(
						page.getByRole( 'heading', { level: 1, name: 'Guide' } )
					).toBeVisible();
					await admin.visitAdminPage(
						'admin.php',
						'page=publishio-connections'
					);
					await expect(
						page.getByRole( 'heading', {
							level: 1,
							name: 'Connections',
						} )
					).toBeVisible();
					await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
					await expect(
						page.getByRole( 'heading', {
							level: 1,
							name: 'Status',
						} )
					).toBeVisible();
					await admin.visitAdminPage(
						'post.php',
						`post=${ postId }&action=edit`
					);
					await expect(
						editor.canvas.getByRole( 'textbox', {
							name: 'Add title',
						} )
					).toHaveText( 'SKILL-10 post' );
					await evidence( page, 'SKILL-10 editor without Yoast' );
				} );

				expect( errors, 'browser errors' ).toEqual( [] );
				expect(
					newLogProblems(),
					'PHP errors from Publishio in debug.log'
				).toEqual( [] );
			}
		);

		test.describe( 'with Yoast SEO active', () => {
			test.beforeAll( () => {
				wpCli( 'plugin install wordpress-seo --activate' );
			} );
			test.afterAll( () => {
				quietly( 'plugin deactivate wordpress-seo' );
			} );

			test(
				'SKILL-11 SEO with Yoast: set-yoast-meta saves every field the skill lists',
				{ tag: '@wp-update' },
				async ( { mcp } ) => {
					const pageId = await draft( mcp, 'SKILL-11 page', 'page' );
					const probe = await mcp.callTool(
						'publishio/get-yoast-meta',
						{
							post_id: pageId,
						}
					);
					expect( probe.isError, probe.text ).toBe( false );

					const fields = {
						seo_title: 'SKILL-11 SEO title',
						meta_description:
							'SKILL-11 meta description under 155 characters.',
						focus_keyphrase: 'publishio pages',
						og_title: 'SKILL-11 OG title',
						og_description: 'SKILL-11 OG description',
						twitter_title: 'SKILL-11 Twitter title',
						twitter_description: 'SKILL-11 Twitter description',
						schema_page_type: 'WebPage',
					};
					const set = await mcp.callTool(
						'publishio/set-yoast-meta',
						{
							post_id: pageId,
							...fields,
						}
					);
					expect( set.isError, set.text ).toBe( false );

					const read = await mcp.callTool(
						'publishio/get-yoast-meta',
						{
							post_id: pageId,
						}
					);
					expect( read.data ).toMatchObject( fields );
				}
			);
		} );
	}
);
