<?php
/**
 * Authentication service — register, login, logout, me, delete account.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Auth
 *
 * Handles all authentication flows:
 * - Registration with LGPD consent validation.
 * - Login with opaque bearer token issuance.
 * - Logout via session revocation.
 * - Profile retrieval and update.
 * - Account soft-deletion with LGPD personal data cleanup.
 *
 * Passwords: hashed with wp_hash_password(), verified with wp_check_password().
 * Tokens: opaque, random, stored as SHA-256 hashes only.
 * password_hash is NEVER included in any response.
 */
class ML_App_Base_Core_Auth {

	/**
	 * Validate an incoming request and return the authenticated app user.
	 * Returns WP_REST_Response (401) if authentication fails.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return object|WP_REST_Response App user row or 401 response.
	 */
	public static function authenticate( WP_REST_Request $request ) {
		$plain_token = ML_App_Base_Core_Token_Service::extract_token_from_request( $request );

		if ( empty( $plain_token ) ) {
			return ML_App_Base_Core_Response::unauthorized();
		}

		$session = ML_App_Base_Core_Token_Service::validate_token( $plain_token );

		if ( ! $session ) {
			return ML_App_Base_Core_Response::unauthorized();
		}

		$user = ML_App_Base_Core_Users_Repository::find_by_id( $session->user_id );

		if ( ! $user || 'active' !== $user->status ) {
			return ML_App_Base_Core_Response::unauthorized();
		}

		// Attach session to user for downstream use.
		$user->_session_id = (int) $session->id;

		return $user;
	}

