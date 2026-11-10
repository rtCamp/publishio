<?php
/**
 * Tests for the media and term tools on a custom post type, and their guards.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Post_Details;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Post_Terms;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Attachments;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Posts;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Featured_Image;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Post_Terms;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Custom_Post_TypeTest
 */
#[CoversClass( Set_Featured_Image::class )]
#[CoversClass( Set_Post_Terms::class )]
#[CoversClass( Search_Attachments::class )]
#[CoversClass( Search_Posts::class )]
#[CoversClass( Get_Post_Terms::class )]
#[CoversClass( Post_Details::class )]
#[CoversClass( Post_Writer::class )]
class Custom_Post_TypeTest extends TestCase {
	private const BOOK = 'publishio_book';

	private const NOTE = 'publishio_note';

	private const LEDGER = 'publishio_ledger';

	private const GENRE = 'publishio_genre';

	private const SECRET = 'publishio_secret';

	private const INTERNAL = 'publishio_internal';

	/**
	 * Register a book type with a genre taxonomy, featured images and excerpts, a note type without them, a ledger type visitors can't view, a secret type with its own capabilities and an internal type without an admin screen, and run as an editor.
	 */
	protected function setUp(): void {
		parent::setUp();

		register_post_type(
			'publishio_book',
			[
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			]
		);
		register_post_type(
			'publishio_note',
			[
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);
		register_post_type(
			'publishio_ledger',
			[
				'labels'       => [
					'name'          => 'Ledger entries',
					'singular_name' => 'Ledger entry',
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);
		register_post_type(
			'publishio_secret',
			[
				'public'          => false,
				'show_ui'         => true,
				'capability_type' => 'publishio_secret',
				'map_meta_cap'    => true,
			]
		);
		register_post_type(
			'publishio_internal',
			[
				'public'  => false,
				'show_ui' => false,
			]
		);
		register_taxonomy(
			self::GENRE,
			self::BOOK,
			[
				'public'       => true,
				'show_in_rest' => true,
			]
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	/**
	 * Remove the types and reset the user.
	 */
	protected function tearDown(): void {
		unregister_taxonomy( self::GENRE );
		unregister_post_type( self::BOOK );
		unregister_post_type( self::NOTE );
		unregister_post_type( self::LEDGER );
		unregister_post_type( self::SECRET );
		unregister_post_type( self::INTERNAL );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Terms go on a custom type through its own taxonomy, and a taxonomy it doesn't have is refused.
	 */
	public function test_terms_on_a_custom_type(): void {
		$book  = $this->post( self::BOOK, 'draft' );
		$genre = self::factory()->term->create(
			[
				'taxonomy' => self::GENRE,
				'slug'     => 'mystery',
			]
		);

		$result = $this->run_ability(
			'set-post-terms',
			[
				'post_id'  => $book,
				'taxonomy' => self::GENRE,
				'terms'    => [ 'mystery' ],
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ $genre ], wp_get_object_terms( $book, self::GENRE, [ 'fields' => 'ids' ] ) );
		$this->assertSame(
			'taxonomy_not_registered',
			$this->code(
				$this->run_ability(
					'set-post-terms',
					[
						'post_id'  => $book,
						'taxonomy' => 'category',
						'terms'    => [],
					]
				)
			)
		);
	}

	/**
	 * A custom type with featured images takes one; a type without them is refused.
	 */
	public function test_featured_image_on_custom_types(): void {
		$attachment = self::factory()->attachment->create_object(
			[
				'file'           => 'cover.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);

		$book = $this->post( self::BOOK, 'draft' );

		$this->assertIsArray(
			$this->run_ability(
				'set-featured-image',
				[
					'post_id'       => $book,
					'attachment_id' => $attachment,
				]
			)
		);
		$this->assertSame( $attachment, (int) get_post_thumbnail_id( $book ) );
		$this->assertSame(
			'no_featured_image',
			$this->code(
				$this->run_ability(
					'set-featured-image',
					[
						'post_id'       => $this->post( self::NOTE, 'draft' ),
						'attachment_id' => $attachment,
					]
				)
			)
		);
	}

	/**
	 * Changing a live post's details needs the user's approval, a post open in the editor can still change, and an unreadable post is reported as not found.
	 */
	public function test_details_follow_the_post_guards(): void {
		$live   = $this->post( self::BOOK, 'publish' );
		$refuse = $this->run_ability(
			'set-post-terms',
			[
				'post_id'  => $live,
				'taxonomy' => self::GENRE,
				'terms'    => [],
			]
		);

		$this->assertSame( 'live_post_needs_approval', $this->code( $refuse ) );
		$this->assertMatchesRegularExpression( '/approval_token: "([0-9]+\.[0-9a-f]{64})"/', $refuse->get_error_message() ); // @phpstan-ignore method.nonObject
		preg_match( '/approval_token: "([^"]+)"/', $refuse->get_error_message(), $match ); // @phpstan-ignore method.nonObject

		$this->assertIsArray(
			$this->run_ability(
				'set-post-terms',
				[
					'post_id'        => $live,
					'taxonomy'       => self::GENRE,
					'terms'          => [],
					'approval_token' => $match[1],
				]
			)
		);

		$open = $this->post( self::BOOK, 'draft' );
		update_post_meta( $open, '_edit_lock', time() . ':' . self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame(
			'ok',
			$this->code(
				$this->run_ability(
					'set-featured-image',
					[
						'post_id'       => $open,
						'attachment_id' => 0,
					]
				)
			)
		);

		$private = self::factory()->post->create(
			[
				'post_type'   => self::BOOK,
				'post_status' => 'draft',
				'post_author' => self::factory()->user->create( [ 'role' => 'administrator' ] ),
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$this->assertSame(
			'post_not_found',
			$this->code(
				$this->run_ability(
					'set-featured-image',
					[
						'post_id'       => $private,
						'attachment_id' => 0,
					]
				)
			)
		);
	}

	/**
	 * A type visitors can't view has no address to share, and a published item of any type needs approval to change.
	 */
	public function test_types_visitors_cant_view_have_no_address(): void {
		$entry = $this->post( self::LEDGER, 'publish' );
		$book  = $this->post( self::BOOK, 'publish' );
		$found = $this->run_ability(
			'search-posts',
			[
				'post_type' => self::LEDGER,
				'status'    => [ 'publish' ],
			]
		);

		$entry_post   = $this->run_ability( 'get-post', [ 'post_id' => $entry ] );
		$book_post    = $this->run_ability( 'get-post', [ 'post_id' => $book ] );
		$entry_review = $this->run_ability( 'preview-post', [ 'post_id' => $entry ] );

		$this->assertIsArray( $entry_post );
		$this->assertIsArray( $book_post );
		$this->assertIsArray( $entry_review );
		$this->assertIsArray( $found );
		$this->assertArrayHasKey( 'url', $entry_post );
		$this->assertNull( $entry_post['url'] );
		$this->assertSame( get_permalink( $book ), $book_post['url'] );
		$this->assertArrayHasKey( 'url', $found[0] );
		$this->assertNull( $found[0]['url'] );
		$this->assertNull( $entry_review['url'] );

		$entry_refusal = $this->run_ability(
			'update-post',
			[
				'post_id' => $entry,
				'title'   => 'Renamed',
			]
		);
		$book_refusal  = $this->run_ability(
			'update-post',
			[
				'post_id' => $book,
				'title'   => 'Renamed',
			]
		);

		$this->assertSame( 'live_post_needs_approval', $this->code( $entry_refusal ) );
		$this->assertStringContainsString( 'is published, so this change takes effect right away.', $entry_refusal->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertStringContainsString( 'is published, so this change takes effect right away.', $book_refusal->get_error_message() ); // @phpstan-ignore method.nonObject

		preg_match( '/approval_token: "([^"]+)"/', $entry_refusal->get_error_message(), $match ); // @phpstan-ignore method.nonObject

		$renamed = $this->run_ability(
			'update-post',
			[
				'post_id'        => $entry,
				'title'          => 'Renamed',
				'approval_token' => $match[1],
			]
		);

		$this->assertIsArray( $renamed );
		$this->assertSame( 'Renamed', $renamed['title'] );
		$this->assertNull( $renamed['url'] );
	}

	/**
	 * Searching every type finds items of types visitors can't view and other people's items, newest first.
	 */
	public function test_any_finds_types_visitors_cant_view(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$entry = self::factory()->post->create(
			[
				'post_type'   => self::LEDGER,
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Harbor Hotel invoice',
				'post_date'   => '2026-03-02 00:00:00',
			]
		);
		$book  = self::factory()->post->create(
			[
				'post_type'   => self::BOOK,
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Harbor Hotel guide',
				'post_date'   => '2026-03-01 00:00:00',
			]
		);

		$found = $this->run_ability(
			'search-posts',
			[
				'search'    => 'Harbor Hotel',
				'post_type' => 'any',
				'status'    => [ 'publish' ],
			]
		);

		$this->assertIsArray( $found );
		$this->assertSame( [ $entry, $book ], array_column( $found, 'post_id' ) );
		$this->assertSame( self::LEDGER, $found[0]['post_type'] );
	}

	/**
	 * Searching every type leaves out other people's items without the right to edit them, types the user can't edit, and types without an admin screen.
	 */
	public function test_any_searches_only_what_the_user_can_edit(): void {
		$admin       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$contributor = self::factory()->user->create( [ 'role' => 'contributor' ] );
		$create      = static fn ( string $type, int $author ): int => self::factory()->post->create(
			[
				'post_type'   => $type,
				'post_status' => 'draft',
				'post_author' => $author,
				'post_title'  => 'Quarterly report',
			]
		);
		$theirs      = $create( self::LEDGER, $admin );
		$secret      = $create( self::SECRET, $admin );
		$internal    = $create( self::INTERNAL, $admin );

		$editor_ids = array_column(
			(array) $this->run_ability(
				'search-posts',
				[
					'search'    => 'Quarterly',
					'post_type' => 'any',
				]
			),
			'post_id'
		);

		wp_set_current_user( $contributor );

		$own = $create( self::LEDGER, $contributor );

		$contributor_ids = array_column(
			(array) $this->run_ability(
				'search-posts',
				[
					'search'    => 'Quarterly',
					'post_type' => 'any',
				]
			),
			'post_id'
		);

		$this->assertContains( $theirs, $editor_ids );
		$this->assertNotContains( $secret, $editor_ids );
		$this->assertNotContains( $internal, $editor_ids );
		$this->assertSame( [ $own ], $contributor_ids );
	}

	/**
	 * Items of several types with the same date come newest ID first, so each type's limit cuts where the merged list does.
	 */
	public function test_any_breaks_date_ties_by_id(): void {
		$create = static fn ( string $type, string $status ): int => self::factory()->post->create(
			[
				'post_type'   => $type,
				'post_status' => $status,
				'post_author' => get_current_user_id(),
				'post_title'  => 'Tied entry',
				'post_date'   => '2026-04-01 00:00:00',
			]
		);

		$create( self::BOOK, 'publish' );
		$create( self::LEDGER, 'publish' );

		$newest = $create( self::BOOK, 'draft' );
		$found  = $this->run_ability(
			'search-posts',
			[
				'search'    => 'Tied entry',
				'post_type' => 'any',
				'per_page'  => 1,
			]
		);

		$this->assertSame( [ $newest ], array_column( (array) $found, 'post_id' ) );
	}

	/**
	 * An excerpt is refused only where it would never show: on a type without excerpts that visitors can't view.
	 */
	public function test_excerpt_only_where_it_can_show(): void {
		$entry   = $this->post( self::LEDGER, 'draft' );
		$before  = get_post_field( 'post_excerpt', $entry );
		$created = $this->run_ability(
			'create-post',
			[
				'post_type' => self::LEDGER,
				'title'     => 'March payroll',
				'excerpt'   => 'Salaries for March.',
			]
		);
		$updated = $this->run_ability(
			'update-post',
			[
				'post_id' => $entry,
				'excerpt' => 'Salaries for March.',
			]
		);
		$note    = $this->run_ability(
			'create-post',
			[
				'post_type' => self::NOTE,
				'title'     => 'Shopping list',
				'excerpt'   => 'Milk and flour.',
			]
		);
		$book    = $this->run_ability(
			'create-post',
			[
				'post_type' => self::BOOK,
				'title'     => 'The harbor',
				'excerpt'   => 'A mystery by the sea.',
			]
		);

		$this->assertSame( 'no_excerpt', $this->code( $created ) );
		$this->assertStringContainsString( 'Ledger entries don\'t have an excerpt', $created->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertSame( 'no_excerpt', $this->code( $updated ) );
		$this->assertSame( $before, get_post_field( 'post_excerpt', $entry ) );
		$this->assertIsArray( $note );
		$this->assertSame( 'Milk and flour.', get_post_field( 'post_excerpt', $note['post_id'] ) );
		$this->assertIsArray( $book );
		$this->assertSame( 'A mystery by the sea.', get_post_field( 'post_excerpt', $book['post_id'] ) );
	}

	/**
	 * The get-post-terms tool finds posts the way the writing tools do.
	 */
	public function test_reading_terms_uses_the_shared_lookup(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$entry = self::factory()->post->create(
			[
				'post_type'   => self::LEDGER,
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Payroll for March',
			]
		);
		$book  = self::factory()->post->create(
			[
				'post_type'   => self::BOOK,
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'The harbor',
			]
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$hidden = $this->run_ability( 'get-post-terms', [ 'post_id' => $entry ] );
		$public = $this->run_ability( 'get-post-terms', [ 'post_id' => $book ] );

		$this->assertTrue( current_user_can( 'read_post', $entry ) );
		$this->assertSame( 'post_not_found', $this->code( $hidden ) );
		$this->assertStringNotContainsString( 'Payroll', $hidden->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertSame( 'forbidden', $this->code( $public ) );
		$this->assertStringContainsString( 'The harbor', $public->get_error_message() ); // @phpstan-ignore method.nonObject
		$this->assertSame( 'post_not_found', $this->code( $this->run_ability( 'get-post-terms', [ 'post_id' => 999999 ] ) ) );
	}

	/**
	 * Attachments come back with their alt text, caption and size, so the AI can choose one without asking.
	 */
	public function test_search_attachments_describes_each_file(): void {
		$attachment = self::factory()->attachment->create_object(
			[
				'file'           => 'harbor.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Harbor at dawn',
				'post_excerpt'   => 'The harbor before the bakery opens.',
			]
		);
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Boats in the harbor' );
		wp_update_attachment_metadata(
			$attachment,
			[
				'width'  => 1600,
				'height' => 900,
			]
		);

		$result = $this->run_ability( 'search-attachments', [ 'search' => 'Harbor' ] );

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'alt'     => 'Boats in the harbor',
				'caption' => 'The harbor before the bakery opens.',
				'width'   => 1600,
				'height'  => 900,
			],
			array_intersect_key( $result[0], array_flip( [ 'alt', 'caption', 'width', 'height' ] ) )
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
