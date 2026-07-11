<?php
/**
 * Plugin activator — runs on plugin activation.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ML_App_Base_Core_Activator
 *
 * Executed when the plugin is activated via the WordPress admin.
 * Creates database tables and stores the schema version option.
 * Safe to run repeatedly — dbDelta handles existing tables gracefully.
 *
 * No DROP, TRUNCATE, or destructive operations are performed.
 * No app data is ever deleted during activation.
 */
class ML_App_Base_Core_Activator {

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		self::load_plugin_admin_functions();

		// Verify minimum PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			self::deactivate_current_plugin_safely();
			wp_die(
				esc_html__(
					'ML App Base Core requires PHP 7.4 or higher. Please upgrade PHP before activating this plugin.',
					'ml-app-base-core'
				)
			);
		}

		// Verify minimum WordPress version.
		global $wp_version;
		if ( version_compare( (string) $wp_version, '6.0', '<' ) ) {
			self::deactivate_current_plugin_safely();
			wp_die(
				esc_html__(
					'ML App Base Core requires WordPress 6.0 or higher.',
					'ml-app-base-core'
				)
			);
		}

		// Create or update all plugin database tables.
		ML_App_Base_Core_Database::create_tables();

		// Flush rewrite rules so REST routes register immediately.
		flush_rewrite_rules();
	}

	/**
	 * Load WordPress plugin admin functions when activation runs in a context where
	 * they have not been loaded yet.
	 *
	 * @return void
	 */
	private static function load_plugin_admin_functions() {
		if ( function_exists( 'deactivate_plugins' ) ) {
			return;
		}

		$plugin_functions_file = ABSPATH . 'wp-admin/includes/plugin.php';
		if ( file_exists( $plugin_functions_file ) ) {
			require_once $plugin_functions_file;
		}
	}

	/**
	 * Deactivate this plugin only when WordPress provides the required function.
	 * This prevents activation fatals in restricted or partially loaded contexts.
	 *
	 * @return void
	 */
	private static function deactivate_current_plugin_safely() {
		if ( function_exists( 'deactivate_plugins' ) && defined( 'ML_APP_BASE_CORE_BASENAME' ) ) {
			deactivate_plugins( ML_APP_BASE_CORE_BASENAME );
		}
	}
}
