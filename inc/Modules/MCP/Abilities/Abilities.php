<?php
/**
 * Abilities module entry point.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Blocks\Get_Block;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Blocks\Get_Custom_Blocks;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Blocks\Render_Block;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Blocks as Blocks_Category;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts as Posts_Category;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Apply_Pattern_Schema;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Get_Pattern;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Get_Pattern_Schema;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Get_Patterns;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Append_Blocks;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Create_Post;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Delete_Block_At;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Get_Post;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Get_Post_Terms;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Get_Taxonomy_Terms;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Get_Yoast_Meta;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Insert_Blocks_At;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Search_Attachments;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Search_Posts;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Set_Featured_Image;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Set_Post_Terms;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Set_Yoast_Meta;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Update_Post;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Upload_Media;
use rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval\Insert_Pattern;
use rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval\Preview_Pattern;
use rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval\Render_Pattern;

/**
 * Class - Abilities
 */
final class Abilities implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register ability categories.
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		$categories = [
			new Patterns_Category(),
			new Blocks_Category(),
			new Posts_Category(),
		];

		foreach ( $categories as $category ) {
			$category->register();
		}
	}

	/**
	 * Register abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$abilities = [
			new Get_Patterns(),
			new Get_Pattern(),
			new Get_Pattern_Schema(),
			new Apply_Pattern_Schema(),
			new Preview_Pattern(),
			new Render_Pattern(),
			new Insert_Pattern(),
			new Get_Custom_Blocks(),
			new Get_Block(),
			new Render_Block(),
			new Create_Post(),
			new Get_Post(),
			new Update_Post(),
			new Set_Featured_Image(),
			new Append_Blocks(),
			new Insert_Blocks_At(),
			new Delete_Block_At(),
			new Search_Posts(),
			new Search_Attachments(),
			new Upload_Media(),
			new Get_Taxonomy_Terms(),
			new Get_Post_Terms(),
			new Set_Post_Terms(),
			new Get_Yoast_Meta(),
			new Set_Yoast_Meta(),
		];

		foreach ( $abilities as $ability ) {
			$ability->register();
		}
	}
}
