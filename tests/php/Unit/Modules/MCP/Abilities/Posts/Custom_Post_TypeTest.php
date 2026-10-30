<?php
/**
 * Tests for the media and term tools on a custom post type, and their guards.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Attachments;
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
#[CoversClass( Post_Writer::class )]
class Custom_Post_TypeTest extends TestCase {
	private const BOOK = 'publishio_book';

	private const NOTE = 'publishio_note';

	private const GENRE = 'publishio_genre';

	/**
	 * Register a book type with a genre taxonomy and featured images, and a note type without them, and run as an editor.
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
