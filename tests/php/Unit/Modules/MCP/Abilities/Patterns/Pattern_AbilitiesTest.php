<?php
/**
 * Tests for the pattern abilities, insert-pattern and Pattern_Library.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Insert_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Create_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Get_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\List_Patterns;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Update_Pattern;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Pattern_AbilitiesTest
 */
#[CoversClass( List_Patterns::class )]
#[CoversClass( Get_Pattern::class )]
#[CoversClass( Insert_Pattern::class )]
#[CoversClass( Create_Pattern::class )]
#[CoversClass( Update_Pattern::class )]
#[CoversClass( Pattern_Library::class )]
class Pattern_AbilitiesTest extends TestCase {
	private const PARAGRAPH = '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->';

	private const HEADING = '<!-- wp:heading --><h2 class="wp-block-heading">%s</h2><!-- /wp:heading -->';

	private const OVERRIDE_HEADING = '<!-- wp:heading {"metadata":{"name":"CTA heading","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Ready?</h2><!-- /wp:heading -->';

	/**
	 * The registered pattern names, for cleanup.
	 *
	 * @var list<string>
	 */
	private array $registered = [];

	/**
	 * The synced pattern.
	 *
	 * @var int
	 */
	private int $synced;

	/**
	 * The unsynced saved pattern.
	 *
	 * @var int
	 */
	private int $unsynced;

