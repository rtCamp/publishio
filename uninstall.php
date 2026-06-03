<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package rtCamp\Publish_With_AI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI;

// Only uninstall if called by WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// We use local constants so this plugin can be uninstalled even if the autoloader is corrupted or missing.
const PLUGIN_PREFIX = 'publish_with_ai_';

/**
 * Uninstalls the plugin. If multisite, uninstalls from all sites.
 */
function run_uninstaller(): void {
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
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Deletes transients.
 *
 * @phpstan-ignore void.pure
 */
function delete_transients(): void {
	$transients = [
		// Add more transients as needed.
	];

	foreach ( $transients as $transient ) { // @phpstan-ignore foreach.emptyArray
		delete_transient( $transient );
	}
}

/**
 * Drops the OAuth client and token tables.
 */
function delete_tables(): void {
	global $wpdb;

	$tables = [
		$wpdb->prefix . 'pwai_oauth_tokens',
		$wpdb->prefix . 'pwai_oauth_clients',
	];

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}

// Run the uninstaller.
run_uninstaller();
