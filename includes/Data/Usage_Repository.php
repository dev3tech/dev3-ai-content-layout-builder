<?php
/**
 * Usage log (tokens, latency, errors).
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Data;

use Dev3\AiContentLayoutBuilder\Core\Installer;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Writes and aggregates {prefix}dev3cl_usage.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */
class Usage_Repository {

	/**
	 * Logs one request.
	 *
	 * @param array $row { user_id, task, context, model, usage{prompt,output,total}, duration_ms, status, error_code }.
	 */
	public static function log( array $row ) {
		if ( ! Settings::get( 'usage_logging', true ) ) {
			return;
		}
		global $wpdb;
		$usage = isset( $row['usage'] ) ? $row['usage'] : array();
		$wpdb->insert(
			Installer::usage_table(),
			array(
				'user_id'       => (int) $row['user_id'],
				'task'          => substr( (string) $row['task'], 0, 40 ),
				'context'       => substr( (string) $row['context'], 0, 40 ),
				'model'         => substr( (string) $row['model'], 0, 80 ),
				'prompt_tokens' => isset( $usage['prompt'] ) ? (int) $usage['prompt'] : 0,
				'output_tokens' => isset( $usage['output'] ) ? (int) $usage['output'] : 0,
				'total_tokens'  => isset( $usage['total'] ) ? (int) $usage['total'] : 0,
				'duration_ms'   => isset( $row['duration_ms'] ) ? (int) $row['duration_ms'] : 0,
				'status'        => isset( $row['status'] ) ? substr( (string) $row['status'], 0, 20 ) : 'ok',
				'error_code'    => isset( $row['error_code'] ) ? substr( (string) $row['error_code'], 0, 40 ) : '',
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Aggregates for the Usage Logs tab.
	 *
	 * @param int $days Window in days.
	 * @return array { totals, by_day, by_task, by_model, by_user }
	 */
	public static function summary( $days = 30 ) {
		global $wpdb;
		$table = Installer::usage_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		$totals   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_tokens),0) AS prompt_tokens, COALESCE(SUM(output_tokens),0) AS output_tokens,
				COALESCE(SUM(total_tokens),0) AS total_tokens, COALESCE(ROUND(AVG(duration_ms)),0) AS avg_ms, COALESCE(SUM(status <> 'ok'),0) AS errors
				FROM {$table} WHERE created_at >= %s",
				$since
			),
			ARRAY_A
		);
		$by_day   = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS day, COUNT(*) AS requests, SUM(total_tokens) AS tokens FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day", $since ), ARRAY_A );
		$by_task  = $wpdb->get_results( $wpdb->prepare( "SELECT task, COUNT(*) AS requests, SUM(total_tokens) AS tokens FROM {$table} WHERE created_at >= %s GROUP BY task ORDER BY requests DESC", $since ), ARRAY_A );
		$by_model = $wpdb->get_results( $wpdb->prepare( "SELECT model, COUNT(*) AS requests, SUM(total_tokens) AS tokens FROM {$table} WHERE created_at >= %s GROUP BY model ORDER BY requests DESC", $since ), ARRAY_A );
		$by_user  = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, COUNT(*) AS requests, SUM(total_tokens) AS tokens FROM {$table} WHERE created_at >= %s GROUP BY user_id ORDER BY requests DESC LIMIT 20", $since ), ARRAY_A );

		foreach ( $by_user as $i => $u ) {
			$user                  = get_userdata( (int) $u['user_id'] );
			$by_user[ $i ]['name'] = $user ? $user->display_name : __( 'Deleted user', 'dev3-ai-content-layout-builder' );
		}

		return array(
			'days'     => $days,
			'totals'   => array_map( 'intval', (array) $totals ),
			'by_day'   => self::ints( $by_day ),
			'by_task'  => self::ints( $by_task ),
			'by_model' => self::ints( $by_model ),
			'by_user'  => self::ints( $by_user ),
		);
	}

	/**
	 * Recent rows.
	 *
	 * @param int $page     Page.
	 * @param int $per_page Page size.
	 * @return array { items, total }
	 */
	public static function recent( $page = 1, $per_page = 50 ) {
		global $wpdb;
		$table = Installer::usage_table();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, max( 0, ( $page - 1 ) * $per_page ) ), ARRAY_A );
		$names = array();
		foreach ( (array) $rows as $i => $row ) {
			$uid = (int) $row['user_id'];
			if ( ! isset( $names[ $uid ] ) ) {
				$user          = get_userdata( $uid );
				$names[ $uid ] = $user ? $user->display_name : '—';
			}
			$rows[ $i ]['user']       = $names[ $uid ];
			$rows[ $i ]['created_at'] = mysql_to_rfc3339( $row['created_at'] );
		}
		return array(
			'items' => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Requests by a user in the last minute (server-side rate limit).
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function last_minute( $user_id ) {
		global $wpdb;
		$table = Installer::usage_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at >= %s", $user_id, gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ) );
	}

	/**
	 * Casts numeric columns.
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function ints( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			foreach ( $row as $k => $v ) {
				if ( is_numeric( $v ) && 'day' !== $k ) {
					$row[ $k ] = (int) $v;
				}
			}
			$out[] = $row;
		}
		return $out;
	}
}
