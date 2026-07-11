<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content {
    public function run() {
        $cache = new ML_App_Base_Content_Cache();
        $cache->init();

        $rest_api = new ML_App_Base_Content_REST_API();
        $rest_api->init();

        if ( is_admin() ) {
            $admin = new ML_App_Base_Content_Admin();
            $admin->init();
        }
    }
}
