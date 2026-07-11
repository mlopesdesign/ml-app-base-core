<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Settings {
    const OPTION_KEY = 'ml_app_base_content_settings';

    public static function defaults() {
        return array(
            'home_header_enabled'          => 1,
            'home_header_logo'             => '',
            'home_header_logo_size'        => 'large',
            'home_header_title'            => 'App Base',
            'home_header_subtitle'         => 'O Doce Refúgio do Samba',
            'home_header_background_color' => '#ffffff',
            'home_header_text_color'       => '#000000',

            'home_news_enabled'            => 1,
            'home_news_title'              => 'Últimas Notícias',
            'home_news_limit'              => 5,
            'home_news_show_image'         => 1,
            'home_news_show_excerpt'       => 1,
            'home_news_show_date'          => 0,
            'home_news_show_read_more'     => 1,
            'home_news_open_in_site'       => 1,
            'home_news_category_slugs'     => array( 'ultimas-noticias', 'ultimas_noticias', 'noticias', 'feijoadas', 'feijoada' ),

            'home_events_enabled'          => 1,
            'home_events_title'            => 'Próximos Eventos',
            'home_events_limit'            => 10,
            'home_events_banner_only'      => 1,
            'home_events_show_favorite'    => 1,
            'home_events_open_link'        => 1,
            'home_events_fallback_expand'  => 1,

            'home_banners_enabled'         => 1,
            'home_banners_title'           => 'Banners',
            'home_banners_limit'           => 10,

            'home_photo_albums_enabled'    => 1,
            'home_photo_albums_title'      => 'Álbuns de Fotos',
            'home_photo_albums_limit'      => 6,
            'photos_screen_title'          => 'Fotos',
            'photos_screen_limit'          => 10,
            'home_photo_albums_show_title' => 1,
            'home_photo_albums_show_date'  => 0,
            'home_photo_albums_show_button'=> 1,
            'home_photo_albums_button_label' => 'Ver fotos',

            'about_title'                  => 'Sobre o App Base',
            'about_content'                => '',
            'about_image'                  => '',
            'about_address'                => '',
            'about_phone'                  => '',
            'about_email'                  => '',
            'about_whatsapp'               => '',
            'about_site'                   => home_url( '/' ),
            'about_instagram'              => '',
            'about_facebook'               => '',
            'about_youtube'                => '',
            'about_tiktok'                 => '',
            'about_twitter'                => '',
            'about_social_icon_color'      => '#990000',
            'about_social_icon_background' => '#ffffff',
            'about_social_icon_size'       => 'medium',

            'ml_banner_group'              => '',
            'ml_banner_limit'              => 10,
        );
    }

    public static function all() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $defaults = self::defaults();
        foreach ( $defaults as $key => $default ) {
            $legacy = get_option( 'ml_app_base_content_' . $key, null );
            if ( null !== $legacy && '' !== $legacy && ! array_key_exists( $key, $saved ) ) {
                $saved[ $key ] = $legacy;
            }
        }

        return wp_parse_args( $saved, $defaults );
    }

    public static function get( $key, $default = null ) {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function update( array $data ) {
        $settings = self::all();
        $settings = array_merge( $settings, self::sanitize( $data ) );
        update_option( self::OPTION_KEY, $settings, false );
        self::clear_cache();
        return $settings;
    }

    public static function sanitize( array $data ) {
        $clean = array();

        $text_fields = array(
            'home_header_logo_size',
            'home_header_title',
            'home_header_subtitle',
            'home_news_title',
            'home_events_title',
            'home_banners_title',
            'home_photo_albums_title',
            'photos_screen_title',
            'home_photo_albums_button_label',
            'about_title',
            'about_address',
            'about_phone',
            'about_whatsapp',
            'ml_banner_group',
        );

        foreach ( $text_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $clean[ $field ] = sanitize_text_field( wp_unslash( $data[ $field ] ) );
            }
        }

        $textarea_fields = array( 'about_content' );
        foreach ( $textarea_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $clean[ $field ] = sanitize_textarea_field( wp_unslash( $data[ $field ] ) );
            }
        }

        $url_fields = array(
            'home_header_logo',
            'about_image',
            'about_site',
            'about_instagram',
            'about_facebook',
            'about_youtube',
            'about_tiktok',
            'about_twitter',
        );
        foreach ( $url_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $clean[ $field ] = esc_url_raw( wp_unslash( $data[ $field ] ) );
            }
        }

        if ( array_key_exists( 'about_email', $data ) ) {
            $clean['about_email'] = sanitize_email( wp_unslash( $data['about_email'] ) );
        }

        if ( array_key_exists( 'about_social_icon_size', $data ) ) {
            $size = sanitize_key( wp_unslash( $data['about_social_icon_size'] ) );
            $clean['about_social_icon_size'] = in_array( $size, array( 'small', 'medium', 'large' ), true ) ? $size : self::defaults()['about_social_icon_size'];
        }

        foreach ( array( 'home_header_background_color', 'home_header_text_color', 'about_social_icon_color', 'about_social_icon_background' ) as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $hex = sanitize_hex_color( wp_unslash( $data[ $field ] ) );
                $defaults = self::defaults();
                $clean[ $field ] = $hex ? $hex : $defaults[ $field ];
            }
        }

        $boolean_fields = array(
            'home_header_enabled',
            'home_news_enabled',
            'home_news_show_image',
            'home_news_show_excerpt',
            'home_news_show_date',
            'home_news_show_read_more',
            'home_news_open_in_site',
            'home_events_enabled',
            'home_events_banner_only',
            'home_events_show_favorite',
            'home_events_open_link',
            'home_events_fallback_expand',
            'home_banners_enabled',
            'home_photo_albums_enabled',
            'home_photo_albums_show_title',
            'home_photo_albums_show_date',
            'home_photo_albums_show_button',
        );

        foreach ( $boolean_fields as $field ) {
            $clean[ $field ] = ! empty( $data[ $field ] ) ? 1 : 0;
        }

        $limit_fields = array(
            'home_news_limit'     => 20,
            'home_events_limit'   => 20,
            'home_banners_limit'  => 20,
            'home_photo_albums_limit' => 20,
            'photos_screen_limit' => 20,
            'ml_banner_limit'     => 20,
        );

        foreach ( $limit_fields as $field => $max ) {
            if ( array_key_exists( $field, $data ) ) {
                $limit = absint( $data[ $field ] );
                $clean[ $field ] = $limit > 0 ? min( $limit, $max ) : self::defaults()[ $field ];
            }
        }

        if ( array_key_exists( 'home_news_category_slugs', $data ) ) {
            $slugs = $data['home_news_category_slugs'];
            if ( ! is_array( $slugs ) ) {
                $slugs = array( $slugs );
            }
            $clean['home_news_category_slugs'] = array_values( array_unique( array_filter( array_map( 'sanitize_title', wp_unslash( $slugs ) ) ) ) );
        }

        if ( isset( $clean['home_events_limit'] ) ) {
            $clean['ml_banner_limit'] = $clean['home_events_limit'];
        }
        if ( isset( $clean['home_banners_limit'] ) ) {
            $clean['ml_banner_limit'] = $clean['home_banners_limit'];
        }

        return $clean;
    }

    public static function home_header() {
        $settings = self::all();
        return array(
            'home_header_enabled'          => (bool) $settings['home_header_enabled'],
            'home_header_logo'             => (string) $settings['home_header_logo'],
            'home_header_logo_size'        => (string) $settings['home_header_logo_size'],
            'home_header_title'            => (string) $settings['home_header_title'],
            'home_header_subtitle'         => (string) $settings['home_header_subtitle'],
            'home_header_background_color' => (string) $settings['home_header_background_color'],
            'home_header_text_color'       => (string) $settings['home_header_text_color'],
        );
    }

    public static function home_controls() {
        $settings = self::all();
        return array(
            'news' => array(
                'enabled'        => (bool) $settings['home_news_enabled'],
                'title'          => (string) $settings['home_news_title'],
                'limit'          => (int) $settings['home_news_limit'],
                'show_image'     => (bool) $settings['home_news_show_image'],
                'show_excerpt'   => (bool) $settings['home_news_show_excerpt'],
                'show_date'      => (bool) $settings['home_news_show_date'],
                'show_read_more' => (bool) $settings['home_news_show_read_more'],
                'open_in_site'   => (bool) $settings['home_news_open_in_site'],
                'category_slugs'  => self::news_category_slugs(),
            ),
            'events' => array(
                'enabled'         => (bool) $settings['home_events_enabled'],
                'title'           => (string) $settings['home_events_title'],
                'limit'           => (int) $settings['home_events_limit'],
                'banner_only'     => (bool) $settings['home_events_banner_only'],
                'show_favorite'   => (bool) $settings['home_events_show_favorite'],
                'open_link'       => (bool) $settings['home_events_open_link'],
                'fallback_expand' => (bool) $settings['home_events_fallback_expand'],
                'source'          => 'ml_banner_pro',
            ),
            'banners' => array(
                'enabled' => (bool) $settings['home_banners_enabled'],
                'title'   => (string) $settings['home_banners_title'],
                'limit'   => (int) $settings['home_banners_limit'],
                'source'  => 'ml_banner_pro',
            ),
            'photo_albums' => array(
                'enabled'            => (bool) $settings['home_photo_albums_enabled'],
                'title'              => (string) $settings['home_photo_albums_title'],
                'home_limit'         => (int) $settings['home_photo_albums_limit'],
                'screen_title'       => (string) $settings['photos_screen_title'],
                'screen_limit'       => (int) $settings['photos_screen_limit'],
                'show_title'         => (bool) $settings['home_photo_albums_show_title'],
                'show_date'          => (bool) $settings['home_photo_albums_show_date'],
                'show_button'        => (bool) $settings['home_photo_albums_show_button'],
                'button_label'       => (string) $settings['home_photo_albums_button_label'],
                'source'             => 'ml_gallery_pro',
            ),
        );
    }

    public static function news_category_slugs() {
        $settings = self::all();
        $slugs = isset( $settings['home_news_category_slugs'] ) ? $settings['home_news_category_slugs'] : array();
        if ( ! is_array( $slugs ) ) {
            $slugs = array_filter( array_map( 'trim', explode( ',', (string) $slugs ) ) );
        }

        $slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $slugs ) ) ) );
        if ( empty( $slugs ) ) {
            $slugs = array( 'ultimas-noticias', 'ultimas_noticias', 'noticias', 'feijoadas', 'feijoada' );
        }

        return $slugs;
    }

    public static function institutional() {
        $settings = self::all();
        return array(
            'about_title'     => (string) $settings['about_title'],
            'about_content'   => (string) $settings['about_content'],
            'about_image'     => (string) $settings['about_image'],
            'about_address'   => (string) $settings['about_address'],
            'about_phone'     => (string) $settings['about_phone'],
            'about_whatsapp'  => (string) $settings['about_whatsapp'],
            'about_site'      => (string) $settings['about_site'],
            'about_instagram' => (string) $settings['about_instagram'],
            'about_facebook'  => (string) $settings['about_facebook'],
            'about_youtube'   => (string) $settings['about_youtube'],
            'about_tiktok'    => (string) $settings['about_tiktok'],
            'about_twitter'   => (string) $settings['about_twitter'],
            'social_icon_color'      => (string) $settings['about_social_icon_color'],
            'social_icon_background' => (string) $settings['about_social_icon_background'],
            'social_icon_size'       => (string) $settings['about_social_icon_size'],
            'contact' => array(
                'address'  => (string) $settings['about_address'],
                'phone'    => (string) $settings['about_phone'],
                'email'    => (string) $settings['about_email'],
                'whatsapp' => (string) $settings['about_whatsapp'],
                'site'     => (string) $settings['about_site'],
            ),
            'social_links' => array_values( array_filter( array(
                self::social_link( 'site', 'Site', $settings['about_site'] ),
                self::social_link( 'instagram', 'Instagram', $settings['about_instagram'] ),
                self::social_link( 'facebook', 'Facebook', $settings['about_facebook'] ),
                self::social_link( 'youtube', 'YouTube', $settings['about_youtube'] ),
                self::social_link( 'tiktok', 'TikTok', $settings['about_tiktok'] ),
                self::social_link( 'twitter', 'Twitter / X', $settings['about_twitter'] ),
                self::social_link( 'whatsapp', 'WhatsApp', self::whatsapp_url( $settings['about_whatsapp'] ) ),
                self::social_link( 'email', 'E-mail', self::mailto_url( $settings['about_email'] ) ),
            ) ) ),
            'social_style' => array(
                'icon_color'      => (string) $settings['about_social_icon_color'],
                'icon_background' => (string) $settings['about_social_icon_background'],
                'icon_size'       => (string) $settings['about_social_icon_size'],
            ),
        );
    }


    private static function social_link( $network, $label, $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return null;
        }
        return array(
            'network' => (string) $network,
            'label'   => (string) $label,
            'url'     => (string) $url,
        );
    }

    private static function mailto_url( $email ) {
        $email = sanitize_email( (string) $email );
        return $email ? 'mailto:' . $email : '';
    }

    private static function whatsapp_url( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        return $digits ? 'https://wa.me/' . $digits : '';
    }

    public static function clear_cache() {
        global $wpdb;
        $prefix = '_transient_ml_app_base_content_';
        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            )
        );
        foreach ( $options as $option ) {
            delete_transient( str_replace( '_transient_', '', $option ) );
        }
    }
}
