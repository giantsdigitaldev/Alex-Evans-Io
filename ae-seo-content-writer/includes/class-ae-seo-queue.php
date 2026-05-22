<?php
/**
 * AE SEO Content Writer — Topic queue (DB table, add/list/run next).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_Queue {

	const TABLE_NAME = 'ae_seo_writer_queue';
	const STATUS_PENDING = 'pending';
	const STATUS_RUNNING = 'running';
	const STATUS_DONE    = 'done';
	const STATUS_FAILED  = 'failed';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function create_table() {
		global $wpdb;
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			topic varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			run_id varchar(100) DEFAULT NULL,
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function add( $topic ) {
		$topic = is_string( $topic ) ? trim( $topic ) : '';
		if ( $topic === '' ) {
			return false;
		}
		global $wpdb;
		$table = self::table_name();
		$r = $wpdb->insert( $table, [
			'topic'  => $topic,
			'status' => self::STATUS_PENDING,
		], [ '%s', '%s' ] );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/** Add multiple topics; returns number added. */
	public static function add_bulk( $lines ) {
		if ( ! is_array( $lines ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $lines, -1, PREG_SPLIT_NO_EMPTY );
		}
		$n = 0;
		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( $t !== '' && self::add( $t ) ) {
				$n++;
			}
		}
		return $n;
	}

	public static function get_all( $order = 'ASC' ) {
		global $wpdb;
		$table = self::table_name();
		$order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
		// Whitelist only; do not use prepare for ORDER BY clause.
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id " . $order, ARRAY_A );
	}

	public static function get_pending() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( "SELECT * FROM $table WHERE status = 'pending' ORDER BY id ASC LIMIT 1", ARRAY_A );
	}

	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function set_status( $id, $status, $run_id = null, $error_message = null ) {
		global $wpdb;
		$table = self::table_name();
		$data  = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		];
		$formats = [ '%s', '%s' ];
		if ( $run_id !== null ) {
			$data['run_id'] = $run_id;
			$formats[] = '%s';
		}
		if ( $error_message !== null ) {
			$data['error_message'] = $error_message;
			$formats[] = '%s';
		} elseif ( $status !== self::STATUS_FAILED ) {
			$data['error_message'] = null;
			$formats[] = '%s';
		}
		return $wpdb->update( $table, $data, [ 'id' => (int) $id ], $formats, [ '%d' ] );
	}

	public static function delete_by_id( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->delete( $table, [ 'id' => (int) $id ], [ '%d' ] );
	}

	public static function count_by_status( $status ) {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
	}
}
