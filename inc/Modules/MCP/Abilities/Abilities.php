<?php
/**
 * Abilities module entry point.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities;

use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\Get_Block_Type;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\List_Block_Types;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\Render_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Blocks as Blocks_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts as Posts_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Create_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Delete_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Duplicate_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Get_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Get_Post_Content;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Insert_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Insert_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Move_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Preview_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Replace_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Update_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Create_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Get_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\List_Patterns;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Update_Pattern;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Post_Terms;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Taxonomy_Terms;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Yoast_Meta;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Attachments;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Posts;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Featured_Image;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Post_Terms;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Yoast_Meta;
use rtCamp\Publishio\Modules\MCP\Abilities\Site\Get_Post_Types;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Render_Preview;

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
			new List_Patterns(),
			new Get_Pattern(),
			new Create_Pattern(),
			new Update_Pattern(),
			new List_Block_Types(),
			new Get_Block_Type(),
			new Render_Blocks(),
			new Get_Post_Types(),
			new Create_Post(),
			new Get_Post(),
			new Get_Post_Content(),
			new Update_Post(),
			new Preview_Post(),
			new Render_Preview(),
			new Insert_Blocks(),
			new Insert_Pattern(),
			new Replace_Block(),
			new Move_Block(),
			new Duplicate_Block(),
			new Delete_Blocks(),
			new Set_Featured_Image(),
			new Search_Posts(),
			new Search_Attachments(),
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
