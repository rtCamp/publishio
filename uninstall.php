<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package rtCamp\Publishio
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio;

// Only uninstall if called by WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// We use local constants so this plugin can be uninstalled even if the autoloader is corrupted or missing.
const PLUGIN_PREFIX = 'publishio_';

/**
 * Uninstalls the plugin. If multisite, uninstalls from all sites.
 */
function run_uninstaller(): void {
	// User meta is shared by every site.
	delete_metadata( 'user', 0, PLUGIN_PREFIX . 'tokens_revoked_at', '', true );

	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The state doesn't matter during uninstall.
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		uninstall();
		restore_current_blog();
	}
}

/**
 * The (site-specific) uninstall function.
 * TODO: Do we need this??
 */
function uninstall(): void {
	// Add additional uninstall routines here.
	delete_options();
	delete_transients();
	delete_tables();
}

/**
 * Deletes options.
 */
function delete_options(): void {
	$options = [
		// Add more options as needed.
		PLUGIN_PREFIX . 'version', // Set by Main::activate().
		PLUGIN_PREFIX . 'db_version',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Deletes transients. Their names carry a hash or user ID, so they are matched by prefix.
 */
function delete_transients(): void {
	global $wpdb;

	$prefixes = [
		PLUGIN_PREFIX . 'cimd_',       // Client metadata cache and per-user fetch limits.
		PLUGIN_PREFIX . 'oauth_code_', // Authorization codes.
	];

	foreach ( $prefixes as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}
}

/**
 * Drops the OAuth token table, and the client table left by versions that used dynamic client registration.
 */
function delete_tables(): void {
	global $wpdb;

	$tables = [
		$wpdb->prefix . 'publishio_oauth_tokens',
		$wpdb->prefix . 'publishio_oauth_clients',
	];

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}

// Run the uninstaller.
run_uninstaller();
