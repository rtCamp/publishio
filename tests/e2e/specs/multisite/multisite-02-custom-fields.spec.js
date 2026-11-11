/**
 * MULTISITE-02: custom fields and post types on a subsite of a network (#171).
 *
 * The publishio-e2e-fields fixture plugin is active on the subsite only, as a plugin activated
 * site by site would be. Runs on a subdirectory network (localhost:8893/site2/) and a subdomain
 * network (site2.localhost:8896).
 */

/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { currentSite } from '../../config/env';
import {
	EDITOR_KEYS,
	INTERNAL,
	PRODUCT,
	activateFieldsPlugin,
	noFieldsOn,
	removeFieldsFixture,
	storedMeta,
} from '../../utils/custom-fields';
import { connectToSite, mcpClientFor } from '../../utils/oauth';
import { ensureSiteUser, loginApi } from '../../utils/users';
import { wpCli } from '../../utils/wp-cli';

// Letters and digits only: multisite rejects underscores in usernames.
const EDITOR = 'e2esubsiteeditor';

test.describe(
	'Custom fields on a subsite',
	{ tag: [ '@multisite', '@plugin-update' ] },
	() => {
		let subsite;
		const mainSitePosts = [];

		/**
		 * An MCP client connected to the subsite as its editor.
		 *
		 * @param {import('@playwright/test').APIRequestContext}                  anonApi    Logged-out context.
		 * @param {import('@playwright/test').PlaywrightWorkerArgs['playwright']} playwright Playwright fixture.
		 */
		async function subsiteEditor( anonApi, playwright ) {
			const editorApi = await loginApi(
				playwright,
				EDITOR,
				undefined,
				subsite
			);
			const connection = await connectToSite(
				anonApi,
				editorApi,
				subsite
			);
			await editorApi.dispose();
			return mcpClientFor( anonApi, connection );
		}

		test.beforeAll( () => {
			subsite = currentSite().subsite.url;
			ensureSiteUser( EDITOR, 'editor', subsite );
			activateFieldsPlugin( subsite );
		} );

		test.afterAll( () => {
			removeFieldsFixture( subsite );
			if ( mainSitePosts.length ) {
				wpCli( `post delete ${ mainSitePosts.join( ' ' ) } --force` );
			}
		} );

		test(
			'MULTISITE-02.1 a subsite editor reads and sets the fields of a type only that subsite has, and the values stay on the subsite',
			{ tag: '@wp-update' },
			async ( { anonApi, playwright } ) => {
				const mcp = await subsiteEditor( anonApi, playwright );
				const { data } = await mcp.callToolOk(
					'publishio/create-post',
					{
						title: `MULTISITE-02 product ${ Date.now() }`,
						post_type: PRODUCT.type,
					}
				);

				const read = await mcp.callToolOk( 'publishio/get-post', {
					post_id: data.post_id,
				} );
				expect( Object.keys( read.data.fields ) ).toEqual(
					EDITOR_KEYS
				);

				const saved = await mcp.callToolOk( 'publishio/update-post', {
					post_id: data.post_id,
					fields: {
						e2e_price: 19.999,
						e2e_color: 'green',
						e2e_features: [ 'Network ready' ],
					},
				} );
				expect( saved.data.fields ).toEqual( {
					e2e_price: 20,
					e2e_color: 'green',
					e2e_features: [ 'Network ready' ],
				} );
				expect( storedMeta( data.post_id, subsite ) ).toMatchObject( {
					e2e_price: [ '20' ],
					e2e_color: [ 'green' ],
					e2e_features: [ 'Network ready' ],
				} );

				// The type exists on the subsite only.
				expect(
					wpCli( `post-type list --field=name --url=${ subsite }` )
				).toContain( PRODUCT.type );
				expect( wpCli( 'post-type list --field=name' ) ).not.toContain(
					PRODUCT.type
				);
			}
		);

		test(
			'MULTISITE-02.2 search-posts with post_type "any" on a subsite finds that subsite\'s posts of every type, not the main site\'s',
			{ tag: '@wp-update' },
			async ( { anonApi, playwright } ) => {
				const term = `multisite02-${ Date.now() }`;
				const create = ( type, url = '' ) =>
					wpCli(
						`post create --post_type=${ type } --post_status=draft --post_title='${ term } ${ type }${
							url ? '' : ' main'
						}' --porcelain${ url ? ` --url=${ url }` : '' }`
					);
				create( 'post', subsite );
				create( INTERNAL.type, subsite );
				mainSitePosts.push( create( 'post' ) );

				const mcp = await subsiteEditor( anonApi, playwright );
				const found = await mcp.callToolOk( 'publishio/search-posts', {
					search: term,
					post_type: 'any',
				} );
				expect(
					found.data.map( ( post ) => post.title ).sort()
				).toEqual( [
					`${ term } ${ INTERNAL.type }`,
					`${ term } post`,
				] );
			}
		);

		test( "MULTISITE-02.3 on the main site, where the fields plugin isn't active, update-post refuses custom fields", async ( {
			mcp,
		} ) => {
			// The project's own connection is to the main site.
			const { data } = await mcp.callToolOk( 'publishio/create-post', {
				title: `MULTISITE-02 main ${ Date.now() }`,
			} );
			mainSitePosts.push( data.post_id );

			const refused = await mcp.callTool( 'publishio/update-post', {
				post_id: data.post_id,
				fields: { e2e_price: 5 },
			} );
			expect( refused.isError ).toBe( true );
			expect( refused.text ).toBe( noFieldsOn( 'Posts' ) );
			expect( storedMeta( data.post_id ) ).not.toHaveProperty(
				'e2e_price'
			);
		} );
	}
);
