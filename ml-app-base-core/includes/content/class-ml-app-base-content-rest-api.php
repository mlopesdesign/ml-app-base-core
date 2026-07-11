<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_REST_API {
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'ml-app-base/v1';

        register_rest_route( $namespace, '/home', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_home' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/home-config', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_home_config' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/events', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_pagination_args(),
        ) );

        register_rest_route( $namespace, '/events/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_event' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/news', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_news' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_pagination_args(),
        ) );

        register_rest_route( $namespace, '/news/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_news_item' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/banners', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_banners' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_pagination_args(),
        ) );

        register_rest_route( $namespace, '/gallery-highlights', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_gallery_highlights' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_pagination_args(),
        ) );


        register_rest_route( $namespace, '/photo-albums', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_photo_albums' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_pagination_args(),
        ) );

        register_rest_route( $namespace, '/institutional', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_institutional' ),
            'permission_callback' => '__return_true',
        ) );
    }

    private function get_pagination_args() {
        return array(
            'page' => array(
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default'           => 10,
                'sanitize_callback' => function( $param ) {
                    $val = absint( $param );
                    return $val > 20 ? 20 : max( 1, $val );
                },
            ),
        );
    }

    public function get_home( $request ) {
        $cache_key = 'home_v033_' . md5( wp_json_encode( ML_App_Base_Content_Settings::home_controls() ) );
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success( $cached );
        }

        $home_controls = ML_App_Base_Content_Settings::home_controls();
        $events_limit  = ! empty( $home_controls['events']['limit'] ) ? (int) $home_controls['events']['limit'] : 10;
        $news_limit    = ! empty( $home_controls['news']['limit'] ) ? (int) $home_controls['news']['limit'] : 5;
        $banners_limit = ! empty( $home_controls['banners']['limit'] ) ? (int) $home_controls['banners']['limit'] : $events_limit;
        $photo_albums_limit = ! empty( $home_controls['photo_albums']['home_limit'] ) ? (int) $home_controls['photo_albums']['home_limit'] : 6;

        $events  = ! empty( $home_controls['events']['enabled'] ) ? ML_App_Base_Content_Query::get_events( 1, $events_limit ) : array( 'items' => array(), 'total' => 0 );
        $news    = ! empty( $home_controls['news']['enabled'] ) ? ML_App_Base_Content_Query::get_news( 1, $news_limit ) : array( 'items' => array(), 'total' => 0 );
        $banners = ! empty( $home_controls['banners']['enabled'] ) ? ML_App_Base_Content_Query::get_banners( 1, $banners_limit ) : array( 'items' => array(), 'total' => 0 );
        $gallery = ML_App_Base_Content_Query::get_gallery_highlights( 1, 6 );
        $photo_albums = ! empty( $home_controls['photo_albums']['enabled'] ) ? ML_App_Base_Content_Query::get_photo_albums( 1, $photo_albums_limit ) : array( 'items' => array(), 'total' => 0 );

        $data = array(
            'plugin_version'     => ML_APP_BASE_CONTENT_VERSION,
            'build_marker'       => 'content-controls-0.1.33',
            'app_name'           => get_bloginfo( 'name' ),
            'organization'       => 'App Base',
            'latest_events'      => $events['items'],
            'latest_news'        => $news['items'],
            'banners'            => $banners['items'],
            'gallery_highlights' => $gallery['items'],
            'photo_albums'       => $photo_albums['items'],
            'home_header'        => ML_App_Base_Content_Settings::home_header(),
            'home_controls'      => $home_controls,
            'section_titles'     => array(
                'latest_events' => $home_controls['events']['title'],
                'latest_news'   => $home_controls['news']['title'],
                'banners'       => $home_controls['banners']['title'],
                'photo_albums'  => $home_controls['photo_albums']['title'],
            ),
            'institutional'      => array(
                'name'        => get_bloginfo( 'name' ),
                'description' => get_bloginfo( 'description' ),
            ),
            'sources'            => array(
                'latest_events' => 'ml_banner_pro',
                'banners'       => 'ml_banner_pro',
                'latest_news'   => 'wordpress_category',
                'photo_albums'  => 'ml_gallery_pro',
            ),
        );

        ML_App_Base_Content_Cache::set( $cache_key, $data, 300 );
        return ML_App_Base_Content_Response::success( $data );
    }

    public function get_home_config( $request ) {
        return ML_App_Base_Content_Response::success( array(
            'home_header'   => ML_App_Base_Content_Settings::home_header(),
            'home_controls' => ML_App_Base_Content_Settings::home_controls(),
            'institutional' => ML_App_Base_Content_Settings::institutional(),
            'sources'       => array(
                'latest_events' => 'ml_banner_pro',
                'banners'       => 'ml_banner_pro',
                'photo_albums'  => 'ml_gallery_pro',
            ),
        ) );
    }

    public function get_events( $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $cache_key = 'events_ml_banner_v029_' . $page . '_' . $per_page . '_' . sanitize_key( ML_App_Base_Content_Settings::get( 'ml_banner_group', '' ) );
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success_list( $cached['items'], $page, $per_page, $cached['total'] );
        }

        $result = ML_App_Base_Content_Query::get_events( $page, $per_page );
        ML_App_Base_Content_Cache::set( $cache_key, $result, 300 );
        return ML_App_Base_Content_Response::success_list( $result['items'], $page, $per_page, $result['total'] );
    }

    public function get_event( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $post = get_post( $id );
        if ( ! $post ) {
            return ML_App_Base_Content_Response::error( 'not_found', 'Event not found', 404 );
        }

        if ( 'mlcp_item' === $post->post_type ) {
            $item = ML_App_Base_Content_Normalizer::normalize_ml_banner( $post );
        } else {
            $item = ML_App_Base_Content_Normalizer::normalize_post( $post, true );
        }

        if ( ! $item ) {
            return ML_App_Base_Content_Response::error( 'not_found', 'Event not found', 404 );
        }

        return ML_App_Base_Content_Response::success( $item );
    }

    public function get_news( $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $cache_key = 'news_v029_' . $page . '_' . $per_page . '_' . md5( wp_json_encode( ML_App_Base_Content_Settings::news_category_slugs() ) );
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success_list( $cached['items'], $page, $per_page, $cached['total'] );
        }

        $result = ML_App_Base_Content_Query::get_news( $page, $per_page );
        ML_App_Base_Content_Cache::set( $cache_key, $result, 300 );
        return ML_App_Base_Content_Response::success_list( $result['items'], $page, $per_page, $result['total'] );
    }

    public function get_news_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $item = ML_App_Base_Content_Normalizer::normalize_post( $id, true );
        if ( ! $item ) {
            return ML_App_Base_Content_Response::error( 'not_found', 'News not found', 404 );
        }
        return ML_App_Base_Content_Response::success( $item );
    }

    public function get_banners( $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $cache_key = 'banners_ml_banner_v029_' . $page . '_' . $per_page . '_' . sanitize_key( ML_App_Base_Content_Settings::get( 'ml_banner_group', '' ) );
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success_list( $cached['items'], $page, $per_page, $cached['total'] );
        }

        $result = ML_App_Base_Content_Query::get_banners( $page, $per_page );
        ML_App_Base_Content_Cache::set( $cache_key, $result, 300 );
        return ML_App_Base_Content_Response::success_list( $result['items'], $page, $per_page, $result['total'] );
    }

    public function get_gallery_highlights( $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $cache_key = 'gallery_' . $page . '_' . $per_page;
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success_list( $cached['items'], $page, $per_page, $cached['total'] );
        }

        $result = ML_App_Base_Content_Query::get_gallery_highlights( $page, $per_page );
        ML_App_Base_Content_Cache::set( $cache_key, $result, 600 );
        return ML_App_Base_Content_Response::success_list( $result['items'], $page, $per_page, $result['total'] );
    }


    public function get_photo_albums( $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $cache_key = 'photo_albums_mlgp_v033_' . $page . '_' . $per_page;
        $cached = ML_App_Base_Content_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return ML_App_Base_Content_Response::success_list( $cached['items'], $page, $per_page, $cached['total'] );
        }

        $result = ML_App_Base_Content_Query::get_photo_albums( $page, $per_page );
        ML_App_Base_Content_Cache::set( $cache_key, $result, 300 );
        return ML_App_Base_Content_Response::success_list( $result['items'], $page, $per_page, $result['total'] );
    }

    public function get_institutional( $request ) {
        return ML_App_Base_Content_Response::success( ML_App_Base_Content_Settings::institutional() );
    }
}
