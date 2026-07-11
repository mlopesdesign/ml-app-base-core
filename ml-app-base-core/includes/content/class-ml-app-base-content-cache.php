<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Cache {
    public function init() {
        add_action( 'save_post', array( $this, 'clear_post_cache' ) );
        add_action( 'deleted_post', array( $this, 'clear_post_cache' ) );
        add_action( 'clean_post_cache', array( $this, 'clear_post_cache' ) );
    }

    public function clear_post_cache( $post_id ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        global $wpdb;
        $prefix = '_transient_ml_app_base_content_';
        
        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            )
        );

        foreach ( $options as $option ) {
            $transient_name = str_replace( '_transient_', '', $option );
            delete_transient( $transient_name );
        }
    }

    public static function get( $key ) {
        return get_transient( 'ml_app_base_content_' . $key );
    }

    public static function set( $key, $value, $expiration ) {
        set_transient( 'ml_app_base_content_' . $key, $value, $expiration );
    }
}
