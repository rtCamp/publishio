<?php
/**
 * Tests for reading and setting custom fields with get-post and update-post.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Get_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Update_Post;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Fields;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Post_FieldsTest
 */
#[CoversClass( Post_Fields::class )]
#[CoversClass( Get_Post::class )]
#[CoversClass( Update_Post::class )]
class Post_FieldsTest extends TestCase {
	private const PRODUCT = 'publishio_product';

	private const PLAIN = 'publishio_plain';

	private const HIDDEN = 'publishio_hidden';

	/**
	 * Register a product type with fields of each kind, and a type without custom fields support, and run as an editor.
	 */
	protected function setUp(): void {
		parent::setUp();

		register_post_type(
			'publishio_product',
			[
				'labels'       => [ 'name' => 'Products' ],
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor', 'custom-fields' ],
			]
		);
		register_post_type(
			'publishio_plain',
			[
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);

		$this->field(
			self::PRODUCT,
			'price',
			[
				'type'              => 'number',
				'description'       => 'Price in USD.',
				'sanitize_callback' => static fn ( mixed $value ): float => round( (float) $value, 2 ),
			]
		);
		$this->field(
			self::PRODUCT,
			'status',
			[
				'type'         => 'string',
				'default'      => 'draft',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
						'enum' => [ 'draft', 'sent', 'paid' ],
					],
				],
			]
		);
		$this->field(
			self::PRODUCT,
			'colors',
			[
				'type'        => 'string',
				'single'      => false,
				'description' => 'One color per entry.',
			]
		);
		$this->field(
			self::PRODUCT,
			'_secret',
			[
				'type'          => 'string',
				'auth_callback' => '__return_true',
			]
		);
		$this->field(
			self::PRODUCT,
			'hidden',
			[
				'type'         => 'string',
				'show_in_rest' => false,
			]
		);
		$this->field(
			self::PRODUCT,
			'locked',
			[
				'type'          => 'string',
				'auth_callback' => '__return_false',
			]
		);
		$this->field( self::PRODUCT, 'footnotes', [ 'type' => 'string' ] );
		$this->field( self::PLAIN, 'price', [ 'type' => 'number' ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	/**
	 * Remove the fields and types, and reset the user.
	 */
	protected function tearDown(): void {
		foreach ( [ 'price', 'status', 'colors', '_secret', 'hidden', 'locked', 'footnotes', 'notes', 'stage', 'featured', 'size', 'code' ] as $key ) {
			unregister_post_meta( self::PRODUCT, $key );
		}

		unregister_post_meta( self::PLAIN, 'price' );
		unregister_post_meta( self::HIDDEN, 'price' );
		unregister_post_type( self::PRODUCT );
		unregister_post_type( self::PLAIN );
		unregister_post_type( self::HIDDEN );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The get-post tool lists the fields the user can set, with what each takes, and leaves out protected, hidden and locked ones and the Footnotes block's storage.
	 */
	public function test_get_post_lists_settable_fields(): void {
		$product = $this->post( self::PRODUCT, 'draft' );

		update_post_meta( $product, 'price', '49.5' );
		update_post_meta( $product, 'status', 'sent' );
		update_post_meta( $product, '_secret', 'x' );

		$result = $this->run_ability( 'get-post', [ 'post_id' => $product ] );

		$this->assertIsArray( $result );
		$this->assertSame( [ 'price', 'status', 'colors' ], array_keys( $result['fields'] ) );
		$this->assertSame(
			[
				'value'       => 49.5,
				'type'        => 'number',
				'description' => 'Price in USD.',
			],
			$result['fields']['price']
		);
		$this->assertSame( 'sent', $result['fields']['status']['value'] );
		$this->assertSame( [ 'draft', 'sent', 'paid' ], $result['fields']['status']['enum'] );
		$this->assertSame( [], $result['fields']['colors']['value'] );
		$this->assertSame( 'array', $result['fields']['colors']['type'] );
	}

	/**
	 * A post without fields to set has no fields key, so the result never carries an empty list where an object belongs.
	 */
	public function test_get_post_leaves_out_fields_when_none(): void {
		$plain = $this->run_ability( 'get-post', [ 'post_id' => $this->post( self::PLAIN, 'draft' ) ] );
		$post  = $this->run_ability( 'get-post', [ 'post_id' => $this->post( 'post', 'draft' ) ] );

		$this->assertIsArray( $plain );
		$this->assertIsArray( $post );
		$this->assertArrayNotHasKey( 'fields', $plain );
		$this->assertArrayNotHasKey( 'fields', $post );
		$this->assertStringNotContainsString( '"fields"', (string) wp_json_encode( $plain ) );
		$this->assertStringContainsString( '"fields":{"price":{', (string) wp_json_encode( $this->run_ability( 'get-post', [ 'post_id' => $this->post( self::PRODUCT, 'draft' ) ] ) ) );
	}

	/**
	 * The update-post tool saves fields through the site's sanitizing, returns them as stored, and resets a field with null.
	 */
	public function test_update_post_saves_fields(): void {
		$product = $this->post( self::PRODUCT, 'draft' );

		$result = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [
					'price'  => 59.999,
					'status' => 'paid',
					'colors' => [ 'red', 'blue' ],
				],
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'price'  => 60.0,
				'status' => 'paid',
				'colors' => [ 'red', 'blue' ],
			],
			$result['fields']
		);
		$this->assertSame( '60', get_post_meta( $product, 'price', true ) );

		$reset = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [ 'status' => null ],
			]
		);

