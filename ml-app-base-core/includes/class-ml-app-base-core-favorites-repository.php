<?php
/**
 * Favorites repository — CRUD for the ml_app_base_favorites table.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Favorites_Repository
 *
 * Stores references to favorited content items (events, news) per user.
 * Content itself lives on appbase.com.br; only the item ID is stored here.
 * On account deletion, all favorites for the user must be permanently deleted.
 */
class ML_App_Base_Core_Favorites_Repository {

	/**
	 * Allowed item types.
	 *
	 * @var array
	 */
	const ALLOWED_TYPES = array( 'event', 'news' );

	/**
	 * Get paginated favorites for a user.
	 *
	 * @param int    $user_id   App user ID.
	 * @param int    $page      Page number (1-based).
	 * @param int    $per_page  Items per page.
	 * @param string $item_type Optional filter by type.
	 * @return array { items: array, total: int }
	 */
	public static function get_for_user( $user_id, $page = 1, $per_page = 20, $item_type = '' ) {
		global $wpdb;
		$table  = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		$where_type = '';
		$args       = array( (int) $user_id );

		if ( ! empty( $item_type ) && in_array( $item_type, self::ALLOWED_TYPES, true ) ) {
			$where_type = ' AND item_type = %s';
			$args[]     = $item_type;
		}

		// Count total.
		$count_args = $args;
		$total      = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d{$where_type}",
				...$count_args
			)
		);

		// Fetch page.
		$args[] = (int) $per_page;
		$args[] = $offset;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, item_type, item_id, item_title, item_url, created_at
				FROM `{$table}`
				WHERE user_id = %d{$where_type}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				...$args
			)
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Find a single favorite by ID and user_id (ownership check).
	 *
	 * @param int $id      Favorite row ID.
	 * @param int $user_id App user ID.
	 * @return object|null
	 */
	public static function find( $id, $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND user_id = %d LIMIT 1",
				(int) $id,
				(int) $user_id
			)
		);
	}

	/**
	 * Add a favorite (ignore if duplicate).
	 *
	 * @param int    $user_id    App user ID.
	 * @param string $item_type  'event' or 'news'.
	 * @param string $item_id    Content ID from public site.
	 * @param string $item_title Optional content title.
	 * @param string $item_url   Optional content URL.
	 * @return int|false Row ID or false.
	 */
	public static function add( $user_id, $item_type, $item_id, $item_title = '', $item_url = '' ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );
		$now   = current_time( 'mysql' );

		// Check for existing favorite.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE user_id = %d AND item_type = %s AND item_id = %s LIMIT 1",
				(int) $user_id,
				sanitize_text_field( $item_type ),
				sanitize_text_field( $item_id )
			)
		);

		if ( $exists ) {
			return (int) $exists;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'    => (int) $user_id,
				'item_type'  => sanitize_text_field( $item_type ),
				'item_id'    => sanitize_text_field( $item_id ),
				'item_title' => sanitize_text_field( $item_title ),
				'item_url'   => esc_url_raw( $item_url ),
				'created_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Remove a favorite by ID (with user ownership check).
	 *
	 * @param int $id      Favorite row ID.
	 * @param int $user_id App user ID.
	 * @return bool True if deleted, false if not found or not owned.
	 */
	public static function remove( $id, $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );

		$result = $wpdb->delete(
			$table,
			array(
				'id'      => (int) $id,
				'user_id' => (int) $user_id,
			),
			array( '%d', '%d' )
		);

		return (bool) $result;
	}

	/**
	 * Permanently delete all favorites for a user (called on account deletion per LGPD).
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function delete_all_for_user( $user_id ) {
		global $wpdb;
		$table  = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );
		$result = $wpdb->delete( $table, array( 'user_id' => (int) $user_id ), array( '%d' ) );
		return false !== $result;
	}
}
