<?php
/**
 * Plugin Name: ML App Base Core
 * Plugin URI:  https://mlopesdesign.com.br
 * Description: Base WordPress backend for reusable native mobile apps: auth, users, devices, FCM push, internal notifications, menu/cardapio, home, news, events, photo albums, settings, logs and debugging.
 * Version:     1.1.1
 * Author:      ML Lopes Design
 * Author URI:  https://mlopesdesign.com.br
 * Text Domain: ml-app-base-core
 * Update URI:  https://github.com/mlopesdesign/ml-app-base-core-releases
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ML_APP_BASE_CORE_VERSION',  '1.1.1' );
define( 'ML_APP_BASE_CORE_FILE',     __FILE__ );
define( 'ML_APP_BASE_CORE_PATH',     plugin_dir_path( __FILE__ ) );
define( 'ML_APP_BASE_CORE_URL',      plugin_dir_url( __FILE__ ) );
define( 'ML_APP_BASE_CORE_BASENAME', plugin_basename( __FILE__ ) );

// Compatibility constants for the embedded content engine.
define( 'ML_APP_BASE_CONTENT_VERSION', ML_APP_BASE_CORE_VERSION );
define( 'ML_APP_BASE_CONTENT_FILE',    __FILE__ );
define( 'ML_APP_BASE_CONTENT_PATH',    ML_APP_BASE_CORE_PATH );
define( 'ML_APP_BASE_CONTENT_URL',     ML_APP_BASE_CORE_URL );
define( 'ML_APP_BASE_CONTENT_BASENAME', ML_APP_BASE_CORE_BASENAME );

$ml_app_base_core_includes = array(
    'includes/class-ml-app-base-core-response.php',
    'includes/class-ml-app-base-core-database.php',
    'includes/class-ml-app-base-core-activator.php',
    'includes/class-ml-app-base-core-token-service.php',
    'includes/class-ml-app-base-core-users-repository.php',
    'includes/class-ml-app-base-core-devices-repository.php',
    'includes/class-ml-app-base-core-favorites-repository.php',
    'includes/class-ml-app-base-core-consents-repository.php',
    'includes/class-ml-app-base-core-notifications-repository.php',
    'includes/class-ml-app-base-core-push-service.php',
    'includes/class-ml-app-base-core-menu.php',
    'includes/class-ml-app-base-core-auth.php',
    'includes/class-ml-app-base-core-rest-api.php',
    'includes/class-ml-app-base-core-admin.php',
    'includes/class-ml-app-base-core.php',
    'includes/Core/Updater.php',
    'includes/content/class-ml-app-base-content-cache.php',
    'includes/content/class-ml-app-base-content-response.php',
    'includes/content/class-ml-app-base-content-settings.php',
    'includes/content/class-ml-app-base-content-normalizer.php',
    'includes/content/class-ml-app-base-content-query.php',
    'includes/content/class-ml-app-base-content-rest-api.php',
    'includes/content/class-ml-app-base-content-admin.php',
    'includes/content/class-ml-app-base-content.php',

);

foreach ( $ml_app_base_core_includes as $ml_app_base_core_file ) {
    $ml_app_base_core_path = ML_APP_BASE_CORE_PATH . $ml_app_base_core_file;
    if ( file_exists( $ml_app_base_core_path ) ) {
        require_once $ml_app_base_core_path;
    }
}

register_activation_hook( __FILE__, array( 'ML_App_Base_Core_Activator', 'activate' ) );

function ml_app_base_core() {
    return ML_App_Base_Core::get_instance();
}

ml_app_base_core();



if ( class_exists( 'ML_App_Base_Core_Updater' ) ) {
    $ml_app_base_updater = new ML_App_Base_Core_Updater(
        ML_APP_BASE_CORE_BASENAME,
        'ml-app-base-core',
        'mlopesdesign/ml-app-base-core-releases',
        ML_APP_BASE_CORE_VERSION
    );
    $ml_app_base_updater->hooks();
}

if ( class_exists( 'ML_App_Base_Content' ) ) {
    $ml_app_base_content = new ML_App_Base_Content();
    $ml_app_base_content->run();
}
