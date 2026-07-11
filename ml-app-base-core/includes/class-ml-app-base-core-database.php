<?php
/**
 * Database schema management — table creation via dbDelta.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Database
 *
 * Handles creation and versioning of all custom database tables.
 * Tables are always created using $wpdb->prefix — never hardcoded as wp_.
 * Production WordPress prefix on app.appbase.com.br is expected to be wpapp_.
 *
 * Schema version is tracked in WordPress options to enable safe upgrades via dbDelta.
 */
class ML_App_Base_Core_Database {

	/**
	 * Current schema version.
	 * Increment this string when any table structure changes.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '0.1.34';

	/**
	 * WordPress option key that stores the installed schema version.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION_OPTION = 'ml_app_base_core_schema_version';

	/**
	 * Run dbDelta for all tables.
	 * Safe to call repeatedly — dbDelta only applies differences.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// -----------------------------------------------------------------------
		// Table 1: {prefix}ml_app_base_users
		// Stores custom app user profiles (separate from wp_users).
		// Contains personal data under LGPD — must be anonymized on deletion.
		// -----------------------------------------------------------------------
		$table_users = $wpdb->prefix . 'ml_app_base_users';
		$sql_users   = "CREATE TABLE {$table_users} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id VARCHAR(64) NOT NULL,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			phone VARCHAR(50) DEFAULT NULL,
			password_hash VARCHAR(255) NOT NULL,
			birth_day TINYINT UNSIGNED DEFAULT NULL,
			birth_month TINYINT UNSIGNED DEFAULT NULL,
			birth_year SMALLINT UNSIGNED DEFAULT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			accepts_terms_at DATETIME DEFAULT NULL,
			accepts_privacy_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY birthday (birth_month, birth_day)
		) {$charset_collate};";

		// -----------------------------------------------------------------------
		// Table 2: {prefix}ml_app_base_sessions
		// Stores opaque bearer token hashes. Plain tokens are never stored.
		// Session expiry enforced via expires_at; logout sets revoked_at.
		// -----------------------------------------------------------------------
		$table_sessions = $wpdb->prefix . 'ml_app_base_sessions';
		$sql_sessions   = "CREATE TABLE {$table_sessions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			token_hash VARCHAR(128) NOT NULL,
			device_id BIGINT UNSIGNED DEFAULT NULL,
			ip_address VARCHAR(100) DEFAULT NULL,
			user_agent TEXT DEFAULT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			last_used_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_id (user_id),
			KEY expires_at (expires_at),
			KEY revoked_at (revoked_at)
		) {$charset_collate};";

		// -----------------------------------------------------------------------
		// Table 3: {prefix}ml_app_base_devices
		// Stores Expo push tokens per user device.
		// Push tokens are personal data under LGPD.
		// -----------------------------------------------------------------------
		$table_devices = $wpdb->prefix . 'ml_app_base_devices';
		$sql_devices   = "CREATE TABLE {$table_devices} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			device_uuid VARCHAR(190) NOT NULL,
			platform VARCHAR(30) NOT NULL,
			expo_push_token TEXT DEFAULT NULL,
			push_enabled TINYINT(1) NOT NULL DEFAULT 1,
			app_version VARCHAR(50) DEFAULT NULL,
			last_seen_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_device (user_id, device_uuid),
			KEY user_id (user_id),
			KEY platform (platform),
			KEY push_enabled (push_enabled)
		) {$charset_collate};";

		// -----------------------------------------------------------------------
		// Table 4: {prefix}ml_app_base_favorites
		// Stores references to favorited public content items per user.
		// Content lives on appbase.com.br; only the ID is stored here.
		// -----------------------------------------------------------------------
		$table_favorites = $wpdb->prefix . 'ml_app_base_favorites';
		$sql_favorites   = "CREATE TABLE {$table_favorites} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			item_type VARCHAR(50) NOT NULL,
			item_id VARCHAR(100) NOT NULL,
			item_title VARCHAR(255) DEFAULT NULL,
			item_url TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_item (user_id, item_type, item_id),
			KEY user_id (user_id),
			KEY item_type (item_type)
		) {$charset_collate};";

		// -----------------------------------------------------------------------
		// Table 5: {prefix}ml_app_base_notification_consents
		// One record per user. Stores LGPD-required notification opt-in flags.
		// Birthday automation must check birthday_enabled AND push_enabled.
		// -----------------------------------------------------------------------
		$table_consents = $wpdb->prefix . 'ml_app_base_notification_consents';
		$sql_consents   = "CREATE TABLE {$table_consents} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			push_enabled TINYINT(1) NOT NULL DEFAULT 0,
			birthday_enabled TINYINT(1) NOT NULL DEFAULT 0,
			events_enabled TINYINT(1) NOT NULL DEFAULT 0,
			news_enabled TINYINT(1) NOT NULL DEFAULT 0,
			consent_source VARCHAR(100) DEFAULT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY birthday_enabled (birthday_enabled),
			KEY push_enabled (push_enabled)
		) {$charset_collate};";

		// -----------------------------------------------------------------------
		// Table 6: {prefix}ml_app_base_notification_logs
		// Audit log for all push notification attempts.
		// On account deletion: anonymize user_id/device_id, keep body for audit.
		// expo_response must never store full push tokens — status codes only.
		// -----------------------------------------------------------------------
		$table_logs = $wpdb->prefix . 'ml_app_base_notification_logs';
		$sql_logs   = "CREATE TABLE {$table_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			device_id BIGINT UNSIGNED DEFAULT NULL,
			notification_type VARCHAR(80) NOT NULL,
			title VARCHAR(255) NOT NULL,
			body TEXT NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'created',
			provider_response LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			sent_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY device_id (device_id),
			KEY notification_type (notification_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";


		// -----------------------------------------------------------------------
		// Table 7: {prefix}ml_app_base_notifications
		// Stores scheduled/manual push notifications configured in WP admin.
		// No full Expo push tokens are stored here.
		// -----------------------------------------------------------------------
		$table_notifications = $wpdb->prefix . 'ml_app_base_notifications';
		$sql_notifications   = "CREATE TABLE {$table_notifications} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			body TEXT NOT NULL,
			target_type VARCHAR(50) NOT NULL DEFAULT 'home',
			target_id TEXT DEFAULT NULL,
			image_url TEXT DEFAULT NULL,
			audience VARCHAR(50) NOT NULL DEFAULT 'all',
			send_mode VARCHAR(50) NOT NULL DEFAULT 'send_now',
			scheduled_at DATETIME DEFAULT NULL,
			recurrence VARCHAR(30) NOT NULL DEFAULT 'none',
			repeat_total SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			repeats_sent SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(50) NOT NULL DEFAULT 'draft',
			last_sent_at DATETIME DEFAULT NULL,
			success_count INT UNSIGNED NOT NULL DEFAULT 0,
			failure_count INT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY target_type (target_type),
			KEY audience (audience)
		) {$charset_collate};";


		// -----------------------------------------------------------------------
		// Table 8: {prefix}ml_app_base_user_notifications
		// Stores notification inbox records visible inside the mobile app.
		// This is separate from Expo delivery logs: a notification can be visible
		// in-app even when the user has no valid push token yet.
		// -----------------------------------------------------------------------
		$table_user_notifications = $wpdb->prefix . 'ml_app_base_user_notifications';
		$sql_user_notifications   = "CREATE TABLE {$table_user_notifications} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			notification_id BIGINT UNSIGNED DEFAULT NULL,
			title VARCHAR(255) NOT NULL,
			body TEXT NOT NULL,
			target_type VARCHAR(50) NOT NULL DEFAULT 'home',
			target_id TEXT DEFAULT NULL,
			image_url TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			read_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY notification_id (notification_id),
			KEY read_at (read_at),
			KEY created_at (created_at),
			KEY target_type (target_type)
		) {$charset_collate};";

		dbDelta( $sql_users );
		dbDelta( $sql_sessions );
		dbDelta( $sql_devices );
		dbDelta( $sql_favorites );
		dbDelta( $sql_consents );
		dbDelta( $sql_logs );
		dbDelta( $sql_notifications );
		dbDelta( $sql_user_notifications );

		// Store schema version for future upgrade checks.
		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Check whether the installed schema is up to date.
	 *
	 * @return bool True if schema version matches current.
	 */
	public static function is_schema_current() {
		return get_option( self::SCHEMA_VERSION_OPTION ) === self::SCHEMA_VERSION;
	}

	/**
	 * Return table name for a given suffix.
	 *
	 * @param string $suffix Table suffix (e.g. 'ml_app_base_users').
	 * @return string Full table name with WordPress prefix.
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}
}
