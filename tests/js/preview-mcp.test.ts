/**
 * Tests for how the Publishio Preview app reads the tool call it follows.
 */
/**
 * External dependencies
 */
import { fallbackCard, previewRequest, toolName } from '@/apps/preview/mcp';

describe( 'toolName', () => {
	it( 'uses the name the host sends, without the prefix', () => {
		expect( toolName( 'publishio-insert-blocks', {} ) ).toBe(
			'insert-blocks'
		);
	} );

	it( 'tells the tools apart by their arguments when the host sends no name', () => {
		expect( toolName( undefined, { post_id: 1, markup: 'x' } ) ).toBe(
			'insert-blocks'
		);
		expect( toolName( undefined, { post_id: 1, pattern: 'a/b' } ) ).toBe(
			'insert-pattern'
		);
		expect(
			toolName( undefined, { title: 'Hero', synced: true, markup: 'x' } )
		).toBe( 'create-pattern' );
		expect(
			toolName( undefined, { pattern: 'core/block/3', markup: 'x' } )
		).toBe( 'update-pattern' );
		expect( toolName( undefined, { post_id: 1 } ) ).toBe( 'preview-post' );
		expect( toolName( undefined, {} ) ).toBe( '' );
	} );
} );

describe( 'previewRequest', () => {
	it( 'previews inserted blocks with where they went', () => {
		expect(
			previewRequest(
				'insert-pattern',
				{ post_id: 4, pattern: 'theme/hero', target: '0:heading' },
				{ post_id: 4, changed: [ '1:group', '2:group' ] }
			)
		).toEqual( {
			view: 'blocks',
			post_id: 4,
			paths: [ '1:group', '2:group' ],
			placement: 'end',
			targeted: true,
			pattern: 'theme/hero',
		} );
	} );

	it( 'previews a saved or updated pattern by its name', () => {
		expect(
			previewRequest( 'update-pattern', {}, { name: 'core/block/3' } )
		).toEqual( {
			view: 'pattern',
			pattern: 'core/block/3',
			updated: true,
		} );
	} );

	it( 'previews a whole page', () => {
		expect( previewRequest( 'preview-post', {}, { post_id: 9 } ) ).toEqual(
			{ view: 'page', post_id: 9 }
		);
	} );

	it( 'shows nothing for other tools or incomplete results', () => {
		expect(
			previewRequest( 'replace-block', {}, { post_id: 1 } )
		).toBeNull();
		expect(
			previewRequest( 'insert-blocks', {}, { post_id: 1, changed: [] } )
		).toBeNull();
		expect( previewRequest( 'create-pattern', {}, {} ) ).toBeNull();
	} );
} );

describe( 'fallbackCard', () => {
	it( 'names inserted blocks from the outline until the server answers', () => {
		const card = fallbackCard(
			{ view: 'blocks', post_id: 4, paths: [ '1:paragraph' ] },
			{
				outline: {
					rows: [
						{ path: '0:heading', text: 'Our story' },
						{ path: '1:paragraph', text: 'Open 7 days a week' },
					],
				},
			}
		);

		expect( card.title ).toBe( 'Open 7 days a week' );
		expect( card.status ).toEqual( { word: 'Added', tone: 'ok' } );
		expect( card.error ).toBe(
			'The block was added. Only the preview didn’t load.'
		);
	} );

	it( 'uses the result’s title and editor link for patterns and pages', () => {
		const pattern = fallbackCard(
			{ view: 'pattern', pattern: 'core/block/3', updated: true },
			{ title: 'Footer CTA', edit_url: 'https://example.com/edit' }
		);

		expect( pattern.title ).toBe( 'Footer CTA' );
		expect( pattern.status ).toEqual( { word: 'Updated', tone: 'info' } );
		expect( pattern.editUrl ).toBe( 'https://example.com/edit' );

		const page = fallbackCard( { view: 'page', post_id: 9 }, {} );

		expect( page.status ).toBeNull();
		expect( page.editUrl ).toBeNull();
	} );
} );
