<?php
/**
 * Push provider service for Expo legacy tokens and native Firebase FCM tokens.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_App_Base_Core_Push_Service {

	const OPTION_FIREBASE_PROJECT_ID = 'ml_app_base_core_firebase_project_id';
	const OPTION_FIREBASE_SERVICE_ACCOUNT_JSON = 'ml_app_base_core_firebase_service_account_json';
	const OPTION_FIREBASE_ENABLED = 'ml_app_base_core_firebase_enabled';
	const TRANSIENT_FCM_ACCESS_TOKEN = 'ml_app_base_core_fcm_access_token';
	const TRANSIENT_FCM_ACCESS_TOKEN_EXP = 'ml_app_base_core_fcm_access_token_exp';

	/**
	 * Send one push notification to a token.
	 *
	 * @param string $token Push token.
	 * @param string $title Notification title.
	 * @param string $body Notification body.
	 * @param array  $data Custom data.
	 * @return array Structured result.
	 */
	public static function send( $token, $title, $body, array $data = array() ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			return self::result( false, 'none', 'empty_token' );
		}

		if ( self::is_expo_token( $token ) ) {
			return self::send_expo( $token, $title, $body, $data );
		}

		return self::send_fcm( $token, $title, $body, $data );
	}

	/**
	 * Send many notifications and aggregate results.
	 *
	 * @param string[] $tokens Push tokens.
	 * @param string   $title Notification title.
	 * @param string   $body Notification body.
	 * @param array    $data Custom data.
	 * @return array Aggregated result.
	 */
	public static function send_many( array $tokens, $title, $body, array $data = array() ) {
		$success_count = 0;
		$failure_count = 0;
		$expo_count    = 0;
		$fcm_count     = 0;
		$last_error    = '';
		$details       = array();

		foreach ( $tokens as $token ) {
			$provider = self::is_expo_token( $token ) ? 'expo' : 'fcm';
			if ( 'expo' === $provider ) {
				$expo_count++;
			} else {
				$fcm_count++;
			}

			$result = self::send( $token, $title, $body, $data );
			if ( ! empty( $result['success'] ) ) {
				$success_count++;
			} else {
				$failure_count++;
				$last_error = isset( $result['error'] ) ? sanitize_text_field( $result['error'] ) : 'send_failed';
			}

			$details[] = array(
				'provider'      => isset( $result['provider'] ) ? $result['provider'] : $provider,
				'success'       => ! empty( $result['success'] ),
				'error'         => isset( $result['error'] ) ? $result['error'] : '',
				'http_code'     => isset( $result['http_code'] ) ? (int) $result['http_code'] : null,
				'response_body' => isset( $result['response_body'] ) ? self::redact_response_body( $result['response_body'] ) : '',
				'token_masked'  => self::mask_token( $token ),
			);
		}

		return array(
			'success_count' => $success_count,
			'failure_count' => $failure_count,
			'expo_count'    => $expo_count,
			'fcm_count'     => $fcm_count,
			'last_error'    => $last_error,
			'details'       => $details,
		);
	}

	/**
	 * Detect Expo push token.
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public static function is_expo_token( $token ) {
		$token = trim( (string) $token );
		return 0 === strpos( $token, 'ExponentPushToken[' ) || 0 === strpos( $token, 'ExpoPushToken[' );
	}

	/**
	 * Check Firebase configuration status.
	 *
	 * @return array Status.
	 */
	public static function get_firebase_status() {
		$project_id = self::get_project_id();
		$account    = self::get_service_account();

		$configured = '' !== $project_id && ! empty( $account['client_email'] ) && ! empty( $account['private_key'] );

		return array(
			'configured'   => $configured,
			'enabled'      => $configured && '1' === (string) get_option( self::OPTION_FIREBASE_ENABLED, '0' ),
			'project_id'   => $project_id,
			'client_email' => isset( $account['client_email'] ) ? $account['client_email'] : '',
		);
	}

	/**
	 * Save Firebase settings.
	 *
	 * @param string $project_id Project ID.
	 * @param string $service_account_json Service account JSON.
	 * @return true|WP_Error
	 */
	public static function save_firebase_settings( $project_id, $service_account_json, $enabled = false ) {
		$project_id = sanitize_text_field( $project_id );
		$service_account_json = trim( (string) $service_account_json );

		if ( '' === $project_id ) {
			return new WP_Error( 'missing_project_id', 'Informe o Firebase Project ID.' );
		}

		$existing = self::get_service_account();
		if ( '' === $service_account_json && empty( $existing ) ) {
			return new WP_Error( 'missing_service_account', 'Cole o Service Account JSON.' );
		}

		if ( '' === $service_account_json ) {
			update_option( self::OPTION_FIREBASE_PROJECT_ID, $project_id, false );
			update_option( self::OPTION_FIREBASE_ENABLED, $enabled ? '1' : '0', false );
			delete_transient( self::TRANSIENT_FCM_ACCESS_TOKEN );
			delete_transient( self::TRANSIENT_FCM_ACCESS_TOKEN_EXP );
			return true;
		}

		$decoded = json_decode( $service_account_json, true );
		if ( ! is_array( $decoded ) || empty( $decoded['client_email'] ) || empty( $decoded['private_key'] ) ) {
			return new WP_Error( 'invalid_service_account', 'Service Account JSON inválido. O JSON precisa conter client_email e private_key.' );
		}

		if ( ! empty( $decoded['project_id'] ) && $decoded['project_id'] !== $project_id ) {
			return new WP_Error( 'project_mismatch', 'O project_id do JSON não confere com o Firebase Project ID informado.' );
		}

		update_option( self::OPTION_FIREBASE_PROJECT_ID, $project_id, false );
		update_option( self::OPTION_FIREBASE_SERVICE_ACCOUNT_JSON, wp_json_encode( $decoded ), false );
		update_option( self::OPTION_FIREBASE_ENABLED, $enabled ? '1' : '0', false );
		delete_transient( self::TRANSIENT_FCM_ACCESS_TOKEN );
		delete_transient( self::TRANSIENT_FCM_ACCESS_TOKEN_EXP );

		return true;
	}

	private static function send_expo( $token, $title, $body, array $data ) {
		$response = wp_remote_post(
			'https://exp.host/--/api/v2/push/send',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'to'       => $token,
						'title'    => $title,
						'body'     => $body,
						'sound'    => 'default',
						'priority' => 'high',
						'data'     => self::stringify_data( $data ),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::result( false, 'expo', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 === (int) $code && isset( $body_json['data']['status'] ) && 'ok' === $body_json['data']['status'] ) {
			return self::result( true, 'expo', '', array( 'http_code' => (int) $code, 'response_body' => wp_remote_retrieve_body( $response ) ) );
		}

		$error = 'Expo Push API HTTP ' . (int) $code;
		if ( isset( $body_json['data']['message'] ) ) {
			$error = sanitize_text_field( $body_json['data']['message'] );
		} elseif ( isset( $body_json['errors'][0]['message'] ) ) {
			$error = sanitize_text_field( $body_json['errors'][0]['message'] );
		}

		return self::result( false, 'expo', $error, array( 'http_code' => (int) $code, 'response_body' => wp_remote_retrieve_body( $response ) ) );
	}

	private static function send_fcm( $token, $title, $body, array $data ) {
		$status = self::get_firebase_status();
		if ( empty( $status['configured'] ) ) {
			return self::result( false, 'fcm', 'Firebase FCM não configurado no plugin.' );
		}

		if ( empty( $status['enabled'] ) ) {
			return self::result( false, 'fcm', 'Firebase FCM está configurado, mas desabilitado no plugin.' );
		}

		$access_token = self::get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return self::result( false, 'fcm', $access_token->get_error_message() );
		}

		$url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( self::get_project_id() ) . '/messages:send';
		$notification_payload = array(
			'title' => (string) $title,
			'body'  => (string) $body,
		);
		$android_notification = array(
			'channel_id' => 'default',
			'sound'      => 'default',
		);
		$image_url = isset( $data['image_url'] ) ? esc_url_raw( (string) $data['image_url'] ) : '';
		if ( '' !== $image_url ) {
			$notification_payload['image'] = $image_url;
			$android_notification['image'] = $image_url;
		}

		$message = array(
			'message' => array(
				'token'        => $token,
				'notification' => $notification_payload,
				'data'         => self::stringify_data( $data ),
				'android'      => array(
					'priority'     => 'HIGH',
					'notification' => $android_notification,
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json; charset=utf-8',
					'Authorization' => 'Bearer ' . $access_token,
				),
				'body'    => wp_json_encode( $message ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::result( false, 'fcm', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 === (int) $code && ! empty( $body_json['name'] ) ) {
			return self::result( true, 'fcm', '', array( 'http_code' => (int) $code, 'response_body' => wp_remote_retrieve_body( $response ), 'url' => $url ) );
		}

		$error = 'FCM HTTP ' . (int) $code;
		if ( isset( $body_json['error']['message'] ) ) {
			$error = sanitize_text_field( $body_json['error']['message'] );
		}

		return self::result( false, 'fcm', $error, array( 'http_code' => (int) $code, 'response_body' => wp_remote_retrieve_body( $response ), 'url' => $url ) );
	}

	private static function get_project_id() {
		return sanitize_text_field( (string) get_option( self::OPTION_FIREBASE_PROJECT_ID, '' ) );
	}

	private static function get_service_account() {
		$raw = get_option( self::OPTION_FIREBASE_SERVICE_ACCOUNT_JSON, '' );
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function get_access_token() {
		$cached = get_transient( self::TRANSIENT_FCM_ACCESS_TOKEN );
		if ( $cached ) {
			return $cached;
		}

		$account = self::get_service_account();
		if ( empty( $account['client_email'] ) || empty( $account['private_key'] ) ) {
			return new WP_Error( 'firebase_not_configured', 'Firebase Service Account não configurada.' );
		}

		$now = time();
		$header = array( 'alg' => 'RS256', 'typ' => 'JWT' );
		$claim = array(
			'iss'   => $account['client_email'],
			'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$unsigned = self::base64url_encode( wp_json_encode( $header ) ) . '.' . self::base64url_encode( wp_json_encode( $claim ) );
		$signature = '';
		$ok = openssl_sign( $unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new WP_Error( 'firebase_sign_failed', 'Falha ao assinar JWT da Service Account.' );
		}

		$jwt = $unsigned . '.' . self::base64url_encode( $signature );
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
						'assertion'  => $jwt,
					),
					'',
					'&'
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'firebase_token_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) $code || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) ? $body['error_description'] : 'HTTP ' . (int) $code;
			return new WP_Error( 'firebase_token_invalid_response', sanitize_text_field( $message ) );
		}

		$ttl = isset( $body['expires_in'] ) ? max( 300, (int) $body['expires_in'] - 120 ) : 3300;
		set_transient( self::TRANSIENT_FCM_ACCESS_TOKEN, $body['access_token'], $ttl );
		set_transient( self::TRANSIENT_FCM_ACCESS_TOKEN_EXP, $now + $ttl, $ttl );

		return $body['access_token'];
	}

	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function stringify_data( array $data ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_null( $value ) ) {
				$value = '';
			}
			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			}
			$out[ sanitize_key( $key ) ] = (string) $value;
		}
		return $out;
	}


	public static function mask_token( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			return '';
		}
		$length = strlen( $token );
		if ( $length <= 16 ) {
			return substr( $token, 0, 4 ) . '...';
		}
		return substr( $token, 0, 12 ) . '...' . substr( $token, -6 );
	}

	private static function redact_response_body( $body ) {
		$body = (string) $body;
		if ( strlen( $body ) > 3000 ) {
			$body = substr( $body, 0, 3000 ) . '...';
		}
		return $body;
	}

	private static function result( $success, $provider, $error, array $extra = array() ) {
		return array_merge(
			array(
				'success'  => (bool) $success,
				'provider' => $provider,
				'error'    => (string) $error,
			),
			$extra
		);
	}
}