	/**
	 * Register a new app user.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function register( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// --- Required field validation ---
		$name  = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$email = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';

		if ( empty( $name ) ) {
			return ML_App_Base_Core_Response::error( 'name_required', __( 'Name is required.', 'ml-app-base-core' ), 400 );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			return ML_App_Base_Core_Response::error( 'invalid_email', __( 'A valid email address is required.', 'ml-app-base-core' ), 400 );
		}

		if ( ML_App_Base_Core_Users_Repository::is_email_blocked( $email ) ) {
			return ML_App_Base_Core_Response::error( 'email_blocked', __( 'This email address is blocked from using the app.', 'ml-app-base-core' ), 403 );
		}

		$password = isset( $params['password'] ) ? $params['password'] : '';
		if ( strlen( $password ) < 8 ) {
			return ML_App_Base_Core_Response::error( 'password_too_short', __( 'Password must be at least 8 characters.', 'ml-app-base-core' ), 400 );
		}

		// LGPD: terms and privacy must both be accepted.
		if ( empty( $params['accepts_terms'] ) ) {
			return ML_App_Base_Core_Response::error( 'terms_required', __( 'You must accept the terms of use to register.', 'ml-app-base-core' ), 400 );
		}
		if ( empty( $params['accepts_privacy'] ) ) {
			return ML_App_Base_Core_Response::error( 'lgpd_consent_required', __( 'You must accept the privacy policy to register.', 'ml-app-base-core' ), 400 );
		}

		// Birthday validation.
		$birth_day   = isset( $params['birth_day'] ) ? (int) $params['birth_day'] : null;
		$birth_month = isset( $params['birth_month'] ) ? (int) $params['birth_month'] : null;
		$birth_year  = isset( $params['birth_year'] ) ? (int) $params['birth_year'] : null;

		if ( null !== $birth_day && ( $birth_day < 1 || $birth_day > 31 ) ) {
			return ML_App_Base_Core_Response::error( 'invalid_birth_day', __( 'Birth day must be between 1 and 31.', 'ml-app-base-core' ), 400 );
		}
		if ( null !== $birth_month && ( $birth_month < 1 || $birth_month > 12 ) ) {
			return ML_App_Base_Core_Response::error( 'invalid_birth_month', __( 'Birth month must be between 1 and 12.', 'ml-app-base-core' ), 400 );
		}

		// Duplicate email check.
		if ( ML_App_Base_Core_Users_Repository::email_exists( $email ) ) {
			return ML_App_Base_Core_Response::error( 'email_already_exists', __( 'This email address is already registered.', 'ml-app-base-core' ), 409 );
		}

		// Hash password — never store plain text.
		$password_hash = wp_hash_password( $password );

		$user_id = ML_App_Base_Core_Users_Repository::create( array(
			'name'            => $name,
			'email'           => $email,
			'password_hash'   => $password_hash,
			'phone'           => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : null,
			'birth_day'       => $birth_day,
			'birth_month'     => $birth_month,
			'birth_year'      => $birth_year,
			'accepts_terms'   => true,
			'accepts_privacy' => true,
		) );

		if ( ! $user_id ) {
			return ML_App_Base_Core_Response::server_error( __( 'Could not create user account. Please try again.', 'ml-app-base-core' ) );
		}

		// Create initial notification consents.
		$notification_prefs = array();
		if ( isset( $params['notification_preferences'] ) && is_array( $params['notification_preferences'] ) ) {
			$notification_prefs = $params['notification_preferences'];
		}
		ML_App_Base_Core_Consents_Repository::create( $user_id, $notification_prefs, 'registration' );

		// Create session and return plain token (only time the token is returned).
		$plain_token = ML_App_Base_Core_Token_Service::create_session( $user_id );
		if ( ! $plain_token ) {
			return ML_App_Base_Core_Response::server_error( __( 'Account created but session could not be started. Please log in.', 'ml-app-base-core' ) );
		}

		$user = ML_App_Base_Core_Users_Repository::find_by_id( $user_id );

		return ML_App_Base_Core_Response::success(
			array(
				'user'  => ML_App_Base_Core_Users_Repository::format_public( $user ),
				'token' => array(
					'plain_token' => $plain_token,
					'token_type'  => 'Bearer',
					'expires_in'  => ML_App_Base_Core_Token_Service::TOKEN_LIFETIME,
				),
			),
			201
		);
	}

	/**
	 * Login an existing user.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function login( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$email    = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$password = isset( $params['password'] ) ? $params['password'] : '';

		if ( empty( $email ) || empty( $password ) ) {
			return ML_App_Base_Core_Response::error( 'invalid_credentials', __( 'Email or password is incorrect.', 'ml-app-base-core' ), 401 );
		}

		if ( ML_App_Base_Core_Users_Repository::is_email_blocked( $email ) ) {
			return ML_App_Base_Core_Response::error( 'email_blocked', __( 'This email address is blocked from using the app.', 'ml-app-base-core' ), 403 );
		}

		$existing_user = ML_App_Base_Core_Users_Repository::find_by_email( $email );
		if ( $existing_user && 'blocked' === $existing_user->status ) {
			return ML_App_Base_Core_Response::error( 'account_blocked', __( 'This account is blocked.', 'ml-app-base-core' ), 403 );
		}

		// Fetch password hash by email — intentionally minimal data exposure.
		$hash = ML_App_Base_Core_Users_Repository::get_password_hash_by_email( $email );

		// Use constant-time comparison via wp_check_password.
		if ( ! $hash || ! wp_check_password( $password, $hash ) ) {
			return ML_App_Base_Core_Response::error( 'invalid_credentials', __( 'Email or password is incorrect.', 'ml-app-base-core' ), 401 );
		}

		$user_id = ML_App_Base_Core_Users_Repository::get_id_by_email( $email );
		$user    = ML_App_Base_Core_Users_Repository::find_by_id( $user_id );

		if ( ! $user || 'active' !== $user->status ) {
			return ML_App_Base_Core_Response::error( 'account_inactive', __( 'This account is no longer active.', 'ml-app-base-core' ), 403 );
		}

		// Issue new session token.
		$plain_token = ML_App_Base_Core_Token_Service::create_session( $user_id );
		if ( ! $plain_token ) {
			return ML_App_Base_Core_Response::server_error();
		}

		return ML_App_Base_Core_Response::success( array(
			'user'  => ML_App_Base_Core_Users_Repository::format_public( $user ),
			'token' => array(
				'plain_token' => $plain_token,
				'token_type'  => 'Bearer',
				'expires_in'  => ML_App_Base_Core_Token_Service::TOKEN_LIFETIME,
			),
		) );
	}

	/**
	 * Logout — revoke current session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function logout( WP_REST_Request $request ) {
		$user = self::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		ML_App_Base_Core_Token_Service::revoke_session( $user->_session_id );

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Logged out successfully.', 'ml-app-base-core' ) ) );
	}

	/**
	 * GET /me — return authenticated user profile and notification preferences.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_me( WP_REST_Request $request ) {
		$user = self::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$consent = ML_App_Base_Core_Consents_Repository::get_for_user( $user->id );

		return ML_App_Base_Core_Response::success( array(
			'user'                     => ML_App_Base_Core_Users_Repository::format_public( $user ),
			'notification_preferences' => ML_App_Base_Core_Consents_Repository::format_public( $consent ),
		) );
	}

	/**
	 * PATCH /me — update permitted profile fields.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function update_me( WP_REST_Request $request ) {
		$user = self::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( array_key_exists( 'email', $params ) ) {
			$email = sanitize_email( $params['email'] );
			if ( empty( $email ) || ! is_email( $email ) ) {
				return ML_App_Base_Core_Response::error( 'invalid_email', __( 'A valid email address is required.', 'ml-app-base-core' ), 400 );
			}

			if ( ML_App_Base_Core_Users_Repository::email_exists_for_other_user( $email, $user->id ) ) {
				return ML_App_Base_Core_Response::error( 'email_already_exists', __( 'This email address is already registered.', 'ml-app-base-core' ), 409 );
			}

			$params['email'] = $email;
		}

		// Validate birth fields if provided.
		if ( isset( $params['birth_day'] ) && null !== $params['birth_day'] ) {
			$bd = (int) $params['birth_day'];
			if ( $bd < 1 || $bd > 31 ) {
				return ML_App_Base_Core_Response::error( 'invalid_birth_day', __( 'Birth day must be between 1 and 31.', 'ml-app-base-core' ), 400 );
			}
		}
		if ( isset( $params['birth_month'] ) && null !== $params['birth_month'] ) {
			$bm = (int) $params['birth_month'];
			if ( $bm < 1 || $bm > 12 ) {
				return ML_App_Base_Core_Response::error( 'invalid_birth_month', __( 'Birth month must be between 1 and 12.', 'ml-app-base-core' ), 400 );
			}
		}

		$updated = ML_App_Base_Core_Users_Repository::update( $user->id, $params );

		if ( ! $updated ) {
			return ML_App_Base_Core_Response::server_error();
		}

		$refreshed = ML_App_Base_Core_Users_Repository::find_by_id( $user->id );

		return ML_App_Base_Core_Response::success( ML_App_Base_Core_Users_Repository::format_public( $refreshed ) );
	}

	/**
	 * DELETE /me — soft-delete account with LGPD data cleanup.
	 * Requires body: { "confirmation": "DELETE" }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function delete_me( WP_REST_Request $request ) {
		$user = self::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$confirmation = $request->get_param( 'confirmation' );

		if ( 'DELETE' !== $confirmation ) {
			return ML_App_Base_Core_Response::error(
				'confirmation_required',
				__( 'Send { "confirmation": "DELETE" } to confirm account deletion.', 'ml-app-base-core' ),
				400
			);
		}

		// Revoke all sessions.
		ML_App_Base_Core_Token_Service::revoke_all_user_sessions( $user->id );

		// Disable all devices.
		ML_App_Base_Core_Devices_Repository::delete_all_for_user( $user->id );

		// Delete favorites.
		ML_App_Base_Core_Favorites_Repository::delete_all_for_user( $user->id );

		// Delete consent records.
		ML_App_Base_Core_Consents_Repository::delete_for_user( $user->id );

		// Soft-delete user.
		ML_App_Base_Core_Users_Repository::soft_delete( $user->id );

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Account deleted successfully.', 'ml-app-base-core' ) ) );
	}
}
