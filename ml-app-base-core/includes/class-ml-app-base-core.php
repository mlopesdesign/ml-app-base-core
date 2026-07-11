<?php
/**
 * Main plugin orchestrator.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core
 *
 * Singleton that wires together all plugin components:
 * - REST API route registration on rest_api_init.
 * - Admin menu registration (when in WP admin).
 * - Schema upgrade check on admin_init.
 */
class ML_App_Base_Core {

	/**
	 * Plugin instance.
	 *
	 * @var ML_App_Base_Core|null
	 */
	private static $instance = null;

	/**
	 * Get or create the plugin singleton.
	 *
	 * @return ML_App_Base_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — attach hooks.
	 */
	private function __construct() {
		$this->attach_hooks();
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	private function attach_hooks() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( 'ML_App_Base_Core_REST_API', 'register_routes' ) );

		// Process scheduled push notifications created in the admin panel.
		add_action( 'ml_app_base_core_send_scheduled_notification', array( 'ML_App_Base_Core_Admin', 'send_scheduled_notification' ), 10, 1 );

		// Register admin page and assets.
		if ( is_admin() ) {
			ML_App_Base_Core_Admin::register();
			add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
		}
	}

	/**
	 * Run schema upgrades if the stored version differs from the current one.
	 * Called on admin_init so upgrades are only attempted when an admin is present.
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema() {
		if ( ! ML_App_Base_Core_Database::is_schema_current() ) {
			ML_App_Base_Core_Database::create_tables();
		}
	}
}