		$this->assertIsArray( $reset );
		$this->assertSame( [ 'status' => 'draft' ], $reset['fields'] );
		$this->assertFalse( metadata_exists( 'post', $product, 'status' ) );

		$never_set = $this->run_ability(
			'update-post',
			[
				'post_id' => $this->post( self::PRODUCT, 'draft' ),
				'fields'  => [ 'price' => null ],
			]
		);

		$this->assertIsArray( $never_set );
		$this->assertSame( [ 'price' => 0.0 ], $never_set['fields'] );

		$blank = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'title'   => 'Renamed',
				'fields'  => '',
			]
		);

		$this->assertIsArray( $blank );
		$this->assertSame( 'Renamed', $blank['title'] );
		$this->assertArrayNotHasKey( 'fields', $blank );
	}

	/**
	 * A reset core would refuse, of a stored value that isn't valid or of a field the user can't delete, is refused before anything is saved.
	 */
	public function test_resets_core_would_refuse_save_nothing(): void {
		$this->field(
			self::PRODUCT,
			'notes',
			[
				'type'          => 'string',
				'auth_callback' => static fn ( mixed ...$args ): bool => 'edit_post_meta' === $args[4],
			]
		);

		$product = $this->post( self::PRODUCT, 'draft' );
		$title   = get_the_title( $product );

		update_post_meta( $product, 'status', 'refunded' );
		update_post_meta( $product, 'notes', 'Keep' );

		$invalid = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'title'   => 'Renamed',
				'fields'  => [
					'price'  => 10,
					'status' => null,
				],
			]
		);
		$denied  = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [ 'notes' => null ],
			]
		);

		$this->assertSame( 'invalid_field_value', $this->code( $invalid ) );
		$this->assertStringContainsString( 'status holds a value that isn\'t valid', $invalid->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertFalse( metadata_exists( 'post', $product, 'price' ) );
		$this->assertSame( $title, get_the_title( $product ) );
		$this->assertSame( 'invalid_field_value', $this->code( $denied ) );
		$this->assertStringContainsString( 'notes can\'t be reset by your account.', $denied->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertSame( 'Keep', get_post_meta( $product, 'notes', true ) );
	}

	/**
	 * Saving only fields runs the save hooks as core's REST API does, and the after-insert hook sees the new value.
	 */
	public function test_field_changes_run_the_save_hooks(): void {
		$product = $this->post( self::PRODUCT, 'draft' );
		$seen    = [];

		add_action(
			'save_post',
			static function () use ( &$seen ): void {
				$seen[] = 'save_post';
			}
		);
		add_action(
			'wp_after_insert_post',
			static function ( int $post_id ) use ( &$seen ): void {
				$seen[] = 'wp_after_insert_post: ' . get_post_meta( $post_id, 'price', true );
			}
		);

		$this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [ 'price' => 12 ],
			]
		);

		$this->assertSame( [ 'save_post', 'wp_after_insert_post: 12' ], $seen );
	}

	/**
	 * Labels keyed by value come back as a plain list, a type hidden from REST has no fields, and a call for one block's outline leaves fields out.
	 */
	public function test_get_post_follows_core_rest(): void {
		$this->field(
			self::PRODUCT,
			'stage',
			[
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
						'enum' => [
							'draft' => 'Draft',
							'paid'  => 'Paid',
						],
					],
				],
			]
		);
		register_post_type(
			'publishio_hidden',
			[
				'show_ui'  => true,
				'supports' => [ 'title', 'custom-fields' ],
			]
		);
		$this->field( self::HIDDEN, 'price', [ 'type' => 'number' ] );

		$product = self::factory()->post->create(
			[
				'post_type'    => self::PRODUCT,
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
				'post_content' => '<!-- wp:paragraph --><p>Fresh bread</p><!-- /wp:paragraph -->',
			]
		);

		$listed = $this->run_ability( 'get-post', [ 'post_id' => $product ] );
		$block  = $this->run_ability(
			'get-post',
			[
				'post_id' => $product,
				'path'    => '0:paragraph',
			]
		);
		$hidden = $this->run_ability( 'get-post', [ 'post_id' => $this->post( self::HIDDEN, 'draft' ) ] );

		$this->assertIsArray( $listed );
		$this->assertSame( [ 'Draft', 'Paid' ], $listed['fields']['stage']['enum'] );
		$this->assertIsArray( $block );
		$this->assertArrayNotHasKey( 'fields', $block );
		$this->assertIsArray( $hidden );
		$this->assertArrayNotHasKey( 'fields', $hidden );
	}

	/**
	 * Fields the user can't set are refused by name, and one invalid value means nothing is saved.
	 */
	public function test_update_post_refuses_bad_fields(): void {
		$product = $this->post( self::PRODUCT, 'draft' );
		$title   = get_the_title( $product );

		foreach ( [ 'color', '_secret', 'hidden', 'locked', 'footnotes' ] as $key ) {
			$refusal = $this->run_ability(
				'update-post',
				[
					'post_id' => $product,
					'fields'  => [ $key => 'x' ],
				]
			);

			$this->assertSame( 'unknown_field', $this->code( $refusal ), $key );
			$this->assertStringContainsString( 'You can set: price, status, colors.', $refusal->get_error_message() ); // @phpstan-ignore method.nonObject
		}

		$invalid = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'title'   => 'Renamed',
				'fields'  => [
					'price'  => 10,
					'status' => 'refunded',
				],
			]
		);

		$this->assertSame( 'invalid_field_value', $this->code( $invalid ) );
		$this->assertStringContainsString( 'status is not one of draft, sent, and paid.', $invalid->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertFalse( metadata_exists( 'post', $product, 'price' ) );
		$this->assertSame( $title, get_the_title( $product ) );

		$none = $this->run_ability(
			'update-post',
			[
				'post_id' => $this->post( self::PLAIN, 'draft' ),
				'fields'  => [ 'price' => 10 ],
			]
		);

		$this->assertSame( 'unknown_field', $this->code( $none ) );
		$this->assertStringContainsString( 'You can\'t set any custom fields on', $none->get_error_message() ); // @phpstan-ignore method.nonObject
	}

	/**
	 * Setting a field on a published post needs the user's approval, like any other change.
	 */
	public function test_fields_on_a_live_post_need_approval(): void {
		$product = $this->post( self::PRODUCT, 'publish' );
		$refusal = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [ 'price' => 10 ],
			]
		);

		$this->assertSame( 'live_post_needs_approval', $this->code( $refusal ) );
		$this->assertFalse( metadata_exists( 'post', $product, 'price' ) );

		preg_match( '/approval_token: "([^"]+)"/', $refusal->get_error_message(), $match ); // @phpstan-ignore method.nonObject

		$this->assertIsArray(
			$this->run_ability(
				'update-post',
				[
					'post_id'        => $product,
					'fields'         => [ 'price' => 10 ],
					'approval_token' => $match[1],
				]
			)
		);
		$this->assertSame( '10', get_post_meta( $product, 'price', true ) );
	}

	/**
	 * A contributor sets fields on their own draft but can't read or set them on someone else's.
	 */
	public function test_contributor_sets_fields_on_own_draft_only(): void {
		$others = $this->post( self::PRODUCT, 'draft' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$own   = $this->post( self::PRODUCT, 'draft' );
		$saved = $this->run_ability(
			'update-post',
			[
				'post_id' => $own,
				'fields'  => [ 'price' => 5 ],
			]
		);

		$this->assertIsArray( $saved );
		$this->assertSame( [ 'price' => 5.0 ], $saved['fields'] );
		$this->assertSame(
			'post_not_found',
			$this->code(
				$this->run_ability(
					'update-post',
					[
						'post_id' => $others,
						'fields'  => [ 'price' => 5 ],
					]
				)
			)
		);
		$this->assertSame( 'post_not_found', $this->code( $this->run_ability( 'get-post', [ 'post_id' => $others ] ) ) );
	}

	/**
	 * Booleans, objects, patterns and fields with several values read clearly and save and reset like any other.
	 */
	public function test_fields_of_each_kind(): void {
		$this->field(
			self::PRODUCT,
			'featured',
			[
				'type'    => 'boolean',
				'default' => false,
			]
		);
		$this->field(
			self::PRODUCT,
			'size',
			[
				'type'         => 'object',
				'show_in_rest' => [
					'schema' => [
						'type'       => 'object',
						'properties' => [ 'width' => [ 'type' => 'number' ] ],
					],
				],
			]
		);
		$this->field(
			self::PRODUCT,
			'code',
			[
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type'    => 'string',
						'pattern' => '^[A-Z]{3}$',
					],
				],
			]
		);

		$product = $this->post( self::PRODUCT, 'draft' );
		$read    = $this->run_ability( 'get-post', [ 'post_id' => $product ] );

		$this->assertIsArray( $read );
		$this->assertStringContainsString( '"size":{"value":{},', (string) wp_json_encode( $read ) );
		$this->assertSame( '^[A-Z]{3}$', $read['fields']['code']['pattern'] );
		$this->assertSame(
			[
				'value'       => [],
				'type'        => 'array',
				'description' => 'One color per entry.',
				'items'       => [ 'type' => 'string' ],
			],
			$read['fields']['colors']
		);

		$this->assertIsArray(
			$this->run_ability(
				'update-post',
				[
					'post_id' => $product,
					'fields'  => [ 'featured' => false ],
				]
			)
		);

		$reset = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [
					'featured' => null,
					'size'     => null,
				],
			]
		);

		$this->assertIsArray( $reset );
		$this->assertFalse( metadata_exists( 'post', $product, 'featured' ) );
		$this->assertSame( '{"featured":false,"size":{}}', wp_json_encode( $reset['fields'] ) );

		$extra = $this->run_ability(
			'update-post',
			[
				'post_id' => $product,
				'fields'  => [ 'size' => [ 'depth' => 2 ] ],
			]
		);

		$this->assertSame( 'invalid_field_value', $this->code( $extra ) );
		$this->assertStringContainsString( 'size: depth is not a valid property of Object.', $extra->get_error_message() ); // @phpstan-ignore method.nonObject
	}

	/**
	 * Register a field on a type, shown in REST unless the arguments say otherwise.
	 *
	 * @param string               $type The post type.
	 * @param string               $key  The meta key.
	 * @param array<string, mixed> $args Arguments for register_post_meta().
	 */
	private function field( string $type, string $key, array $args ): void {
		register_post_meta(
			$type,
			$key,
			$args + [
				'single'       => true,
				'show_in_rest' => true,
			]
		);
	}

	/**
	 * A post of a type by the current user.
	 *
	 * @param string $type   The post type.
	 * @param string $status The status.
	 */
	private function post( string $type, string $status ): int {
		return self::factory()->post->create(
			[
				'post_type'   => $type,
				'post_status' => $status,
				'post_author' => get_current_user_id(),
			]
		);
	}

	/**
	 * Run an ability.
	 *
	 * @param string               $name  The ability name without the prefix.
	 * @param array<string, mixed> $input The input.
	 */
	private function run_ability( string $name, array $input ): mixed {
		return wp_get_ability( 'publishio/' . $name )->execute( $input ); // @phpstan-ignore method.nonObject
	}

	/**
	 * The error code of a result, or "ok".
	 *
	 * @param mixed $result A result.
	 */
	private function code( mixed $result ): string {
		return is_wp_error( $result ) ? (string) $result->get_error_code() : 'ok';
	}
}
