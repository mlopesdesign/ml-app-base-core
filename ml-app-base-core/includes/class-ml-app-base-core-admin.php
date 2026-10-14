<?php
/**
 * Admin page — WordPress admin interface for ML App Base Core.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_App_Base_Core_Admin {

	const CRON_HOOK = 'ml_app_base_core_send_scheduled_notification';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'wp_ajax_ml_app_base_core_update_order', array( __CLASS__, 'handle_ajax_update_order' ) );
	}

	public static function add_menu_page() {
		add_menu_page(
			esc_html__( 'ML App Base', 'ml-app-base-core' ),
			esc_html__( 'ML App Base', 'ml-app-base-core' ),
			'manage_options',
			'ml-app-base-core',
			array( __CLASS__, 'render_page' ),
			'dashicons-smartphone',
			21
		);

		add_submenu_page(
			'ml-app-base-core',
			esc_html__( 'Cardápios', 'ml-app-base-core' ),
			esc_html__( 'Cardápios', 'ml-app-base-core' ),
			'manage_options',
			'ml-app-base-core-cardapios',
			array( __CLASS__, 'render_cardapios_page' )
		);

		add_submenu_page(
			'ml-app-base-core',
			esc_html__( 'Categorias do Cardápio', 'ml-app-base-core' ),
			esc_html__( 'Categorias', 'ml-app-base-core' ),
			'manage_options',
			'ml-app-base-core-categories',
			array( __CLASS__, 'render_categories_page' )
		);

		add_submenu_page(
			'ml-app-base-core',
			esc_html__( 'Itens do Cardápio', 'ml-app-base-core' ),
			esc_html__( 'Itens do Cardápio', 'ml-app-base-core' ),
			'manage_options',
			'ml-app-base-core-items',
			array( __CLASS__, 'render_items_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		// O prefixo do hook dos submenus deriva do titulo do menu ("ML App Base" ->
		// "ml-app-base_page_..."); antes a lista tinha "mlab-app_page_..." e os estilos nao
		// carregavam nas paginas de Cardapios/Categorias/Itens.
		$is_main = 'toplevel_page_ml-app-base-core' === $hook;
		$is_sub  = (bool) preg_match( '/_page_ml-app-base-core-(cardapios|categories|items)$/', (string) $hook );
		if ( ! $is_main && ! $is_sub ) {
			return;
		}

		wp_enqueue_style(
			'ml-app-base-core-admin',
			ML_APP_BASE_CORE_URL . 'assets/admin.css',
			array(),
			ML_APP_BASE_CORE_VERSION
		);

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_add_inline_script(
			'jquery-ui-sortable',
			"jQuery(function($){\n" .
			"  $('.mlab-sortable-body').sortable({\n" .
			"    handle: '.mlab-drag-handle',\n" .
			"    axis: 'y',\n" .
			"    helper: function(e, ui){ ui.children().each(function(){ $(this).width($(this).width()); }); return ui; },\n" .
			"    update: function(){\n" .
			"      var tbody = $(this);\n" .
			"      var ids = tbody.children('tr').map(function(){ return $(this).data('record-id'); }).get();\n" .
			"      tbody.addClass('mlab-saving-order');\n" .
			"      $.post(ajaxurl, { action: 'ml_app_base_core_update_order', nonce: '" . esc_js( wp_create_nonce( 'ml_app_base_order' ) ) . "', type: tbody.data('sort-type'), order: ids })\n" .
			"        .always(function(){ tbody.removeClass('mlab-saving-order'); });\n" .
			"    }\n" .
			"  });\n" .
			"  $('.mlab-active-menu-radio').on('change', function(){ if (this.checked) { $(this).closest('form').trigger('submit'); } });\n" .
			"  $('#mlab-select-push-image').on('click', function(e){ e.preventDefault(); var frame = wp.media({ title: 'Selecionar imagem para notificação', button: { text: 'Usar esta imagem' }, multiple: false }); frame.on('select', function(){ var attachment = frame.state().get('selection').first().toJSON(); $('#push_image_url').val(attachment.url || ''); $('#mlab-push-image-preview img').attr('src', attachment.url || ''); $('#mlab-push-image-preview').toggle(!!attachment.url); }); frame.open(); });\n" .
			"  $('#mlab-clear-push-image').on('click', function(e){ e.preventDefault(); $('#push_image_url').val(''); $('#mlab-push-image-preview img').attr('src', ''); $('#mlab-push-image-preview').hide(); });\n" .
			"  $('#push_image_url').on('input', function(){ var url = $(this).val(); $('#mlab-push-image-preview img').attr('src', url); $('#mlab-push-image-preview').toggle(!!url); });\n" .
			"});"
		);
	}

	public static function handle_ajax_update_order() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ml_app_base_order' ) ) {
			wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
		}

		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();

		if ( empty( $order ) || ! in_array( $type, array( 'menus', 'categories', 'items' ), true ) ) {
			wp_send_json_error( array( 'message' => 'invalid_order' ), 400 );
		}

		$result = ML_App_Base_Core_Menu::update_order( $type, $order );
		wp_send_json_success( array( 'updated' => (bool) $result ) );
	}

	public static function handle_actions() {
		if ( ! isset( $_POST['ml_app_base_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ml_app_base_nonce'] ) ), 'ml_app_base_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( 'save_push' === $action ) {
			self::handle_push_form();
		}

		if ( 'save_firebase_settings' === $action ) {
			self::handle_save_firebase_settings();
		}


		if ( 'delete_notification' === $action ) {
			self::handle_delete_notification();
		}

		if ( 'delete_notification_log' === $action ) {
			self::handle_delete_notification_log();
		}

		if ( 'send_fcm_debug_test' === $action ) {
			self::handle_send_fcm_debug_test();
		}

		if ( 'reset_fcm_debugger' === $action ) {
			self::handle_reset_fcm_debugger();
		}

		if ( 'reset_push_logs' === $action ) {
			self::handle_reset_push_logs();
		}

		if ( 'reset_notification_history' === $action ) {
			self::handle_reset_notification_history();
		}

		if ( 'block_user' === $action ) {
			self::handle_block_user();
		}

		if ( 'unblock_user' === $action ) {
			self::handle_unblock_user();
		}

		if ( 'delete_user' === $action ) {
			self::handle_delete_user();
		}

		if ( 'block_email' === $action ) {
			self::handle_block_email();
		}

		if ( 'unblock_email' === $action ) {
			self::handle_unblock_email();
		}

		if ( 'save_app_menu' === $action ) {
			self::handle_save_app_menu();
		}

		if ( 'delete_app_menu' === $action ) {
			self::handle_delete_app_menu();
		}

		if ( 'activate_app_menu' === $action ) {
			self::handle_activate_app_menu();
		}

		if ( 'save_cardapio' === $action ) {
			self::handle_save_cardapio();
		}

		if ( 'delete_cardapio' === $action ) {
			self::handle_delete_cardapio();
		}

		if ( 'save_menu_item' === $action ) {
			self::handle_save_menu_item();
		}

		if ( 'delete_menu_item' === $action ) {
			self::handle_delete_menu_item();
		}
	}

	private static function handle_block_user() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			self::redirect_users( array( 'user_error' => 'invalid_user' ) );
		}

		$user = ML_App_Base_Core_Users_Repository::find_by_id( $user_id );
		if ( ! $user ) {
			self::redirect_users( array( 'user_error' => 'not_found' ) );
		}

		ML_App_Base_Core_Users_Repository::set_status( $user_id, 'blocked' );
		ML_App_Base_Core_Users_Repository::block_email( $user->email, 'Bloqueado pelo painel administrativo.' );
		ML_App_Base_Core_Token_Service::revoke_all_user_sessions( $user_id );
		ML_App_Base_Core_Devices_Repository::delete_all_for_user( $user_id );

		self::redirect_users( array( 'blocked_user' => 1 ) );
	}

	private static function handle_unblock_user() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			self::redirect_users( array( 'user_error' => 'invalid_user' ) );
		}

		$user = ML_App_Base_Core_Users_Repository::find_by_id( $user_id );
		if ( ! $user ) {
			self::redirect_users( array( 'user_error' => 'not_found' ) );
		}

		ML_App_Base_Core_Users_Repository::set_status( $user_id, 'active' );
		ML_App_Base_Core_Users_Repository::unblock_email( $user->email );

		self::redirect_users( array( 'unblocked_user' => 1 ) );
	}

	private static function handle_delete_user() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			self::redirect_users( array( 'user_error' => 'invalid_user' ) );
		}

		$user = ML_App_Base_Core_Users_Repository::find_by_id( $user_id );
		if ( ! $user ) {
			self::redirect_users( array( 'user_error' => 'not_found' ) );
		}

		$block_email = ! empty( $_POST['block_email_too'] );
		if ( $block_email ) {
			ML_App_Base_Core_Users_Repository::block_email( $user->email, 'Bloqueado durante exclusão administrativa.' );
		}

		ML_App_Base_Core_Token_Service::revoke_all_user_sessions( $user_id );
		ML_App_Base_Core_Devices_Repository::delete_all_for_user( $user_id );
		ML_App_Base_Core_Favorites_Repository::delete_all_for_user( $user_id );
		ML_App_Base_Core_Consents_Repository::delete_for_user( $user_id );
		ML_App_Base_Core_Users_Repository::admin_delete( $user_id );

		self::redirect_users( array( 'deleted_user' => 1 ) );
	}

	private static function handle_block_email() {
		$email  = isset( $_POST['blocked_email'] ) ? sanitize_email( wp_unslash( $_POST['blocked_email'] ) ) : '';
		$reason = isset( $_POST['blocked_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_reason'] ) ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			self::redirect_users( array( 'user_error' => 'invalid_email' ) );
		}

		ML_App_Base_Core_Users_Repository::block_email( $email, $reason );
		self::redirect_users( array( 'blocked_email' => 1 ) );
	}

	private static function handle_unblock_email() {
		$email = isset( $_POST['blocked_email'] ) ? sanitize_email( wp_unslash( $_POST['blocked_email'] ) ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			self::redirect_users( array( 'user_error' => 'invalid_email' ) );
		}

		ML_App_Base_Core_Users_Repository::unblock_email( $email );
		self::redirect_users( array( 'unblocked_email' => 1 ) );
	}


	private static function handle_delete_notification() {
		global $wpdb;

		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		if ( ! $notification_id ) {
			self::redirect_notifications( array( 'error' => 'invalid_notification' ) );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $notification_id ) );

		$notifications_table      = ML_App_Base_Core_Database::table( 'ml_app_base_notifications' );
		$user_notifications_table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );

		if ( self::table_exists( $user_notifications_table ) ) {
			$wpdb->delete( $user_notifications_table, array( 'notification_id' => $notification_id ), array( '%d' ) );
		}

		if ( self::table_exists( $notifications_table ) ) {
			$wpdb->delete( $notifications_table, array( 'id' => $notification_id ), array( '%d' ) );
		}

		self::redirect_notifications( array( 'deleted_notification' => 1 ) );
	}

	private static function handle_delete_notification_log() {
		global $wpdb;

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		if ( ! $log_id ) {
			self::redirect_notifications( array( 'error' => 'invalid_log' ) );
		}

		$logs_table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_logs' );
		if ( self::table_exists( $logs_table ) ) {
			$wpdb->delete( $logs_table, array( 'id' => $log_id ), array( '%d' ) );
		}

		self::redirect_notifications( array( 'deleted_log' => 1 ) );
	}

	private static function handle_send_fcm_debug_test() {
		$token = self::get_latest_fcm_token();
		if ( '' === $token ) {
			self::redirect_settings( array( 'fcm_test' => 'no_fcm_token' ) );
		}

		$result = ML_App_Base_Core_Push_Service::send(
			$token,
			'Teste FCM App Base',
			'Teste técnico de push nativo Firebase.',
			array(
				'type'   => 'debug',
				'source' => 'ml_app_base_core_debugger',
			)
		);

		set_transient( 'ml_app_base_core_last_fcm_debug_result', $result, 15 * MINUTE_IN_SECONDS );

		self::insert_notification_log(
			'Teste FCM App Base',
			'Teste técnico de push nativo Firebase.',
			'debug',
			array(
				'success_count' => ! empty( $result['success'] ) ? 1 : 0,
				'failure_count' => empty( $result['success'] ) ? 1 : 0,
				'last_error'    => isset( $result['error'] ) ? $result['error'] : '',
				'fcm_count'     => 1,
				'expo_count'    => 0,
				'details'       => array( $result ),
			)
		);

		self::redirect_settings( array( 'fcm_test' => ! empty( $result['success'] ) ? 'success' : 'failed' ) );
	}

	private static function handle_reset_fcm_debugger() {
		delete_transient( 'ml_app_base_core_last_fcm_debug_result' );
		self::redirect_settings( array( 'fcm_debug_reset' => 1 ) );
	}

	private static function handle_reset_push_logs() {
		global $wpdb;
		$logs_table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_logs' );
		if ( self::table_exists( $logs_table ) ) {
			$wpdb->query( "DELETE FROM `{$logs_table}`" );
		}
		delete_transient( 'ml_app_base_core_last_fcm_debug_result' );
		self::redirect_settings( array( 'push_logs_reset' => 1 ) );
	}

	private static function get_latest_fcm_token() {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		if ( ! self::table_exists( $table ) ) {
			return '';
		}

		$token = $wpdb->get_var(
			"SELECT expo_push_token FROM `{$table}` WHERE deleted_at IS NULL AND push_enabled = 1 AND expo_push_token IS NOT NULL AND expo_push_token <> '' AND expo_push_token NOT LIKE 'ExponentPushToken[%' AND expo_push_token NOT LIKE 'ExpoPushToken[%' ORDER BY last_seen_at DESC, id DESC LIMIT 1"
		);

		return trim( (string) $token );
	}

	private static function handle_reset_notification_history() {
		global $wpdb;

		$notifications_table      = ML_App_Base_Core_Database::table( 'ml_app_base_notifications' );
		$logs_table               = ML_App_Base_Core_Database::table( 'ml_app_base_notification_logs' );
		$user_notifications_table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );

		$notification_ids = array();
		if ( self::table_exists( $notifications_table ) ) {
			$notification_ids = array_map( 'absint', (array) $wpdb->get_col( "SELECT id FROM `{$notifications_table}`" ) );
		}

		foreach ( $notification_ids as $notification_id ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $notification_id ) );
		}

		$deleted = array(
			'notifications'      => 0,
			'logs'               => 0,
			'user_notifications' => 0,
		);

		if ( self::table_exists( $user_notifications_table ) ) {
			$deleted['user_notifications'] = (int) $wpdb->query( "DELETE FROM `{$user_notifications_table}`" );
		}

		if ( self::table_exists( $logs_table ) ) {
			$deleted['logs'] = (int) $wpdb->query( "DELETE FROM `{$logs_table}`" );
		}

		if ( self::table_exists( $notifications_table ) ) {
			$deleted['notifications'] = (int) $wpdb->query( "DELETE FROM `{$notifications_table}`" );
		}

		self::redirect_notifications(
			array(
				'reset'             => 1,
				'notifications'     => $deleted['notifications'],
				'logs'              => $deleted['logs'],
				'user_notifications'=> $deleted['user_notifications'],
			)
		);
	}



	private static function handle_save_firebase_settings() {
		$project_id = isset( $_POST['firebase_project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['firebase_project_id'] ) ) : '';
		$service_account_json = isset( $_POST['firebase_service_account_json'] ) ? wp_unslash( $_POST['firebase_service_account_json'] ) : '';
		$enabled = ! empty( $_POST['firebase_enabled'] );

		$result = ML_App_Base_Core_Push_Service::save_firebase_settings( $project_id, $service_account_json, $enabled );
		if ( is_wp_error( $result ) ) {
			self::redirect_settings( array( 'firebase_error' => rawurlencode( $result->get_error_message() ) ) );
		}

		self::redirect_settings( array( 'firebase_saved' => 1 ) );
	}

	private static function handle_push_form() {
		$title       = isset( $_POST['push_title'] ) ? sanitize_text_field( wp_unslash( $_POST['push_title'] ) ) : '';
		$body        = isset( $_POST['push_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['push_message'] ) ) : '';
		$target_type = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : 'home';
		$target_id   = isset( $_POST['target_id'] ) ? sanitize_text_field( wp_unslash( $_POST['target_id'] ) ) : '';
		$image_url   = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$audience    = isset( $_POST['audience'] ) ? sanitize_key( wp_unslash( $_POST['audience'] ) ) : 'all';
		$send_mode   = isset( $_POST['send_mode'] ) ? sanitize_key( wp_unslash( $_POST['send_mode'] ) ) : 'send_now';
		$scheduled_date = isset( $_POST['scheduled_date'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_date'] ) ) : '';
		$scheduled_time = isset( $_POST['scheduled_time'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_time'] ) ) : '';
		$recurrence  = isset( $_POST['recurrence'] ) ? sanitize_key( wp_unslash( $_POST['recurrence'] ) ) : 'none';
		$repeat_total = isset( $_POST['repeat_total'] ) ? max( 1, min( 30, absint( $_POST['repeat_total'] ) ) ) : 1;

		$allowed_targets = array( 'home', 'event', 'news', 'about', 'custom_url' );
		if ( ! in_array( $target_type, $allowed_targets, true ) ) {
			$target_type = 'home';
		}

		$allowed_audiences = array( 'all', 'favorites_only' );
		if ( ! in_array( $audience, $allowed_audiences, true ) ) {
			$audience = 'all';
		}

		$allowed_recurrence = array( 'none', 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $recurrence, $allowed_recurrence, true ) ) {
			$recurrence = 'none';
		}

		if ( 'none' === $recurrence ) {
			$repeat_total = 1;
		}

		if ( '' === $title || '' === $body ) {
			self::redirect_notifications( array( 'error' => 'missing_fields' ) );
		}

		if ( 'favorites_only' === $audience && ( '' === $target_id || ! in_array( $target_type, array( 'event', 'news' ), true ) ) ) {
			self::redirect_notifications( array( 'error' => 'favorites_requires_target' ) );
		}

		if ( 'schedule' === $send_mode ) {
			$timestamp = self::parse_local_datetime_to_timestamp( $scheduled_date, $scheduled_time );
			if ( ! $timestamp || $timestamp <= time() ) {
				self::redirect_notifications( array( 'error' => 'invalid_schedule' ) );
			}

			$notification_id = self::insert_notification_record(
				array(
					'title'        => $title,
					'body'         => $body,
					'target_type'  => $target_type,
					'target_id'    => $target_id,
					'image_url'    => $image_url,
					'audience'     => $audience,
					'send_mode'    => 'schedule',
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', $timestamp ),
					'recurrence'   => $recurrence,
					'repeat_total' => $repeat_total,
					'status'       => 'scheduled',
				)
			);

			if ( ! $notification_id ) {
				self::redirect_notifications( array( 'error' => 'schedule_save_failed' ) );
			}

			wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( (int) $notification_id ) );
			self::redirect_notifications( array( 'scheduled' => 1, 'notification_id' => (int) $notification_id ) );
		}

		$result = self::send_push_to_audience( $title, $body, $target_type, $target_id, $audience, $image_url );

		$notification_id = self::insert_notification_record(
			array(
				'title'         => $title,
				'body'          => $body,
				'target_type'   => $target_type,
				'target_id'     => $target_id,
				'image_url'     => $image_url,
				'audience'      => $audience,
				'send_mode'     => 'send_now',
				'scheduled_at'  => null,
				'recurrence'    => 'none',
				'repeat_total'  => 1,
				'repeats_sent'  => 1,
				'success_count' => $result['success_count'],
				'failure_count' => $result['failure_count'],
				'last_error'    => $result['last_error'],
				'last_sent_at'  => current_time( 'mysql', true ),
				'status'        => $result['failure_count'] > 0 && 0 === $result['success_count'] ? 'failed' : 'sent',
			)
		);

		if ( $notification_id ) {
			ML_App_Base_Core_Notifications_Repository::create_for_audience( $notification_id, $title, $body, $target_type, $target_id, $audience, $image_url );
		}

		self::insert_notification_log( $title, $body, $target_type, $result );
		self::redirect_notifications( array( 'success' => 1, 'sent' => $result['success_count'], 'failed' => $result['failure_count'], 'fcm' => isset( $result['fcm_count'] ) ? (int) $result['fcm_count'] : 0, 'expo' => isset( $result['expo_count'] ) ? (int) $result['expo_count'] : 0 ) );
	}

	public static function send_scheduled_notification( $notification_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notifications' );
		$notification = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", (int) $notification_id )
		);

		if ( ! $notification || 'scheduled' !== $notification->status ) {
			return;
		}

		$wpdb->update(
			$table,
			array( 'status' => 'sending', 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $notification->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$result = self::send_push_to_audience( $notification->title, $notification->body, $notification->target_type, $notification->target_id, $notification->audience, isset( $notification->image_url ) ? (string) $notification->image_url : '' );
		self::insert_notification_log( $notification->title, $notification->body, $notification->target_type, $result );
		ML_App_Base_Core_Notifications_Repository::create_for_audience(
			(int) $notification->id,
			$notification->title,
			$notification->body,
			$notification->target_type,
			$notification->target_id,
			$notification->audience,
			isset( $notification->image_url ) ? (string) $notification->image_url : ''
		);

		$repeats_sent = (int) $notification->repeats_sent + 1;
		$repeat_total = max( 1, (int) $notification->repeat_total );
		$next_timestamp = self::next_recurrence_timestamp( $notification->scheduled_at, $notification->recurrence );
		$has_next = 'none' !== $notification->recurrence && $repeats_sent < $repeat_total && $next_timestamp;

		$update = array(
			'repeats_sent'  => $repeats_sent,
			'success_count' => (int) $notification->success_count + (int) $result['success_count'],
			'failure_count' => (int) $notification->failure_count + (int) $result['failure_count'],
			'last_error'    => $result['last_error'],
			'last_sent_at'  => current_time( 'mysql', true ),
			'updated_at'    => current_time( 'mysql' ),
			'status'        => $has_next ? 'scheduled' : ( $result['failure_count'] > 0 && 0 === $result['success_count'] ? 'failed' : 'sent' ),
		);

		if ( $has_next ) {
			$update['scheduled_at'] = gmdate( 'Y-m-d H:i:s', $next_timestamp );
			wp_schedule_single_event( $next_timestamp, self::CRON_HOOK, array( (int) $notification->id ) );
		}

		$wpdb->update( $table, $update, array( 'id' => (int) $notification->id ) );
	}

	private static function parse_local_datetime_to_timestamp( $date, $time ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return false;
		}

		$timezone = wp_timezone();
		$dt = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $timezone );
		if ( ! $dt ) {
			return false;
		}

		return $dt->getTimestamp();
	}

	private static function next_recurrence_timestamp( $scheduled_at, $recurrence ) {
		if ( 'none' === $recurrence ) {
			return false;
		}

		$timestamp = strtotime( $scheduled_at . ' UTC' );
		if ( ! $timestamp ) {
			return false;
		}

		if ( 'daily' === $recurrence ) {
			return strtotime( '+1 day', $timestamp );
		}

		if ( 'weekly' === $recurrence ) {
			return strtotime( '+1 week', $timestamp );
		}

		if ( 'monthly' === $recurrence ) {
			return strtotime( '+1 month', $timestamp );
		}

		return false;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	private static function count_internal_notifications( $notification_id ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_user_notifications' );
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE notification_id = %d",
				(int) $notification_id
			)
		);
	}


	private static function insert_notification_record( array $data ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notifications' );
		$now = current_time( 'mysql' );

		$payload = array(
			'title'         => $data['title'] ?? '',
			'body'          => $data['body'] ?? '',
			'target_type'   => $data['target_type'] ?? 'home',
			'target_id'     => $data['target_id'] ?? '',
			'image_url'     => isset( $data['image_url'] ) ? esc_url_raw( (string) $data['image_url'] ) : '',
			'audience'      => $data['audience'] ?? 'all',
			'send_mode'     => $data['send_mode'] ?? 'send_now',
			'scheduled_at'  => $data['scheduled_at'] ?? null,
			'recurrence'    => $data['recurrence'] ?? 'none',
			'repeat_total'  => $data['repeat_total'] ?? 1,
			'repeats_sent'  => $data['repeats_sent'] ?? 0,
			'status'        => $data['status'] ?? 'created',
			'last_sent_at'  => $data['last_sent_at'] ?? null,
			'success_count' => $data['success_count'] ?? 0,
			'failure_count' => $data['failure_count'] ?? 0,
			'last_error'    => $data['last_error'] ?? '',
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$result = $wpdb->insert(
			$table,
			$payload,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	private static function insert_notification_log( $title, $body, $notification_type, array $result ) {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_logs' );

		$wpdb->insert(
			$table,
			array(
				'notification_type' => sanitize_text_field( $notification_type ),
				'title'             => sanitize_text_field( $title ),
				'body'              => sanitize_textarea_field( $body ),
				'status'            => $result['failure_count'] > 0 && 0 === $result['success_count'] ? 'failed' : 'sent',
				'provider_response' => wp_json_encode(
					array(
						'success_count' => (int) $result['success_count'],
						'failure_count' => (int) $result['failure_count'],
						'last_error'    => isset( $result['last_error'] ) ? $result['last_error'] : '',
						'expo_count'    => isset( $result['expo_count'] ) ? (int) $result['expo_count'] : 0,
						'fcm_count'     => isset( $result['fcm_count'] ) ? (int) $result['fcm_count'] : 0,
						'details'       => isset( $result['details'] ) ? $result['details'] : array(),
					)
				),
				'created_at'        => current_time( 'mysql' ),
				'sent_at'           => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private static function send_push_to_audience( $title, $body, $target_type, $target_id, $audience, $image_url = '' ) {
		$tokens = self::get_tokens_for_audience( $audience, $target_type, $target_id );

		if ( empty( $tokens ) ) {
			return array( 'success_count' => 0, 'failure_count' => 0, 'last_error' => 'Nenhum dispositivo cadastrado com token válido.', 'expo_count' => 0, 'fcm_count' => 0 );
		}

		$target_url = self::derive_target_url( $target_type, $target_id );
		$image_url  = esc_url_raw( (string) $image_url );
		$data       = array(
			'type'               => $target_type,
			'target_type'        => $target_type,
			'id'                 => $target_id,
			'target_id'          => $target_id,
			'target_url'         => $target_url,
			'title'              => $title,
			'message'            => $body,
			'body'               => $body,
			'image_url'          => $image_url,
			'image'              => $image_url,
			'notification_title' => $title,
			'source'             => 'ml_app_base_core',
		);

		$result = ML_App_Base_Core_Push_Service::send_many( array_unique( array_filter( $tokens ) ), $title, $body, $data );

		return array(
			'success_count' => (int) $result['success_count'],
			'failure_count' => (int) $result['failure_count'],
			'last_error'    => isset( $result['last_error'] ) ? $result['last_error'] : '',
			'expo_count'    => isset( $result['expo_count'] ) ? (int) $result['expo_count'] : 0,
			'fcm_count'     => isset( $result['fcm_count'] ) ? (int) $result['fcm_count'] : 0,
			'details'       => isset( $result['details'] ) ? $result['details'] : array(),
		);
	}

	/**
	 * Derive a navigable target URL from the target fields.
	 *
	 * The admin field accepts either an internal ID or a full URL. When a URL is
	 * provided, expose it explicitly to the mobile app so notification taps open
	 * the intended post/link instead of a blank listing screen.
	 *
	 * @param string $target_type Notification target type.
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

	private static function get_tokens_for_audience( $audience, $target_type, $target_id ) {
		if ( 'favorites_only' !== $audience ) {
			return ML_App_Base_Core_Devices_Repository::get_all_tokens();
		}

		global $wpdb;
		$devices = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		$favorites = ML_App_Base_Core_Database::table( 'ml_app_base_favorites' );

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT d.expo_push_token
				 FROM `{$devices}` d
				 INNER JOIN `{$favorites}` f ON f.user_id = d.user_id
				 WHERE d.expo_push_token IS NOT NULL
				   AND d.push_enabled = 1
				   AND d.deleted_at IS NULL
				   AND f.item_type = %s
				   AND f.item_id = %s",
				$target_type,
				(string) $target_id
			)
		);
	}

	private static function redirect_notifications( array $args ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core', 'tab' => 'notificacoes' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_settings( array $args ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core', 'tab' => 'configuracoes' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_users( array $args ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core', 'tab' => 'usuarios' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function get_status_label( $status ) {
		$labels = array(
			'draft'     => 'Rascunho',
			'scheduled' => 'Agendada',
			'sending'   => 'Enviando',
			'sent'      => 'Enviada',
			'failed'    => 'Falhou',
			'created'   => 'Criada',
		);
		return $labels[ $status ] ?? $status;
	}

	private static function handle_save_app_menu() {
		$id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$saved_id = ML_App_Base_Core_Menu::save_menu(
			array(
				'id'          => $id,
				'title'       => isset( $_POST['menu_title'] ) ? wp_unslash( $_POST['menu_title'] ) : '',
				'slug'        => isset( $_POST['menu_slug'] ) ? wp_unslash( $_POST['menu_slug'] ) : '',
				'description' => isset( $_POST['menu_description'] ) ? wp_unslash( $_POST['menu_description'] ) : '',
				'status'      => isset( $_POST['menu_status'] ) ? wp_unslash( $_POST['menu_status'] ) : 'active',
				'order'       => isset( $_POST['menu_order'] ) ? wp_unslash( $_POST['menu_order'] ) : 0,
			)
		);

		if ( ! $saved_id ) {
			self::redirect_cardapios( array( 'error' => 'missing_menu' ) );
		}

		self::redirect_cardapios( array( 'updated' => 1 ) );
	}

	private static function handle_delete_app_menu() {
		$id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$result = ML_App_Base_Core_Menu::delete_menu( $id );
		if ( is_wp_error( $result ) ) {
			self::redirect_cardapios( array( 'error' => $result->get_error_code() ) );
		}
		self::redirect_cardapios( array( 'deleted' => $result ? 1 : 0 ) );
	}

	private static function handle_activate_app_menu() {
		$id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		$result = ML_App_Base_Core_Menu::activate_menu( $id );
		self::redirect_cardapios( array( 'activated' => $result ? 1 : 0 ) );
	}

	private static function handle_save_cardapio() {
		$id = isset( $_POST['cardapio_id'] ) ? absint( $_POST['cardapio_id'] ) : 0;
		$saved_id = ML_App_Base_Core_Menu::save_cardapio(
			array(
				'id'          => $id,
				'title'       => isset( $_POST['cardapio_title'] ) ? wp_unslash( $_POST['cardapio_title'] ) : '',
				'slug'        => isset( $_POST['cardapio_slug'] ) ? wp_unslash( $_POST['cardapio_slug'] ) : '',
				'menu_id'     => isset( $_POST['cardapio_menu_id'] ) ? wp_unslash( $_POST['cardapio_menu_id'] ) : 0,
				'parent_id'   => isset( $_POST['cardapio_parent_id'] ) ? wp_unslash( $_POST['cardapio_parent_id'] ) : 0,
				'description'  => isset( $_POST['cardapio_description'] ) ? wp_unslash( $_POST['cardapio_description'] ) : '',
				'image_id'     => isset( $_POST['cardapio_image_id'] ) ? wp_unslash( $_POST['cardapio_image_id'] ) : 0,
				'image_url'    => isset( $_POST['cardapio_image_url'] ) ? wp_unslash( $_POST['cardapio_image_url'] ) : '',
				'show_on_home' => ! empty( $_POST['cardapio_show_on_home'] ) ? 1 : 0,
				'status'       => isset( $_POST['cardapio_status'] ) ? wp_unslash( $_POST['cardapio_status'] ) : 'active',
				'order'        => isset( $_POST['cardapio_order'] ) ? wp_unslash( $_POST['cardapio_order'] ) : 0,
			)
		);

		if ( ! $saved_id ) {
			self::redirect_categories( array( 'error' => 'missing_cardapio' ) );
		}

		self::redirect_categories( array( 'updated' => 1 ) );
	}

	private static function handle_delete_cardapio() {
		$id     = isset( $_POST['cardapio_id'] ) ? absint( $_POST['cardapio_id'] ) : 0;
		$result = ML_App_Base_Core_Menu::delete_cardapio( $id );

		if ( is_wp_error( $result ) ) {
			self::redirect_categories( array( 'error' => $result->get_error_code() ) );
		}

		self::redirect_categories( array( 'deleted' => $result ? 1 : 0 ) );
	}

	private static function handle_save_menu_item() {
		$id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$saved_id = ML_App_Base_Core_Menu::save_item(
			array(
				'id'          => $id,
				'cardapio_id' => isset( $_POST['item_cardapio_id'] ) ? wp_unslash( $_POST['item_cardapio_id'] ) : 0,
				'title'       => isset( $_POST['item_title'] ) ? wp_unslash( $_POST['item_title'] ) : '',
				'description' => isset( $_POST['item_description'] ) ? wp_unslash( $_POST['item_description'] ) : '',
				'price'       => isset( $_POST['item_price'] ) ? wp_unslash( $_POST['item_price'] ) : '',
				'promo_price' => isset( $_POST['item_promo_price'] ) ? wp_unslash( $_POST['item_promo_price'] ) : '',
				'promo_label' => isset( $_POST['item_promo_label'] ) ? wp_unslash( $_POST['item_promo_label'] ) : '',
				'featured'    => ! empty( $_POST['item_featured'] ) ? 1 : 0,
				'image_id'    => isset( $_POST['item_image_id'] ) ? wp_unslash( $_POST['item_image_id'] ) : 0,
				'image_url'   => isset( $_POST['item_image_url'] ) ? wp_unslash( $_POST['item_image_url'] ) : '',
				'status'      => isset( $_POST['item_status'] ) ? wp_unslash( $_POST['item_status'] ) : 'active',
				'order'       => isset( $_POST['item_order'] ) ? wp_unslash( $_POST['item_order'] ) : 0,
			)
		);

		if ( ! $saved_id ) {
			self::redirect_items( array( 'error' => 'missing_item' ) );
		}

		self::redirect_items( array( 'updated' => 1 ) );
	}

	private static function handle_delete_menu_item() {
		$id     = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$result = ML_App_Base_Core_Menu::delete_item( $id );
		self::redirect_items( array( 'deleted' => $result ? 1 : 0 ) );
	}

	private static function redirect_cardapios( array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core-cardapios' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_categories( array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core-categories' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_items( array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => 'ml-app-base-core-items' ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	public static function render_cardapios_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ml-app-base-core' ) );
		}

		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$mode    = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
		$editing = $edit_id ? ML_App_Base_Core_Menu::get_menu( $edit_id ) : null;
		$is_form = $editing || 'add' === $mode;
		?>
		<div class="wrap ml-app-base-core-admin">
			<?php self::render_admin_hero( 'Cardápios', 'Monte versões de cardápio, ative o cardápio do dia e organize categorias para o app.', 'Cardápios' ); ?>
			<?php self::render_menu_navigation( $is_form ? 'add_cardapio' : 'cardapios' ); ?>
			<?php self::render_menu_notices(); ?>

			<?php if ( $is_form ) : ?>
				<div class="mlab-admin-panel">
					<div class="mlab-card mlab-form-card">
						<h2><?php echo $editing ? esc_html__( 'Editar Cardápio', 'ml-app-base-core' ) : esc_html__( 'Adicionar Cardápio', 'ml-app-base-core' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Cardápio é o conjunto completo que o app usa. Apenas um cardápio fica ativo por vez.', 'ml-app-base-core' ); ?></p>
						<?php self::render_app_menu_form( $editing ); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="mlab-admin-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-cardapios', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Adicionar Cardápio', 'ml-app-base-core' ); ?></a>
					<span><?php esc_html_e( 'Ative um cardápio pelo tique. Ao ativar um, os outros são desligados automaticamente. Reordene arrastando as linhas.', 'ml-app-base-core' ); ?></span>
				</div>
				<div class="mlab-card mlab-list-card">
					<h2><?php esc_html_e( 'Cardápios cadastrados', 'ml-app-base-core' ); ?></h2>
					<?php self::render_app_menus_table(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_categories_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ml-app-base-core' ) );
		}

		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$mode    = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
		$editing = $edit_id ? ML_App_Base_Core_Menu::get_cardapio( $edit_id ) : null;
		$menus   = ML_App_Base_Core_Menu::get_menus();
		$is_form = $editing || 'add' === $mode;
		?>
		<div class="wrap ml-app-base-core-admin">
			<?php self::render_admin_hero( 'Categorias do Cardápio', 'Organize categorias e subcategorias com imagem, ordem, status e exibição na Home.', 'Categorias' ); ?>
			<?php self::render_menu_navigation( $is_form ? 'add_category' : 'categories' ); ?>
			<?php self::render_menu_notices(); ?>

			<?php if ( empty( $menus ) ) : ?>
				<div class="mlab-card"><h2><?php esc_html_e( 'Crie um cardápio primeiro', 'ml-app-base-core' ); ?></h2><p><?php esc_html_e( 'Antes de criar categorias, cadastre um cardápio como “Cardápio de Domingo”.', 'ml-app-base-core' ); ?></p><p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-cardapios', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Adicionar Cardápio', 'ml-app-base-core' ); ?></a></p></div>
			<?php elseif ( $is_form ) : ?>
				<div class="mlab-admin-panel">
					<div class="mlab-card mlab-form-card">
						<h2><?php echo $editing ? esc_html__( 'Editar Categoria', 'ml-app-base-core' ) : esc_html__( 'Adicionar Categoria', 'ml-app-base-core' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Categorias são o que o usuário vê no app: Bebidas, Petiscos, Comidas. Subcategorias ficam abaixo delas, como Cervejas, Drinks e Churrasco.', 'ml-app-base-core' ); ?></p>
						<?php self::render_cardapio_form( $editing ); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="mlab-admin-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Adicionar Categoria', 'ml-app-base-core' ); ?></a>
					<span><?php esc_html_e( 'Esta aba lista categorias e subcategorias. Ordene arrastando as linhas; o formulário fica somente em “Adicionar Categoria”.', 'ml-app-base-core' ); ?></span>
				</div>
				<div class="mlab-card mlab-list-card">
					<h2><?php esc_html_e( 'Categorias cadastradas', 'ml-app-base-core' ); ?></h2>
					<?php self::render_cardapios_table(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_items_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ml-app-base-core' ) );
		}

		$edit_id     = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$mode        = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
		$filter_id   = isset( $_GET['cardapio_id'] ) ? absint( $_GET['cardapio_id'] ) : 0;
		$editing     = $edit_id ? ML_App_Base_Core_Menu::get_item( $edit_id ) : null;
		$active_menu = ML_App_Base_Core_Menu::get_active_menu();
		$cardapios   = ML_App_Base_Core_Menu::get_cardapios( false, $active_menu ? (int) $active_menu['id'] : null );
		$is_form     = $editing || 'add' === $mode;
		?>
		<div class="wrap ml-app-base-core-admin">
			<?php self::render_admin_hero( 'Itens do Cardápio', 'Cadastre bebidas, petiscos e itens com preço, imagem, destaque e disponibilidade.', 'Itens' ); ?>
			<?php self::render_menu_navigation( $is_form ? 'add_item' : 'items' ); ?>
			<?php self::render_menu_notices(); ?>
			<?php if ( empty( $cardapios ) ) : ?>
				<div class="mlab-card">
					<h2><?php esc_html_e( 'Crie a primeira categoria', 'ml-app-base-core' ); ?></h2>
					<p><?php esc_html_e( 'Antes de cadastrar itens, crie pelo menos uma categoria/subcategoria dentro do cardápio ativo.', 'ml-app-base-core' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Adicionar Categoria', 'ml-app-base-core' ); ?></a></p>
				</div>
			<?php elseif ( $is_form ) : ?>
				<div class="mlab-admin-panel">
					<div class="mlab-card mlab-form-card">
						<h2><?php echo $editing ? esc_html__( 'Editar Item', 'ml-app-base-core' ) : esc_html__( 'Adicionar Item', 'ml-app-base-core' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Escolha a categoria ou subcategoria do item. A aba “Itens do Cardápio” fica apenas para listar, filtrar e ordenar.', 'ml-app-base-core' ); ?></p>
						<?php self::render_item_form( $editing, $cardapios, $filter_id ); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="mlab-admin-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-items', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Adicionar Item', 'ml-app-base-core' ); ?></a>
					<span><?php esc_html_e( 'Esta aba lista itens. O cadastro fica somente em “Adicionar Item”. Ordene arrastando as linhas.', 'ml-app-base-core' ); ?></span>
				</div>
				<div class="mlab-card mlab-list-card">
					<h2><?php esc_html_e( 'Itens cadastrados', 'ml-app-base-core' ); ?></h2>
					<?php self::render_items_filter( $cardapios, $filter_id ); ?>
					<?php self::render_items_table( $filter_id ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_menu_navigation( $active ) {
		$links = array(
			'dashboard'      => array( 'label' => 'Principal', 'url' => admin_url( 'admin.php?page=ml-app-base-core' ) ),
			'cardapios'      => array( 'label' => 'Cardápios', 'url' => admin_url( 'admin.php?page=ml-app-base-core-cardapios' ) ),
			'categories'     => array( 'label' => 'Categorias', 'url' => admin_url( 'admin.php?page=ml-app-base-core-categories' ) ),
			'items'          => array( 'label' => 'Itens do Cardápio', 'url' => admin_url( 'admin.php?page=ml-app-base-core-items' ) ),
			'add_cardapio'   => array( 'label' => 'Adicionar Cardápio', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core-cardapios', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ),
			'add_category'   => array( 'label' => 'Adicionar Categoria', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ),
			'add_item'       => array( 'label' => 'Adicionar Item', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core-items', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ),
		);

		echo '<nav class="nav-tab-wrapper mlab-menu-tabs">';
		foreach ( $links as $key => $link ) {
			$class = 'nav-tab';
			if ( $key === $active || ( 'edit_cardapio' === $active && 'categories' === $key ) || ( 'edit_item' === $active && 'items' === $key ) ) {
				$class .= ' nav-tab-active';
			}
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
		}
		echo '</nav>';
	}

	private static function render_menu_notices() {
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success inline"><p>Registro salvo com sucesso.</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success inline"><p>Registro excluído com sucesso.</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_key( wp_unslash( $_GET['error'] ) );
			$messages = array(
				'missing_menu'        => 'Informe pelo menos o nome do cardápio.',
				'menu_has_categories' => 'Este cardápio possui categorias vinculadas. Mova ou exclua as categorias antes de remover.',
				'missing_cardapio'    => 'Informe pelo menos o nome da categoria.',
				'missing_item'        => 'Informe o nome do item e selecione um cardápio válido.',
				'cardapio_has_items'    => 'Este cardápio possui itens vinculados. Mova ou exclua os itens antes de remover o cardápio.',
				'cardapio_has_children' => 'Esta categoria possui subcategorias vinculadas. Mova ou exclua as subcategorias antes de remover.',
			);
			echo '<div class="notice notice-error inline"><p>' . esc_html( $messages[ $error ] ?? 'Não foi possível concluir a ação.' ) . '</p></div>';
		}
	}

	private static function render_app_menu_form( $menu = null ) {
		$menu = is_array( $menu ) ? $menu : array();
		$id = isset( $menu['id'] ) ? absint( $menu['id'] ) : 0;
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
			<input type="hidden" name="action" value="save_app_menu">
			<input type="hidden" name="menu_id" value="<?php echo esc_attr( $id ); ?>">
			<table class="form-table">
				<tr><th scope="row"><label for="menu_title">Nome do cardápio</label></th><td><input type="text" id="menu_title" name="menu_title" class="regular-text" required value="<?php echo esc_attr( $menu['title'] ?? '' ); ?>"><p class="description">Ex.: Cardápio de Domingo, Cardápio de Feijoada, Cardápio de Evento.</p></td></tr>
				<tr><th scope="row"><label for="menu_slug">Slug</label></th><td><input type="text" id="menu_slug" name="menu_slug" class="regular-text" value="<?php echo esc_attr( $menu['slug'] ?? '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="menu_description">Descrição</label></th><td><textarea id="menu_description" name="menu_description" rows="3" class="large-text"><?php echo esc_textarea( $menu['description'] ?? '' ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="menu_status">Status</label></th><td><select id="menu_status" name="menu_status"><option value="active" <?php selected( $menu['status'] ?? 'active', 'active' ); ?>>Ativo no app</option><option value="inactive" <?php selected( $menu['status'] ?? 'active', 'inactive' ); ?>>Inativo</option></select><p class="description">Somente um cardápio pode ficar ativo. Ao salvar como ativo, os outros são desligados automaticamente.</p></td></tr>
				<tr><th scope="row"><label for="menu_order">Ordem</label></th><td><input type="number" id="menu_order" name="menu_order" class="small-text" value="<?php echo esc_attr( $menu['order'] ?? 0 ); ?>"></td></tr>
			</table>
			<?php submit_button( $id ? 'Atualizar Cardápio' : 'Adicionar Cardápio' ); ?>
		</form>
		<?php
	}

	private static function render_app_menus_table() {
		$menus = ML_App_Base_Core_Menu::get_menus();
		if ( empty( $menus ) ) {
			echo '<div class="mlab-empty-state"><p>Nenhum cardápio cadastrado ainda.</p><p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-cardapios', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ) . '">Adicionar Cardápio</a></p></div>';
			return;
		}

		$all_categories = ML_App_Base_Core_Menu::get_cardapios();
		echo '<table class="widefat striped mlab-admin-table mlab-admin-table--menus"><thead><tr><th class="mlab-col-drag">Ordem</th><th class="mlab-col-active">Ativo</th><th>Cardápio</th><th>Categorias</th><th>Ações</th></tr></thead><tbody class="mlab-sortable-body" data-sort-type="menus">';
		foreach ( $menus as $menu ) {
			$cat_count = 0;
			foreach ( $all_categories as $category ) {
				if ( (int) $category['menu_id'] === (int) $menu['id'] ) {
					$cat_count++;
				}
			}
			echo '<tr data-record-id="' . esc_attr( $menu['id'] ) . '">';
			echo '<td class="mlab-drag-cell"><span class="dashicons dashicons-menu mlab-drag-handle" aria-hidden="true"></span><span class="screen-reader-text">Arrastar</span></td>';
			echo '<td class="mlab-active-cell"><form method="post" action="">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="activate_app_menu"><input type="hidden" name="menu_id" value="' . esc_attr( $menu['id'] ) . '">';
			echo '<label class="mlab-active-toggle"><input type="radio" class="mlab-active-menu-radio" name="active_menu" value="' . esc_attr( $menu['id'] ) . '" ' . checked( 'active', $menu['status'], false ) . '><span></span></label>';
			echo '</form></td>';
			echo '<td><strong>' . esc_html( $menu['title'] ) . '</strong><br><small>' . esc_html( wp_trim_words( $menu['description'], 18 ) ) . '</small></td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=ml-app-base-core-categories' ) ) . '">' . esc_html( $cat_count ) . ' categoria(s)</a></td>';
			echo '<td class="mlab-actions">';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-cardapios', 'edit' => (int) $menu['id'] ), admin_url( 'admin.php' ) ) ) . '">Editar</a> ';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ) . '">Adicionar Categoria</a> ';
			echo '<form method="post" action="" style="display:inline-block;" onsubmit="return confirm(\'Excluir este cardápio? A exclusão será bloqueada se houver categorias vinculadas.\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_app_menu"><input type="hidden" name="menu_id" value="' . esc_attr( $menu['id'] ) . '">';
			submit_button( 'Excluir', 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_cardapio_form( $cardapio = null ) {
		$cardapio  = is_array( $cardapio ) ? $cardapio : array();
		$id        = isset( $cardapio['id'] ) ? absint( $cardapio['id'] ) : 0;
		$parent_id = isset( $cardapio['parent_id'] ) ? absint( $cardapio['parent_id'] ) : 0;
		$image_url = isset( $cardapio['image_url'] ) ? esc_url( $cardapio['image_url'] ) : '';
		$menus = ML_App_Base_Core_Menu::get_menus();
		$active_menu = ML_App_Base_Core_Menu::get_active_menu();
		$menu_id = isset( $cardapio['menu_id'] ) ? absint( $cardapio['menu_id'] ) : ( $active_menu ? absint( $active_menu['id'] ) : 0 );
		$parents = ML_App_Base_Core_Menu::get_parent_cardapios( false, $menu_id );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
			<input type="hidden" name="action" value="save_cardapio">
			<input type="hidden" name="cardapio_id" value="<?php echo esc_attr( $id ); ?>">
			<table class="form-table">
				<tr><th scope="row"><label for="cardapio_menu_id">Cardápio</label></th><td><select id="cardapio_menu_id" name="cardapio_menu_id" required><?php foreach ( $menus as $menu ) : ?><option value="<?php echo esc_attr( $menu['id'] ); ?>" <?php selected( $menu_id, (int) $menu['id'] ); ?>><?php echo esc_html( $menu['title'] . ( 'active' === $menu['status'] ? ' — ativo' : '' ) ); ?></option><?php endforeach; ?></select><p class="description">Escolha em qual cardápio esta categoria aparece. Só o cardápio ativo aparece no app.</p></td></tr>
				<tr><th scope="row"><label for="cardapio_title">Nome</label></th><td><input type="text" id="cardapio_title" name="cardapio_title" class="regular-text" required value="<?php echo esc_attr( $cardapio['title'] ?? '' ); ?>"><p class="description">Ex.: Bebidas, Petiscos, Cervejas, Drinks.</p></td></tr>
				<tr><th scope="row"><label for="cardapio_parent_id">Categoria pai</label></th><td><select id="cardapio_parent_id" name="cardapio_parent_id"><option value="0">Categoria principal</option><?php foreach ( $parents as $parent ) : if ( $id && (int) $parent['id'] === (int) $id ) { continue; } ?><option value="<?php echo esc_attr( $parent['id'] ); ?>" <?php selected( $parent_id, (int) $parent['id'] ); ?>><?php echo esc_html( $parent['title'] ); ?></option><?php endforeach; ?></select><p class="description">Use categoria principal para Bebidas/Petiscos. Selecione uma categoria pai para criar subcategoria como Cervejas, Drinks, Churrasco.</p></td></tr>
				<tr><th scope="row"><label for="cardapio_slug">Slug</label></th><td><input type="text" id="cardapio_slug" name="cardapio_slug" class="regular-text" value="<?php echo esc_attr( $cardapio['slug'] ?? '' ); ?>"><p class="description">Se ficar vazio, será gerado pelo nome.</p></td></tr>
				<tr><th scope="row"><label for="cardapio_description">Descrição</label></th><td><textarea id="cardapio_description" name="cardapio_description" rows="3" class="large-text"><?php echo esc_textarea( $cardapio['description'] ?? '' ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="cardapio_image_url">Foto da categoria</label></th><td><input type="hidden" id="cardapio_image_id" name="cardapio_image_id" value="<?php echo esc_attr( $cardapio['image_id'] ?? 0 ); ?>"><input type="url" id="cardapio_image_url" name="cardapio_image_url" class="regular-text" value="<?php echo esc_attr( $image_url ); ?>"> <button type="button" class="button" id="mlab-select-cardapio-image">Selecionar mídia</button><p class="description">Foto usada na Home do Cardápio para representar esta categoria, como Bebidas, Petiscos ou Comidas.</p><div id="mlab-cardapio-image-preview" style="margin-top:10px;<?php echo empty( $image_url ) ? 'display:none;' : ''; ?>"><img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:4px;background:#fff;"></div></td></tr>
				<tr><th scope="row"><label for="cardapio_show_on_home">Home do Cardápio</label></th><td><label><input type="checkbox" id="cardapio_show_on_home" name="cardapio_show_on_home" value="1" <?php checked( ! array_key_exists( 'show_on_home', $cardapio ) || ! empty( $cardapio['show_on_home'] ) ); ?>> Exibir esta categoria na Home do Cardápio</label><p class="description">Use para controlar quais categorias aparecem como cards principais no app.</p></td></tr>
				<tr><th scope="row"><label for="cardapio_order">Ordem</label></th><td><input type="number" id="cardapio_order" name="cardapio_order" class="small-text" value="<?php echo esc_attr( $cardapio['order'] ?? 0 ); ?>"></td></tr>
				<tr><th scope="row"><label for="cardapio_status">Status</label></th><td><select id="cardapio_status" name="cardapio_status"><option value="active" <?php selected( $cardapio['status'] ?? 'active', 'active' ); ?>>Ativo</option><option value="inactive" <?php selected( $cardapio['status'] ?? 'active', 'inactive' ); ?>>Inativo</option></select></td></tr>
			</table>
			<?php submit_button( $id ? 'Atualizar Categoria' : 'Adicionar Categoria' ); ?>
		</form>
		<script>
		(function($){
			$('#mlab-select-cardapio-image').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Selecionar foto da categoria', button: { text: 'Usar esta imagem' }, multiple: false });
				frame.on('select', function(){
					var attachment = frame.state().get('selection').first().toJSON();
					$('#cardapio_image_id').val(attachment.id || 0);
					$('#cardapio_image_url').val(attachment.url || '');
					if (attachment.url) {
						$('#mlab-cardapio-image-preview').show().find('img').attr('src', attachment.url);
					}
				});
				frame.open();
			});
		})(jQuery);
		</script>
		<?php
	}

	private static function render_cardapios_table() {
		$active_menu = ML_App_Base_Core_Menu::get_active_menu();
		$active_menu_id = $active_menu ? (int) $active_menu['id'] : null;
		$cardapios = ML_App_Base_Core_Menu::get_cardapios( false, $active_menu_id );
		if ( empty( $cardapios ) ) {
			echo '<div class="mlab-empty-state"><p>Nenhuma categoria cadastrada para o cardápio ativo.</p><p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'mode' => 'add' ), admin_url( 'admin.php' ) ) ) . '">Adicionar Categoria</a></p></div>';
			return;
		}

		$names = array();
		foreach ( $cardapios as $cardapio ) {
			$names[ (int) $cardapio['id'] ] = $cardapio['title'];
		}

		echo '<table class="widefat striped mlab-admin-table mlab-admin-table--categories"><thead><tr><th class="mlab-col-drag">Ordem</th><th>Foto</th><th>Categoria</th><th>Tipo</th><th>Home</th><th>Status</th><th>Itens</th><th>Ações</th></tr></thead><tbody class="mlab-sortable-body" data-sort-type="categories">';
		foreach ( $cardapios as $cardapio ) {
			$items_count = count( ML_App_Base_Core_Menu::get_items( (int) $cardapio['id'] ) );
			$is_child    = ! empty( $cardapio['parent_id'] );
			$image = ! empty( $cardapio['image_url'] ) ? $cardapio['image_url'] : '';
			echo '<tr data-record-id="' . esc_attr( $cardapio['id'] ) . '" class="' . ( $is_child ? 'mlab-row-child' : 'mlab-row-parent' ) . '">';
			echo '<td class="mlab-drag-cell"><span class="dashicons dashicons-menu mlab-drag-handle" aria-hidden="true"></span><span class="screen-reader-text">Arrastar</span></td>';
			echo '<td class="mlab-thumb-cell">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="" class="mlab-admin-thumb">' : '<span class="mlab-thumb-placeholder">—</span>' ) . '</td>';
			echo '<td><strong>' . esc_html( $cardapio['title'] ) . '</strong>' . ( $is_child ? '<br><small>Dentro de: ' . esc_html( $names[ (int) $cardapio['parent_id'] ] ?? 'Categoria removida' ) . '</small>' : '<br><small>Categoria principal</small>' ) . '</td>';
			echo '<td>' . esc_html( $is_child ? 'Subcategoria' : 'Categoria' ) . '</td>';
			echo '<td>' . ( ! empty( $cardapio['show_on_home'] ) ? '<span class="mlab-pill mlab-pill--ok">Sim</span>' : '<span class="mlab-pill">Não</span>' ) . '</td>';
			echo '<td>' . ( 'active' === $cardapio['status'] ? '<span class="mlab-pill mlab-pill--ok">Ativo</span>' : '<span class="mlab-pill">Inativo</span>' ) . '</td>';
			echo '<td>' . esc_html( $items_count ) . '</td>';
			echo '<td class="mlab-actions">';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-categories', 'edit' => (int) $cardapio['id'] ), admin_url( 'admin.php' ) ) ) . '">Editar</a> ';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-items', 'mode' => 'add', 'cardapio_id' => (int) $cardapio['id'] ), admin_url( 'admin.php' ) ) ) . '">Adicionar Item</a> ';
			echo '<form method="post" action="" style="display:inline-block;" onsubmit="return confirm(\'Excluir esta categoria? A exclusão será bloqueada se houver subcategorias ou itens vinculados.\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_cardapio"><input type="hidden" name="cardapio_id" value="' . esc_attr( $cardapio['id'] ) . '">';
			submit_button( 'Excluir', 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_items_filter( array $cardapios, $filter_id ) {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin-bottom:12px;">';
		echo '<input type="hidden" name="page" value="ml-app-base-core-items">';
		echo '<label for="cardapio_filter"><strong>Filtrar por categoria/subcategoria:</strong></label> ';
		echo '<select id="cardapio_filter" name="cardapio_id"><option value="0">Todos</option>';
		foreach ( self::get_cardapio_select_options() as $option ) {
			echo '<option value="' . esc_attr( $option['id'] ) . '" ' . selected( (int) $filter_id, (int) $option['id'], false ) . '>' . esc_html( $option['label'] ) . '</option>';
		}
		echo '</select> ';
		submit_button( 'Filtrar', 'secondary', 'submit', false );
		echo '</form>';
	}

	private static function render_item_form( $item, array $cardapios, $filter_id = 0 ) {
		$item = is_array( $item ) ? $item : array();
		$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$selected_cardapio = isset( $item['cardapio_id'] ) ? absint( $item['cardapio_id'] ) : absint( $filter_id );
		$options = self::get_item_category_select_options();
		$active_menu = ML_App_Base_Core_Menu::get_active_menu();

		if ( ! $selected_cardapio && ! empty( $options[0]['options'][0]['id'] ) ) {
			$selected_cardapio = absint( $options[0]['options'][0]['id'] );
		}

		if ( empty( $options ) ) {
			?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Nenhuma categoria disponível para cadastrar itens.', 'ml-app-base-core' ); ?></strong></p>
				<p><?php esc_html_e( 'Crie primeiro as categorias do cardápio ativo, como Bebidas, Petiscos e Comidas. Depois cadastre os itens escolhendo uma categoria ou subcategoria.', 'ml-app-base-core' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ml-app-base-core-categories' ) ); ?>"><?php esc_html_e( 'Criar Categorias', 'ml-app-base-core' ); ?></a></p>
			</div>
			<?php
			return;
		}
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
			<input type="hidden" name="action" value="save_menu_item">
			<input type="hidden" name="item_id" value="<?php echo esc_attr( $id ); ?>">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Cardápio ativo', 'ml-app-base-core' ); ?></th>
					<td><strong><?php echo esc_html( is_array( $active_menu ) ? $active_menu['title'] : __( 'Cardápio', 'ml-app-base-core' ) ); ?></strong><p class="description"><?php esc_html_e( 'Os itens cadastrados aqui serão exibidos no cardápio ativo. Para trocar o cardápio ativo, use a tela Cardápios.', 'ml-app-base-core' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="item_cardapio_id"><?php esc_html_e( 'Categoria do item', 'ml-app-base-core' ); ?></label></th>
					<td>
						<select id="item_cardapio_id" name="item_cardapio_id" required class="regular-text">
							<option value=""><?php esc_html_e( 'Selecione a categoria ou subcategoria', 'ml-app-base-core' ); ?></option>
							<?php foreach ( $options as $group ) : ?>
								<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
									<?php foreach ( $group['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option['id'] ); ?>" <?php selected( $selected_cardapio, (int) $option['id'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Escolha exatamente onde o item vai aparecer no app. Ex.: Bebidas > Cervejas, Bebidas > Drinks, Petiscos > Churrasco.', 'ml-app-base-core' ); ?></p>
					</td>
				</tr>
				<tr><th scope="row"><label for="item_title">Nome do item</label></th><td><input type="text" id="item_title" name="item_title" class="regular-text" required value="<?php echo esc_attr( $item['title'] ?? '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="item_description">Descrição</label></th><td><textarea id="item_description" name="item_description" rows="3" class="large-text"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="item_price">Preço</label></th><td><input type="text" id="item_price" name="item_price" class="regular-text" placeholder="Ex.: R$ 25,00" value="<?php echo esc_attr( $item['price'] ?? '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="item_featured">Destaque/Promoção</label></th><td><label><input type="checkbox" id="item_featured" name="item_featured" value="1" <?php checked( ! empty( $item['featured'] ) ); ?>> Destacar este item na Home e no Cardápio</label><p class="description">Quando marcado, o item aparece como destaque/promoção na Home do aplicativo e na Home do Cardápio.</p></td></tr>
				<tr><th scope="row"><label for="item_promo_label">Texto do destaque</label></th><td><input type="text" id="item_promo_label" name="item_promo_label" class="regular-text" placeholder="Ex.: Promoção, Mais vendido, Destaque do dia" value="<?php echo esc_attr( $item['promo_label'] ?? '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="item_promo_price">Preço promocional</label></th><td><input type="text" id="item_promo_price" name="item_promo_price" class="regular-text" placeholder="Ex.: R$ 19,90" value="<?php echo esc_attr( $item['promo_price'] ?? '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="item_image_url">Foto</label></th><td><input type="hidden" id="item_image_id" name="item_image_id" value="<?php echo esc_attr( $item['image_id'] ?? 0 ); ?>"><input type="url" id="item_image_url" name="item_image_url" class="regular-text" value="<?php echo esc_attr( $item['image_url'] ?? '' ); ?>"> <button type="button" class="button" id="mlab-select-menu-image">Selecionar mídia</button><p class="description">Use a biblioteca de mídia ou cole uma URL absoluta.</p><div id="mlab-menu-image-preview" style="margin-top:10px;<?php echo empty( $item['image_url'] ) ? 'display:none;' : ''; ?>"><img src="<?php echo esc_url( $item['image_url'] ?? '' ); ?>" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:4px;background:#fff;"></div></td></tr>
				<tr><th scope="row"><label for="item_order">Ordem</label></th><td><input type="number" id="item_order" name="item_order" class="small-text" value="<?php echo esc_attr( $item['order'] ?? 0 ); ?>"></td></tr>
				<tr><th scope="row"><label for="item_status">Status</label></th><td><select id="item_status" name="item_status"><option value="active" <?php selected( $item['status'] ?? 'active', 'active' ); ?>>Ativo</option><option value="inactive" <?php selected( $item['status'] ?? 'active', 'inactive' ); ?>>Inativo</option></select></td></tr>
			</table>
			<?php submit_button( $id ? 'Atualizar Item' : 'Adicionar Item' ); ?>
		</form>
		<script>
		(function($){
			$('#mlab-select-menu-image').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Selecionar foto do item', button: { text: 'Usar esta imagem' }, multiple: false });
				frame.on('select', function(){
					var attachment = frame.state().get('selection').first().toJSON();
					$('#item_image_id').val(attachment.id || 0);
					$('#item_image_url').val(attachment.url || '');
					if (attachment.url) {
						$('#mlab-menu-image-preview').show().find('img').attr('src', attachment.url);
					}
				});
				frame.open();
			});
		})(jQuery);
		</script>
		<?php
	}

	private static function render_items_table( $filter_id = 0 ) {
		$items = ML_App_Base_Core_Menu::get_items( absint( $filter_id ) );
		$labels = self::get_cardapio_label_map();

		if ( empty( $items ) ) {
			echo '<div class="mlab-empty-state"><p>Nenhum item cadastrado para este filtro.</p><p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-items', 'mode' => 'add', 'cardapio_id' => absint( $filter_id ) ), admin_url( 'admin.php' ) ) ) . '">Adicionar Item</a></p></div>';
			return;
		}

		echo '<table class="widefat striped mlab-admin-table mlab-admin-table--items"><thead><tr><th class="mlab-col-drag">Ordem</th><th>Item</th><th>Categoria/Subcategoria</th><th>Preço</th><th>Destaque</th><th>Status</th><th>Ações</th></tr></thead><tbody class="mlab-sortable-body" data-sort-type="items">';
		foreach ( $items as $item ) {
			$image = ! empty( $item['image_url'] ) ? $item['image_url'] : '';
			echo '<tr data-record-id="' . esc_attr( $item['id'] ) . '">';
			echo '<td class="mlab-drag-cell"><span class="dashicons dashicons-menu mlab-drag-handle" aria-hidden="true"></span><span class="screen-reader-text">Arrastar</span></td>';
			echo '<td><div class="mlab-item-title">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="" class="mlab-item-thumb">' : '' ) . '<span><strong>' . esc_html( $item['title'] ) . '</strong><br><small>' . esc_html( wp_trim_words( $item['description'], 14 ) ) . '</small></span></div></td>';
			echo '<td>' . esc_html( $labels[ (int) $item['cardapio_id'] ] ?? 'Categoria removida' ) . '</td>';
			echo '<td>' . esc_html( $item['price'] ?: '-' ) . ( ! empty( $item['promo_price'] ) ? '<br><small>Promo: ' . esc_html( $item['promo_price'] ) . '</small>' : '' ) . '</td>';
			echo '<td>' . ( ! empty( $item['featured'] ) ? '<span class="mlab-pill mlab-pill--hot">' . esc_html( $item['promo_label'] ?: 'Destaque' ) . '</span>' : '<span class="mlab-pill">Não</span>' ) . '</td>';
			echo '<td>' . ( 'active' === $item['status'] ? '<span class="mlab-pill mlab-pill--ok">Ativo</span>' : '<span class="mlab-pill">Inativo</span>' ) . '</td>';
			echo '<td class="mlab-actions">';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'ml-app-base-core-items', 'edit' => (int) $item['id'] ), admin_url( 'admin.php' ) ) ) . '">Editar</a> ';
			echo '<form method="post" action="" style="display:inline-block;" onsubmit="return confirm(\'Excluir este item?\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_menu_item"><input type="hidden" name="item_id" value="' . esc_attr( $item['id'] ) . '">';
			submit_button( 'Excluir', 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function get_cardapio_select_options() {
		$options = array();
		foreach ( self::get_item_category_select_options() as $group ) {
			foreach ( $group['options'] as $option ) {
				$options[] = $option;
			}
		}
		return $options;
	}

	private static function get_item_category_select_options() {
		$groups = array();
		$active_menu = ML_App_Base_Core_Menu::get_active_menu();
		$menu_id = $active_menu ? (int) $active_menu['id'] : null;

		foreach ( ML_App_Base_Core_Menu::get_parent_cardapios( false, $menu_id ) as $parent ) {
			$child_options = array(
				array(
					'id'    => (int) $parent['id'],
					'label' => sprintf( '%s %s', $parent['title'], __( '(categoria principal)', 'ml-app-base-core' ) ),
				),
			);

			foreach ( ML_App_Base_Core_Menu::get_child_cardapios( (int) $parent['id'], false ) as $child ) {
				if ( null !== $menu_id && ! in_array( (int) $child['menu_id'], array( 0, $menu_id ), true ) ) {
					continue;
				}
				$child_options[] = array(
					'id'    => (int) $child['id'],
					'label' => $child['title'],
				);
			}

			$groups[] = array(
				'id'      => (int) $parent['id'],
				'label'   => $parent['title'],
				'options' => $child_options,
			);
		}

		return $groups;
	}

	private static function get_cardapio_label_map() {
		$map = array();
		foreach ( self::get_item_category_select_options() as $group ) {
			foreach ( $group['options'] as $option ) {
				$clean_label = preg_replace( '/\s*\(categoria principal\)$/', '', $option['label'] );
				$map[ (int) $option['id'] ] = ( $clean_label === $group['label'] ) ? $group['label'] : $group['label'] . ' > ' . $clean_label;
			}
		}
		return $map;
	}


	private static function render_admin_hero( $title, $subtitle = '', $active_context = '' ) {
		$version = defined( 'ML_APP_BASE_CORE_VERSION' ) ? ML_APP_BASE_CORE_VERSION : '';
		?>
		<section class="mlab-admin-hero">
			<div class="mlab-admin-hero-brand">
				<div class="mlab-admin-hero-mark" aria-hidden="true"><span>ML</span></div>
				<div class="mlab-admin-hero-copy">
					<span class="mlab-admin-eyebrow">Backend base de aplicativo</span>
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php if ( $subtitle ) : ?><p class="mlab-admin-intro"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
				</div>
			</div>
			<div class="mlab-admin-hero-meta">
				<span class="mlab-version-badge">v<?php echo esc_html( $version ); ?></span>
				<div class="mlab-admin-tags">
					<span class="mlab-chip">Push FCM</span>
					<span class="mlab-chip">Cardápios</span>
					<span class="mlab-chip">Usuários</span>
					<?php if ( $active_context ) : ?><span class="mlab-chip is-current"><?php echo esc_html( $active_context ); ?></span><?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}

	private static function render_main_navigation( $active_tab ) {
		$tabs = array(
			'dashboard'     => array( 'label' => 'Dashboard', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core', 'tab' => 'dashboard' ), admin_url( 'admin.php' ) ) ),
			'notificacoes' => array( 'label' => 'Notificações', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core', 'tab' => 'notificacoes' ), admin_url( 'admin.php' ) ) ),
			'dispositivos' => array( 'label' => 'Dispositivos', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core', 'tab' => 'dispositivos' ), admin_url( 'admin.php' ) ) ),
			'usuarios'     => array( 'label' => 'Usuários', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core', 'tab' => 'usuarios' ), admin_url( 'admin.php' ) ) ),
			'cardapios'    => array( 'label' => 'Cardápios', 'url' => admin_url( 'admin.php?page=ml-app-base-core-cardapios' ) ),
			'categories'   => array( 'label' => 'Categorias', 'url' => admin_url( 'admin.php?page=ml-app-base-core-categories' ) ),
			'items'        => array( 'label' => 'Itens do Cardápio', 'url' => admin_url( 'admin.php?page=ml-app-base-core-items' ) ),
			'configuracoes'=> array( 'label' => 'Configurações', 'url' => add_query_arg( array( 'page' => 'ml-app-base-core', 'tab' => 'configuracoes' ), admin_url( 'admin.php' ) ) ),
		);
		?>
		<nav class="mlab-tab-nav" aria-label="Navegação do ML App Base Core">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a href="<?php echo esc_url( $tab['url'] ); ?>" class="mlab-tab-button <?php echo $key === $active_tab ? 'is-active' : ''; ?>"><?php echo esc_html( $tab['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ml-app-base-core' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		?>
		<div class="wrap ml-app-base-core-admin">
			<?php self::render_admin_hero( 'ML App Base Core', 'Gestão do aplicativo, notificações, usuários, dispositivos, cardápios e integrações Firebase.', 'Painel' ); ?>
			<?php self::render_main_navigation( $active_tab ); ?>

			<div class="mlab-tab-content">
				<?php if ( 'dashboard' === $active_tab ) : ?>
					<?php
					global $wpdb;
					$users_count = $wpdb->get_var( "SELECT COUNT(*) FROM " . ML_App_Base_Core_Database::table( 'ml_app_base_users' ) );
					$devices_count = $wpdb->get_var( "SELECT COUNT(*) FROM " . ML_App_Base_Core_Database::table( 'ml_app_base_devices' ) . " WHERE deleted_at IS NULL" );
					?>
					<div class="mlab-summary-grid">
						<div class="mlab-summary-card">
							<span class="mlab-summary-label">Usuários Cadastrados</span>
							<strong><?php echo esc_html( $users_count ); ?></strong>
							<small>Contas registradas no aplicativo</small>
						</div>
						<div class="mlab-summary-card">
							<span class="mlab-summary-label">Dispositivos Ativos</span>
							<strong><?php echo esc_html( $devices_count ); ?></strong>
							<small>Tokens e aparelhos habilitados</small>
						</div>
						<div class="mlab-card">
							<h2>Regras de Segurança</h2>
							<ul>
								<li>Este plugin roda apenas em <code>app.appbase.com.br</code>.</li>
								<li>Não exibe dados sensíveis como senhas ou tokens completos.</li>
								<li>Notificações podem ser enviadas agora ou agendadas por data/hora.</li>
							</ul>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( 'notificacoes' === $active_tab ) : ?>
					<div class="mlab-card">
						<h2>Enviar ou Agendar Notificação Push</h2>
						<p>Defina se a notificação sai agora ou em data/hora agendada. Repetição padrão: uma única vez.</p>
						<?php if ( isset( $_GET['success'] ) ) : ?>
							<div class="notice notice-success inline"><p>Notificação registrada/enviada. Push sucesso: <?php echo esc_html( absint( $_GET['sent'] ) ); ?>, push falhas: <?php echo esc_html( absint( $_GET['failed'] ) ); ?>. FCM: <?php echo esc_html( absint( $_GET['fcm'] ?? 0 ) ); ?>, Expo legado: <?php echo esc_html( absint( $_GET['expo'] ?? 0 ) ); ?>. A central interna pode receber mesmo quando não há push token válido.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['scheduled'] ) ) : ?>
							<div class="notice notice-success inline"><p>Notificação agendada com sucesso.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['reset'] ) ) : ?>
							<div class="notice notice-success inline"><p>Histórico de notificações limpo. Campanhas: <?php echo esc_html( absint( $_GET['notifications'] ?? 0 ) ); ?>, logs: <?php echo esc_html( absint( $_GET['logs'] ?? 0 ) ); ?>, notificações internas: <?php echo esc_html( absint( $_GET['user_notifications'] ?? 0 ) ); ?>.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['deleted_notification'] ) ) : ?>
							<div class="notice notice-success inline"><p>Notificação apagada do histórico e da central interna.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['deleted_log'] ) ) : ?>
							<div class="notice notice-success inline"><p>Log técnico apagado.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['error'] ) ) : ?>
							<div class="notice notice-error inline"><p><?php echo esc_html( self::notification_error_message( sanitize_key( wp_unslash( $_GET['error'] ) ) ) ); ?></p></div>
						<?php endif; ?>
						<form method="post" action="">
							<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
							<input type="hidden" name="action" value="save_push">
							<table class="form-table">
								<tr><th scope="row"><label for="push_title">Título</label></th><td><input type="text" id="push_title" name="push_title" required class="regular-text"></td></tr>
								<tr><th scope="row"><label for="push_message">Mensagem</label></th><td><textarea id="push_message" name="push_message" required class="regular-text" rows="3"></textarea></td></tr>
								<tr><th scope="row"><label for="push_image_url">Imagem/Banner</label></th><td><input type="url" id="push_image_url" name="image_url" class="regular-text" placeholder="https://.../banner.jpg"> <button type="button" class="button" id="mlab-select-push-image">Selecionar da mídia</button> <button type="button" class="button" id="mlab-clear-push-image">Limpar</button><p class="description">Use a biblioteca de mídia para escolher banner/foto do evento ou cole uma URL. O push simples continua funcionando sem imagem.</p><div id="mlab-push-image-preview" style="display:none;margin-top:10px;"><img src="" alt="Prévia" style="max-width:260px;height:auto;border:1px solid #dcdcde;border-radius:4px;background:#fff;"></div></td></tr>
								<tr><th scope="row"><label for="target_type">Destino</label></th><td><select id="target_type" name="target_type"><option value="home">Home / Geral</option><option value="event">Evento</option><option value="news">Notícia</option><option value="about">Sobre</option><option value="custom_url">URL externa</option></select></td></tr>
								<tr><th scope="row"><label for="target_id">ID ou Link de Destino</label></th><td><input type="text" id="target_id" name="target_id" class="regular-text" placeholder="ID do evento/notícia ou URL"><p class="description">Obrigatório para favoritos do evento/notícia.</p></td></tr>
								<tr><th scope="row"><label for="audience">Público</label></th><td><select id="audience" name="audience"><option value="all">Todos os dispositivos</option><option value="favorites_only">Somente usuários que favoritaram este evento/notícia</option></select></td></tr>
								<tr><th scope="row"><label for="send_mode">Disparo</label></th><td><select id="send_mode" name="send_mode"><option value="send_now">Enviar agora</option><option value="schedule">Agendar data e hora</option></select></td></tr>
								<tr><th scope="row">Data e hora</th><td><input type="date" name="scheduled_date"> <input type="time" name="scheduled_time"><p class="description">Usa o fuso horário configurado no WordPress.</p></td></tr>
								<tr><th scope="row"><label for="recurrence">Frequência</label></th><td><select id="recurrence" name="recurrence"><option value="none">Uma vez</option><option value="daily">Diária</option><option value="weekly">Semanal</option><option value="monthly">Mensal</option></select> <label for="repeat_total">Quantidade de disparos</label> <input type="number" id="repeat_total" name="repeat_total" min="1" max="30" value="1" class="small-text"><p class="description">Para campanha pontual, deixe "Uma vez" e quantidade 1.</p></td></tr>
							</table>
							<?php submit_button( 'Salvar / Enviar Notificação' ); ?>
						</form>
					</div>

					<div class="mlab-card" style="margin-top: 20px;">
						<h2>Agendamentos e Histórico</h2>
						<p>Use o reset apenas para limpar testes antigos. Esta ação não apaga usuários, dispositivos, favoritos, perfil nem configurações do app.</p>
						<form method="post" action="" onsubmit="return confirm('Limpar histórico, agendamentos e notificações internas de teste? Esta ação não apaga usuários nem dispositivos.');" style="margin: 12px 0 18px;">
							<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
							<input type="hidden" name="action" value="reset_notification_history">
							<?php submit_button( 'Resetar histórico de notificações', 'delete', 'submit', false ); ?>
						</form>
						<?php self::render_notifications_history(); ?>
					</div>
				<?php endif; ?>

				<?php if ( 'dispositivos' === $active_tab ) : ?>
					<div class="mlab-card"><h2>Dispositivos Cadastrados</h2><?php self::render_devices_table(); ?></div>
				<?php endif; ?>

				<?php if ( 'usuarios' === $active_tab ) : ?>
					<?php self::render_user_action_notices(); ?>
					<div class="mlab-card"><h2>Usuários do Aplicativo</h2><?php self::render_users_table(); ?></div>
					<div class="mlab-card" style="margin-top: 20px;"><h2>Bloqueio por E-mail</h2><?php self::render_blocked_emails_panel(); ?></div>
				<?php endif; ?>

				<?php if ( 'configuracoes' === $active_tab ) : ?>
					<?php $firebase_status = ML_App_Base_Core_Push_Service::get_firebase_status(); ?>
					<div class="mlab-card">
						<h2>Configurações do Aplicativo</h2>
						<p>As notificações usam o fuso horário do WordPress: <code><?php echo esc_html( wp_timezone_string() ); ?></code>.</p>
						<?php if ( isset( $_GET['firebase_saved'] ) ) : ?>
							<div class="notice notice-success inline"><p>Configurações Firebase salvas.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['firebase_error'] ) ) : ?>
							<div class="notice notice-error inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['firebase_error'] ) ) ); ?></p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['fcm_test'] ) ) : ?>
							<div class="notice <?php echo 'success' === sanitize_key( wp_unslash( $_GET['fcm_test'] ) ) ? 'notice-success' : 'notice-error'; ?> inline"><p>Teste FCM executado. Veja o resultado técnico abaixo.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['fcm_debug_reset'] ) ) : ?>
							<div class="notice notice-success inline"><p>Debugger FCM resetado.</p></div>
						<?php endif; ?>
						<?php if ( isset( $_GET['push_logs_reset'] ) ) : ?>
							<div class="notice notice-success inline"><p>Logs técnicos de push apagados.</p></div>
						<?php endif; ?>
						<h3>Firebase Cloud Messaging — Push nativo Android</h3>
						<p>Status: <strong><?php echo ! empty( $firebase_status['configured'] ) ? 'Configurado' : 'Não configurado'; ?></strong> · Envio FCM: <strong><?php echo ! empty( $firebase_status['enabled'] ) ? 'Habilitado' : 'Desabilitado'; ?></strong></p>
						<?php if ( ! empty( $firebase_status['client_email'] ) ) : ?>
							<p>Service Account: <code><?php echo esc_html( $firebase_status['client_email'] ); ?></code></p>
						<?php endif; ?>
						<form method="post" action="">
							<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
							<input type="hidden" name="action" value="save_firebase_settings">
							<table class="form-table">
								<tr>
									<th scope="row">Habilitar Firebase FCM</th>
									<td><label><input type="checkbox" name="firebase_enabled" value="1" <?php checked( ! empty( $firebase_status['enabled'] ) ); ?>> Ativar envio de push Android pelo Firebase FCM</label><p class="description">Quando desabilitado, o plugin mantém as credenciais salvas, mas não envia tokens FCM nativos.</p></td>
								</tr>
								<tr>
									<th scope="row"><label for="firebase_project_id">Firebase Project ID</label></th>
									<td><input type="text" id="firebase_project_id" name="firebase_project_id" class="regular-text" value="<?php echo esc_attr( $firebase_status['project_id'] ); ?>" placeholder="ex: app-base-project"><p class="description">Deve ser o mesmo project_id do Firebase do app Android <code>com.appbase.app</code>.</p></td>
								</tr>
								<tr>
									<th scope="row"><label for="firebase_service_account_json">Service Account JSON</label></th>
									<td><textarea id="firebase_service_account_json" name="firebase_service_account_json" rows="10" class="large-text code" placeholder="Cole aqui o JSON da conta de serviço do Firebase para configurar ou substituir"></textarea><p class="description">Por segurança, a chave privada salva não é reexibida. Deixe em branco para manter a Service Account atual.</p></td>
								</tr>
							</table>
							<?php submit_button( 'Salvar Firebase FCM' ); ?>
						</form>
						<?php self::render_fcm_debugger_panel(); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function notification_error_message( $error ) {
		$messages = array(
			'missing_fields' => 'Preencha título e mensagem.',
			'invalid_schedule' => 'Data/hora inválida ou anterior ao horário atual.',
			'schedule_save_failed' => 'Não foi possível salvar o agendamento.',
			'favorites_requires_target' => 'Para enviar apenas aos favoritos, informe um evento/notícia como destino.',
		);
		return $messages[ $error ] ?? 'Não foi possível processar a notificação.';
	}

	private static function render_notifications_history() {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notifications' );
		if ( ! self::table_exists( $table ) ) {
			echo '<p>Tabela de notificações não encontrada. Reative o plugin ou aguarde a atualização do schema.</p>';
			return;
		}

		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT 20" );
		if ( ! $rows ) {
			echo '<p>Nenhuma notificação criada ainda.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Criada</th><th>Título</th><th>Imagem</th><th>Destino</th><th>Público</th><th>Disparo</th><th>Status</th><th>Push S/F</th><th>Inbox</th><th>Último erro</th><th>Ações</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row->created_at ) . '</td>';
			echo '<td>' . esc_html( $row->title ) . '</td>';
			if ( ! empty( $row->image_url ) ) {
				echo '<td><img src="' . esc_url( $row->image_url ) . '" alt="" style="max-width:72px;height:auto;border-radius:4px;border:1px solid #dcdcde;background:#fff;"></td>';
			} else {
				echo '<td>—</td>';
			}
			echo '<td>' . esc_html( $row->target_type . ( $row->target_id ? ': ' . $row->target_id : '' ) ) . '</td>';
			echo '<td>' . esc_html( 'favorites_only' === $row->audience ? 'Favoritos' : 'Todos' ) . '</td>';
			echo '<td>' . esc_html( 'schedule' === $row->send_mode ? 'Agendada: ' . $row->scheduled_at . ' / ' . $row->recurrence . ' x' . $row->repeat_total : 'Imediata' ) . '</td>';
			echo '<td>' . esc_html( self::get_status_label( $row->status ) ) . '</td>';
			echo '<td>' . esc_html( (int) $row->success_count . '/' . (int) $row->failure_count ) . '</td>';
			echo '<td>' . esc_html( self::count_internal_notifications( (int) $row->id ) ) . '</td>';
			echo '<td>' . esc_html( $row->last_error ?: '-' ) . '</td>';
			echo '<td><form method="post" action="" onsubmit="return confirm(\'Apagar esta notificação do histórico e da central interna?\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_notification"><input type="hidden" name="notification_id" value="' . esc_attr( $row->id ) . '">';
			submit_button( 'Apagar', 'delete small', 'submit', false );
			echo '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		self::render_notification_logs_table();
	}


	private static function render_notification_logs_table() {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_notification_logs' );
		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT 15" );
		if ( ! $rows ) {
			echo '<h3>Logs técnicos</h3><p>Nenhum log técnico de push registrado.</p>';
			return;
		}

		echo '<h3 style="margin-top:24px;">Logs técnicos de push</h3>';
		echo '<p class="description">Mostra provider, HTTP status, resposta do Firebase/Expo e erro técnico sem expor tokens completos.</p>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Data</th><th>Tipo</th><th>Status</th><th>Resposta técnica</th><th>Ações</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$response = json_decode( (string) $row->provider_response, true );
			$summary = '';
			if ( is_array( $response ) ) {
				$summary = wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} else {
				$summary = (string) $row->provider_response;
			}
			if ( strlen( $summary ) > 2200 ) {
				$summary = substr( $summary, 0, 2200 ) . "\n...";
			}

			echo '<tr>';
			echo '<td>' . esc_html( $row->created_at ) . '</td>';
			echo '<td>' . esc_html( $row->notification_type ) . '</td>';
			echo '<td>' . esc_html( $row->status ) . '</td>';
			echo '<td><pre style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#f6f7f7;padding:10px;border-radius:6px;">' . esc_html( $summary ) . '</pre></td>';
			echo '<td><form method="post" action="" onsubmit="return confirm(\'Apagar este log técnico?\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_notification_log"><input type="hidden" name="log_id" value="' . esc_attr( $row->id ) . '">';
			submit_button( 'Apagar log', 'delete small', 'submit', false );
			echo '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_fcm_debugger_panel() {
		$token = self::get_latest_fcm_token();
		$result = get_transient( 'ml_app_base_core_last_fcm_debug_result' );

		echo '<hr style="margin:24px 0;">';
		echo '<h3>Debugger de Push FCM</h3>';
		echo '<p>Use este teste para validar o Firebase contra o último dispositivo Android nativo cadastrado. O token completo não é exibido.</p>';
		echo '<p><strong>Último token FCM:</strong> ' . ( $token ? '<code>' . esc_html( ML_App_Base_Core_Push_Service::mask_token( $token ) ) . '</code>' : '<em>nenhum token FCM encontrado</em>' ) . '</p>';
		$status = ML_App_Base_Core_Push_Service::get_firebase_status();
		$can_test = $token && ! empty( $status['configured'] ) && ! empty( $status['enabled'] );
		if ( ! $can_test ) {
			echo '<p class="description"><strong>Para liberar o teste:</strong> deixe o Firebase configurado, marque “Habilitar Firebase FCM”, salve e mantenha ao menos um Android nativo logado.</p>';
		}
		echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:12px 0;">';
		echo '<form method="post" action="" style="margin:0;">';
		wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
		echo '<input type="hidden" name="action" value="send_fcm_debug_test">';
		echo '<button type="submit" class="button button-secondary"' . ( $can_test ? '' : ' disabled="disabled"' ) . '>Enviar teste FCM para último Android</button>';
		echo '</form>';
		echo '<form method="post" action="" style="margin:0;">';
		wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
		echo '<input type="hidden" name="action" value="reset_fcm_debugger">';
		echo '<button type="submit" class="button">Resetar debugger</button>';
		echo '</form>';
		echo '<form method="post" action="" style="margin:0;" onsubmit="return confirm(\'Apagar todos os logs técnicos de push?\');">';
		wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
		echo '<input type="hidden" name="action" value="reset_push_logs">';
		echo '<button type="submit" class="button button-link-delete">Resetar logs de push</button>';
		echo '</form>';
		echo '</div>';

		if ( is_array( $result ) ) {
			$debug = wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			echo '<h4>Último resultado do debugger</h4>';
			echo '<pre style="white-space:pre-wrap;max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border-radius:6px;">' . esc_html( $debug ) . '</pre>';
		}
	}

	private static function render_devices_table() {
		global $wpdb;
		$table = ML_App_Base_Core_Database::table( 'ml_app_base_devices' );
		$devices = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE deleted_at IS NULL ORDER BY last_seen_at DESC LIMIT 20" );
		if ( ! $devices ) {
			echo '<p>Nenhum dispositivo cadastrado.</p>';
			return;
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID Usuário</th><th>Plataforma</th><th>Versão App</th><th>Token (Mascarado)</th><th>Último Acesso</th></tr></thead><tbody>';
		foreach ( $devices as $dev ) {
			$masked_token = empty( $dev->expo_push_token ) ? 'N/A' : substr( $dev->expo_push_token, 0, 10 ) . '...';
			$provider = ML_App_Base_Core_Push_Service::is_expo_token( $dev->expo_push_token ) ? 'Expo legado' : 'FCM nativo';
			echo '<tr><td>' . esc_html( $dev->user_id ) . '</td><td>' . esc_html( $dev->platform ) . ' · ' . esc_html( $provider ) . '</td><td>' . esc_html( $dev->app_version ) . '</td><td><code>' . esc_html( $masked_token ) . '</code></td><td>' . esc_html( $dev->last_seen_at ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_user_action_notices() {
		$notices = array(
			'blocked_user'   => array( 'success', 'Usuário bloqueado. Sessões revogadas e dispositivos desativados.' ),
			'unblocked_user' => array( 'success', 'Usuário desbloqueado.' ),
			'deleted_user'   => array( 'success', 'Usuário excluído com segurança. Dados pessoais foram anonimizados.' ),
			'blocked_email'  => array( 'success', 'E-mail bloqueado.' ),
			'unblocked_email'=> array( 'success', 'E-mail desbloqueado.' ),
		);

		foreach ( $notices as $key => $notice ) {
			if ( isset( $_GET[ $key ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' inline"><p>' . esc_html( $notice[1] ) . '</p></div>';
			}
		}

		if ( isset( $_GET['user_error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( self::user_error_message( sanitize_key( wp_unslash( $_GET['user_error'] ) ) ) ) . '</p></div>';
		}
	}

	private static function user_error_message( $error ) {
		$messages = array(
			'invalid_user'  => 'Usuário inválido.',
			'not_found'     => 'Usuário não encontrado ou já removido.',
			'invalid_email' => 'Informe um e-mail válido.',
		);
		return $messages[ $error ] ?? 'Não foi possível processar a ação.';
	}

	private static function render_blocked_emails_panel() {
		$blocked = ML_App_Base_Core_Users_Repository::get_blocked_emails();
		?>
		<form method="post" action="" style="margin-bottom: 18px;">
			<?php wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' ); ?>
			<input type="hidden" name="action" value="block_email">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="blocked_email">E-mail</label></th>
					<td><input type="email" id="blocked_email" name="blocked_email" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="blocked_reason">Motivo</label></th>
					<td><input type="text" id="blocked_reason" name="blocked_reason" class="regular-text" placeholder="Opcional"></td>
				</tr>
			</table>
			<?php submit_button( 'Bloquear e-mail', 'secondary', 'submit', false ); ?>
		</form>
		<?php

		if ( empty( $blocked ) ) {
			echo '<p>Nenhum e-mail bloqueado.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>E-mail</th><th>Motivo</th><th>Bloqueado em</th><th>Ação</th></tr></thead><tbody>';
		foreach ( $blocked as $entry ) {
			$email = isset( $entry['email'] ) ? $entry['email'] : '';
			echo '<tr>';
			echo '<td><code>' . esc_html( $email ) . '</code></td>';
			echo '<td>' . esc_html( $entry['reason'] ?? '-' ) . '</td>';
			echo '<td>' . esc_html( $entry['blocked_at'] ?? '-' ) . '</td>';
			echo '<td><form method="post" action="" style="display:inline;">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="unblock_email">';
			echo '<input type="hidden" name="blocked_email" value="' . esc_attr( $email ) . '">';
			submit_button( 'Desbloquear', 'secondary', 'submit', false );
			echo '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_users_table() {
		$users = ML_App_Base_Core_Users_Repository::get_recent_admin_users( 50 );
		if ( ! $users ) {
			echo '<p>Nenhum usuário cadastrado.</p>';
			return;
		}

		echo '<p>Bloquear usuário revoga sessões, desativa dispositivos e também bloqueia o e-mail. Excluir usuário revoga sessões, remove dispositivos/favoritos/consentimentos e anonimiza dados pessoais sem apagar tabelas.</p>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Aniversário</th><th>Status</th><th>Cadastro</th><th>Ações</th></tr></thead><tbody>';
		foreach ( $users as $u ) {
			$birthday = ( null !== $u->birth_day && null !== $u->birth_month ) ? sprintf( '%02d/%02d', (int) $u->birth_day, (int) $u->birth_month ) : '-';
			echo '<tr>';
			echo '<td>' . esc_html( $u->id ) . '</td>';
			echo '<td>' . esc_html( $u->name ) . '</td>';
			echo '<td><code>' . esc_html( $u->email ) . '</code></td>';
			echo '<td>' . esc_html( $u->phone ?: '-' ) . '</td>';
			echo '<td>' . esc_html( $birthday ) . '</td>';
			echo '<td>' . esc_html( 'blocked' === $u->status ? 'Bloqueado' : 'Ativo' ) . '</td>';
			echo '<td>' . esc_html( $u->created_at ) . '</td>';
			echo '<td>';

			if ( 'blocked' === $u->status ) {
				echo '<form method="post" action="" style="display:inline-block;margin-right:6px;">';
				wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
				echo '<input type="hidden" name="action" value="unblock_user">';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( $u->id ) . '">';
				submit_button( 'Desbloquear', 'secondary', 'submit', false );
				echo '</form>';
			} else {
				echo '<form method="post" action="" style="display:inline-block;margin-right:6px;">';
				wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
				echo '<input type="hidden" name="action" value="block_user">';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( $u->id ) . '">';
				submit_button( 'Bloquear', 'secondary', 'submit', false );
				echo '</form>';
			}

			echo '<form method="post" action="" style="display:inline-block;" onsubmit="return confirm(\'Excluir este usuário? Sessões, dispositivos, favoritos e consentimentos serão removidos. Dados pessoais serão anonimizados.\');">';
			wp_nonce_field( 'ml_app_base_action', 'ml_app_base_nonce' );
			echo '<input type="hidden" name="action" value="delete_user">';
			echo '<input type="hidden" name="user_id" value="' . esc_attr( $u->id ) . '">';
			echo '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="block_email_too" value="1"> bloquear e-mail também</label>';
			submit_button( 'Excluir', 'delete', 'submit', false );
			echo '</form>';

			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

}
