<?php
/**
 * In-app notification repository.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Notifications_Repository
 *
 * Stores and reads notification inbox records shown inside the mobile app.
 * Push delivery and in-app visibility are intentionally separate: a user can
 * receive an inbox notification even when no Expo token is available yet.
 */
class ML_App_Base_Core_Notifications_Repository {

	/**
	 * Create inbox notification rows for an audience.
	 *
	 * @param int    $notification_id Notification campaign ID.
	 * @param string $title           Notification title.
	 * @param string $body            Notification body.
	 * @param string $target_type     home|event|news|about|custom_url.
	 * @param string $target_id       Target ID or URL.
	 * @param string $audience        all|favorites_only.
	 * @return int Number of user notification rows created.
	 */
	public static function create_for_audience( $notification_id, $title, $body, $target_type = 'home', $target_id = '', $audience = 'all', $image_url = '' ) {
		$user_ids = self::get_user_ids_for_audience( $audience, $target_type, $target_id );

		if ( empty( $user_ids ) ) {
			return 0;
		}

		$created = 0;
		foreach ( $user_ids as $user_id ) {
			if ( self::create_for_user( (int) $user_id, (int) $notification_id, $title, $body, $target_type, $target_id, $image_url ) ) {
				$created++;
			}
		}

		return $created;
	}

	/**
	 * Create a notification row for a single user.
	 *
	 * @param int    $user_id         App user ID.
	 * @param int    $notification_id Notification campaign ID.
	 * @param string $title           Notification title.
	 * @param string $body            Notification body.
	 * @param string $target_type     Target type.
	 * @param string $target_id       Target ID or URL.
	 * @return int|false Inserted row ID or false.
	 */
	public static function create_for_user( $user_id, $notification_id, $title, $body, $target_type = 'home', $target_id = '', $image_url = '' ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'         => (int) $user_id,
				'notification_id' => $notification_id ? (int) $notification_id : null,
				'title'           => sanitize_text_field( $title ),
				'body'            => sanitize_textarea_field( $body ),
				'target_type'     => sanitize_key( $target_type ?: 'home' ),
				'target_id'       => sanitize_text_field( (string) $target_id ),
				'image_url'       => esc_url_raw( (string) $image_url ),
				'created_at'      => $now,
				'read_at'         => null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Derive a navigable target URL from a stored target.
	 *
	 * @param string $target_type Target type.
	 * @param string $target_id   Target ID or URL.
	 * @return string
	 */
	private static function derive_target_url( $target_type, $target_id ) {
		$target_id = trim( (string) $target_id );

		if ( '' === $target_id ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $target_id ) ) {
			return esc_url_raw( $target_id );
		}

		if ( 'custom_url' === $target_type ) {
			return esc_url_raw( $target_id );
		}

		return '';
	}

	/**
	 * Get active user IDs for a notification audience.
	 *
	 * @param string $audience    all|favorites_only.
	 * @param string $target_type Target type.
	 * @param string $target_id   Target ID.
	 * @return int[]
	 */
	public static function get_user_ids_for_audience( $audience = 'all', $target_type = 'home', $target_id = '' ) {
		global $wpdb;
		$users = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		if ( 'favorites_only' !== $audience ) {
			$ids = $wpdb->get_col( "SELECT id FROM `{$users}` WHERE status = 'active'" );
			return array_map( 'intval', $ids ? $ids : array() );
		}

		if ( empty( $target_id ) || ! in_array( $target_type, array( 'event', 'news' ), true ) ) {
			return array();
		}

		$favorites = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );
		$ids       = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.id
				 FROM `{$users}` u
				 INNER JOIN `{$favorites}` f ON f.user_id = u.id
				 WHERE u.status = 'active'
				   AND f.item_type = %s
				   AND f.item_id = %s",
				$target_type,
				(string) $target_id
			)
		);

		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * Get paginated notifications for a user.
	 *
	 * @param int $user_id  App user ID.
	 * @param int $page     Page number.
	 * @param int $per_page Page size.
	 * @return array{items:array,total:int}
	 */
	public static function get_for_user( $user_id, $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table    = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );
		$page     = max( 1, (int) $page );
		$per_page = min( 100, max( 1, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d", (int) $user_id )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, notification_id, title, body, target_type, target_id, image_url, created_at, read_at
				 FROM `{$table}`
				 WHERE user_id = %d
				 ORDER BY created_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				(int) $user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$row['id']              = (int) $row['id'];
			$row['notification_id'] = null !== $row['notification_id'] ? (int) $row['notification_id'] : null;
			$row['is_read']         = ! empty( $row['read_at'] );
			$row['message']         = isset( $row['body'] ) ? (string) $row['body'] : '';
			$row['target_url']      = self::derive_target_url( $row['target_type'] ?? 'home', $row['target_id'] ?? '' );
			$row['image_url']       = isset( $row['image_url'] ) ? esc_url_raw( (string) $row['image_url'] ) : '';
			$row['image']           = $row['image_url'];
			$items[]                = $row;
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Count unread notifications for a user.
	 *
	 * @param int $user_id App user ID.
	 * @return int
	 */
	public static function get_unread_count( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND read_at IS NULL", (int) $user_id )
		);
	}

	/**
	 * Mark one notification as read.
	 *
	 * @param int $notification_row_id User notification row ID.
	 * @param int $user_id             App user ID.
	 * @return bool
	 */
	public static function mark_read( $notification_row_id, $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			array( 'read_at' => $now ),
			array(
				'id'      => (int) $notification_row_id,
				'user_id' => (int) $user_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark every notification as read for a user.
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function mark_all_read( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET read_at = %s WHERE user_id = %d AND read_at IS NULL",
				$now,
				(int) $user_id
			)
		);

		return false !== $result;
	}
}
