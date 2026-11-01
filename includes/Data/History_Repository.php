<?php
/**
 * Generation history (per user).
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Data;

use Dev3\AiContentLayoutBuilder\Core\Installer;
use Dev3\AiContentLayoutBuilder\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD over {prefix}dev3cl_history.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */
class History_Repository {

	/**
	 * Stores an entry.
	 *
	 * @param array $entry { user_id, task, context, model, prompt, output, meta }.
	 * @return int Insert id (0 when disabled).
	 */
	public static function add( array $entry ) {
		if ( ! Settings::get( 'history_enabled', true ) ) {
			return 0;
		}
		global $wpdb;
		$wpdb->insert(
			Installer::history_table(),
			array(
				'user_id'    => (int) $entry['user_id'],
				'task'       => substr( (string) $entry['task'], 0, 40 ),
				'context'    => substr( (string) $entry['context'], 0, 40 ),
				'model'      => substr( (string) $entry['model'], 0, 80 ),
				'prompt'     => (string) $entry['prompt'],
				'output'     => (string) $entry['output'],
				'meta'       => isset( $entry['meta'] ) ? wp_json_encode( $entry['meta'] ) : null,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Lists a user's entries, newest first.
	 *
	 * @param int    $user_id  User.
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Page size.
	 * @param string $task     Optional task filter.
	 * @param string $search   Optional search.
	 * @return array { items, total }
	 */
	public static function list_for( $user_id, $page = 1, $per_page = 20, $task = '', $search = '' ) {
		global $wpdb;
		$table  = Installer::history_table();
		$where  = 'user_id = %d';
		$params = array( (int) $user_id );
		if ( '' !== $task ) {
			$where   .= ' AND task = %s';
			$params[] = $task;
		}
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (prompt LIKE %s OR output LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		// $where only ever contains fixed SQL with placeholders that match $params.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Deletes one entry owned by the user, or all of them when $id is 0.
	 *
	 * @param int $user_id User.
	 * @param int $id      Entry id or 0.
	 * @return int Rows deleted.
	 */
	public static function delete( $user_id, $id = 0 ) {
		global $wpdb;
		$where = array( 'user_id' => (int) $user_id );
		if ( $id > 0 ) {
			$where['id'] = (int) $id;
		}
		return (int) $wpdb->delete( Installer::history_table(), $where, array_fill( 0, count( $where ), '%d' ) );
	}

	/**
	 * Row → API shape.
	 *
	 * @param array $row DB row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$meta = $row['meta'] ? json_decode( $row['meta'], true ) : array();
		return array(
			'id'         => (int) $row['id'],
			'task'       => $row['task'],
			'context'    => $row['context'],
			'model'      => $row['model'],
			'prompt'     => $row['prompt'],
			'output'     => $row['output'],
			'meta'       => is_array( $meta ) ? $meta : array(),
			'created_at' => mysql_to_rfc3339( $row['created_at'] ),
		);
	}
}
