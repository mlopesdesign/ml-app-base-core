<?php
/**
 * Token service — generates, hashes, and validates opaque bearer tokens.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Token_Service
 *
 * Manages opaque session bearer tokens.
 *
 * Security model:
 * - Plain tokens are generated with strong entropy (random_bytes) and returned
 *   to the client exactly ONCE (at login or register).
 * - Only the SHA-256 hash is stored in the database (ml_app_base_sessions).
 * - Authentication verifies a supplied plain token by hashing and comparing.
 * - JWT is NOT used in this stage; no external libraries required.
 * - Tokens are never logged or written to files.
 */
class ML_App_Base_Core_Token_Service {

	/**
	 * Session lifetime in seconds (default 30 days).
	 *
	 * @var int
	 */
	const TOKEN_LIFETIME = 2592000;

	/**
	 * Generate a new cryptographically secure opaque token.
	 *
	 * @return string Plain token (returned to client once; NOT stored).
	 */
	public static function generate_plain_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash a plain token for safe database storage.
	 *
	 * @param string $plain_token The plain token returned to the client.
	 * @return string SHA-256 hex hash.
	 */
	public static function hash_token( $plain_token ) {
		return hash( 'sha256', $plain_token );
	}

	/**
	 * Create a new session record and return the plain token.
	 *
	 * @param int      $user_id   App user ID.
	 * @param int|null $device_id Optional device ID to associate.
	 * @return string|false Plain token on success, false on failure.
	 */
	public static function create_session( $user_id, $device_id = null ) {
		global $wpdb;

		$plain_token = self::generate_plain_token();
		$token_hash  = self::hash_token( $plain_token );
		$now         = current_time( 'mysql' );
		$expires_at  = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_LIFETIME );

		$table = ML_App_Base_Core_Database::table( 'ml_app_base_sessions' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => (int) $user_id,
				'token_hash' => $token_hash,
				'device_id'  => $device_id ? (int) $device_id : null,
				'expires_at' => $expires_at,
				'created_at' => $now,
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		return $plain_token;
	}

	/**
	 * Validate a plain bearer token from the Authorization header.
	 * Returns the session row if valid, false otherwise.
	 *
	 * A session is invalid if:
	 * - It does not exist.
	 * - It is expired (expires_at in the past).
	 * - It has been revoked (revoked_at is set).
	 *
	 * @param string $plain_token Plain token extracted from the Authorization header.
	 * @return object|false Session row or false.
	 */
	public static function validate_token( $plain_token ) {
		global $wpdb;

		if ( empty( $plain_token ) ) {
			return false;
		}

		$token_hash = self::hash_token( $plain_token );
		$table      = ML_App_Base_Core_Database::table( 'ml_app_base_sessions' );
		$now        = current_time( 'mysql' );

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}`
				WHERE token_hash = %s
				  AND revoked_at IS NULL
				  AND expires_at > %s
				LIMIT 1",
				$token_hash,
				$now
			)
		);

		if ( ! $session ) {
			return false;
		}

		// Update last_used_at.
		$wpdb->update(
			$table,
			array( 'last_used_at' => $now ),
			array( 'id' => $session->id ),
			array( '%s' ),
			array( '%d' )
		);

		return $session;
	}

	/**
	 * Revoke a specific session by its ID.
	 *
	 * @param int $session_id Session row ID.
	 * @return bool
	 */
	public static function revoke_session( $session_id ) {
		global $wpdb;

		$table = ML_App_Base_Core_Database::table( 'ml_app_base_sessions' );

		$result = $wpdb->update(
			$table,
			array( 'revoked_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $session_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Revoke all active sessions for a user (e.g. on account deletion).
	 *
	 * @param int $user_id App user ID.
	 * @return int Number of sessions revoked.
	 */
	public static function revoke_all_user_sessions( $user_id ) {
		global $wpdb;

		$table = ML_App_Base_Core_Database::table( 'ml_app_base_sessions' );
		$now   = current_time( 'mysql' );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				SET revoked_at = %s
				WHERE user_id = %d
				  AND revoked_at IS NULL",
				$now,
				(int) $user_id
			)
		);
	}

	/**
	 * Extract plain token from WordPress REST request Authorization header.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return string Plain token or empty string.
	 */
	public static function extract_token_from_request( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'authorization' );

		if ( empty( $auth_header ) ) {
			return '';
		}

		if ( strpos( $auth_header, 'Bearer ' ) === 0 ) {
			return trim( substr( $auth_header, 7 ) );
		}

		return '';
	}
}
