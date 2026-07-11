<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Query {
    public static function get_events( $page = 1, $per_page = 10 ) {
        return self::get_ml_banner_events( $page, $per_page );
    }

    public static function get_ml_banner_events( $page = 1, $per_page = 10 ) {
        $post_type = self::get_ml_banner_post_type();
        if ( ! $post_type ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'paged'          => max( 1, (int) $page ),
            'posts_per_page' => max( 1, min( 20, (int) $per_page ) ),
            'no_found_rows'  => false,
            'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_mlcp_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_mlcp_expire_at',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_mlcp_expire_at',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_mlcp_expire_at',
                        'value'   => current_time( 'timestamp', true ),
                        'compare' => '>',
                        'type'    => 'NUMERIC',
                    ),
                ),
            ),
        );

        $group_slug = sanitize_title( ML_App_Base_Content_Settings::get( 'ml_banner_group', '' ) );
        if ( $group_slug && taxonomy_exists( 'mlcp_group' ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'mlcp_group',
                    'field'    => 'slug',
                    'terms'    => array( $group_slug ),
                ),
            );
        }

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $item = ML_App_Base_Content_Normalizer::normalize_ml_banner( $post );
            if ( $item && ! empty( $item['image']['url'] ) ) {
                $items[] = $item;
            }
        }

        return array(
            'items' => $items,
            'total' => (int) $query->found_posts,
        );
    }

    public static function get_news( $page = 1, $per_page = 10 ) {
        $selected_slugs = ML_App_Base_Content_Settings::news_category_slugs();
        $category_ids   = self::category_ids_from_slugs( $selected_slugs );

        if ( empty( $category_ids ) ) {
            $category_ids = self::category_ids_from_slugs( array( 'ultimas-noticias', 'ultimas_noticias', 'noticias', 'feijoadas', 'feijoada' ) );
        }

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'paged'          => max( 1, (int) $page ),
            'posts_per_page' => max( 1, min( 20, (int) $per_page ) ),
            'no_found_rows'  => false,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $category_ids ) ) {
            $args['category__in'] = $category_ids;
        } else {
            $args['category_name'] = 'ultimas-noticias,ultimas_noticias,noticias,feijoadas,feijoada';
        }

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $item = ML_App_Base_Content_Normalizer::normalize_post( $post, false );
            if ( $item ) {
                $items[] = $item;
            }
        }

        return array(
            'items' => $items,
            'total' => (int) $query->found_posts,
        );
    }

    private static function category_ids_from_slugs( array $slugs ) {
        $ids = array();
        foreach ( $slugs as $slug ) {
            $slug = sanitize_title( $slug );
            if ( '' === $slug ) {
                continue;
            }
            $term = get_category_by_slug( $slug );
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (int) $term->term_id;
            }
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }


    public static function get_photo_albums( $page = 1, $per_page = 10 ) {
        global $wpdb;

        $galleries_table = $wpdb->prefix . 'mlgp_galleries';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $galleries_table ) );

        if ( $table_exists !== $galleries_table ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $page = max( 1, (int) $page );
        $per_page = max( 1, min( 20, (int) $per_page ) );
        $offset = ( $page - 1 ) * $per_page;

        $status_sql = "status IN ('publish','published')";
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$galleries_table} WHERE {$status_sql}" );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, slug, cover_attachment_id, cover_item_id, created_at
                FROM {$galleries_table}
                WHERE {$status_sql}
                ORDER BY COALESCE(NULLIF(created_at, ''), '1970-01-01') DESC, id DESC
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $items = array();
        foreach ( $rows as $row ) {
            $item = self::normalize_mlgp_gallery_for_app( $row );
            if ( $item && ! empty( $item['image']['url'] ) ) {
                $items[] = $item;
            }
        }

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    private static function normalize_mlgp_gallery_for_app( array $gallery ) {
        $gallery_id = (int) ( $gallery['id'] ?? 0 );
        if ( $gallery_id <= 0 ) {
            return null;
        }

        $title = isset( $gallery['title'] ) ? ML_App_Base_Content_Normalizer::clean_text( $gallery['title'] ) : '';
        $slug = isset( $gallery['slug'] ) ? sanitize_title( (string) $gallery['slug'] ) : '';
        $image = self::get_mlgp_gallery_cover_payload( $gallery );
        $link = self::get_mlgp_gallery_public_url( $gallery );
        $created_at = isset( $gallery['created_at'] ) ? (string) $gallery['created_at'] : '';
        $timestamp = $created_at ? strtotime( $created_at ) : false;

        return array(
            'id'         => (string) $gallery_id,
            'type'       => 'photo_album',
            'title'      => $title,
            'name'       => $title,
            'slug'       => $slug,
            'excerpt'    => '',
            'date'       => $timestamp ? gmdate( 'c', $timestamp ) : '',
            'display_date' => $timestamp ? date_i18n( 'd/m/Y', $timestamp ) : '',
            'image'      => $image,
            'url'        => $link,
            'permalink'  => $link,
            'link'       => $link,
            'target_url' => $link,
            'source'     => 'ml_gallery_pro',
            'ml_gallery_pro' => true,
            'meta'       => array(
                'gallery_id' => $gallery_id,
                'cover_attachment_id' => (int) ( $gallery['cover_attachment_id'] ?? 0 ),
                'cover_item_id' => (int) ( $gallery['cover_item_id'] ?? 0 ),
            ),
        );
    }

    private static function get_mlgp_gallery_cover_payload( array $gallery ) {
        $url = '';
        $width = 0;
        $height = 0;
        $alt = '';
        $attachment_id = (int) ( $gallery['cover_attachment_id'] ?? 0 );

        if ( $attachment_id > 0 ) {
            $src = wp_get_attachment_image_src( $attachment_id, 'large' );
            if ( is_array( $src ) && ! empty( $src[0] ) ) {
                $url = (string) $src[0];
                $width = isset( $src[1] ) ? (int) $src[1] : 0;
                $height = isset( $src[2] ) ? (int) $src[2] : 0;
            }
            $alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        }

        if ( ! $url ) {
            $row = self::get_mlgp_cover_item_row( $gallery );
            if ( is_array( $row ) ) {
                foreach ( array( 'large_url', 'medium_url', 'file_url' ) as $key ) {
                    if ( ! empty( $row[ $key ] ) ) {
                        $url = esc_url_raw( (string) $row[ $key ] );
                        break;
                    }
                }

                $attachment_id = (int) ( $row['attachment_id'] ?? 0 );
                if ( $attachment_id > 0 ) {
                    $src = wp_get_attachment_image_src( $attachment_id, 'large' );
                    if ( is_array( $src ) && ! empty( $src[0] ) ) {
                        $url = (string) $src[0];
                        $width = isset( $src[1] ) ? (int) $src[1] : 0;
                        $height = isset( $src[2] ) ? (int) $src[2] : 0;
                    }
                    $alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
                }
            }
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

    private static function get_mlgp_cover_item_row( array $gallery ) {
        global $wpdb;

        $gallery_id = (int) ( $gallery['id'] ?? 0 );
        if ( $gallery_id <= 0 ) {
            return null;
        }

        $items_table = $wpdb->prefix . 'mlgp_gallery_items';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) );
        if ( $table_exists !== $items_table ) {
            return null;
        }

        $cover_item_id = (int) ( $gallery['cover_item_id'] ?? 0 );
        if ( $cover_item_id > 0 ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT large_url, medium_url, file_url, attachment_id
                    FROM {$items_table}
                    WHERE id = %d
                    LIMIT 1",
                    $cover_item_id
                ),
                ARRAY_A
            );
            if ( is_array( $row ) ) {
                return $row;
            }
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT large_url, medium_url, file_url, attachment_id
                FROM {$items_table}
                WHERE gallery_id = %d AND is_visible = 1
                ORDER BY sort_order ASC, id ASC
                LIMIT 1",
                $gallery_id
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    private static function get_mlgp_gallery_public_url( array $gallery ) {
        $gallery_id = (int) ( $gallery['id'] ?? 0 );
        if ( $gallery_id <= 0 ) {
            return '';
        }

        $album_ids = self::get_mlgp_related_album_ids( $gallery_id );
        if ( empty( $album_ids ) ) {
            return '';
        }

        foreach ( $album_ids as $album_id ) {
            $album_id = absint( $album_id );
            if ( $album_id <= 0 ) {
                continue;
            }

            $base_url = self::get_mlgp_album_base_url( $album_id );
            if ( $base_url ) {
                return add_query_arg( sanitize_key( 'mlgp_album_view_' . $album_id ), 'gallery-' . $gallery_id, $base_url );
            }
        }

        return '';
    }

    private static function get_mlgp_related_album_ids( $gallery_id ) {
        global $wpdb;

        $album_items_table = $wpdb->prefix . 'mlgp_album_items';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $album_items_table ) );
        if ( $table_exists !== $album_items_table ) {
            return array();
        }

        $parents = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT album_id FROM {$album_items_table} WHERE item_type = %s AND item_id = %d ORDER BY sort_order ASC, id ASC",
                'gallery',
                $gallery_id
            )
        );

        if ( ! is_array( $parents ) ) {
            return array();
        }

        return array_values( array_unique( array_filter( array_map( 'absint', $parents ) ) ) );
    }

    private static function get_mlgp_album_base_url( $album_id ) {
        $album_id = absint( $album_id );
        if ( $album_id <= 0 ) {
            return '';
        }

        $album_meta = self::get_mlgp_album_meta( $album_id );

        if ( ! empty( $album_meta['public_url'] ) ) {
            return (string) $album_meta['public_url'];
        }

        $post = self::find_mlgp_album_public_post( $album_id, $album_meta );
        if ( $post instanceof WP_Post ) {
            $permalink = get_permalink( $post );
            if ( is_string( $permalink ) && '' !== $permalink ) {
                return $permalink;
            }
        }

        $slug = isset( $album_meta['slug'] ) ? trim( (string) $album_meta['slug'] ) : '';
        if ( '' !== $slug ) {
            return home_url( '/' . ltrim( $slug, '/' ) . '/' );
        }

        return '';
    }

    private static function get_mlgp_album_meta( $album_id ) {
        global $wpdb;

        $albums_table = $wpdb->prefix . 'mlgp_albums';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $albums_table ) );
        if ( $table_exists !== $albums_table ) {
            return array();
        }

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$albums_table} WHERE id = %d LIMIT 1", $album_id ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return array();
        }

        $meta = array(
            'slug'       => isset( $row['slug'] ) ? (string) $row['slug'] : '',
            'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
            'public_url' => '',
        );

        foreach ( array( 'public_url', 'url', 'permalink' ) as $key ) {
            if ( ! empty( $row[ $key ] ) && is_string( $row[ $key ] ) ) {
                $meta['public_url'] = (string) $row[ $key ];
                break;
            }
        }

        foreach ( array( 'page_id', 'post_id', 'public_page_id' ) as $key ) {
            if ( ! empty( $row[ $key ] ) ) {
                $permalink = get_permalink( (int) $row[ $key ] );
                if ( is_string( $permalink ) && '' !== $permalink ) {
                    $meta['public_url'] = $permalink;
                    break;
                }
            }
        }

        return $meta;
    }

    private static function find_mlgp_album_public_post( $album_id, array $album_meta ) {
        $album_title = isset( $album_meta['title'] ) ? trim( wp_strip_all_tags( (string) $album_meta['title'] ) ) : '';
        $album_slug  = isset( $album_meta['slug'] ) ? sanitize_title( (string) $album_meta['slug'] ) : '';
        $needles = array_filter( array_unique( array( 'mlgp_album_view_' . $album_id, 'album_id="' . $album_id . '"', "album_id='" . $album_id . "'", $album_title, $album_slug ) ) );

        $post_types = get_post_types( array( 'public' => true ), 'names' );
        $query = new WP_Query( array(
            'post_type'              => array_values( $post_types ),
            'post_status'            => 'publish',
            'posts_per_page'         => 100,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
        ) );

        if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
            return null;
        }

        $best = null;
        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }
            $haystack = strtolower( implode( ' ', array_filter( array( (string) $post->post_title, (string) $post->post_name, (string) $post->post_content ) ) ) );
            foreach ( $needles as $needle ) {
                $needle = strtolower( trim( (string) $needle ) );
                if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
                    if ( false !== strpos( strtolower( (string) $post->post_title ), strtolower( $album_title ) ) || false !== strpos( strtolower( (string) $post->post_name ), strtolower( $album_slug ) ) ) {
                        return $post;
                    }
                    if ( null === $best ) {
                        $best = $post;
                    }
                }
            }
        }

        return $best;
    }

    public static function get_banners( $page = 1, $per_page = 10 ) {
        return self::get_ml_banner_events( $page, $per_page );
    }

    public static function get_gallery_highlights( $page = 1, $per_page = 6 ) {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'category_name'  => 'galeria,fotos,gallery',
            'paged'          => max( 1, (int) $page ),
            'posts_per_page' => max( 1, min( 20, (int) $per_page ) ),
            'no_found_rows'  => false,
        );

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $item = ML_App_Base_Content_Normalizer::normalize_post( $post, false );
            if ( $item ) {
                $items[] = $item;
            }
        }

        return array(
            'items' => $items,
            'total' => (int) $query->found_posts,
        );
    }

    private static function get_ml_banner_post_type() {
        $candidates = array( 'mlcp_item' );
        foreach ( $candidates as $post_type ) {
            if ( post_type_exists( $post_type ) ) {
                return $post_type;
            }
        }
        return null;
    }
}
