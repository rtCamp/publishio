<?php
/**
 * Block Field Resolver.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Patterns;

use WP_Block_Type_Registry;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Block_Attr_Writer;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Cover_Media_Writer;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Field_Writer_Interface;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Html_Attribute_Writer;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Image_Id_Writer;
use rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Rich_Text_Writer;

/**
 * Resolves which fields to extract/apply for a given block name.
 *
 * Uses FIELD_MAP (block name → attribute keys to expose) plus
 * the WP_Block_Type_Registry to discover source/selector metadata.
 */
class Block_Field_Resolver {
	/**
	 * Field map: block name → list of attribute keys to expose.
	 * The resolver fetches the source/selector/attribute metadata from the block registry.
	 */
	private const FIELD_MAP = [
		'core/paragraph'   => [ 'content' ],
		'core/heading'     => [ 'content' ],
		'core/button'      => [ 'text', 'url' ],
		'core/image'       => [ 'url', 'alt', 'id' ],
		'core/list-item'   => [ 'content' ],
		'core/cover'       => [ 'url', 'alt', 'id' ],
		'core/video'       => [ 'src' ],
		'core/social-link' => [ 'url', 'service' ],
	];

	/**
	 * Override configs for attributes where block.json doesn't provide
	 * the needed source metadata or where the default writer is wrong.
	 *
	 * Format: 'blockName' => [ 'attrKey' => [ ...fieldDef overrides ] ]
	 */
	private const OVERRIDES = [
		'core/cover' => [
			'url' => [ '_writer' => 'cover-media' ],
			'alt' => [ '_writer' => 'cover-media' ],
			'id'  => [ '_writer' => 'cover-media' ],
		],
		'core/image' => [
			'id' => [ '_writer' => 'image-id' ],
		],
	];

	/**
	 * Writer singletons.
	 *
	 * @var array<string, \rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Field_Writer_Interface>
	 */
	private static array $writers = [];

	/**
	 * Resolve the field definitions for a given block name.
	 *
	 * @param string $block_name Block name.
	 *
	 * @return array<string, array{field_def: array<string, mixed>, writer: \rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer\Field_Writer_Interface}>
	 *               Keyed by attribute name. Each entry has the merged field
	 *               definition and the appropriate writer instance.
	 */
	public static function resolve( string $block_name ): array {
		$attr_keys = self::FIELD_MAP[ $block_name ] ?? [];
		if ( empty( $attr_keys ) ) {
			return [];
		}

		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type ) {
			return [];
		}

		$schemas = $block_type->attributes ?? [];
		$result  = [];

		foreach ( $attr_keys as $key ) {
			$schema    = $schemas[ $key ] ?? [];
			$field_def = array_merge( $schema, [ '_attrKey' => $key ] );

			// Apply override if exists.
			if ( isset( self::OVERRIDES[ $block_name ][ $key ] ) ) {
				$field_def = array_merge( $field_def, self::OVERRIDES[ $block_name ][ $key ] );
			}

			$writer         = self::writer_for( $field_def );
			$result[ $key ] = [
				'field_def' => $field_def,
				'writer'    => $writer,
			];
		}

		return $result;
	}

	/**
	 * Check whether we have any field definitions for a block name.
	 *
	 * @param string $block_name Block name.
	 */
	public static function has( string $block_name ): bool {
		return isset( self::FIELD_MAP[ $block_name ] );
	}

	// ------------------------------------------------------------------

	/**
	 * Get the appropriate writer for a field definition.
	 *
	 * @param array<string, mixed> $field_def Field definition.
	 */
	private static function writer_for( array $field_def ): Field_Writer_Interface {
		// Explicit writer override.
		if ( ! empty( $field_def['_writer'] ) ) {
			return self::get_writer( $field_def['_writer'] );
		}

		$source = $field_def['source'] ?? null;

		return match ( $source ) {
			'rich-text', 'html' => self::get_writer( 'rich-text' ),
			'attribute'         => self::get_writer( 'attribute' ),
			default             => self::get_writer( 'block-attr' ),
		};
	}

	/**
	 * Get or create a writer singleton.
	 *
	 * @param string $type Writer type key.
	 */
	private static function get_writer( string $type ): Field_Writer_Interface {
		if ( ! isset( self::$writers[ $type ] ) ) {
			self::$writers[ $type ] = match ( $type ) {
				'rich-text'   => new Rich_Text_Writer(),
				'attribute'   => new Html_Attribute_Writer(),
				'block-attr'  => new Block_Attr_Writer(),
				'cover-media' => new Cover_Media_Writer(),
				'image-id'    => new Image_Id_Writer(),
				default       => new Block_Attr_Writer(),
			};
		}

		return self::$writers[ $type ];
	}
}
