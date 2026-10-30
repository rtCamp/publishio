<?php
/**
 * Fixture assets for the preview tests.
 *
 * Files go under the uploads directory because the asset resolver only accepts URLs beneath this install's own asset roots, and uploads is the one place a test may write to.
 *
 * @package rtCamp\Publishio\Tests\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Abstracts;

/**
 * Trait - Preview_Fixtures
 */
trait Preview_Fixtures {
	/**
	 * Name of the fixture directory inside the uploads directory.
	 */
	private const FIXTURE_DIR = 'publishio-preview-test';

	/**
	 * The initialised filesystem abstraction.
	 */
	private function filesystem(): \WP_Filesystem_Base {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		WP_Filesystem();

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			$this->fail( 'The WordPress filesystem abstraction is unavailable.' );
		}

		return $wp_filesystem;
	}

	/**
	 * Absolute path of the fixture directory.
	 */
	private function fixture_dir(): string {
		return wp_get_upload_dir()['basedir'] . '/' . self::FIXTURE_DIR;
	}

	/**
	 * Create the fixture directory.
	 *
	 * Recursively, because a subsite's uploads directory does not exist until something writes to it.
	 */
	private function make_fixture_dir(): void {
		if ( ! wp_mkdir_p( $this->fixture_dir() ) ) {
			$this->fail( 'Could not create the fixture directory.' );
		}
	}

	/**
	 * Remove the fixture directory and everything in it.
	 */
	private function remove_fixture_dir(): void {
		$this->filesystem()->delete( $this->fixture_dir(), true );
	}

	/**
	 * Write a fixture asset and return the URL it would be enqueued from.
	 *
	 * @param string $filename Asset file name.
	 * @param string $contents Asset contents.
	 */
	private function fixture( string $filename, string $contents ): string {
		if ( ! $this->filesystem()->put_contents( $this->fixture_dir() . '/' . $filename, $contents ) ) {
			$this->fail( "Could not write the {$filename} fixture." );
		}

		return wp_get_upload_dir()['baseurl'] . '/' . self::FIXTURE_DIR . '/' . $filename;
	}

	/**
	 * Build the link tag WordPress would have printed for a stylesheet.
	 *
	 * @param string $href The stylesheet URL.
	 */
	private function link_tag( string $href ): string {
		return sprintf( '<link rel="%s" href="%s" />', 'stylesheet', esc_url( $href ) );
	}

	/**
	 * Build the script tag WordPress would have printed.
	 *
	 * @param string $src The script URL.
	 */
	private function script_tag( string $src ): string {
		return sprintf( '<%1$s %2$s="%3$s" id="fixture-js"></%1$s>', 'script', 'src', esc_url( $src ) );
	}
}
