<?php
/**
 * Uninstall handler for VMFA Media Cleanup.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Removes all plugin data: options, custom database tables, and post meta.
 *
 * @package VmfaMediaCleanup
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin options.
delete_option( 'vmfa_media_cleanup_settings' );
delete_option( 'vmfa_cleanup_scan_progress' );
delete_option( 'vmfa_cleanup_results' );

// Drop the custom reference index table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vmfa_media_references" );

// Clean up post meta created by the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vmfa_file_hash' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vmfa_hash_algo' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vmfa_flagged_for_review' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vmfa_duplicate_primary' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vmfa_trashed' ) );
