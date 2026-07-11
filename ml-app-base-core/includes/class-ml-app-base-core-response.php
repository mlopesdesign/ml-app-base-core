<?php
/**
 * Response helper — standardized JSON output for all REST endpoints.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Response
 *
 * Produces uniform success/error WP_REST_Response objects conforming to the
 * API_CONTRACT.md standard shapes.
 */
class ML_App_Base_Core_Response {

	/**
	 * Single-resource success response.
	 *
	 * @param mixed $data        Response data.
	 * @param int   $status_code HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	public static function success( $data = array(), $status_code = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status_code
		);
	}

	/**
	 * Paginated list success response.
	 *
	 * @param array $data        List items.
	 * @param int   $page        Current page.
	 * @param int   $per_page    Items per page.
	 * @param int   $total       Total item count.
	 * @param int   $status_code HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	public static function paginated( $data, $page, $per_page, $total, $status_code = 200 ) {
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		return new WP_REST_Response(
			array(
				'success'    => true,
				'data'       => $data,
				'pagination' => array(
					'page'        => (int) $page,
					'per_page'    => (int) $per_page,
					'total'       => (int) $total,
					'total_pages' => $total_pages,
				),
			),
			$status_code
		);
	}

	/**
	 * Error response.
	 *
	 * @param string $code        Machine-readable error code.
	 * @param string $message     Human-readable message.
	 * @param int    $status_code HTTP status code.
	 * @param array  $details     Optional extra context.
	 * @return WP_REST_Response
	 */
	public static function error( $code, $message, $status_code = 400, $details = array() ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
					'details' => (object) $details,
				),
			),
			$status_code
		);
	}

	/**
	 * Unauthorized (401) error response.
	 *
	 * @return WP_REST_Response
	 */
	public static function unauthorized() {
		return self::error( 'unauthorized', __( 'Authentication required.', 'ml-app-base-core' ), 401 );
	}

	/**
	 * Forbidden (403) error response.
	 *
	 * @return WP_REST_Response
	 */
	public static function forbidden() {
		return self::error( 'forbidden', __( 'You do not have permission to perform this action.', 'ml-app-base-core' ), 403 );
	}

	/**
	 * Not found (404) error response.
	 *
	 * @param string $message Optional custom message.
	 * @return WP_REST_Response
	 */
	public static function not_found( $message = '' ) {
		if ( empty( $message ) ) {
			$message = __( 'Resource not found.', 'ml-app-base-core' );
		}
		return self::error( 'not_found', $message, 404 );
	}

	/**
	 * Server error (500) response.
	 *
	 * @param string $message Optional custom message.
	 * @return WP_REST_Response
	 */
	public static function server_error( $message = '' ) {
		if ( empty( $message ) ) {
			$message = __( 'An unexpected error occurred.', 'ml-app-base-core' );
		}
		return self::error( 'server_error', $message, 500 );
	}
}
