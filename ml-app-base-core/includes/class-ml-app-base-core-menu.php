<?php
/**
 * Cardápio storage and API formatting for ML App Base Core.
 *
 * Real data model:
 * - Cardápio: a full menu set, e.g. "Cardápio de Domingo". Only one can be active.
 * - Category: group inside the active cardápio, e.g. Bebidas, Petiscos, Comidas.
 * - Subcategory: child group inside a category, e.g. Cervejas, Drinks, Churrasco.
 * - Item: product linked to a category/subcategory.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_App_Base_Core_Menu {

	const MENUS_OPTION      = 'ml_app_base_core_menus';
	const CARDAPIOS_OPTION  = 'ml_app_base_core_cardapios'; // Backward-compatible category/subcategory storage.
	const ITEMS_OPTION      = 'ml_app_base_core_menu_items';

	public static function get_menus( $active_only = false ) {
		$menus = get_option( self::MENUS_OPTION, array() );
		if ( ! is_array( $menus ) ) {
			$menus = array();
		}
		$menus = array_values( array_map( array( __CLASS__, 'normalize_menu' ), $menus ) );
		if ( $active_only ) {
			$menus = array_values( array_filter( $menus, function( $menu ) { return 'active' === $menu['status']; } ) );
		}
		usort( $menus, array( __CLASS__, 'sort_records' ) );
		return $menus;
	}

	public static function get_menu( $id ) {
		foreach ( self::get_menus() as $menu ) {
			if ( (int) $menu['id'] === (int) $id ) {
				return $menu;
			}
		}
		return null;
	}

	public static function get_active_menu() {
		foreach ( self::get_menus() as $menu ) {
			if ( 'active' === $menu['status'] ) {
				return $menu;
			}
		}
		$menus = self::get_menus();
		return ! empty( $menus ) ? $menus[0] : null;
	}

	public static function save_menu( array $data ) {
		$menus = self::get_menus();
		$id    = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		if ( '' === $title ) {
			return 0;
		}
		$now = current_time( 'mysql' );
		$status = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$entry = array(
			'id'          => $id > 0 ? $id : self::next_id( $menus ),
			'title'       => $title,
			'slug'        => $slug,
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'status'      => $status,
			'order'       => isset( $data['order'] ) ? (int) $data['order'] : 0,
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$updated = false;
		foreach ( $menus as $index => $menu ) {
			if ( (int) $menu['id'] === (int) $entry['id'] ) {
				$entry['created_at'] = ! empty( $menu['created_at'] ) ? $menu['created_at'] : $now;
				$menus[ $index ] = $entry;
				$updated = true;
				break;
			}
		}
		if ( ! $updated ) {
			$menus[] = $entry;
		}

		if ( 'active' === $entry['status'] ) {
			$menus = self::enforce_single_active_menu( $menus, (int) $entry['id'] );
		} elseif ( ! self::has_active_menu( $menus ) && ! empty( $menus ) ) {
			// Keep the app with one active menu. If the edited active menu was disabled, activate the first available one.
			$menus[0]['status'] = 'active';
			$menus[0]['updated_at'] = $now;
		}

		update_option( self::MENUS_OPTION, array_values( $menus ), false );
		return (int) $entry['id'];
	}

	public static function activate_menu( $id ) {
		$id = absint( $id );
		if ( ! $id || ! self::get_menu( $id ) ) {
			return false;
		}
		$menus = self::enforce_single_active_menu( self::get_menus(), $id );
		update_option( self::MENUS_OPTION, array_values( $menus ), false );
		return true;
	}

	public static function delete_menu( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		foreach ( self::get_cardapios() as $category ) {
			if ( (int) $category['menu_id'] === $id ) {
				return new WP_Error( 'menu_has_categories', __( 'Este cardápio possui categorias vinculadas. Mova ou exclua as categorias antes de remover.', 'ml-app-base-core' ) );
			}
		}
		$menus = array_values( array_filter( self::get_menus(), function( $menu ) use ( $id ) { return (int) $menu['id'] !== $id; } ) );
		if ( ! self::has_active_menu( $menus ) && ! empty( $menus ) ) {
			$menus[0]['status'] = 'active';
			$menus[0]['updated_at'] = current_time( 'mysql' );
		}
		update_option( self::MENUS_OPTION, $menus, false );
		return true;
	}

	public static function get_cardapios( $active_only = false, $menu_id = null ) {
		$cardapios = get_option( self::CARDAPIOS_OPTION, array() );
		if ( ! is_array( $cardapios ) ) {
			$cardapios = array();
		}
		$cardapios = array_values( array_map( array( __CLASS__, 'normalize_cardapio' ), $cardapios ) );
		if ( null !== $menu_id ) {
			$menu_id = absint( $menu_id );
			$cardapios = array_values( array_filter( $cardapios, function( $cardapio ) use ( $menu_id ) { return (int) $cardapio['menu_id'] === $menu_id || 0 === (int) $cardapio['menu_id']; } ) );
		}
		if ( $active_only ) {
			$cardapios = array_values( array_filter( $cardapios, function( $cardapio ) { return 'active' === $cardapio['status']; } ) );
		}
		usort( $cardapios, array( __CLASS__, 'sort_records' ) );
		return $cardapios;
	}

	public static function get_parent_cardapios( $active_only = false, $menu_id = null ) {
		return array_values( array_filter( self::get_cardapios( $active_only, $menu_id ), function( $cardapio ) { return empty( $cardapio['parent_id'] ); } ) );
	}

	public static function get_child_cardapios( $parent_id, $active_only = false ) {
		$parent_id = absint( $parent_id );
		return array_values( array_filter( self::get_cardapios( $active_only ), function( $cardapio ) use ( $parent_id ) { return (int) $cardapio['parent_id'] === $parent_id; } ) );
	}

	public static function get_items( $cardapio_id = 0, $active_only = false ) {
		$items = get_option( self::ITEMS_OPTION, array() );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		$items = array_values( array_map( array( __CLASS__, 'normalize_item' ), $items ) );
		if ( $cardapio_id > 0 ) {
			$items = array_values( array_filter( $items, function( $item ) use ( $cardapio_id ) { return (int) $item['cardapio_id'] === (int) $cardapio_id; } ) );
		}
		if ( $active_only ) {
			$items = array_values( array_filter( $items, function( $item ) { return 'active' === $item['status']; } ) );
		}
		usort( $items, array( __CLASS__, 'sort_records' ) );
		return $items;
	}

	public static function get_cardapio( $id ) {
		foreach ( self::get_cardapios() as $cardapio ) {
			if ( (int) $cardapio['id'] === (int) $id ) {
				return $cardapio;
			}
		}
		return null;
	}

	public static function get_item( $id ) {
		foreach ( self::get_items() as $item ) {
			if ( (int) $item['id'] === (int) $id ) {
				return $item;
			}
		}
		return null;
	}

	public static function save_cardapio( array $data ) {
		$cardapios = self::get_cardapios();
		$id        = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$now       = current_time( 'mysql' );
		$title     = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		if ( '' === $title ) {
			return 0;
		}
		$active_menu = self::get_active_menu();
		$menu_id = isset( $data['menu_id'] ) ? absint( $data['menu_id'] ) : 0;
		if ( ! $menu_id && $active_menu ) {
			$menu_id = (int) $active_menu['id'];
		}
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		$parent_id = isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : 0;
		if ( $parent_id === $id ) {
			$parent_id = 0;
		}
		if ( $parent_id > 0 ) {
			$parent = self::get_cardapio( $parent_id );
			if ( ! $parent ) {
				$parent_id = 0;
			} else {
				$menu_id = (int) $parent['menu_id'];
				if ( ! empty( $parent['parent_id'] ) ) {
					$parent_id = (int) $parent['parent_id'];
				}
			}
		}
		$status = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$image_id = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'large' );
			$image_url = $image_url ? esc_url_raw( $image_url ) : '';
		}
		if ( '' === $image_url && ! empty( $data['image_url'] ) ) {
			$image_url = esc_url_raw( $data['image_url'] );
		}
		$entry = array(
			'id'           => $id > 0 ? $id : self::next_id( $cardapios ),
			'menu_id'      => $menu_id,
			'parent_id'    => $parent_id,
			'title'        => $title,
			'slug'         => $slug,
			'description'  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'image_id'     => $image_id,
			'image_url'    => $image_url,
			'show_on_home' => ! empty( $data['show_on_home'] ) ? 1 : 0,
			'status'       => $status,
			'order'        => isset( $data['order'] ) ? (int) $data['order'] : 0,
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		$updated = false;
		foreach ( $cardapios as $index => $cardapio ) {
			if ( (int) $cardapio['id'] === (int) $entry['id'] ) {
				$entry['created_at'] = ! empty( $cardapio['created_at'] ) ? $cardapio['created_at'] : $now;
				$cardapios[ $index ] = $entry;
				$updated = true;
				break;
			}
		}
		if ( ! $updated ) {
			$cardapios[] = $entry;
		}
		update_option( self::CARDAPIOS_OPTION, array_values( $cardapios ), false );
		return (int) $entry['id'];
	}

	public static function delete_cardapio( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( count( self::get_child_cardapios( $id ) ) > 0 ) {
			return new WP_Error( 'cardapio_has_children', __( 'Esta categoria possui subcategorias vinculadas. Remova ou mova as subcategorias antes de excluir.', 'ml-app-base-core' ) );
		}
		if ( count( self::get_items( $id ) ) > 0 ) {
			return new WP_Error( 'cardapio_has_items', __( 'Esta categoria possui itens vinculados. Remova ou mova os itens antes de excluir.', 'ml-app-base-core' ) );
		}
		$cardapios = array_values( array_filter( self::get_cardapios(), function( $cardapio ) use ( $id ) { return (int) $cardapio['id'] !== $id; } ) );
		update_option( self::CARDAPIOS_OPTION, $cardapios, false );
		return true;
	}

	public static function save_item( array $data ) {
		$items = self::get_items();
		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$cardapio_id = isset( $data['cardapio_id'] ) ? absint( $data['cardapio_id'] ) : 0;
		$now = current_time( 'mysql' );
		if ( ! $cardapio_id || ! self::get_cardapio( $cardapio_id ) ) {
			return 0;
		}
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		if ( '' === $title ) {
			return 0;
		}
		$status = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$image_id = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'large' );
			$image_url = $image_url ? esc_url_raw( $image_url ) : '';
		}
		if ( '' === $image_url && ! empty( $data['image_url'] ) ) {
			$image_url = esc_url_raw( $data['image_url'] );
		}
		$entry = array(
			'id'          => $id > 0 ? $id : self::next_id( $items ),
			'cardapio_id' => $cardapio_id,
			'title'       => $title,
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'price'       => isset( $data['price'] ) ? sanitize_text_field( $data['price'] ) : '',
			'promo_price' => isset( $data['promo_price'] ) ? sanitize_text_field( $data['promo_price'] ) : '',
			'promo_label' => isset( $data['promo_label'] ) ? sanitize_text_field( $data['promo_label'] ) : '',
			'featured'    => ! empty( $data['featured'] ) ? 1 : 0,
			'image_id'    => $image_id,
			'image_url'   => $image_url,
			'status'      => $status,
			'order'       => isset( $data['order'] ) ? (int) $data['order'] : 0,
			'created_at'  => $now,
			'updated_at'  => $now,
		);
		$updated = false;
		foreach ( $items as $index => $item ) {
			if ( (int) $item['id'] === (int) $entry['id'] ) {
				$entry['created_at'] = ! empty( $item['created_at'] ) ? $item['created_at'] : $now;
				$items[ $index ] = $entry;
				$updated = true;
				break;
			}
		}
		if ( ! $updated ) {
			$items[] = $entry;
		}
		update_option( self::ITEMS_OPTION, array_values( $items ), false );
		return (int) $entry['id'];
	}

	public static function delete_item( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		$items = array_values( array_filter( self::get_items(), function( $item ) use ( $id ) { return (int) $item['id'] !== $id; } ) );
		update_option( self::ITEMS_OPTION, $items, false );
		return true;
	}

	public static function get_api_payload() {
		$active_menu = self::get_active_menu();
		$active_menu_id = $active_menu ? (int) $active_menu['id'] : 0;
		$all_categories = self::get_cardapios( true );
		$categories_for_menu = array_values( array_filter( $all_categories, function( $category ) use ( $active_menu_id ) {
			return 0 === (int) $category['menu_id'] || ( $active_menu_id > 0 && (int) $category['menu_id'] === $active_menu_id );
		} ) );
		$all_items = self::get_items( 0, true );
		$category_by_id = array();
		foreach ( $categories_for_menu as $category ) {
			$category_by_id[ (int) $category['id'] ] = $category;
		}
		$root_categories = array_values( array_filter( $categories_for_menu, function( $category ) use ( $category_by_id ) {
			$parent_id = (int) $category['parent_id'];
			return 0 === $parent_id || ! isset( $category_by_id[ $parent_id ] );
		} ) );
		if ( empty( $root_categories ) && ! empty( $categories_for_menu ) ) {
			$root_categories = $categories_for_menu;
		}
		$sections = array();
		$flat_items = array();
		$featured_items = array();
		foreach ( $root_categories as $category ) {
			$category_items = array();
			foreach ( self::get_items( (int) $category['id'], true ) as $item ) {
				$formatted = self::format_item_for_api( $item, $category );
				$category_items[] = $formatted;
				$flat_items[] = $formatted;
				if ( ! empty( $formatted['featured'] ) ) {
					$featured_items[] = $formatted;
				}
			}
			$subcategories = array();
			foreach ( self::get_child_cardapios( (int) $category['id'], true ) as $subcategory ) {
				if ( ! isset( $category_by_id[ (int) $subcategory['id'] ] ) ) {
					continue;
				}
				$subcategory_items = array();
				foreach ( self::get_items( (int) $subcategory['id'], true ) as $item ) {
					$formatted = self::format_item_for_api( $item, $subcategory, $category );
					$subcategory_items[] = $formatted;
					$flat_items[] = $formatted;
					if ( ! empty( $formatted['featured'] ) ) {
						$featured_items[] = $formatted;
					}
				}
				$subcategories[] = self::format_category_for_api( $subcategory, $subcategory_items, array(), $category );
			}
			$sections[] = self::format_category_for_api( $category, $category_items, $subcategories, null, $active_menu );
		}

		$home_categories = array_values( array_filter( $sections, function( $section ) { return ! isset( $section['show_on_home'] ) || ! empty( $section['show_on_home'] ); } ) );
		$active_menu_api = self::format_menu_for_api( $active_menu );
		return array(
			'active_cardapio'   => $active_menu_api,
			'current_cardapio'  => $active_menu_api,
			'cardapio'          => $active_menu_api,
			'cardapios_list'    => array_map( array( __CLASS__, 'format_menu_for_api' ), self::get_menus() ),
			'categories'        => $sections,
			'cardapios'         => $sections,
			'menus'             => $sections,
			'sections'          => $sections,
			'menu_sections'     => $sections,
			'category_sections' => $sections,
			'category_cards'    => $home_categories,
			'home_categories'   => $home_categories,
			'tabs'              => $home_categories,
			'items'             => $flat_items,
			'featured'          => $featured_items,
			'featured_items'    => $featured_items,
			'highlights'        => $featured_items,
			'promotions'        => $featured_items,
			'home'              => array( 'categories' => $home_categories, 'category_cards' => $home_categories, 'featured' => $featured_items, 'featured_items' => $featured_items, 'highlights' => $featured_items, 'promotions' => $featured_items ),
			'cardapio_home'     => array( 'categories' => $home_categories, 'category_cards' => $home_categories, 'featured' => $featured_items, 'featured_items' => $featured_items ),
			'counts'            => array( 'cardapios' => count( self::get_menus() ), 'categories' => count( $sections ), 'home_categories' => count( $home_categories ), 'items' => count( $flat_items ), 'featured' => count( $featured_items ) ),
			'updated_at'        => current_time( 'mysql' ),
			'version'           => ML_APP_BASE_CORE_VERSION,
		);
	}

	public static function get_api_categories() {
		$active_menu = self::get_active_menu();
		$menu_id = $active_menu ? (int) $active_menu['id'] : 0;
		$categories = array();
		foreach ( self::get_cardapios( true ) as $cardapio ) {
			if ( $menu_id > 0 && (int) $cardapio['menu_id'] !== $menu_id && 0 !== (int) $cardapio['menu_id'] ) {
				continue;
			}
			$parent = ! empty( $cardapio['parent_id'] ) ? self::get_cardapio( (int) $cardapio['parent_id'] ) : null;
			$categories[] = array_merge( self::format_category_for_api( $cardapio, array(), array(), $parent, $active_menu ), array( 'items_count' => count( self::get_items( (int) $cardapio['id'], true ) ) ) );
		}
		return $categories;
	}


	public static function update_order( $type, array $ordered_ids ) {
		$type = sanitize_key( $type );
		$ordered_ids = array_values( array_filter( array_map( 'absint', $ordered_ids ) ) );
		if ( empty( $ordered_ids ) ) {
			return false;
		}

		$option = '';
		if ( 'menus' === $type ) {
			$option = self::MENUS_OPTION;
		} elseif ( 'categories' === $type ) {
			$option = self::CARDAPIOS_OPTION;
		} elseif ( 'items' === $type ) {
			$option = self::ITEMS_OPTION;
		} else {
			return false;
		}

		$records = get_option( $option, array() );
		if ( ! is_array( $records ) ) {
			return false;
		}

		$position = array();
		foreach ( $ordered_ids as $index => $id ) {
			$position[ (int) $id ] = ( $index + 1 ) * 10;
		}

		$changed = false;
		foreach ( $records as $index => $record ) {
			$record_id = isset( $record['id'] ) ? absint( $record['id'] ) : 0;
			if ( $record_id && isset( $position[ $record_id ] ) ) {
				$records[ $index ]['order'] = $position[ $record_id ];
				$records[ $index ]['updated_at'] = current_time( 'mysql' );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( $option, array_values( $records ), false );
		}

		return $changed;
	}

	private static function normalize_menu( $menu ) {
		$menu = is_array( $menu ) ? $menu : array();
		$title = isset( $menu['title'] ) ? sanitize_text_field( $menu['title'] ) : '';
		$slug = isset( $menu['slug'] ) ? sanitize_title( $menu['slug'] ) : '';
		if ( '' === $slug && '' !== $title ) {
			$slug = sanitize_title( $title );
		}
		return array(
			'id'          => isset( $menu['id'] ) ? absint( $menu['id'] ) : 0,
			'title'       => $title,
			'slug'        => $slug,
			'description' => isset( $menu['description'] ) ? sanitize_textarea_field( $menu['description'] ) : '',
			'status'      => isset( $menu['status'] ) && 'inactive' === $menu['status'] ? 'inactive' : 'active',
			'order'       => isset( $menu['order'] ) ? (int) $menu['order'] : 0,
			'created_at'  => isset( $menu['created_at'] ) ? sanitize_text_field( $menu['created_at'] ) : '',
			'updated_at'  => isset( $menu['updated_at'] ) ? sanitize_text_field( $menu['updated_at'] ) : '',
		);
	}

	private static function normalize_cardapio( $cardapio ) {
		$cardapio = is_array( $cardapio ) ? $cardapio : array();
		$title = isset( $cardapio['title'] ) ? sanitize_text_field( $cardapio['title'] ) : '';
		$slug = isset( $cardapio['slug'] ) ? sanitize_title( $cardapio['slug'] ) : '';
		if ( '' === $slug && '' !== $title ) {
			$slug = sanitize_title( $title );
		}
		return array(
			'id'           => isset( $cardapio['id'] ) ? absint( $cardapio['id'] ) : 0,
			'menu_id'      => isset( $cardapio['menu_id'] ) ? absint( $cardapio['menu_id'] ) : 0,
			'parent_id'    => isset( $cardapio['parent_id'] ) ? absint( $cardapio['parent_id'] ) : 0,
			'title'        => $title,
			'slug'         => $slug,
			'description'  => isset( $cardapio['description'] ) ? sanitize_textarea_field( $cardapio['description'] ) : '',
			'image_id'     => isset( $cardapio['image_id'] ) ? absint( $cardapio['image_id'] ) : 0,
			'image_url'    => isset( $cardapio['image_url'] ) ? esc_url_raw( $cardapio['image_url'] ) : '',
			'show_on_home' => array_key_exists( 'show_on_home', $cardapio ) ? ( ! empty( $cardapio['show_on_home'] ) ? 1 : 0 ) : 1,
			'status'       => isset( $cardapio['status'] ) && 'inactive' === $cardapio['status'] ? 'inactive' : 'active',
			'order'        => isset( $cardapio['order'] ) ? (int) $cardapio['order'] : 0,
			'created_at'   => isset( $cardapio['created_at'] ) ? sanitize_text_field( $cardapio['created_at'] ) : '',
			'updated_at'   => isset( $cardapio['updated_at'] ) ? sanitize_text_field( $cardapio['updated_at'] ) : '',
		);
	}

	private static function normalize_item( $item ) {
		$item = is_array( $item ) ? $item : array();
		return array(
			'id'          => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
			'cardapio_id' => isset( $item['cardapio_id'] ) ? absint( $item['cardapio_id'] ) : 0,
			'title'       => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
			'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
			'price'       => isset( $item['price'] ) ? sanitize_text_field( $item['price'] ) : '',
			'promo_price' => isset( $item['promo_price'] ) ? sanitize_text_field( $item['promo_price'] ) : '',
			'promo_label' => isset( $item['promo_label'] ) ? sanitize_text_field( $item['promo_label'] ) : '',
			'featured'    => ! empty( $item['featured'] ) ? 1 : 0,
			'image_id'    => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
			'image_url'   => isset( $item['image_url'] ) ? esc_url_raw( $item['image_url'] ) : '',
			'status'      => isset( $item['status'] ) && 'inactive' === $item['status'] ? 'inactive' : 'active',
			'order'       => isset( $item['order'] ) ? (int) $item['order'] : 0,
			'created_at'  => isset( $item['created_at'] ) ? sanitize_text_field( $item['created_at'] ) : '',
			'updated_at'  => isset( $item['updated_at'] ) ? sanitize_text_field( $item['updated_at'] ) : '',
		);
	}

	private static function format_menu_for_api( $menu ) {
		if ( ! is_array( $menu ) ) {
			return array( 'id' => 0, 'title' => __( 'Cardápio', 'ml-app-base-core' ), 'name' => __( 'Cardápio', 'ml-app-base-core' ), 'status' => 'active', 'active' => true );
		}
		return array(
			'id'          => (int) $menu['id'],
			'name'        => $menu['title'],
			'title'       => $menu['title'],
			'nome'        => $menu['title'],
			'label'       => $menu['title'],
			'slug'        => $menu['slug'],
			'description' => $menu['description'],
			'status'      => $menu['status'],
			'active'      => 'active' === $menu['status'],
			'order'       => (int) $menu['order'],
		);
	}

	private static function format_category_for_api( array $category, array $items = array(), array $subcategories = array(), $parent = null, $menu = null ) {
		$image_url = self::resolve_category_image_url( $category, 'large' );
		$thumb_url = self::resolve_category_image_url( $category, 'medium' );
		if ( '' === $thumb_url ) { $thumb_url = $image_url; }
		$featured_items = array_values( array_filter( $items, function( $item ) { return ! empty( $item['featured'] ); } ) );
		foreach ( $subcategories as $subcategory ) {
			if ( ! empty( $subcategory['featured_items'] ) && is_array( $subcategory['featured_items'] ) ) {
				$featured_items = array_merge( $featured_items, $subcategory['featured_items'] );
			}
		}
		return array(
			'id' => (int) $category['id'], 'menu_id' => (int) $category['menu_id'], 'menuId' => (int) $category['menu_id'], 'cardapio_menu_id' => (int) $category['menu_id'],
			'menu_name' => is_array( $menu ) ? $menu['title'] : '', 'parent_id' => (int) $category['parent_id'], 'parentId' => (int) $category['parent_id'], 'parent_name' => $parent ? $parent['title'] : '',
			'name' => $category['title'], 'title' => $category['title'], 'nome' => $category['title'], 'label' => $category['title'], 'slug' => $category['slug'], 'description' => $category['description'],
			'image_id' => (int) $category['image_id'], 'image' => $image_url, 'image_url' => $image_url, 'photo' => $image_url, 'photo_url' => $image_url, 'foto' => $image_url, 'foto_url' => $image_url, 'thumbnail' => $thumb_url, 'thumbnail_url' => $thumb_url,
			'show_on_home' => ! empty( $category['show_on_home'] ), 'home' => ! empty( $category['show_on_home'] ), 'status' => $category['status'], 'active' => 'active' === $category['status'], 'order' => (int) $category['order'], 'sort_order' => (int) $category['order'], 'position' => (int) $category['order'], 'menu_order' => (int) $category['order'],
			'items' => $items, 'featured_items' => $featured_items, 'featured' => $featured_items, 'highlights' => $featured_items, 'subcategories' => $subcategories, 'children' => $subcategories,
		);
	}

	private static function format_item_for_api( array $item, $category = null, $parent = null ) {
		$image_url = self::resolve_item_image_url( $item, 'large' );
		$thumb_url = self::resolve_item_image_url( $item, 'medium' );
		if ( '' === $thumb_url ) { $thumb_url = $image_url; }
		$promo_price = isset( $item['promo_price'] ) ? $item['promo_price'] : '';
		$price = isset( $item['price'] ) ? $item['price'] : '';
		return array(
			'id' => (int) $item['id'], 'cardapio_id' => (int) $item['cardapio_id'], 'cardapioId' => (int) $item['cardapio_id'], 'category_id' => (int) $item['cardapio_id'], 'categoryId' => (int) $item['cardapio_id'], 'category_name' => $category ? $category['title'] : '', 'categoryTitle' => $category ? $category['title'] : '', 'parent_id' => $parent ? (int) $parent['id'] : 0, 'parent_name' => $parent ? $parent['title'] : '',
			'name' => $item['title'], 'title' => $item['title'], 'nome' => $item['title'], 'label' => $item['title'], 'description' => $item['description'], 'descricao' => $item['description'], 'price' => $price, 'preco' => $price, 'valor' => $price, 'promo_price' => $promo_price, 'promotionalPrice' => $promo_price, 'promo_label' => $item['promo_label'], 'badge' => $item['promo_label'],
			'featured' => ! empty( $item['featured'] ), 'highlight' => ! empty( $item['featured'] ), 'promotional' => ! empty( $item['featured'] ), 'show_on_home' => ! empty( $item['featured'] ), 'home' => ! empty( $item['featured'] ), 'home_featured' => ! empty( $item['featured'] ), 'cardapio_home' => ! empty( $item['featured'] ),
			'image_id' => (int) $item['image_id'], 'image' => $image_url, 'image_url' => $image_url, 'photo' => $image_url, 'photo_url' => $image_url, 'foto' => $image_url, 'foto_url' => $image_url, 'thumbnail' => $thumb_url, 'thumbnail_url' => $thumb_url,
			'status' => $item['status'], 'active' => 'active' === $item['status'], 'enabled' => 'active' === $item['status'], 'published' => 'active' === $item['status'], 'order' => (int) $item['order'], 'sort_order' => (int) $item['order'], 'position' => (int) $item['order'], 'menu_order' => (int) $item['order'],
		);
	}

	private static function resolve_category_image_url( array $category, $size = 'large' ) {
		$image_id = isset( $category['image_id'] ) ? absint( $category['image_id'] ) : 0;
		if ( $image_id ) { $url = wp_get_attachment_image_url( $image_id, $size ); if ( $url ) { return esc_url_raw( $url ); } }
		return ! empty( $category['image_url'] ) ? esc_url_raw( $category['image_url'] ) : '';
	}

	private static function resolve_item_image_url( array $item, $size = 'large' ) {
		$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
		if ( $image_id ) { $url = wp_get_attachment_image_url( $image_id, $size ); if ( $url ) { return esc_url_raw( $url ); } }
		return ! empty( $item['image_url'] ) ? esc_url_raw( $item['image_url'] ) : '';
	}

	private static function sort_records( $a, $b ) {
		$order_a = isset( $a['order'] ) ? (int) $a['order'] : 0;
		$order_b = isset( $b['order'] ) ? (int) $b['order'] : 0;
		if ( $order_a === $order_b ) {
			return strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
		}
		return $order_a <=> $order_b;
	}

	private static function next_id( array $records ) {
		$max = 0;
		foreach ( $records as $record ) {
			$max = max( $max, isset( $record['id'] ) ? (int) $record['id'] : 0 );
		}
		return $max + 1;
	}

	private static function has_active_menu( array $menus ) {
		foreach ( $menus as $menu ) { if ( 'active' === $menu['status'] ) { return true; } }
		return false;
	}

	private static function enforce_single_active_menu( array $menus, $active_id ) {
		$now = current_time( 'mysql' );
		foreach ( $menus as $index => $menu ) {
			$menus[ $index ]['status'] = ( (int) $menu['id'] === (int) $active_id ) ? 'active' : 'inactive';
			$menus[ $index ]['updated_at'] = $now;
		}
		return $menus;
	}
}
