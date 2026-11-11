/**
 * Custom-field fixture for SMOKE-12 and MULTISITE-02: the publishio-e2e-fields plugin
 * (tests/e2e/plugins/publishio-e2e-fields), which wp-env maps into the repo-mounted sites.
 * It stays inactive until a test activates it.
 */

/**
 * Internal dependencies
 */
import { wpCli } from './wp-cli';

const FIELDS_PLUGIN = 'publishio-e2e-fields';

/** Post types the fixture registers, with the plural label Publishio names them by. */
export const PRODUCT = { type: 'e2e_product', label: 'E2E Products' };
export const NO_FIELDS = { type: 'e2e_nofields', label: 'E2E No-field items' };
export const INTERNAL = { type: 'e2e_internal', label: 'E2E Internal notes' };

/** Only administrators may edit it (its auth_callback checks manage_options). */
export const ADMIN_ONLY_FIELD = 'e2e_manager_note';

/** Every field an administrator can set on a new E2E product, as get-post describes it. */
export const PRODUCT_FIELDS = {
	e2e_price: {
		value: 0,
		type: 'number',
		title: 'Price',
		description: 'Price in US dollars.',
		minimum: 0,
	},
	// No value yet: WordPress shows null, because an empty string fails the field's own pattern.
	e2e_sku: {
		value: null,
		type: 'string',
		title: 'SKU',
		pattern: '^[A-Z]{3}-[0-9]{4}$',
		maxLength: 8,
	},
	e2e_color: {
		value: 'red',
		type: 'string',
		title: 'Colour',
		enum: [ 'red', 'green', 'blue' ],
	},
	e2e_in_stock: { value: true, type: 'boolean', title: 'In stock' },
	e2e_stock: {
		value: 0,
		type: 'integer',
		title: 'Units in stock',
		minimum: 0,
		maximum: 1000,
	},
	e2e_tagline: { value: '', type: 'string', title: 'Tagline' },
	// No value yet: null, as for e2e_sku (an empty string isn't an email address).
	e2e_contact: {
		value: null,
		type: 'string',
		title: 'Contact email',
		format: 'email',
	},
	e2e_features: {
		value: [],
		type: 'array',
		title: 'Features',
		items: { type: 'string' },
	},
	e2e_dimensions: {
		value: {},
		type: 'object',
		title: 'Dimensions',
		properties: {
			width: { type: 'number' },
			height: { type: 'number' },
		},
	},
	[ ADMIN_ONLY_FIELD ]: { value: '', type: 'string', title: 'Manager note' },
};

/** The keys an administrator can set, in get-post's order. */
export const ADMIN_KEYS = Object.keys( PRODUCT_FIELDS );

/** The keys an editor can set: all but the administrators-only one. */
export const EDITOR_KEYS = ADMIN_KEYS.filter(
	( key ) => key !== ADMIN_ONLY_FIELD
);

/**
 * update-post's refusal of a field the user can't set.
 *
 * @param {string}   key      The field sent.
 * @param {string}   label    The post type's plural label.
 * @param {string[]} settable The keys the user can set, in get-post's order.
 */
export const unknownField = ( key, label, settable ) =>
	`unknown_field: "${ key }" isn't a custom field you can set on ${ label }. You can set: ${ settable.join(
		', '
	) }. Nothing was saved.`;

/**
 * update-post's refusal on a type without custom-field support.
 *
 * @param {string} label The post type's plural label.
 */
export const noFieldsOn = ( label ) =>
	`unknown_field: You can't set any custom fields on ${ label }. Leave out fields.`;

/**
 * update-post's refusal of invalid values: every problem, in the order the fields were sent.
 *
 * @param {...string} problems Each field's problem, as WordPress words it.
 */
export const invalidFields = ( ...problems ) =>
	`invalid_field_value: ${ problems.join(
		' '
	) } Nothing was saved. Fix these and send the fields again.`;

/**
 * update-post's refusal to reset a field whose stored value get-post showed as null.
 *
 * @param {string} key The field's key.
 */
export const cannotReset = ( key ) =>
	`${ key } holds a value that isn't valid, so it can't be reset. Set a valid value instead.`;

const urlArg = ( siteUrl ) => ( siteUrl ? ` --url=${ siteUrl }` : '' );

/**
 * Activate the fixture plugin.
 *
 * @param {string} siteUrl A network's site to activate it on; the main site when omitted.
 */
export function activateFieldsPlugin( siteUrl = '' ) {
	wpCli( `plugin activate ${ FIELDS_PLUGIN }${ urlArg( siteUrl ) }` );
	// The new types' permalinks 404 until the rewrite rules are rebuilt.
	wpCli( `rewrite flush${ urlArg( siteUrl ) }` );
}

/**
 * Delete the fixture's posts, then deactivate it, so the other specs see the site as before.
 *
 * @param {string} siteUrl A network's site; the main site when omitted.
 */
export function removeFieldsFixture( siteUrl = '' ) {
	for ( const { type } of [ PRODUCT, NO_FIELDS, INTERNAL ] ) {
		const ids = wpCli(
			`post list --post_type=${ type } --post_status=any --format=ids${ urlArg(
				siteUrl
			) }`
		);
		if ( ids ) {
			wpCli( `post delete ${ ids } --force${ urlArg( siteUrl ) }` );
		}
	}
	wpCli( `plugin deactivate ${ FIELDS_PLUGIN }${ urlArg( siteUrl ) }` );
	wpCli( `rewrite flush${ urlArg( siteUrl ) }` );
}

/**
 * A post's stored custom fields, read with WP-CLI (not through Publishio).
 *
 * @param {number} postId  Post ID.
 * @param {string} siteUrl A network's site; the main site when omitted.
 * @return {Object<string, Array<*>>} Every stored value, by meta key.
 */
export function storedMeta( postId, siteUrl = '' ) {
	const rows = JSON.parse(
		wpCli(
			`post meta list ${ postId } --format=json --unserialize${ urlArg(
				siteUrl
			) }`
		)
	);
	const meta = {};
	for ( const { meta_key: key, meta_value: value } of rows ) {
		( meta[ key ] ??= [] ).push( value );
	}
	return meta;
}
