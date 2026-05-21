<?php
/**
 * Example Module.
 *
 * Initializes all classes for the Example module.
 *
 * @package rtCamp\Publish_With_AI\Modules
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Example
 */
final class Example implements Registrable {
	/**
	 * Registrable classes for this module.
	 *
	 * @var class-string<\rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Example\Example_Post_Type::class,
		Example\Example_Taxonomy::class,
		Example\Example_Post_Type_Meta::class,
		Example\Example_REST_Controller::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			$instance = new $class_name();

			$instance->register_hooks();
		}
	}
}
