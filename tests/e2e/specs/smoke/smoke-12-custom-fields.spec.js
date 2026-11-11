/**
 * SMOKE-12: custom fields with get-post and update-post, and search-posts across every type the
 * user can edit (#171).
 *
 * The publishio-e2e-fields fixture plugin registers a product type with fields of every kind,
 * a type without custom-field support and a type visitors can't view (utils/custom-fields.js).
 * Fields are set through Publishio and checked through WordPress (REST, WP-CLI, the front end).
 */

/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import {
	ADMIN_KEYS,
	ADMIN_ONLY_FIELD,
	EDITOR_KEYS,
	INTERNAL,
	NO_FIELDS,
	PRODUCT,
	PRODUCT_FIELDS,
	activateFieldsPlugin,
	cannotReset,
	invalidFields,
	noFieldsOn,
	removeFieldsFixture,
	storedMeta,
	unknownField,
} from '../../utils/custom-fields';
import { connect, mcpClientFor } from '../../utils/oauth';
import { ensureUser, loginApi } from '../../utils/users';
import { wpCli } from '../../utils/wp-cli';
import { evidence } from '../install/helpers';

/** A value of every kind, and what the fixture's sanitizers store for them. */
const VALID = {
	e2e_price: 19.999,
	e2e_sku: 'ABC-1234',
	e2e_color: 'blue',
	e2e_in_stock: false,
	e2e_stock: 5,
	e2e_tagline: '<script>alert(1)</script><b>Bold</b> tagline',
	e2e_contact: 'sales@example.test',
	e2e_features: [ 'Waterproof', 'Solar' ],
	e2e_dimensions: { width: 2.5, height: 4 },
	[ ADMIN_ONLY_FIELD ]: 'Reorder in May',
};
const STORED = { ...VALID, e2e_price: 20, e2e_tagline: 'Bold tagline' };

const unique = ( label ) => `SMOKE-12 ${ label } ${ Date.now() }`;

/**
 * A new E2E product draft, created through Publishio.
 *
 * @param {import('../../utils/mcp').McpClient} mcp   MCP client.
 * @param {string}                              title Post title.
 */
async function newProduct( mcp, title ) {
	const { data } = await mcp.callToolOk( 'publishio/create-post', {
		title,
		post_type: PRODUCT.type,
	} );
	return data.post_id;
}

/**
 * Each field's value from a get-post result.
 *
 * @param {Object<string, {value: *}>} fields get-post's fields.
 */
const valuesOf = ( fields ) =>
	Object.fromEntries(
		Object.entries( fields ).map( ( [ key, field ] ) => [
			key,
			field.value,
		] )
	);

/**
 * An MCP client connected as an editor.
 *
 * @param {Object}                                                        fixtures              Playwright fixtures.
 * @param {import('@playwright/test').APIRequestContext}                  fixtures.anonApi      Logged-out context.
 * @param {import('@playwright/test').PlaywrightWorkerArgs['playwright']} fixtures.playwright   Playwright fixture.
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils}   fixtures.requestUtils WordPress request utils.
 */
async function editorMcp( { anonApi, playwright, requestUtils } ) {
	const editorApi = await loginApi(
		playwright,
		await ensureUser( requestUtils, 'editor' )
	);
	const connection = await connect( anonApi, editorApi );
	await editorApi.dispose();
	return mcpClientFor( anonApi, connection );
}

