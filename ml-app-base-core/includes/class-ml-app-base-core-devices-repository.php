<?php
/**
 * Devices repository — CRUD for the ml_app_base_devices table.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Devices_Repository
 *
 * Manages Expo push token / device records per user.
 * Push tokens are personal data under LGPD.
 * On user deletion, all associated devices must be soft-deleted.
 */
class ML_App_Base_Core_Devices_Repository {

	/**
	 * Find a device by user_id + device_uuid.
	 *
	 * @param int    $user_id     App user ID.
	 * @param string $device_uuid Device UUID from the mobile app.
	 * @return object|null
	 */
	public static function find_by_uuid( $user_id, $device_uuid ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}`
				WHERE user_id = %d
				  AND device_uuid = %s
				  AND deleted_at IS NULL
				LIMIT 1",
				(int) $user_id,
				sanitize_text_field( $device_uuid )
			)
		);
	}

	/**
	 * Upsert a device record (insert or update if already exists).
	 *
	 * @param int    $user_id         App user ID.
	 * @param array  $data {
	 *   @type string $device_uuid     Unique device identifier.
	 *   @type string $platform        'android' or 'ios'.
	 *   @type string $expo_push_token Expo push token.
	 *   @type string $app_version     App version string.
	 *   @type bool   $push_enabled    Whether push is enabled.
	 * }
	 * @return int|false Device row ID or false.
	 */
	public static function upsert( $user_id, array $data ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		$now   = current_time( 'mysql' );

		$device_uuid = sanitize_text_field( $data['device_uuid'] );
		$existing    = self::find_by_uuid( $user_id, $device_uuid );

		if ( $existing ) {
			// Update existing record.
			$wpdb->update(
				$table,
				array(
					'platform'        => sanitize_text_field( $data['platform'] ?? $existing->platform ),
					'expo_push_token' => isset( $data['expo_push_token'] ) ? sanitize_text_field( $data['expo_push_token'] ) : $existing->expo_push_token,
					'push_enabled'    => isset( $data['push_enabled'] ) ? (int) (bool) $data['push_enabled'] : $existing->push_enabled,
					'app_version'     => isset( $data['app_version'] ) ? sanitize_text_field( $data['app_version'] ) : $existing->app_version,
					'last_seen_at'    => $now,
					'updated_at'      => $now,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}

		// Insert new record.
		$result = $wpdb->insert(
			$table,
			array(
				'user_id'         => (int) $user_id,
				'device_uuid'     => $device_uuid,
				'platform'        => sanitize_text_field( $data['platform'] ?? '' ),
				'expo_push_token' => isset( $data['expo_push_token'] ) ? sanitize_text_field( $data['expo_push_token'] ) : null,
				'push_enabled'    => isset( $data['push_enabled'] ) ? (int) (bool) $data['push_enabled'] : 1,
				'app_version'     => isset( $data['app_version'] ) ? sanitize_text_field( $data['app_version'] ) : null,
				'last_seen_at'    => $now,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Soft-unregister a device (disable push, mark deleted).
	 *
	 * @param int    $user_id     App user ID (ownership check).
	 * @param string $device_uuid Device UUID to unregister.
	 * @return bool
	 */
	public static function unregister( $user_id, $device_uuid ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			array(
				'push_enabled' => 0,
				'deleted_at'   => $now,
				'updated_at'   => $now,
			),
			array(
				'user_id'     => (int) $user_id,
				'device_uuid' => sanitize_text_field( $device_uuid ),
			),
			array( '%d', '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Soft-delete all devices for a user (called on account deletion).
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function delete_all_for_user( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				SET push_enabled = 0, deleted_at = %s, updated_at = %s
				WHERE user_id = %d AND deleted_at IS NULL",
				$now,
				$now,
				(int) $user_id
			)
		);

		return false !== $result;
	}

	/**
	 * Get all valid Expo push tokens.
	 *
	 * @return string[] Array of push tokens.
	 */
	public static function get_all_tokens() {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );

		return $wpdb->get_col(
			"SELECT expo_push_token FROM `{$table}`
			WHERE expo_push_token IS NOT NULL
			  AND push_enabled = 1
			  AND deleted_at IS NULL"
		);
	}
}
