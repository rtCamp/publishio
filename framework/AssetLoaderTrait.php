<?php
/**
 * Trait for WordPress asset loading.
 *
 * @package rtCamp\Publishio
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Framework;

/**
 * Trait - AssetLoaderTrait
 */
trait AssetLoaderTrait {
	/**
	 * The path to the built assets directory, relative to the plugin directory.
	 * No preceding or trailing slashes.
	 *
	 * @var string
	 */
	private string $assets_dir;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Register data from the block manifest file.
	 *
	 * @param string $block_path The relative path to the block collect. E.g. `build/blocks`.
	 * @param string $manifest_file Path to the manifest file, relative to the plugin directory. E.g. `build/blocks-manifest.php`.
	 */
	private function register_block_manifest( string $block_path, string $manifest_file ): void {
		$manifest_path = trailingslashit( $this->plugin_dir ) . $manifest_file;
		if ( ! file_exists( $manifest_path ) ) {
			_doing_it_wrong(
				self::class,
				esc_html__( 'Block manifest file is missing. Blocks will not be registered.', 'publishio' ),
				'0.0.1'
			);
			return;
		}

		wp_register_block_types_from_metadata_collection( trailingslashit( $this->plugin_dir ) . $block_path, $manifest_path );
	}

	/**
	 * Register a script.
	 *
	 * @param string   $handle        Name of the script. Should be unique.
	 * @param string   $filename      Path of the script relative to js directory.
	 *                                excluding the .js extension.
	 * @param string[] $deps          Optional. An array of registered script handles this script depends on. If not set, the dependencies will be inherited from the asset file.
	 * @param ?string  $ver           Optional. String specifying script version number, if not set, the version will be inherited from the asset file.
	 * @param bool     $in_footer     Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 */
	private function register_script( string $handle, string $filename, array $deps = [], $ver = null, bool $in_footer = true ): bool {

		$asset = $this->get_asset_file( $filename );
		// Bail if the asset file does not exist or is invalid.
		if ( ! $asset ) {
			return false;
		}

		$asset_src = sprintf( '%s/%s.js', $this->plugin_url . untrailingslashit( $this->assets_dir ), $filename );
		$deps      = $deps ?: ( $asset['dependencies'] ?? [] );
		$version   = $ver ?? $asset['version'];

		return wp_register_script(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$in_footer
		);
	}

	/**
	 * Register a CSS stylesheet
	 *
	 * @param string   $handle        Name of the stylesheet. Should be unique.
	 * @param string   $filename      Path of the stylesheet relative to the css directory,
	 *                                excluding the .css extension.
	 * @param string[] $deps          Optional. An array of registered stylesheet handles this stylesheet depends on.
	 * @param ?string  $ver           Optional. String specifying style version number, if not set, the version will be inherited from the asset file.
	 *
	 * @param string   $media         Optional. The media for which this stylesheet has been defined.
	 *                                Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                                '(orientation: portrait)' and '(max-width: 640px)'.
	 */
	private function register_style( string $handle, string $filename, array $deps = [], $ver = null, string $media = 'all' ): bool {
		$asset = $this->get_asset_file( $filename );
		// Bail if the asset file does not exist or is invalid.
		if ( ! $asset ) {
			return false;
		}

		$asset_src = sprintf( '%s/%s.css', $this->plugin_url . untrailingslashit( $this->assets_dir ), $filename );
		$version   = $ver ?? $asset['version'];

		// Register as a style.
		return wp_register_style(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$media
		);
	}

	/**
	 * Get the asset version from the asset file.
	 *
	 * This is used to ensure that the version is consistent between registered scripts and styles, and to avoid code duplication in the `register_script` and `register_style` methods.
	 *
	 * @param string $filename Path of the asset relative to the assets directory, excluding the file extension.
	 *
	 * @return ?array{version:string, ...} The asset file array, or null if the asset file does not exist or is invalid.
	 */
	private function get_asset_file( string $filename ): ?array {
		$asset_file = sprintf( '%s/%s.asset.php', $this->plugin_dir . untrailingslashit( $this->assets_dir ), $filename );

		// Bail if the asset file does not exist.
		if ( ! file_exists( $asset_file ) ) {
			_doing_it_wrong(
				self::class,
				sprintf(
					/* translators: %s: The asset filename. */
					esc_html__( 'Asset file for "%s" is missing. The script will not be registered.', 'publishio' ),
					esc_html( $filename )
				),
				'0.0.1'
			);
			return null;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- The file is checked for existence above.
		$asset = require $asset_file;

		if ( ! is_array( $asset ) ) {
			_doing_it_wrong(
				self::class,
				sprintf(
					/* translators: %s: The asset filename. */
					esc_html__( 'Asset file for "%s" is invalid. The script will not be registered.', 'publishio' ),
					esc_html( $filename )
				),
				'0.0.1'
			);
			return null;
		}

		// Fallback to filemtime if version is not set in the asset file.
		if ( ! isset( $asset['version'] ) ) {
			$asset['version'] = filemtime( $asset_file );
		}

		return $asset;
	}
}
