<?php
/**
 * Plugin Name: Publishio E2E custom fields
 * Description: Test fixture for the Publishio E2E suite (SMOKE-12, MULTISITE-02). Registers custom post types and custom fields of every kind get-post and update-post handle. Mapped into the E2E sites by wp-env; never shipped.
 * Version:     1.0.0
 * Requires PHP: 8.2
 * License:     GPL-2.0-or-later
 *
 * @package publishio-e2e-fields
 */

declare( strict_types = 1 );

add_action(
	'init',
	static function (): void {
		// A product type with custom fields, as a shop or catalogue plugin would add it.
		register_post_type(
			'e2e_product',
			[
				'label'        => 'E2E Products',
				'labels'       => [
					'name'          => 'E2E Products',
					'singular_name' => 'E2E Product',
				],
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor', 'custom-fields' ],
			]
		);

		// A type that shows in the block editor but doesn't support custom fields.
		register_post_type(
			'e2e_nofields',
			[
				'label'        => 'E2E No-field items',
				'labels'       => [
					'name'          => 'E2E No-field items',
					'singular_name' => 'E2E No-field item',
				],
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);

		// A type with an admin screen that visitors can't view (search-posts with post_type "any").
		register_post_type(
			'e2e_internal',
			[
				'label'        => 'E2E Internal notes',
				'labels'       => [
					'name'          => 'E2E Internal notes',
					'singular_name' => 'E2E Internal note',
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);

		$fields = [
			'e2e_price'        => [
				'type'              => 'number',
				'label'             => 'Price',
				'description'       => 'Price in US dollars.',
				'default'           => 0,
				// The site rounds the price, so update-post returns the value as stored.
				'sanitize_callback' => static fn ( $value ) => round( (float) $value, 2 ),
				'show_in_rest'      => [ 'schema' => [ 'minimum' => 0 ] ],
			],
			'e2e_sku'          => [
				'type'         => 'string',
				'label'        => 'SKU',
				'show_in_rest' => [
					'schema' => [
						'pattern'   => '^[A-Z]{3}-[0-9]{4}$',
						'maxLength' => 8,
					],
				],
			],
			'e2e_color'        => [
				'type'         => 'string',
				'label'        => 'Colour',
				'default'      => 'red',
				'show_in_rest' => [ 'schema' => [ 'enum' => [ 'red', 'green', 'blue' ] ] ],
			],
			'e2e_in_stock'     => [
				'type'         => 'boolean',
				'label'        => 'In stock',
				'default'      => true,
				'show_in_rest' => true,
			],
			'e2e_stock'        => [
				'type'         => 'integer',
				'label'        => 'Units in stock',
				'default'      => 0,
				'show_in_rest' => [
					'schema' => [
						'minimum' => 0,
						'maximum' => 1000,
					],
				],
			],
			'e2e_tagline'      => [
				'type'              => 'string',
				'label'             => 'Tagline',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			],
			'e2e_contact'      => [
				'type'         => 'string',
				'label'        => 'Contact email',
				'show_in_rest' => [ 'schema' => [ 'format' => 'email' ] ],
			],
			'e2e_features'     => [
				'type'         => 'string',
				'label'        => 'Features',
				'single'       => false,
				'show_in_rest' => true,
			],
			'e2e_dimensions'   => [
				'type'         => 'object',
				'label'        => 'Dimensions',
				'show_in_rest' => [
					'schema' => [
						'type'                 => 'object',
						'properties'           => [
							'width'  => [ 'type' => 'number' ],
							'height' => [ 'type' => 'number' ],
						],
						'additionalProperties' => false,
					],
				],
			],
			// Only administrators may edit it.
			'e2e_manager_note' => [
				'type'          => 'string',
				'label'         => 'Manager note',
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => static fn (): bool => current_user_can( 'manage_options' ),
			],
			// Protected (leading underscore), even though it's in the REST API.
			'_e2e_secret'      => [
				'type'          => 'string',
				'show_in_rest'  => true,
				'auth_callback' => '__return_true',
			],
			// Not in the REST API, so the block editor can't set it either.
			'e2e_hidden'       => [
				'type'         => 'string',
				'show_in_rest' => false,
			],
		];

		foreach ( $fields as $key => $args ) {
			register_post_meta( 'e2e_product', $key, $args + [ 'single' => true ] );
		}

		// Registered and in the REST API, but its type doesn't support custom fields.
		register_post_meta(
			'e2e_nofields',
			'e2e_rating',
			[
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
			]
		);
	}
);
