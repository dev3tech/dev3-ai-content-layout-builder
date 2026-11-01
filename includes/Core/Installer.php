<?php
/**
 * Activation, upgrades and custom tables.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the history and usage tables.
 */
class Installer {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'dev3cl_db_version';
	const CRON_HOOK         = 'dev3cl_daily_maintenance';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install_tables();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
		add_option( Settings::OPTION, Settings::defaults(), '', false );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( 'dev3cl_license_check' ); // Licence check from builds before licensing was removed.
	}

	/**
	 * Runs dbDelta when the stored schema version is older.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install_tables();
		}
	}

	/**
	 * History table name.
	 *
	 * @return string
	 */
	public static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'dev3cl_history';
	}

	/**
	 * Usage table name.
	 *
	 * @return string
	 */
	public static function usage_table() {
		global $wpdb;
		return $wpdb->prefix . 'dev3cl_usage';
	}

	/**
	 * Creates or updates the tables.
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$history = self::history_table();
		$usage   = self::usage_table();

		dbDelta(
			"CREATE TABLE {$history} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				task varchar(40) NOT NULL DEFAULT '',
				context varchar(40) NOT NULL DEFAULT '',
				model varchar(80) NOT NULL DEFAULT '',
				prompt text NOT NULL,
				output longtext NOT NULL,
				meta text NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_created (user_id,created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$usage} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				task varchar(40) NOT NULL DEFAULT '',
				context varchar(40) NOT NULL DEFAULT '',
				model varchar(80) NOT NULL DEFAULT '',
				prompt_tokens int(10) unsigned NOT NULL DEFAULT 0,
				output_tokens int(10) unsigned NOT NULL DEFAULT 0,
				total_tokens int(10) unsigned NOT NULL DEFAULT 0,
				duration_ms int(10) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'ok',
				error_code varchar(40) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY created (created_at),
				KEY user_created (user_id,created_at)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Daily cron: prune history and usage beyond retention.
	 */
	public static function maintenance() {
		global $wpdb;
		$days    = (int) Settings::get( 'history_days', 90 );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$history = self::history_table();
		$usage   = self::usage_table();
		// Table names come from $wpdb->prefix, not user input; %i needs WP 6.2+.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$history} WHERE created_at < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$usage} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS ) ) );
		// phpcs:enable
	}
}
