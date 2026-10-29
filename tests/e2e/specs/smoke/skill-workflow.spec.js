/**
 * SKILL: skills/publishio/SKILL.md checked against the plugin.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { BLOCK_WARNING } from '../../utils/editor';
import { ALREADY, wpCli, wpCliQuietly } from '../../utils/wp-cli';
import { collectErrors, evidence } from '../install/helpers';
import { markDebugLog } from '../../utils/debug-log';
import { compareVersions } from '../../utils/plugin-zip';
import { STATUS_PAGE } from '../../utils/status';
import { requiresTwentyTwentyFive } from '../../utils/theme';

const PARAGRAPH = ( text ) =>
	`<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`;

// The plugin inside the wp-env containers (mounted sites only).
const PLUGIN_DIR = 'wp-content/plugins/publishio';

/** Yoast SEO 28.7: the last release that supports WordPress 6.9 (28.8 needs 7.0). */
const YOAST_FOR_WP69 = {
	version: '28.7',
	zip: 'https://downloads.wordpress.org/plugin/wordpress-seo.28.7.zip',
};

/**
 * Yoast SEO to install for SKILL-11: the latest release on WordPress 7.0 and later, so new Yoast
 * releases are covered, and 28.7 below that. Installed by zip URL because
 * `wp plugin install wordpress-seo --version=…` checks the latest release's requirements and
 * refuses on 6.9.
 *
 * @return {{ source: string, version?: string }} What to pass to `wp plugin install`, and the
 *                                                 version it must install, when pinned.
 */
const yoastToInstall = () =>
	compareVersions( wpCli( 'core version' ), '7.0' ) < 0
		? { source: YOAST_FOR_WP69.zip, version: YOAST_FOR_WP69.version }
		: { source: 'wordpress-seo' };

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

/**
 * Replace the text of a block's first heading, paragraph or link.
 *
 * @param {string} markup The block's markup.
 * @param {string} text   The new text.
 * @return {string} The markup with the new text.
 */
const withText = ( markup, text ) =>
	markup.replace(
		/(<(h[1-6]|p|a)\b[^>]*>)[\s\S]*?(<\/\2>)/,
		`$1${ text }$3`
	);

/**
 * The first outline row inside the inserted blocks whose path ends with a block name.
 *
 * @param {{changed: string[], outline: Object}} result An insert result.
 * @param {string}                               name   The block's short name.
 * @return {string} The row's path.
 */
