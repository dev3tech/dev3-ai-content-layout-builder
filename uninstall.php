<?php
/**
 * Uninstall: removes data only when "Delete data on uninstall" is enabled.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dev3cl_settings = get_option( 'dev3cl_settings', array() );
if ( empty( $dev3cl_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dev3cl_history" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dev3cl_usage" );
// phpcs:enable

foreach ( array( 'dev3cl_settings', 'dev3cl_templates', 'dev3cl_license', 'dev3cl_db_version' ) as $dev3cl_option ) {
	delete_option( $dev3cl_option );
}
delete_transient( 'dev3cl_models' );
delete_transient( 'dev3cl_templates_list' );
delete_site_transient( 'dev3cl_update_info' );
wp_clear_scheduled_hook( 'dev3cl_daily_maintenance' );
wp_clear_scheduled_hook( 'dev3cl_license_check' );