	/**
	 * Run as an editor, with theme and plugin patterns and two saved patterns.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->register( get_stylesheet() . '/hero', 'Hero', sprintf( self::HEADING, 'Welcome' ) );
		$this->register( 'acme-plugin/two-roots', 'Two roots', sprintf( self::HEADING, 'One' ) . "\n\n" . sprintf( self::PARAGRAPH, 'Two' ), [ 'postTypes' => [ 'page' ] ] );
		$this->register( 'acme-plugin/hidden', 'Hidden', sprintf( self::PARAGRAPH, 'Hidden' ), [ 'inserter' => false ] );
		$this->register( 'acme-plugin/nested', 'Nested', sprintf( self::PARAGRAPH, 'Outer' ) . '<!-- wp:pattern {"slug":"' . get_stylesheet() . '/hero"} /-->' );

		$this->synced   = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Footer CTA',
				'post_content' => self::OVERRIDE_HEADING,
			]
		);
		$this->unsynced = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Quote card',
				'post_content' => sprintf( self::PARAGRAPH, 'Card' ),
				'meta_input'   => [ Pattern_Library::SYNC_META => 'unsynced' ],
			]
		);
	}

	/**
	 * Remove the registered patterns and reset the user.
	 */
	protected function tearDown(): void {
		foreach ( $this->registered as $name ) {
			unregister_block_pattern( $name );
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The list tells sources apart, hides inserter-hidden patterns, and filters by source, synced, post type and search.
	 */
	public function test_list_patterns(): void {
		$all = $this->sources( [] );

		$this->assertSame( 'theme', $all[ get_stylesheet() . '/hero' ] );
		$this->assertSame( 'plugin', $all['acme-plugin/two-roots'] );
		$this->assertSame( 'user', $all[ 'core/block/' . $this->synced ] );
		$this->assertArrayNotHasKey( 'acme-plugin/hidden', $all );

		$this->assertSame(
			[ 'core/block/' . $this->synced ],
			array_keys(
				$this->sources(
					[
						'source' => 'user',
						'synced' => true,
					]
				)
			)
		);
		$this->assertArrayNotHasKey( 'acme-plugin/two-roots', $this->sources( [ 'post_type' => 'post' ] ) );
		$this->assertArrayHasKey( 'acme-plugin/two-roots', $this->sources( [ 'post_type' => 'page' ] ) );
		$this->assertSame( [ 'core/block/' . $this->unsynced ], array_keys( $this->sources( [ 'search' => 'quote' ] ) ) );
	}

	/**
	 * Unknown names are refused; a synced pattern lists its override fields, and not_allowed lists blocks the post type can't use.
	 */
	public function test_get_pattern(): void {
		$this->assertSame( 'unknown_pattern', $this->code( $this->run_ability( 'get-pattern', [ 'name' => 'acme/none' ] ) ) );
		$this->assertSame( 'unknown_pattern', $this->code( $this->run_ability( 'get-pattern', [ 'name' => 'core/block/abc' ] ) ) );

		$synced = $this->run_ability( 'get-pattern', [ 'name' => 'core/block/' . $this->synced ] );

		$this->assertIsArray( $synced );
		$this->assertTrue( $synced['synced'] );
		$this->assertSame( 'CTA heading', $synced['overrides'][0]['name'] );
		$this->assertSame( 'core/heading', $synced['overrides'][0]['block'] );
		$this->assertContains( 'content', $synced['overrides'][0]['attributes'] );

		$filter = static fn (): array => [ 'core/paragraph' ];
		add_filter( 'allowed_block_types_all', $filter );
		$plugin = $this->run_ability(
			'get-pattern',
			[
				'name'      => 'acme-plugin/two-roots',
				'post_type' => 'page',
			]
		);
		remove_filter( 'allowed_block_types_all', $filter );

		$this->assertIsArray( $plugin );
		$this->assertSame( [ 'core/heading', 'core/paragraph' ], $plugin['blocks_used'] );
		$this->assertSame( [ 'core/heading' ], $plugin['not_allowed'] );
		$this->assertCount( 2, $plugin['outline']['rows'] );
	}

	/**
	 * Inserting refuses unknown patterns, patterns for other post types, and live posts without approval.
	 */
	public function test_insert_pattern_refusals(): void {
		$post_id = $this->post( 'post', 'draft' );

		$this->assertSame( 'unknown_pattern', $this->code( $this->insert( $post_id, 'acme/none' ) ) );
		$this->assertSame( 'pattern_not_for_post_type', $this->code( $this->insert( $post_id, 'acme-plugin/two-roots' ) ) );
		$this->assertSame( 'live_post_needs_approval', $this->code( $this->insert( $this->post( 'page', 'publish' ), 'acme-plugin/two-roots' ) ) );
	}

	/**
	 * Every root block goes in, nested patterns are resolved, a synced pattern goes in as a reference and a saved unsynced one as a copy.
	 */
	public function test_insert_pattern(): void {
		$page_id = $this->post( 'page', 'draft' );
		$result  = $this->insert( $page_id, 'acme-plugin/two-roots' );

		$this->assertIsArray( $result );
		$this->assertSame( [ '1:heading', '2:paragraph' ], $result['changed'] );

		$post_id = $this->post( 'post', 'draft' );

		$this->assertIsArray( $this->insert( $post_id, 'acme-plugin/nested' ) );
		$this->assertIsArray( $this->insert( $post_id, 'core/block/' . $this->synced ) );
		$this->assertIsArray( $this->insert( $post_id, 'core/block/' . $this->unsynced ) );

		$names = array_map( static fn ( array $block ): ?string => $block['blockName'], array_values( array_filter( parse_blocks( (string) get_post_field( 'post_content', $post_id ) ), static fn ( array $block ): bool => null !== $block['blockName'] ) ) );

		$this->assertSame( [ 'core/paragraph', 'core/paragraph', 'core/heading', 'core/block', 'core/paragraph' ], $names );
		$this->assertStringContainsString( '<!-- wp:block {"ref":' . $this->synced . '} /-->', (string) get_post_field( 'post_content', $post_id ) );
		$this->assertStringNotContainsString( 'wp:pattern', (string) get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Creating refuses a missing or double source, blocks that aren't side by side, invalid markup, a synced pattern from a live post without approval, new categories the account can't add, and accounts that can't publish.
	 */
	public function test_create_pattern_refusals(): void {
		$post_id = $this->post( 'post', 'draft', sprintf( self::PARAGRAPH, 'A' ) . sprintf( self::PARAGRAPH, 'B' ) . sprintf( self::PARAGRAPH, 'C' ) );

		$this->assertSame( 'invalid_source', $this->code( $this->create( [] ) ) );
		$this->assertSame(
			'invalid_source',
			$this->code(
				$this->create(
					[
						'markup'  => sprintf( self::PARAGRAPH, 'A' ),
						'post_id' => $post_id,
					]
				)
			)
		);
		$this->assertSame(
			'invalid_selection',
			$this->code(
				$this->create(
					[
						'post_id' => $post_id,
						'paths'   => [ '0:paragraph', '2:paragraph' ],
					]
				)
			)
		);
		$this->assertSame( 'invalid_attribute', $this->code( $this->create( [ 'markup' => '<!-- wp:paragraph {"bogus":1} --><p>A</p><!-- /wp:paragraph -->' ] ) ) );

		$live     = $this->post( 'post', 'publish' );
		$patterns = wp_count_posts( 'wp_block' )->publish;

		$this->assertSame(
			'live_post_needs_approval',
			$this->code(
				$this->create(
					[
						'synced'  => true,
						'post_id' => $live,
						'paths'   => [ '0:paragraph' ],
					]
				)
			)
		);
		$this->assertSame( $patterns, wp_count_posts( 'wp_block' )->publish );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );
		$this->assertSame(
			'invalid_category',
			$this->code(
				$this->create(
					[
						'markup'     => sprintf( self::PARAGRAPH, 'A' ),
						'categories' => [ 'Brand new' ],
					]
				)
			)
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		$this->assertSame( 'forbidden', $this->code( $this->create( [ 'markup' => sprintf( self::PARAGRAPH, 'A' ) ] ) ) );
	}

	/**
	 * An unsynced pattern is saved with its categories; made from a post it leaves the post alone, while a synced one replaces the blocks with a reference.
	 */
	public function test_create_pattern(): void {
		$from_markup = $this->create(
			[
				'markup'     => sprintf( self::PARAGRAPH, 'Saved' ),
				'categories' => [ 'Cards' ],
			]
		);

		$this->assertIsArray( $from_markup );

		$id = (int) substr( $from_markup['name'], strlen( Pattern_Library::USER_PREFIX ) );

		$this->assertSame( 'unsynced', get_post_meta( $id, Pattern_Library::SYNC_META, true ) );
		$this->assertSame( [ 'Cards' ], wp_get_object_terms( $id, Pattern_Library::CATEGORY_TAXONOMY, [ 'fields' => 'names' ] ) );

		$content = sprintf( self::PARAGRAPH, 'A' ) . sprintf( self::PARAGRAPH, 'B' ) . sprintf( self::PARAGRAPH, 'C' );
		$post_id = $this->post( 'post', 'draft', $content );

		$this->assertIsArray(
			$this->create(
				[
					'post_id' => $post_id,
					'paths'   => [ '0:paragraph' ],
				]
			)
		);
		$this->assertSame( $content, get_post_field( 'post_content', $post_id ) );

		$synced = $this->create(
			[
				'synced'  => true,
				'post_id' => $post_id,
				'paths'   => [ '2:paragraph', '1:paragraph' ],
			]
		);

		$this->assertIsArray( $synced );
		$this->assertSame( [ '1:block' ], $synced['changed'] );

		$ref    = (int) substr( $synced['name'], strlen( Pattern_Library::USER_PREFIX ) );
		$blocks = array_values( array_filter( parse_blocks( (string) get_post_field( 'post_content', $post_id ) ), static fn ( array $block ): bool => null !== $block['blockName'] ) );

		$this->assertSame( [ 'core/paragraph', 'core/block' ], array_column( $blocks, 'blockName' ) );
		$this->assertSame( $ref, $blocks[1]['attrs']['ref'] );
		$this->assertSame( '', get_post_meta( $ref, Pattern_Library::SYNC_META, true ) );
		$this->assertStringContainsString( '<p>B</p>', (string) get_post_field( 'post_content', $ref ) );
		$this->assertStringContainsString( '<p>C</p>', (string) get_post_field( 'post_content', $ref ) );
	}

	/**
	 * Theme and synced patterns are refused; an unsynced one is saved, and a post that already used it keeps its copy.
	 */
	public function test_update_pattern(): void {
		$markup = sprintf( self::PARAGRAPH, 'New card' );

		$this->assertSame( 'pattern_read_only', $this->code( $this->update( get_stylesheet() . '/hero', $markup ) ) );
		$this->assertSame( 'synced_pattern_not_supported', $this->code( $this->update( 'core/block/' . $this->synced, $markup ) ) );
		$this->assertSame( 'invalid_attribute', $this->code( $this->update( 'core/block/' . $this->unsynced, '<!-- wp:paragraph {"bogus":1} --><p>A</p><!-- /wp:paragraph -->' ) ) );

		$post_id = $this->post( 'post', 'draft' );
		$this->insert( $post_id, 'core/block/' . $this->unsynced );

		$result = $this->run_ability(
			'update-pattern',
			[
				'pattern' => 'core/block/' . $this->unsynced,
				'markup'  => $markup,
				'title'   => 'Quote card v2',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Quote card v2', $result['title'] );
		$this->assertSame( $markup, get_post_field( 'post_content', $this->unsynced ) );
		$this->assertStringContainsString( '<p>Card</p>', (string) get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Attribute forms the site's patterns use pass every write, since the editor migrates them, while other unknown attributes are still refused.
	 */
	public function test_attribute_forms_from_patterns_are_accepted(): void {
		$legacy = '<!-- wp:heading {"legacyAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Legacy</h2><!-- /wp:heading -->';
		$this->register( 'acme-plugin/legacy', 'Legacy', $legacy );

		$post_id  = $this->post( 'post', 'draft' );
		$rendered = $this->run_ability(
			'render-blocks',
			[
				'markup'  => $legacy,
				'post_id' => $post_id,
			]
		);

		$this->assertIsArray( $rendered );
		$this->assertTrue( $rendered['valid'] );

		$example = $this->run_ability( 'get-block-type', [ 'name' => 'heading' ] );

		$this->assertIsArray( $example );
		$this->assertSame( 'ok', $this->code( $this->add( $post_id, (string) $example['example_markup'] ) ) );
		$this->assertSame( 'ok', $this->code( $this->add( $post_id, str_replace( 'Legacy', 'Edited', $legacy ) ) ) );
		$this->assertSame( 'ok', $this->code( $this->create( [ 'markup' => $legacy ] ) ) );
		$this->assertSame( 'invalid_attribute', $this->code( $this->add( $post_id, '<!-- wp:heading {"legacyAlign":1} --><h2 class="wp-block-heading">Typed</h2><!-- /wp:heading -->' ) ) );
		$this->assertSame( 'invalid_attribute', $this->code( $this->add( $post_id, '<!-- wp:heading {"bogus":1} --><h2 class="wp-block-heading">Bogus</h2><!-- /wp:heading -->' ) ) );

		$old = '<!-- wp:paragraph {"oldThing":"x"} --><p>Old</p><!-- /wp:paragraph -->';
		wp_update_post(
			wp_slash(
				[
					'ID'           => $this->unsynced,
					'post_content' => $old,
				]
			)
		);

		$this->assertSame( 'ok', $this->code( $this->update( 'core/block/' . $this->unsynced, $old . sprintf( self::PARAGRAPH, 'Added' ) ) ) );
		$this->assertSame( 'invalid_attribute', $this->code( $this->update( 'core/block/' . $this->unsynced, '<!-- wp:paragraph {"newThing":"x"} --><p>New</p><!-- /wp:paragraph -->' ) ) );
	}

	/**
	 * Add markup at the end of a post.
	 *
	 * @param int    $post_id The post.
	 * @param string $markup  The markup.
	 */
	private function add( int $post_id, string $markup ): mixed {
		return $this->run_ability(
			'insert-blocks',
			[
				'post_id' => $post_id,
				'markup'  => $markup,
			]
		);
	}

	/**
	 * Register a pattern for the test.
	 *
	 * @param string               $name    The name.
	 * @param string               $title   The title.
	 * @param string               $content The markup.
	 * @param array<string, mixed> $extra   Other properties.
	 */
	private function register( string $name, string $title, string $content, array $extra = [] ): void {
		register_block_pattern(
			$name,
			[
				'title'   => $title,
				'content' => $content,
			] + $extra
		);

		$this->registered[] = $name;
	}

	/**
	 * A post by the current user.
	 *
	 * @param string $type    The post type.
	 * @param string $status  The status.
	 * @param string $content The content.
	 */
	private function post( string $type, string $status, string $content = '' ): int {
		return self::factory()->post->create(
			[
				'post_type'    => $type,
				'post_status'  => $status,
				'post_author'  => get_current_user_id(),
				'post_content' => '' === $content ? sprintf( self::PARAGRAPH, 'Intro' ) : $content,
			]
		);
	}

	/**
	 * The listed patterns' sources by name.
	 *
	 * @param array<string, mixed> $input The list-patterns input.
	 *
	 * @return array<string, string>
	 */
	private function sources( array $input ): array {
		$list = $this->run_ability( 'list-patterns', $input );

		$this->assertIsArray( $list );

		return array_column( array_filter( $list['patterns'], static fn ( array $pattern ): bool => ! str_starts_with( $pattern['name'], 'core/' ) || str_starts_with( $pattern['name'], 'core/block/' ) ), 'source', 'name' );
	}

	/**
	 * Insert a pattern at the end of a post.
	 *
	 * @param int    $post_id The post.
	 * @param string $pattern The pattern name.
	 */
	private function insert( int $post_id, string $pattern ): mixed {
		return $this->run_ability(
			'insert-pattern',
			[
				'post_id' => $post_id,
				'pattern' => $pattern,
			]
		);
	}

	/**
	 * Create a pattern, unsynced unless the input says otherwise.
	 *
	 * @param array<string, mixed> $input The input besides the title.
	 */
	private function create( array $input ): mixed {
		return $this->run_ability(
			'create-pattern',
			$input + [
				'title'  => 'New pattern',
				'synced' => false,
			]
		);
	}

	/**
	 * Update a pattern's markup.
	 *
	 * @param string $pattern The pattern name.
	 * @param string $markup  The markup.
	 */
	private function update( string $pattern, string $markup ): mixed {
		return $this->run_ability(
			'update-pattern',
			[
				'pattern' => $pattern,
				'markup'  => $markup,
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
