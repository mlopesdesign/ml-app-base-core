<?php
/**
 * REST API registration — all ml-app-base/v1 routes.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_REST_API
 *
 * Registers all REST routes for the ml-app-base/v1 namespace.
 * Every route has a permission_callback.
 * Authenticated routes use __return_true as permission_callback (auth is
 * validated inside the handler, returning 401 on failure) to preserve the
 * standard JSON error shape rather than letting WordPress override it.
 *
 * Route overview:
 *   Public:       GET  /config
 *                 POST /auth/register
 *                 POST /auth/login
 *
 *   Authenticated: POST   /auth/logout
 *                  GET    /me
 *                  PATCH  /me
 *                  DELETE /me
 *                  POST   /devices/register
 *                  POST   /devices/unregister
 *                  GET    /favorites
 *                  POST   /favorites
 *                  DELETE /favorites/{id}
 *                  GET    /notifications/preferences
 *                  PATCH  /notifications/preferences
 */
class ML_App_Base_Core_REST_API {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'ml-app-base/v1';

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public static function register_routes() {

		// --- App config (public) ---
		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Auth: register (public) ---
		register_rest_route(
			self::NAMESPACE,
			'/auth/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'ML_App_Base_Core_Auth', 'register' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Auth: login (public) ---
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'ML_App_Base_Core_Auth', 'login' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Auth: logout (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/auth/logout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'ML_App_Base_Core_Auth', 'logout' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Me: get / update / delete profile (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'ML_App_Base_Core_Auth', 'get_me' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( 'ML_App_Base_Core_Auth', 'update_me' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( 'ML_App_Base_Core_Auth', 'delete_me' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// --- Devices: register (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/devices/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'register_device' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Devices: unregister (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/devices/unregister',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'unregister_device' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- Favorites: list + add (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/favorites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_favorites' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'add_favorite' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// --- Favorites: delete by ID (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/favorites/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'remove_favorite' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// --- Notifications: preferences get / update (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/notifications/preferences',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_notification_preferences' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( __CLASS__, 'update_notification_preferences' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// --- Notifications: in-app inbox (authenticated) ---
		register_rest_route(
			self::NAMESPACE,
			'/notifications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_notifications' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications/unread-count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_notifications_unread_count' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications/(?P<id>\d+)/read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'mark_notification_read' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications/read-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'mark_all_notifications_read' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- App cardápio/menu (public) ---
		$menu_routes = array( '/menu', '/cardapio', '/cardapios' );
		foreach ( $menu_routes as $route ) {
			register_rest_route(
				self::NAMESPACE,
				$route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_menu' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/menu/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_menu_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/menu/home',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_menu_home' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/menu/featured',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_menu_featured' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Route handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /config
	 * Returns app configuration. No sensitive data.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_config( WP_REST_Request $request ) {
		$data = array(
			'app_name'          => 'App Base',
			'organization'      => 'G.R.E.S. App Base',
			'api_version'       => 'v1',
			'plugin_version'    => ML_APP_BASE_CORE_VERSION,
			'privacy_url'       => 'https://app.example.com/politica-de-privacidade',
			'terms_url'         => 'https://app.example.com/termos-de-uso',
			'support_whatsapp'  => '', // Placeholder — set in WordPress options when configured.
			'features'          => array(
				'registration'        => true,
				'push_notifications'  => true,
				'birthday_messages'   => true,
				'favorites'           => true,
			),
		);

		return ML_App_Base_Core_Response::success( $data );
	}

	/**
	 * GET /menu, /cardapio, /cardapios.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_menu( WP_REST_Request $request ) {
		return ML_App_Base_Core_Response::success( ML_App_Base_Core_Menu::get_api_payload() );
	}

	/**
	 * GET /menu/categories.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_menu_categories( WP_REST_Request $request ) {
		$payload = ML_App_Base_Core_Menu::get_api_payload();
		return ML_App_Base_Core_Response::success(
			array(
				'categories'       => isset( $payload['categories'] ) ? $payload['categories'] : array(),
				'cardapios'        => isset( $payload['cardapios'] ) ? $payload['cardapios'] : array(),
				'menus'            => isset( $payload['menus'] ) ? $payload['menus'] : array(),
				'sections'         => isset( $payload['sections'] ) ? $payload['sections'] : array(),
				'category_cards'   => isset( $payload['category_cards'] ) ? $payload['category_cards'] : array(),
				'home_categories'  => isset( $payload['home_categories'] ) ? $payload['home_categories'] : array(),
				'flat_categories'  => ML_App_Base_Core_Menu::get_api_categories(),
				'counts'           => isset( $payload['counts'] ) ? $payload['counts'] : array(),
			)
		);
	}

	/**
	 * GET /menu/home.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_menu_home( WP_REST_Request $request ) {
		$payload = ML_App_Base_Core_Menu::get_api_payload();
		return ML_App_Base_Core_Response::success( isset( $payload['home'] ) ? $payload['home'] : array() );
	}

	/**
	 * GET /menu/featured.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_menu_featured( WP_REST_Request $request ) {
		$payload = ML_App_Base_Core_Menu::get_api_payload();
		return ML_App_Base_Core_Response::success(
			array(
				'featured'       => isset( $payload['featured'] ) ? $payload['featured'] : array(),
				'featured_items' => isset( $payload['featured_items'] ) ? $payload['featured_items'] : array(),
				'promotions'     => isset( $payload['promotions'] ) ? $payload['promotions'] : array(),
			)
		);
	}

	/**
	 * POST /devices/register
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function register_device( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$params      = $request->get_json_params();
		$device_uuid = '';
		if ( isset( $params['device_uuid'] ) ) {
			$device_uuid = sanitize_text_field( $params['device_uuid'] );
		} elseif ( isset( $params['device_id'] ) ) {
			$device_uuid = sanitize_text_field( $params['device_id'] );
		}
		$platform = isset( $params['platform'] ) ? sanitize_text_field( strtolower( $params['platform'] ) ) : 'unknown';

		if ( empty( $device_uuid ) ) {
			return ML_App_Base_Core_Response::error( 'device_uuid_required', __( 'device_uuid or device_id is required.', 'ml-app-base-core' ), 400 );
		}

		if ( ! in_array( $platform, array( 'android', 'ios', 'web', 'unknown' ), true ) ) {
			$platform = 'unknown';
		}

		$expo_push_token = null;
		if ( isset( $params['fcm_token'] ) && '' !== trim( (string) $params['fcm_token'] ) ) {
			$expo_push_token = $params['fcm_token'];
		} elseif ( isset( $params['push_token'] ) ) {
			$expo_push_token = $params['push_token'];
		} elseif ( isset( $params['expo_push_token'] ) ) {
			$expo_push_token = $params['expo_push_token'];
		}

		$device_id = ML_App_Base_Core_Devices_Repository::upsert( $user->id, array(
			'device_uuid'     => $device_uuid,
			'platform'        => $platform,
			'expo_push_token' => $expo_push_token,
			'app_version'     => isset( $params['app_version'] ) ? $params['app_version'] : null,
			'push_enabled'    => isset( $params['push_enabled'] ) ? (bool) $params['push_enabled'] : true,
		) );

		if ( ! $device_id ) {
			return ML_App_Base_Core_Response::server_error();
		}

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Device registered successfully.', 'ml-app-base-core' ), 'device_id' => (int) $device_id ) );
	}

	/**
	 * POST /devices/unregister
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function unregister_device( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$params      = $request->get_json_params();
		$device_uuid = isset( $params['device_uuid'] ) ? sanitize_text_field( $params['device_uuid'] ) : '';

		if ( empty( $device_uuid ) ) {
			return ML_App_Base_Core_Response::error( 'device_uuid_required', __( 'device_uuid is required.', 'ml-app-base-core' ), 400 );
		}

		ML_App_Base_Core_Devices_Repository::unregister( $user->id, $device_uuid );

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Device unregistered successfully.', 'ml-app-base-core' ) ) );
	}

	/**
	 * GET /favorites
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_favorites( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$item_type = sanitize_text_field( $request->get_param( 'item_type' ) ?? '' );

		$result = ML_App_Base_Core_Favorites_Repository::get_for_user( $user->id, $page, $per_page, $item_type );

		return ML_App_Base_Core_Response::paginated( $result['items'], $page, $per_page, $result['total'] );
	}

	/**
	 * POST /favorites
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function add_favorite( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$params    = $request->get_json_params();
		$item_type = isset( $params['item_type'] ) ? sanitize_text_field( $params['item_type'] ) : '';
		$item_id   = isset( $params['item_id'] ) ? sanitize_text_field( $params['item_id'] ) : '';

		if ( ! in_array( $item_type, ML_App_Base_Core_Favorites_Repository::ALLOWED_TYPES, true ) ) {
			return ML_App_Base_Core_Response::error(
				'invalid_item_type',
				/* translators: %s: Allowed types list */
				sprintf( __( 'item_type must be one of: %s.', 'ml-app-base-core' ), implode( ', ', ML_App_Base_Core_Favorites_Repository::ALLOWED_TYPES ) ),
				400
			);
		}

		if ( empty( $item_id ) ) {
			return ML_App_Base_Core_Response::error( 'item_id_required', __( 'item_id is required.', 'ml-app-base-core' ), 400 );
		}

		$fav_id = ML_App_Base_Core_Favorites_Repository::add(
			$user->id,
			$item_type,
			$item_id,
			isset( $params['item_title'] ) ? $params['item_title'] : '',
			isset( $params['item_url'] ) ? $params['item_url'] : ''
		);

		if ( ! $fav_id ) {
			return ML_App_Base_Core_Response::server_error();
		}

		$fav = ML_App_Base_Core_Favorites_Repository::find( $fav_id, $user->id );

		return ML_App_Base_Core_Response::success( $fav, 201 );
	}

	/**
	 * DELETE /favorites/{id}
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function remove_favorite( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$id      = (int) $request->get_param( 'id' );
		$deleted = ML_App_Base_Core_Favorites_Repository::remove( $id, $user->id );

		if ( ! $deleted ) {
			return ML_App_Base_Core_Response::not_found( __( 'Favorite not found or does not belong to this user.', 'ml-app-base-core' ) );
		}

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Favorite removed.', 'ml-app-base-core' ) ) );
	}

	/**
	 * GET /notifications/preferences
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_notification_preferences( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$consent = ML_App_Base_Core_Consents_Repository::get_for_user( $user->id );

		return ML_App_Base_Core_Response::success( ML_App_Base_Core_Consents_Repository::format_public( $consent ) );
	}

	/**
	 * PATCH /notifications/preferences
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function update_notification_preferences( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$params = $request->get_json_params();
		$allowed = array( 'push_enabled', 'birthday_enabled', 'events_enabled', 'news_enabled' );
		$prefs   = array();

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$prefs[ $key ] = (bool) $params[ $key ];
			}
		}

		if ( empty( $prefs ) ) {
			return ML_App_Base_Core_Response::error( 'no_fields', __( 'No valid preference fields provided.', 'ml-app-base-core' ), 400 );
		}

		ML_App_Base_Core_Consents_Repository::update( $user->id, $prefs );

		$consent = ML_App_Base_Core_Consents_Repository::get_for_user( $user->id );

		return ML_App_Base_Core_Response::success( ML_App_Base_Core_Consents_Repository::format_public( $consent ) );
	}

	/**
	 * GET /notifications — list in-app notifications for the authenticated user.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_notifications( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$result   = ML_App_Base_Core_Notifications_Repository::get_for_user( $user->id, $page, $per_page );

		return ML_App_Base_Core_Response::paginated( $result['items'], $page, $per_page, $result['total'] );
	}

	/**
	 * GET /notifications/unread-count — unread notification count.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_notifications_unread_count( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		return ML_App_Base_Core_Response::success( array( 'unread_count' => ML_App_Base_Core_Notifications_Repository::get_unread_count( $user->id ) ) );
	}

	/**
	 * POST /notifications/{id}/read — mark one notification read.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function mark_notification_read( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		$id     = absint( $request->get_param( 'id' ) );
		$result = ML_App_Base_Core_Notifications_Repository::mark_read( $id, $user->id );

		if ( ! $result ) {
			return ML_App_Base_Core_Response::not_found( __( 'Notification not found or does not belong to this user.', 'ml-app-base-core' ) );
		}

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'Notification marked as read.', 'ml-app-base-core' ) ) );
	}

	/**
	 * POST /notifications/read-all — mark all notifications read.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function mark_all_notifications_read( WP_REST_Request $request ) {
		$user = ML_App_Base_Core_Auth::authenticate( $request );
		if ( $user instanceof WP_REST_Response ) {
			return $user;
		}

		ML_App_Base_Core_Notifications_Repository::mark_all_read( $user->id );

		return ML_App_Base_Core_Response::success( array( 'message' => __( 'All notifications marked as read.', 'ml-app-base-core' ) ) );
	}

}