const pathIn = ( result, name ) =>
	result.outline.rows.find(
		( row ) =>
			result.changed.some(
				( root ) =>
					row.path === root || row.path.startsWith( `${ root } > ` )
			) && row.path.endsWith( `:${ name }` )
	)?.path;

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
			expect( publish.text ).toBe(
				'Ability "publishio/create-post" has invalid input. Reason: status is not a valid property of Object.'
			);

			const id = await draft( mcp, 'SKILL-01 draft' );
			for ( const field of [ 'content', 'status' ] ) {
				const res = await mcp.callTool( 'publishio/update-post', {
					post_id: id,
					[ field ]: field === 'status' ? 'publish' : 'Replaced',
				} );
				expect( res.isError, field ).toBe( true );
				expect( res.text ).toBe(
					`Ability "publishio/update-post" has invalid input. Reason: ${ field } is not a valid property of Object.`
				);
			}

			const saved = (
				await mcp.callTool( 'publishio/get-post', { post_id: id } )
			).data;
			expect( saved.status ).toBe( 'draft' );
			expect( saved.outline.rows ).toEqual( [] );
		} );

		test(
			'SKILL-02 any post type takes block markup at a path; delete-blocks removes by path',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const postId = await draft( mcp, 'SKILL-02 post' );
				const added = await mcp.callTool( 'publishio/insert-blocks', {
					post_id: postId,
					markup: PARAGRAPH( 'First' ) + PARAGRAPH( 'Third' ),
				} );
				expect( added.isError, added.text ).toBe( false );

				const inserted = await mcp.callTool(
					'publishio/insert-blocks',
					{
						post_id: postId,
						target: '1:paragraph',
						placement: 'before',
						markup: PARAGRAPH( 'Second' ),
					}
				);
				expect( inserted.isError, inserted.text ).toBe( false );
				expect( inserted.data.changed ).toEqual( [ '1:paragraph' ] );
				let content = (
					await mcp.callTool( 'publishio/get-post-content', {
						post_id: postId,
					} )
				).data.markup;
				expect( content.indexOf( 'First' ) ).toBeLessThan(
					content.indexOf( 'Second' )
				);
				expect( content.indexOf( 'Second' ) ).toBeLessThan(
					content.indexOf( 'Third' )
				);

				const deleted = await mcp.callTool( 'publishio/delete-blocks', {
					post_id: postId,
					paths: [ '0:paragraph' ],
				} );
				expect( deleted.isError, deleted.text ).toBe( false );
				content = (
					await mcp.callTool( 'publishio/get-post-content', {
						post_id: postId,
					} )
				).data.markup;
				expect( content ).not.toContain( 'First' );
				expect( content ).toContain( 'Second' );

				const outOfRange = await mcp.callTool(
					'publishio/delete-blocks',
					{
						post_id: postId,
						paths: [ '9:paragraph' ],
					}
				);
				expect( outOfRange.isError ).toBe( true );
				expect( outOfRange.text ).toContain( 'path_not_found' );

				const pageId = await draft( mcp, 'SKILL-02 page', 'page' );
				const pageInsert = await mcp.callTool(
					'publishio/insert-blocks',
					{
						post_id: pageId,
						markup: PARAGRAPH( 'Also on pages' ),
					}
				);
				expect( pageInsert.isError, pageInsert.text ).toBe( false );
				const pageDelete = await mcp.callTool(
					'publishio/delete-blocks',
					{
						post_id: pageId,
						paths: [ '0:paragraph' ],
					}
				);
				expect( pageDelete.isError, pageDelete.text ).toBe( false );
				expect(
					(
						await mcp.callTool( 'publishio/get-post-content', {
							post_id: pageId,
						} )
					).data.markup
				).toBe( '' );
			}
		);

		test(
			'SKILL-03 dry run: render-blocks checks and renders edited pattern markup without saving',
			{ tag: '@wp-update' },
			async ( { mcp, page } ) => {
				requiresTwentyTwentyFive();
				const { markup } = (
					await mcp.callTool( 'publishio/get-pattern', {
						name: 'twentytwentyfive/cta-centered-heading',
					} )
				).data;
				const pageId = await draft( mcp, 'SKILL-03 page', 'page' );

				const rendered = await mcp.callTool(
					'publishio/render-blocks',
					{
						markup: withText( markup, 'Preview headline' ),
						post_id: pageId,
					}
				);
				expect( rendered.isError, rendered.text ).toBe( false );
				expect(
					rendered.data.valid,
					JSON.stringify( rendered.data.errors )
				).toBe( true );
				await page.setContent( rendered.data.html );
				await expect(
					page.getByRole( 'heading', { name: 'Preview headline' } )
				).toBeVisible();
				await evidence( page, 'SKILL-03 render-blocks preview' );

				const invalid = await mcp.callTool( 'publishio/render-blocks', {
					markup: '<!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->',
					post_id: pageId,
				} );
				expect( invalid.data.valid ).toBe( false );
				expect( invalid.data.errors.map( ( e ) => e.code ) ).toContain(
					'invalid_parent'
				);

				expect(
					(
						await mcp.callTool( 'publishio/get-post-content', {
							post_id: pageId,
						} )
					).data.markup
				).toBe( '' );
			}
		);

		test( 'SKILL-04 landing pages: each section is added at the end, one per call; the token cannot write pages over the REST API', async ( {
			mcp,
			anonApi,
			connection,
		} ) => {
			requiresTwentyTwentyFive();
			const pageId = await draft( mcp, 'SKILL-04 page', 'page' );
			for ( const headline of [ 'First section', 'Second section' ] ) {
				const res = await mcp.callTool( 'publishio/insert-pattern', {
					post_id: pageId,
					pattern: 'twentytwentyfive/cta-centered-heading',
				} );
				expect( res.isError, res.text ).toBe( false );

				const path = pathIn( res.data, 'heading' );
				const { markup } = (
					await mcp.callTool( 'publishio/get-post-content', {
						post_id: pageId,
						path,
					} )
				).data;
				const replaced = await mcp.callTool(
					'publishio/replace-block',
					{
						post_id: pageId,
						path,
						markup: withText( markup, headline ),
					}
				);
				expect( replaced.isError, replaced.text ).toBe( false );
			}
			const content = (
				await mcp.callTool( 'publishio/get-post-content', {
					post_id: pageId,
				} )
			).data.markup;
			expect( content.indexOf( 'First section' ) ).toBeGreaterThan( -1 );
			expect( content.indexOf( 'Second section' ) ).toBeGreaterThan(
				content.indexOf( 'First section' )
			);

			const rest = await anonApi.post(
				`/wp-json/wp/v2/pages/${ pageId }`,
				{
					headers: {
						Authorization: `Bearer ${ connection.tokens.access_token }`,
					},
					data: {
						content: content + PARAGRAPH( 'Appended over REST' ),
					},
				}
			);
			expect( rest.status(), await rest.text() ).toBe( 401 );
		} );

		test( 'SKILL-05 repeating items: delete-blocks removes an item and duplicate-block adds copies', async ( {
			mcp,
		} ) => {
			requiresTwentyTwentyFive();
			const pageId = await draft( mcp, 'SKILL-05 page', 'page' );
			const res = await mcp.callTool( 'publishio/insert-pattern', {
				post_id: pageId,
				pattern: 'twentytwentyfive/services-3-col',
			} );
			expect( res.isError, res.text ).toBe( false );

			const columns = res.data.outline.rows
				.map( ( row ) => row.path )
				.filter( ( path ) => /:column$/.test( path ) );
			expect( columns ).toHaveLength( 3 );

			const removed = await mcp.callTool( 'publishio/delete-blocks', {
				post_id: pageId,
				paths: [ columns[ 2 ] ],
			} );
			expect( removed.isError, removed.text ).toBe( false );

			const copied = await mcp.callTool( 'publishio/duplicate-block', {
				post_id: pageId,
				path: columns[ 0 ],
				count: 2,
			} );
			expect( copied.isError, copied.text ).toBe( false );
			expect( copied.data.changed ).toHaveLength( 2 );

			const content = (
				await mcp.callTool( 'publishio/get-post-content', {
					post_id: pageId,
				} )
			).data.markup;
			expect( content.match( /<!-- wp:column[ {]/g ) ?? [] ).toHaveLength(
				4
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
							'publishio/insert-blocks',
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

					// Point a block at the attachment, as an AI edits the block's markup: comment attributes and the img tag together.
					const useImage = ( markup ) =>
						markup
							.replace(
								/^(<!-- wp:\S+ )(\{.*?\})( -->)/,
								( _, open, json, close ) => {
									const attrs = JSON.parse( json );
									if ( 'url' in attrs ) {
										attrs.url = image.url;
									}
									if ( 'alt' in attrs ) {
										attrs.alt = alt;
									}
									return `${ open }${ JSON.stringify( {
										...attrs,
										id: attachmentId,
									} ) }${ close }`;
								}
							)
							.replace( /<img\b[^>]*>/, ( tag ) =>
								( / class="/.test( tag )
									? tag.replace(
											/ class="([^"]*)"/,
											` class="$1 wp-image-${ attachmentId }"`
									  )
									: tag.replace(
											'<img',
											`<img class="wp-image-${ attachmentId }"`
									  )
								)
									.replace(
										/ src="[^"]*"/,
										` src="${ image.url }"`
									)
									.replace(
										/ alt="[^"]*"/,
										` alt="${ alt }"`
									)
							);

					const pageId = await draft( mcp, 'SKILL-06 page', 'page' );
					for ( const [ name, blockName ] of [
						[ 'twentytwentyfive/banner-intro-image', 'image' ],
						[ 'twentytwentyfive/hero-full-width-image', 'cover' ],
					] ) {
						await test.step( `the ${ blockName } in ${ name } uses it`, async () => {
							const inserted = await mcp.callTool(
								'publishio/insert-pattern',
								{ post_id: pageId, pattern: name }
							);
							expect( inserted.isError, inserted.text ).toBe(
								false
							);

							const path = pathIn( inserted.data, blockName );
							expect(
								path,
								`${ name } has a ${ blockName }`
							).toBeTruthy();
							const { markup } = (
								await mcp.callTool(
									'publishio/get-post-content',
									{ post_id: pageId, path }
								)
							).data;
							const replaced = await mcp.callTool(
								'publishio/replace-block',
								{
									post_id: pageId,
									path,
									markup: useImage( markup ),
								}
							);
							expect( replaced.isError, replaced.text ).toBe(
								false
							);
						} );
					}

					await test.step( 'the page holds both blocks and opens cleanly', async () => {
						const content = (
							await mcp.callTool( 'publishio/get-post-content', {
								post_id: pageId,
							} )
						).data.markup;
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
					wpCliQuietly(
						`post delete ${ attachmentId } --force`,
						ALREADY.postMissing
					);
				}
			}
		);

		test(
			'SKILL-07 taxonomies: list, assign and read back terms; unknown terms are refused',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				wpCliQuietly(
					'term create category "SKILL News" --slug=skill-news',
					ALREADY.termExists
				);
				wpCliQuietly(
					'term create post_tag "SKILL Tag" --slug=skill-tag',
					ALREADY.termExists
				);

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
				expect( unknown.text ).toBe(
					'Term "skill-made-up" not found in taxonomy "post_tag".'
				);
			}
		);

		test( 'SKILL-08 new terms: the token cannot create them over the REST API, so the user adds them in WordPress', async ( {
			anonApi,
			connection,
		} ) => {
			const res = await anonApi.post( '/wp-json/wp/v2/tags', {
				headers: {
					Authorization: `Bearer ${ connection.tokens.access_token }`,
				},
				data: { name: `SKILL confirmed ${ Date.now() }` },
			} );
			expect( res.status(), await res.text() ).toBe( 401 );
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
				wpCliQuietly(
					'plugin deactivate wordpress-seo',
					ALREADY.pluginMissing
				);
				wpCliQuietly(
					'plugin delete wordpress-seo',
					ALREADY.pluginMissing
				);
				expect(
					wpCli( 'plugin list --field=name' ).split( '\n' )
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
				// --force: replace a copy left by an earlier run with the version chosen here.
				const yoast = yoastToInstall();
				wpCli( `plugin install ${ yoast.source } --force --activate` );
				expect(
					wpCli( 'plugin list --status=active --field=name' ).split(
						'\n'
					)
				).toContain( 'wordpress-seo' );
				if ( yoast.version ) {
					expect(
						wpCli( 'plugin get wordpress-seo --field=version' )
					).toBe( yoast.version );
				}
			} );
			test.afterAll( () => {
				wpCliQuietly(
					'plugin deactivate wordpress-seo',
					ALREADY.pluginMissing
				);
			} );

			test(
				'SKILL-11 SEO with Yoast: set-yoast-meta saves every field the skill lists',
				{ tag: '@wp-update' },
				async ( { mcp } ) => {
					test.info().annotations.push( {
						type: 'Yoast SEO',
						description: wpCli(
							'plugin get wordpress-seo --field=version'
						),
					} );
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

		test( 'SKILL-12 every tool the skill tells the AI to call is listed by the MCP server', async ( {
			mcp,
		} ) => {
			const guide = await mcp.rpc( 'resources/read', {
				uri: 'wordpress://publishio/content-generation-guide',
			} );
			const named = [
				...new Set(
					guide.contents[ 0 ].text.match( /publishio-[a-z-]+[a-z]/g )
				),
			];
			expect( named.length ).toBeGreaterThan( 10 );

			const listed = ( await mcp.listTools() ).map( ( t ) => t.name );
			expect( listed ).toEqual( expect.arrayContaining( named ) );
		} );
	}
);
