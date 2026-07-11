<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Normalizer {
    public static function normalize_post( $post, $include_content = false ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return null;
        }

        $image = self::get_post_image( $post->ID );
        $permalink = get_permalink( $post );

        $item = array(
            'id'                       => (string) $post->ID,
            'type'                     => $post->post_type,
            'title'                    => self::clean_text( get_the_title( $post ) ),
            'slug'                     => $post->post_name,
            'excerpt'                  => self::clean_excerpt( $post ),
            'date'                     => get_post_time( 'c', true, $post ),
            'display_date'             => get_the_date( 'd/m/Y', $post ),
            'image'                    => $image,
            'url'                      => $permalink,
            'permalink'                => $permalink,
            'link'                     => $permalink,
            'target_url'               => $permalink,
            'app_show_on_home'         => (bool) get_post_meta( $post->ID, '_ml_app_base_show_on_home', true ),
            'app_display_mode'         => (string) ( get_post_meta( $post->ID, '_ml_app_base_display_mode', true ) ?: 'banner_only' ),
            'app_allow_favorite'       => (bool) get_post_meta( $post->ID, '_ml_app_base_allow_favorite', true ),
            'app_notification_enabled' => (bool) get_post_meta( $post->ID, '_ml_app_base_notification_enabled', true ),
            'source'                   => 'wordpress_post',
            'meta'                     => new stdClass(),
        );

        if ( $include_content ) {
            $item['content'] = self::clean_content( $post->post_content );
        }

        return $item;
    }

    public static function normalize_ml_banner( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return null;
        }

        $image_id  = (int) get_post_meta( $post->ID, '_mlcp_image_id', true );
        $image_url = (string) get_post_meta( $post->ID, '_mlcp_image_url', true );
        $link      = esc_url_raw( (string) get_post_meta( $post->ID, '_mlcp_link', true ) );
        $date      = sanitize_text_field( (string) get_post_meta( $post->ID, '_mlcp_date', true ) );
        $subtitle  = sanitize_text_field( (string) get_post_meta( $post->ID, '_mlcp_subtitle', true ) );
        $new_tab   = (bool) get_post_meta( $post->ID, '_mlcp_new_tab', true );

        $image = self::build_image_payload( $image_id, $image_url );
        $title = self::clean_text( get_the_title( $post ) );
        $target = $link ? $link : '';

        return array(
            'id'                       => (string) $post->ID,
            'type'                     => 'ml_banner_pro',
            'title'                    => $title,
            'slug'                     => $post->post_name,
            'excerpt'                  => self::clean_text( $subtitle ),
            'date'                     => get_post_time( 'c', true, $post ),
            'display_date'             => $date,
            'image'                    => $image,
            'url'                      => $target,
            'permalink'                => $target,
            'link'                     => $target,
            'target_url'               => $target,
            'source'                   => 'ml_banner_pro',
            'ml_banner_pro'            => true,
            'open_in_new_tab'          => $new_tab,
            'app_show_on_home'         => true,
            'app_display_mode'         => 'banner_only',
            'app_allow_favorite'       => false,
            'app_notification_enabled' => false,
            'meta'                     => array(
                'mlcp_date'     => $date,
                'mlcp_subtitle' => $subtitle,
                'mlcp_link'     => $target,
            ),
        );
    }

    public static function clean_text( $value ) {
        $value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
        $value = strip_shortcodes( $value );
        $value = wp_strip_all_tags( $value, true );
        $value = preg_replace( '/\s+/u', ' ', $value );
        return trim( (string) $value );
    }

    public static function clean_excerpt( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }
        $raw = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
        $raw = preg_replace( '/<a[^>]*class=["\'][^"\']*more-link[^"\']*["\'][\s\S]*?<\/a>/i', '', (string) $raw );
        $raw = preg_replace( '/<span[^>]*class=["\'][^"\']*screen-reader-text[^"\']*["\'][\s\S]*?<\/span>/i', '', (string) $raw );
        $text = self::clean_text( $raw );
        return wp_trim_words( $text, 45, '' );
    }

    public static function clean_content( $value ) {
        $value = strip_shortcodes( (string) $value );
        $value = apply_filters( 'the_content', $value );
        return $value;
    }

    private static function get_post_image( $post_id ) {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) {
            return null;
        }

        return self::build_image_payload( $thumb_id, wp_get_attachment_image_url( $thumb_id, 'full' ) );
    }

    private static function build_image_payload( $attachment_id = 0, $fallback_url = '' ) {
        $attachment_id = (int) $attachment_id;
        $url = '';
        $width = 0;
        $height = 0;
        $alt = '';

        if ( $attachment_id > 0 ) {
            $src = wp_get_attachment_image_src( $attachment_id, 'full' );
            if ( is_array( $src ) ) {
                $url = (string) $src[0];
                $width = isset( $src[1] ) ? (int) $src[1] : 0;
                $height = isset( $src[2] ) ? (int) $src[2] : 0;
            }
            $alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        }

        if ( ! $url && $fallback_url ) {
            $url = esc_url_raw( $fallback_url );
        }

        if ( ! $url ) {
            return null;
        }

        return array(
            'id'     => $attachment_id,
            'url'    => $url,
            'width'  => $width,
            'height' => $height,
            'alt'    => $alt,
            'sizes'  => array(
                'thumbnail' => $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : $url,
                'medium'    => $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : $url,
                'large'     => $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'large' ) : $url,
            ),
        );
    }
}
