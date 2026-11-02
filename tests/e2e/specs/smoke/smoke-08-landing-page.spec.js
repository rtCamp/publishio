/**
 * SMOKE-08: build a landing page from theme patterns (page is left for review).
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { BLOCK_WARNING } from '../../utils/editor';
import { evidence } from '../install/helpers';
import { requiresTwentyTwentyFive } from '../../utils/theme';

const SECTIONS = [
	{
		pattern: 'twentytwentyfive/banner-intro-image',
		text: [
			'Publish pages from your AI assistant',
			'Publishio connects Claude and ChatGPT to WordPress, so drafts are built from your own patterns and stay on brand.',
			'Connect your site',
		],
	},
	{
		pattern: 'twentytwentyfive/services-3-col',
		text: [
			'What you get',
			'Your patterns, not templates',
			'Every section comes from the patterns your theme already ships.',
			'Drafts you review',
			'Nothing is published until an editor approves it in WordPress.',
			'Secure by design',
			'OAuth 2.1 with PKCE, and access follows each user’s role.',
		],
	},
	{
		pattern: 'twentytwentyfive/testimonials-2-col',
		text: [
			'“We went from brief to a reviewed landing page in under an hour.” — Content lead',
			'“The drafts match our design system, so review is about the words.” — Editor',
		],
	},
	{
		pattern: 'twentytwentyfive/text-faqs',
		text: [
			'Frequently asked questions',
			'Does Publishio publish for me?',
			'No. It only creates drafts; publishing stays in WordPress.',
			'Which AI apps work?',
			'Any MCP client with OAuth, including Claude and ChatGPT.',
			'Can I limit what it can edit?',
			'Yes. The connection acts with the WordPress role of the user who approved it.',
			'How do I disconnect?',
			'Remove the connection under Publishio → Connections at any time.',
		],
	},
	{
		pattern: 'twentytwentyfive/cta-centered-heading',
		text: [
			'Ready to build your next page?',
			'Install Publishio, connect your AI app and start from your own patterns.',
			'Get started',
		],
	},
];

// Text only: images are left out so the theme's images and IDs stay intact.
const TEXT_BLOCK = /(^|> )\d+:(heading|paragraph|button)$/;

/**
 * Write copy into an inserted section's text blocks in order, as an AI edits text: read the block, change its text, replace it.
 *
 * @param {import('../../utils/mcp').McpClient}  mcp      Initialized MCP client.
 * @param {number}                               postId   The page.
 * @param {{changed: string[], outline: Object}} inserted The insert-pattern result.
 * @param {string[]}                             text     Copy for the text blocks, in order.
 */
async function fillText( mcp, postId, inserted, text ) {
	const copy = [ ...text ];
	const rows = inserted.outline.rows.filter(
		( row ) =>
			inserted.changed.some(
				( root ) =>
					row.path === root || row.path.startsWith( `${ root } > ` )
			) && TEXT_BLOCK.test( row.path )
	);

	for ( const row of rows.slice( 0, copy.length ) ) {
		const { markup } = (
			await mcp.callTool( 'publishio/get-post-content', {
				post_id: postId,
				path: row.path,
			} )
		).data;
		const replaced = await mcp.callTool( 'publishio/replace-block', {
			post_id: postId,
			path: row.path,
			markup: markup.replace(
				/(<(h[1-6]|p|a)\b[^>]*>)[\s\S]*?(<\/\2>)/,
				`$1${ copy.shift() }$3`
			),
		} );
		expect( replaced.isError, `${ row.path }: ${ replaced.text }` ).toBe(
			false
		);
	}
}

test.describe(
	'Landing page from patterns',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		requiresTwentyTwentyFive();

		test(
			'SMOKE-08 builds a complete landing page from theme patterns',
			{ tag: '@wp-update' },
			async ( { mcp, admin, editor, page } ) => {
				const available = (
					await mcp.callTool( 'publishio/list-patterns', {
						post_type: 'page',
					} )
				).data.patterns.map( ( p ) => p.name );
				for ( const { pattern } of SECTIONS ) {
					expect(
						available,
						`${ pattern } is offered over MCP`
					).toContain( pattern );
				}

				const created = await mcp.callTool( 'publishio/create-post', {
					title: 'Publishio: pages from your AI assistant',
					post_type: 'page',
				} );
				expect( created.isError, created.text ).toBe( false );
				const pageId = created.data.post_id;

				for ( const { pattern, text } of SECTIONS ) {
					const inserted = await mcp.callTool(
						'publishio/insert-pattern',
						{
							post_id: pageId,
							pattern,
						}
					);
					expect(
						inserted.isError,
						`${ pattern }: ${ inserted.text }`
					).toBe( false );
					await fillText( mcp, pageId, inserted.data, text );
				}

				const updated = await mcp.callTool( 'publishio/update-post', {
					post_id: pageId,
					slug: 'publishio-ai-pages',
					excerpt:
						'Build on-brand WordPress pages from your own patterns with Claude or ChatGPT.',
				} );
				expect( updated.isError, updated.text ).toBe( false );

				const saved = await mcp.callTool( 'publishio/get-post', {
					post_id: pageId,
				} );
				const markup = (
					await mcp.callTool( 'publishio/get-post-content', {
						post_id: pageId,
					} )
				).data.markup;
				expect( saved.data.status ).toBe( 'draft' );
				expect( saved.data.slug ).toBe( 'publishio-ai-pages' );
				for ( const { text } of SECTIONS ) {
					expect( markup ).toContain( text[ 0 ].slice( 0, 20 ) );
				}
				test.info().annotations.push(
					{ type: 'page', description: saved.data.url },
					{ type: 'edit', description: saved.data.edit_url }
				);

				await admin.visitAdminPage(
					'post.php',
					`post=${ pageId }&action=edit`
				);
				await expect(
					editor.canvas.getByText( SECTIONS[ 0 ].text[ 0 ] )
				).toBeVisible();
				await expect(
					editor.canvas.locator( BLOCK_WARNING )
				).toHaveCount( 0 );
				await evidence( page, 'SMOKE-08 editor' );

				await page.goto( `/?page_id=${ pageId }&preview=true` );
				await expect(
					page.getByRole( 'heading', {
						name: SECTIONS[ 4 ].text[ 0 ],
					} )
				).toBeVisible();
				await evidence( page, 'SMOKE-08 page preview' );
			}
		);
	}
);
