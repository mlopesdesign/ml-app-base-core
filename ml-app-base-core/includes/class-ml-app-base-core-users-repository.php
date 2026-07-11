<?php
/**
 * Users repository — CRUD operations for the ml_app_base_users table.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Users_Repository
 *
 * All queries use $wpdb->prepare() and $wpdb->prefix.
 * password_hash is NEVER returned by any public method.
 * Personal data must be anonymized on account deletion (LGPD).
 */
class ML_App_Base_Core_Users_Repository {

	/**
	 * Find a user by email address.
	 *
	 * @param string $email Email to search.
	 * @return object|null User row (without password_hash) or null.
	 */
	public static function find_by_email( $email ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, public_id, name, email, phone, birth_day, birth_month, birth_year,
				        status, accepts_terms_at, accepts_privacy_at, created_at, updated_at
				FROM `{$table}`
				WHERE email = %s
				  AND deleted_at IS NULL
				LIMIT 1",
				sanitize_email( $email )
			)
		);
	}

	/**
	 * Find a user by ID.
	 *
	 * @param int $user_id App user ID.
	 * @return object|null User row (without password_hash) or null.
	 */
	public static function find_by_id( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, public_id, name, email, phone, birth_day, birth_month, birth_year,
				        status, accepts_terms_at, accepts_privacy_at, created_at, updated_at
				FROM `{$table}`
				WHERE id = %d
				  AND deleted_at IS NULL
				LIMIT 1",
				(int) $user_id
			)
		);
	}

	/**
	 * Fetch the password_hash for a given email (used only for login verification).
	 * Returns only the hash — never the full user row with this method combined.
	 *
	 * @param string $email Email address.
	 * @return string|null Password hash or null.
	 */
	public static function get_password_hash_by_email( $email ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT password_hash FROM `{$table}`
				WHERE email = %s
				  AND status = 'active'
				  AND deleted_at IS NULL
				LIMIT 1",
				sanitize_email( $email )
			)
		);
	}

	/**
	 * Get user ID by email (lightweight lookup).
	 *
	 * @param string $email Email address.
	 * @return int|null User ID or null.
	 */
	public static function get_id_by_email( $email ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE email = %s AND deleted_at IS NULL LIMIT 1",
				sanitize_email( $email )
			)
		);

		return $id ? (int) $id : null;
	}

	/**
	 * Check whether an email is already registered.
	 *
	 * @param string $email Email to check.
	 * @return bool
	 */
	public static function email_exists( $email ) {
		return null !== self::get_id_by_email( $email );
	}

	/**
	 * Check whether an email is already registered by another active app user.
	 *
	 * @param string $email   Email to check.
	 * @param int    $user_id Current user ID to exclude.
	 * @return bool
	 */
	public static function email_exists_for_other_user( $email, $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE email = %s AND id <> %d AND deleted_at IS NULL LIMIT 1",
				sanitize_email( $email ),
				(int) $user_id
			)
		);

		return null !== $found;
	}


	/**
	 * Normalize an email for comparisons and block-list storage.
	 *
	 * @param string $email Raw email.
	 * @return string Normalized email.
	 */
	public static function normalize_email( $email ) {
		return strtolower( sanitize_email( trim( (string) $email ) ) );
	}

	/**
	 * Get the e-mail block list stored in WordPress options.
	 *
	 * @return array<string,array> Map keyed by normalized e-mail.
	 */
	public static function get_blocked_emails() {
		$blocked = get_option( 'ml_app_base_core_blocked_emails', array() );
		return is_array( $blocked ) ? $blocked : array();
	}

	/**
	 * Check whether an e-mail address is administratively blocked.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_email_blocked( $email ) {
		$normalized = self::normalize_email( $email );
		if ( empty( $normalized ) ) {
			return false;
		}

		$blocked = self::get_blocked_emails();
		return isset( $blocked[ $normalized ] );
	}

	/**
	 * Add an e-mail to the administrative block list.
	 *
	 * @param string $email  Email address.
	 * @param string $reason Optional reason.
	 * @return bool
	 */
	public static function block_email( $email, $reason = '' ) {
		$normalized = self::normalize_email( $email );
		if ( empty( $normalized ) || ! is_email( $normalized ) ) {
			return false;
		}

		$blocked = self::get_blocked_emails();
		$blocked[ $normalized ] = array(
			'email'      => $normalized,
			'reason'     => sanitize_text_field( $reason ),
			'blocked_at' => current_time( 'mysql' ),
			'blocked_by' => get_current_user_id(),
		);

		return update_option( 'ml_app_base_core_blocked_emails', $blocked, false );
	}

	/**
	 * Remove an e-mail from the administrative block list.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function unblock_email( $email ) {
		$normalized = self::normalize_email( $email );
		if ( empty( $normalized ) ) {
			return false;
		}

		$blocked = self::get_blocked_emails();
		if ( isset( $blocked[ $normalized ] ) ) {
			unset( $blocked[ $normalized ] );
		}

		return update_option( 'ml_app_base_core_blocked_emails', $blocked, false );
	}

	/**
	 * Set the administrative status for a user.
	 *
	 * @param int    $user_id App user ID.
	 * @param string $status  active|blocked.
	 * @return bool
	 */
	public static function set_status( $user_id, $status ) {
		global $wpdb;
		$table  = ML_App_Base_Core_Database::table( 'ml_app_base_users' );
		$status = in_array( $status, array( 'active', 'blocked' ), true ) ? $status : 'active';
		$now    = current_time( 'mysql' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = %s, updated_at = %s WHERE id = %d AND deleted_at IS NULL",
				$status,
				$now,
				(int) $user_id
			)
		);

		return false !== $result;
	}

	/**
	 * Get recent non-deleted users for the admin table.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_recent_admin_users( $limit = 50 ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );
		$limit = max( 1, min( 200, (int) $limit ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, public_id, name, email, phone, birth_day, birth_month, status, created_at, updated_at
				 FROM `{$table}`
				 WHERE deleted_at IS NULL
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * Administratively delete a user while preserving database integrity.
	 *
	 * This is a safe soft delete with anonymization of personal fields so the
	 * original e-mail can be registered again unless it is also blocked.
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function admin_delete( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );
		$now   = current_time( 'mysql' );
		$user  = self::find_by_id( $user_id );

		if ( ! $user ) {
			return false;
		}

		$anon_email = 'deleted-' . (int) $user_id . '-' . substr( wp_hash( (string) $user->email . '|' . time() ), 0, 12 ) . '@deleted.local';

		$result = $wpdb->update(
			$table,
			array(
				'name'        => 'Usuário removido',
				'email'       => $anon_email,
				'phone'       => null,
				'status'      => 'deleted',
				'deleted_at'  => $now,
				'updated_at'  => $now,
			),
			array( 'id' => (int) $user_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Insert a new user record.
	 * Returns the new user ID or false on failure.
	 *
	 * @param array $data {
	 *   @type string $name               Full name.
	 *   @type string $email              Email address.
	 *   @type string $password_hash      Already-hashed password (wp_hash_password).
	 *   @type string $phone              Optional phone.
	 *   @type int    $birth_day          Optional.
	 *   @type int    $birth_month        Optional.
	 *   @type int    $birth_year         Optional.
	 *   @type bool   $accepts_terms      Must be true.
	 *   @type bool   $accepts_privacy    Must be true.
	 * }
	 * @return int|false New user ID or false.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );
		$now   = current_time( 'mysql' );

		$public_id = wp_generate_uuid4();

		$row = array(
			'public_id'         => $public_id,
			'name'              => sanitize_text_field( $data['name'] ),
			'email'             => sanitize_email( $data['email'] ),
			'password_hash'     => $data['password_hash'],
			'phone'             => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
			'birth_day'         => isset( $data['birth_day'] ) ? (int) $data['birth_day'] : null,
			'birth_month'       => isset( $data['birth_month'] ) ? (int) $data['birth_month'] : null,
			'birth_year'        => isset( $data['birth_year'] ) ? (int) $data['birth_year'] : null,
			'status'            => 'active',
			'accepts_terms_at'  => ! empty( $data['accepts_terms'] ) ? $now : null,
			'accepts_privacy_at' => ! empty( $data['accepts_privacy'] ) ? $now : null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( $table, $row, $formats );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update permitted profile fields for a user.
	 *
	 * @param int   $user_id App user ID.
	 * @param array $fields  Associative array of allowed fields to update.
	 * @return bool
	 */
	public static function update( $user_id, array $fields ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );

		// Whitelist of updatable fields in this version.
		$allowed = array( 'name', 'email', 'phone', 'birth_day', 'birth_month', 'birth_year' );
		$data    = array( 'updated_at' => current_time( 'mysql' ) );

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				switch ( $key ) {
					case 'name':
						$data[ $key ] = sanitize_text_field( $fields[ $key ] );
						break;
					case 'email':
						$data[ $key ] = sanitize_email( $fields[ $key ] );
						break;
					case 'phone':
						$data[ $key ] = '' === trim( (string) $fields[ $key ] ) ? null : sanitize_text_field( $fields[ $key ] );
						break;
					case 'birth_day':
					case 'birth_month':
					case 'birth_year':
						$data[ $key ] = null !== $fields[ $key ] ? (int) $fields[ $key ] : null;
						break;
				}
			}
		}

		$result = $wpdb->update( $table, $data, array( 'id' => (int) $user_id ) );
		return false !== $result;
	}

	/**
	 * Soft-delete a user account (LGPD-safe: status = deleted, deleted_at set).
	 * Does NOT physically delete the row — audit trail preserved.
	 * Personal data anonymization is the responsibility of the full deletion flow in Auth.
	 *
	 * @param int $user_id App user ID.
	 * @return bool
	 */
	public static function soft_delete( $user_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_users' );
		$now   = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			array(
				'status'     => 'deleted',
				'deleted_at' => $now,
				'updated_at' => $now,
			),
			array( 'id' => (int) $user_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Format a user row into a safe public object (no password_hash).
	 *
	 * @param object $user Raw DB row.
	 * @return array Safe user array.
	 */
	public static function format_public( $user ) {
		return array(
			'user_id'     => (int) $user->id,
			'public_id'   => $user->public_id,
			'name'        => $user->name,
			'email'       => $user->email,
			'phone'       => $user->phone,
			'birth_day'   => null !== $user->birth_day ? (int) $user->birth_day : null,
			'birth_month' => null !== $user->birth_month ? (int) $user->birth_month : null,
			'birth_year'  => null !== $user->birth_year ? (int) $user->birth_year : null,
			'created_at'  => $user->created_at,
		);
	}
}
