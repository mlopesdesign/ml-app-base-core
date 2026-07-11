<?php
/**
 * Consents repository — CRUD for the ml_app_base_notification_consents table.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Consents_Repository
 *
 * Manages LGPD-required notification consent records.
 * One record per user. Birthday automation must always check
 * birthday_enabled AND push_enabled before sending.
 */
class ML_App_Base_Core_Consents_Repository {

	/**
	 * Get consent record for a user.
	 *
	 * @param int $user_id App user ID.
	 * @return object|null
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_consents' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE user_id = %d LIMIT 1",
				(int) $user_id
			)
		);
	}

	/**
	 * Create an initial consent record for a newly registered user.
	 *
	 * @param int   $user_id  App user ID.
	 * @param array $prefs {
	 *   @type bool $push_enabled     Whether push is globally enabled.
	 *   @type bool $birthday_enabled Birthday notification consent.
	 *   @type bool $events_enabled   Event notification consent.
	 *   @type bool $news_enabled     News notification consent.
	 * }
	 * @param string $source Source identifier (e.g. 'registration', 'preferences_screen').
	 * @return int|false Row ID or false.
	 */
	public static function create( $user_id, array $prefs = array(), $source = 'registration' ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_consents' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'          => (int) $user_id,
				'push_enabled'     => isset( $prefs['push_enabled'] ) ? (int) (bool) $prefs['push_enabled'] : 0,
				'birthday_enabled' => isset( $prefs['birthday_enabled'] ) ? (int) (bool) $prefs['birthday_enabled'] : 0,
				'events_enabled'   => isset( $prefs['events_enabled'] ) ? (int) (bool) $prefs['events_enabled'] : 0,
				'news_enabled'     => isset( $prefs['news_enabled'] ) ? (int) (bool) $prefs['news_enabled'] : 0,
				'consent_source'   => sanitize_text_field( $source ),
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update notification preferences for a user.
	 * Only updates fields explicitly provided.
	 *
	 * @param int   $user_id App user ID.
	 * @param array $prefs   Fields to update.
	 * @return bool
	 */
	public static function update( $user_id, array $prefs ) {
		global $wpdb;
		$table   = ML_App_Base_Core_Database::table( 'ml_app_base_notification_consents' );
		$allowed = array( 'push_enabled', 'birthday_enabled', 'events_enabled', 'news_enabled' );
		$data    = array( 'updated_at' => current_time( 'mysql' ) );

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $prefs ) ) {
				$data[ $key ] = (int) (bool) $prefs[ $key ];
			}
		}

		// Upsert: if record does not exist, create it.
		$existing = self::get_for_user( $user_id );
		if ( ! $existing ) {
			return false !== self::create( $user_id, $prefs, 'preferences_screen' );
		}

		$result = $wpdb->update( $table, $data, array( 'user_id' => (int) $user_id ) );
		return false !== $result;
	}

	/**
	 * Format consent record into a safe public array.
	 *
	 * @param object|null $consent DB row.
	 * @return array
	 */
	public static function format_public( $consent ) {
		if ( ! $consent ) {
			return array(
				'push_enabled'     => false,
				'birthday_enabled' => false,
				'events_enabled'   => false,
				'news_enabled'     => false,
			);
		}

		return array(
			'push_enabled'     => (bool) $consent->push_enabled,
			'birthday_enabled' => (bool) $consent->birthday_enabled,
			'events_enabled'   => (bool) $consent->events_enabled,
			'news_enabled'     => (bool) $consent->news_enabled,
		);
	}

	/**
	 * Delete consent record for a user (called on account deletion per LGPD).
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function delete_for_user( $user_id ) {
		global $wpdb;
		$table  = ML_App_Base_Core_Database::table( 'ml_app_base_notification_consents' );
		$result = $wpdb->delete( $table, array( 'user_id' => (int) $user_id ), array( '%d' ) );
		return false !== $result;
	}
}