test.describe(
	'Custom fields and post types',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test.beforeAll( () => {
			activateFieldsPlugin();
		} );

		test.afterAll( () => {
			removeFieldsFixture();
		} );

		test(
			'SMOKE-12.1 get-post lists each field the user can set, with its value and what it takes, and nothing protected or hidden',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const id = await newProduct( mcp, unique( 'describe' ) );
				// Stored outside Publishio: a protected field and one not in the REST API.
				wpCli( `post meta update ${ id } _e2e_secret secret-value-1` );
				wpCli( `post meta update ${ id } e2e_hidden hidden-value-1` );

				const post = await mcp.callToolOk( 'publishio/get-post', {
					post_id: id,
				} );
				expect( post.data.fields ).toEqual( PRODUCT_FIELDS );
				expect( post.text ).not.toContain( 'secret-value-1' );
				expect( post.text ).not.toContain( 'hidden-value-1' );

				// With path, get-post reads one block and leaves fields out.
				await mcp.callToolOk( 'publishio/insert-blocks', {
					post_id: id,
					markup: '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->',
				} );
				const block = await mcp.callToolOk( 'publishio/get-post', {
					post_id: id,
					path: post.data.outline.rows[ 0 ]?.path ?? '0:paragraph',
				} );
				expect( block.data.post_id ).toBe( id );
				expect( block.data ).not.toHaveProperty( 'fields' );
			}
		);

		test(
			'SMOKE-12.2 update-post saves a valid value of every kind and returns each as the site stored it',
			{ tag: '@wp-update' },
			async ( { mcp, requestUtils } ) => {
				const id = await newProduct( mcp, unique( 'valid' ) );

				const saved = await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: VALID,
				} );
				// The price is rounded and the tagline's markup stripped by the site's sanitizers.
				expect( saved.data.fields ).toEqual( STORED );

				const rest = await requestUtils.rest( {
					path: `/wp/v2/${ PRODUCT.type }/${ id }`,
					params: { context: 'edit' },
				} );
				expect( rest.meta ).toMatchObject( STORED );

				const read = await mcp.callToolOk( 'publishio/get-post', {
					post_id: id,
				} );
				expect( valuesOf( read.data.fields ) ).toEqual( STORED );
			}
		);

		test(
			'SMOKE-12.3 invalid values are refused with every problem in one error, and nothing is saved',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const title = unique( 'invalid' );
				const id = await newProduct( mcp, title );

				const outOfRange = await mcp.callTool(
					'publishio/update-post',
					{
						post_id: id,
						title: 'Changed title',
						fields: {
							e2e_price: -1,
							e2e_sku: 'abc',
							e2e_color: 'purple',
							e2e_in_stock: 'maybe',
							e2e_stock: 1001,
							e2e_contact: 'not-an-email',
							e2e_dimensions: { depth: 1 },
						},
					}
				);
				expect( outOfRange.isError ).toBe( true );
				expect( outOfRange.text ).toBe(
					invalidFields(
						'e2e_price must be greater than or equal to 0',
						'e2e_sku does not match pattern ^[A-Z]{3}-[0-9]{4}$.',
						'e2e_color is not one of red, green, and blue.',
						'e2e_in_stock is not of type boolean.',
						'e2e_stock must be between 0 (inclusive) and 1000 (inclusive)',
						'e2e_contact: Invalid email address.',
						'e2e_dimensions: depth is not a valid property of Object.'
					)
				);

				const wrongType = await mcp.callTool( 'publishio/update-post', {
					post_id: id,
					fields: { e2e_price: 'cheap', e2e_stock: 2.5 },
				} );
				expect( wrongType.isError ).toBe( true );
				expect( wrongType.text ).toBe(
					invalidFields(
						'e2e_price is not of type number.',
						'e2e_stock is not of type integer.'
					)
				);

				const after = await mcp.callToolOk( 'publishio/get-post', {
					post_id: id,
				} );
				expect( after.data.title ).toBe( title );
				expect( after.data.fields ).toEqual( PRODUCT_FIELDS );
			}
		);

		test( 'SMOKE-12.4 a field the user cannot set is refused with the fields they can set, and nothing is saved', async ( {
			mcp,
		} ) => {
			const id = await newProduct( mcp, unique( 'unknown' ) );

			// Unknown, protected (leading underscore) and not in the REST API.
			for ( const key of [ 'nope', '_e2e_secret', 'e2e_hidden' ] ) {
				const res = await mcp.callTool( 'publishio/update-post', {
					post_id: id,
					fields: { [ key ]: 'x' },
				} );
				expect( res.isError, key ).toBe( true );
				expect( res.text ).toBe(
					unknownField( key, PRODUCT.label, ADMIN_KEYS )
				);
			}

			// One unknown field stops the valid ones in the same call too.
			const mixed = await mcp.callTool( 'publishio/update-post', {
				post_id: id,
				fields: { e2e_price: 5, nope: 1 },
			} );
			expect( mixed.isError ).toBe( true );
			expect( mixed.text ).toBe(
				unknownField( 'nope', PRODUCT.label, ADMIN_KEYS )
			);

			const read = await mcp.callToolOk( 'publishio/get-post', {
				post_id: id,
			} );
			expect( read.data.fields.e2e_price.value ).toBe( 0 );
			expect( Object.keys( storedMeta( id ) ) ).toEqual(
				expect.not.arrayContaining( [
					'nope',
					'_e2e_secret',
					'e2e_hidden',
					'e2e_price',
				] )
			);
		} );

		test(
			'SMOKE-12.5 null resets a field to its default, including a false boolean and a list; a stored invalid value cannot be reset',
			{ tag: '@wp-update' },
			async ( { mcp } ) => {
				const id = await newProduct( mcp, unique( 'reset' ) );
				await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: {
						e2e_in_stock: false,
						e2e_price: 9.5,
						e2e_features: [ 'A', 'B' ],
						e2e_dimensions: { width: 1, height: 2 },
					},
				} );

				const reset = await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: {
						e2e_in_stock: null,
						e2e_price: null,
						e2e_features: [],
						e2e_dimensions: null,
					},
				} );
				expect( reset.data.fields ).toEqual( {
					e2e_in_stock: true,
					e2e_price: 0,
					e2e_features: [],
					e2e_dimensions: {},
				} );
				expect(
					Object.keys( storedMeta( id ) ).filter( ( key ) =>
						key.startsWith( 'e2e_' )
					)
				).toEqual( [] );

				// A value stored outside Publishio that get-post can't show isn't reset unseen.
				wpCli( `post meta update ${ id } e2e_stock lots` );
				const read = await mcp.callToolOk( 'publishio/get-post', {
					post_id: id,
				} );
				expect( read.data.fields.e2e_stock.value ).toBeNull();

				const refused = await mcp.callTool( 'publishio/update-post', {
					post_id: id,
					fields: { e2e_stock: null },
				} );
				expect( refused.isError ).toBe( true );
				expect( refused.text ).toBe(
					invalidFields( cannotReset( 'e2e_stock' ) )
				);
				expect( storedMeta( id ).e2e_stock ).toEqual( [ 'lots' ] );

				const fixed = await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: { e2e_stock: 7 },
				} );
				expect( fixed.data.fields ).toEqual( { e2e_stock: 7 } );
				expect( storedMeta( id ).e2e_stock ).toEqual( [ '7' ] );
			}
		);

		test( 'SMOKE-12.6 a type without custom-field support has no fields in get-post and update-post refuses them', async ( {
			mcp,
		} ) => {
			const { data } = await mcp.callToolOk( 'publishio/create-post', {
				title: unique( 'no fields' ),
				post_type: NO_FIELDS.type,
			} );

			const read = await mcp.callToolOk( 'publishio/get-post', {
				post_id: data.post_id,
			} );
			expect( read.data.post_id ).toBe( data.post_id );
			expect( read.data ).not.toHaveProperty( 'fields' );

			const refused = await mcp.callTool( 'publishio/update-post', {
				post_id: data.post_id,
				fields: { e2e_rating: 3 },
			} );
			expect( refused.isError ).toBe( true );
			expect( refused.text ).toBe( noFieldsOn( NO_FIELDS.label ) );
			expect( storedMeta( data.post_id ) ).not.toHaveProperty(
				'e2e_rating'
			);
		} );

		test(
			'SMOKE-12.7 an editor neither sees nor sets a field only administrators may edit',
			{ tag: '@wp-update' },
			async ( { mcp, anonApi, playwright, requestUtils } ) => {
				const id = await newProduct( mcp, unique( 'roles' ) );
				await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: { [ ADMIN_ONLY_FIELD ]: 'Admin only' },
				} );

				const editor = await editorMcp( {
					anonApi,
					playwright,
					requestUtils,
				} );
				const read = await editor.callToolOk( 'publishio/get-post', {
					post_id: id,
				} );
				expect( Object.keys( read.data.fields ) ).toEqual(
					EDITOR_KEYS
				);

				const refused = await editor.callTool(
					'publishio/update-post',
					{
						post_id: id,
						fields: { [ ADMIN_ONLY_FIELD ]: 'Editor change' },
					}
				);
				expect( refused.isError ).toBe( true );
				expect( refused.text ).toBe(
					unknownField( ADMIN_ONLY_FIELD, PRODUCT.label, EDITOR_KEYS )
				);

				const priced = await editor.callToolOk(
					'publishio/update-post',
					{
						post_id: id,
						fields: { e2e_price: 12 },
					}
				);
				expect( priced.data.fields ).toEqual( { e2e_price: 12 } );
				expect( storedMeta( id )[ ADMIN_ONLY_FIELD ] ).toEqual( [
					'Admin only',
				] );
			}
		);

		test( "SMOKE-12.8 a published product's fields change only with the user's approval", async ( {
			mcp,
		} ) => {
			const title = unique( 'live' );
			const id = Number(
				wpCli(
					`post create --post_type=${ PRODUCT.type } --post_status=publish --post_title='${ title }' --porcelain`
				)
			);

			const asked = await mcp.callTool( 'publishio/update-post', {
				post_id: id,
				fields: { e2e_price: 5 },
			} );
			expect( asked.isError ).toBe( true );
			const marker = 'approval_token: "';
			const start = asked.text.indexOf( marker ) + marker.length;
			const token = asked.text.slice(
				start,
				asked.text.indexOf( '"', start )
			);
			expect( asked.text ).toBe(
				`live_post_needs_approval: "${ title }" is published, so this change takes effect right away. Ask the user first, unless they already agreed to changes to it. If they agree, call again with approval_token: "${ token }".`
			);
			expect( token ).not.toBe( '' );
			expect( storedMeta( id ) ).not.toHaveProperty( 'e2e_price' );

			const approved = await mcp.callToolOk( 'publishio/update-post', {
				post_id: id,
				fields: { e2e_price: 5 },
				approval_token: token,
			} );
			expect( approved.data.fields ).toEqual( { e2e_price: 5 } );
			expect( storedMeta( id ).e2e_price ).toEqual( [ '5' ] );
		} );

		test(
			'SMOKE-12.9 a block bound to a custom field shows the value update-post set',
			{ tag: '@wp-update' },
			async ( { mcp, page } ) => {
				const id = await newProduct( mcp, unique( 'bound' ) );
				await mcp.callToolOk( 'publishio/insert-blocks', {
					post_id: id,
					markup: '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"e2e_tagline"}}}}} --><p>Tagline placeholder</p><!-- /wp:paragraph -->',
				} );
				const tagline = `Built to last ${ Date.now() }`;
				await mcp.callToolOk( 'publishio/update-post', {
					post_id: id,
					fields: { e2e_tagline: tagline },
				} );

				// Publishing is the user's step; Publishio never publishes.
				wpCli( `post update ${ id } --post_status=publish` );
				await page.goto( wpCli( `post url ${ id }` ) );

				await expect( page.getByText( tagline ) ).toBeVisible();
				await expect(
					page.getByText( 'Tagline placeholder' )
				).toHaveCount( 0 );
				await evidence( page, 'SMOKE-12.9 bound tagline' );
			}
		);

		test(
			'SMOKE-12.10 search-posts with post_type "any" finds every type an editor can edit, including other users\' posts and types visitors can\'t view',
			{ tag: '@wp-update' },
			async ( { anonApi, playwright, requestUtils } ) => {
				const term = `smoke12-${ Date.now() }`;
				const author = wpCli( 'user get admin --field=ID' );
				const types = [ 'post', 'page', PRODUCT.type, INTERNAL.type ];
				for ( const type of types ) {
					wpCli(
						`post create --post_type=${ type } --post_status=draft --post_author=${ author } --post_title='${ term } ${ type }' --porcelain`
					);
				}

				const editor = await editorMcp( {
					anonApi,
					playwright,
					requestUtils,
				} );
				const any = await editor.callToolOk( 'publishio/search-posts', {
					search: term,
					post_type: 'any',
				} );
				expect( any.data.map( ( post ) => post.title ).sort() ).toEqual(
					types.map( ( type ) => `${ term } ${ type }` ).sort()
				);

				const internal = await editor.callToolOk(
					'publishio/search-posts',
					{ search: term, post_type: INTERNAL.type }
				);
				expect( internal.data.map( ( post ) => post.title ) ).toEqual( [
					`${ term } ${ INTERNAL.type }`,
				] );

				const listed = await editor.callToolOk(
					'publishio/get-post-types'
				);
				expect(
					listed.data.post_types.map( ( type ) => type.slug )
				).toEqual(
					expect.arrayContaining( [
						PRODUCT.type,
						NO_FIELDS.type,
						INTERNAL.type,
					] )
				);
			}
		);
	}
);
