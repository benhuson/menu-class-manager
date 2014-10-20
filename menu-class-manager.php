<?php

/*
Plugin Name: Menu Class Manager
Description: This helps the primary menu decide what items should be selected when you get deeper into the navigation. You need to add classes to the menu items for this to work.
Version: 0.1
Author: Ben Huson
*/

add_action( 'wp', array( 'Menu_Class_Manager', 'setup_inbuilt_states' ) );
add_filter( 'wp_nav_menu_objects', array( 'Menu_Class_Manager', 'apply_menu_class_filters' ), 10, 2 );

class Menu_Class_Manager {

	protected static $selected_states = array();
	protected static $deselected_states = array();

	public static function setup_inbuilt_states() {

		// Selected States
		self::register_selected_state( 'mcm-archive-%%$post_type%%' );
		self::register_selected_state( 'mcm-single-%%$post_type%%' );
		self::register_selected_state( 'mcm-taxonomy-%%$taxonomy%%' );
		self::register_selected_state( 'mcm-taxonomy-%%$taxonomy%%-term-%%term%%' );
		self::register_selected_state( 'mcm-taxonomy-%%$taxonomy%%-term-%%term%%' );

		// Deselected States
		self::register_deselected_state( 'mcm-no-archive-%%$post_type%%' );
		self::register_deselected_state( 'mcm-no-single-%%$post_type%%' );
		self::register_deselected_state( 'mcm-no-taxonomy-%%$taxonomy%%' );
		self::register_deselected_state( 'mcm-no-taxonomy-%%$taxonomy%%-term-%%term%%' );
		self::register_deselected_state( 'mcm-no-taxonomy-%%$taxonomy%%-term-%%term%%' );

	}

	public static function register_selected_state( $class ) {

		$class = self::sanitize_state( $class );

		if ( ! in_array( $class, self::$selected_states ) ) {
			self::$selected_states[] = $class;
		}

	}

	public static function register_deselected_state( $class ) {

		$class = self::sanitize_state( $class );

		if ( ! in_array( $class, self::$deselected_states ) ) {
			self::$deselected_states[] = $class;
		}

	}

	public static function sanitize_state( $state ) {

		$state = strtolower( $state );
		$state = preg_replace( '/[^a-z0-9_\-\%]/', '', $state );

		return $state;

	}

	public static function format_current_state( $state ) {

		$qo = get_queried_object();

		// Posts
		if ( strpos( $state, '%%post_type%%' ) !== false ) {
			if ( is_a( $qo, 'WP_Post' ) || is_post_type_archive() ) {
				$post_type = is_post_type_archive() ? get_post_type() : get_post_type( $qo );
				$state = str_replace( '%%post_type%%', $post_type, $state );
			} else {
				$state = '';
			}
		}

		// Taxonomies & Terms
		if ( strpos( $state, '%%taxonomy%%' ) !== false || strpos( $state, '%%taxonomy%%' ) !== false ) {
			$taxonomy = apply_filters( 'menu_class_manager_current_taxonomy', get_query_var( 'taxonomy' ) );

			if ( ! empty( $taxonomy ) ) {
				$state = str_replace( '%%taxonomy%%', $taxonomy, $state );

				if ( strpos( $state, '%%term%%' ) !== false ) {
					$term_object = apply_filters( 'menu_class_manager_current_term', $qo );
					if ( isset( $term_object->term_id ) && isset( $term_object->slug ) ) {
						$term_format = $state;
						$states = array( str_replace( '%%term%%', $term_object->slug, $term_format ) );
						$anc = get_ancestors( $term_object->term_id, $term_object->taxonomy );
						if ( count( $anc ) > 0 ) {
							foreach ( $anc as $a ) {
								$a = get_term( $a, $term_object->taxonomy );
								$states[] = str_replace( '%%term%%', $a->slug, $term_format );
							}
						}
						$state = $states;
					}
				}

			} else {
				$state = '';
			}
		}

		if ( ! is_array( $state ) ) {
			$state = array( $state );
		}

		return $state;

	}

	public static function get_current_selected_states() {

		$states = array();

		// Replace post/taxonomy placeholder strings
		foreach ( self::$selected_states as $state ) {
			$states = array_merge( $states, self::format_current_state( $state ) );
		}

		// Remove empty states
		$states = array_filter( $states );

		return $states;

	}

	public static function get_current_deselected_states() {

		$states = array();

		// Replace post/taxonomy placeholder strings
		foreach ( self::$deselected_states as $state ) {
			$states = array_merge( $states, self::format_current_state( $state ) );
		}

		// Remove empty states
		$states = array_filter( $states );

		return $states;

	}

	public static function apply_menu_class_filters( $sorted_menu_items, $args ) {

		$states = self::get_current_selected_states();
		$deselected_states = self::get_current_deselected_states();

		// Loop through menu items
		foreach ( $sorted_menu_items as $key => $val ) {
			$classes = $sorted_menu_items[ $key ]->classes;

			// Manual Deselected states
			$found_states = array_intersect( $deselected_states, $classes );
			if ( count( $found_states ) > 0 ) {
				$sorted_menu_items[ $key ]->classes = array_diff( $sorted_menu_items[ $key ]->classes, self::get_selected_classes() );
			}

			// Manual states
			$found_states = array_intersect( $states, $classes );
			if ( count( $found_states ) > 0 ) {
				$sorted_menu_items[ $key ]->classes[] = 'current-menu-ancestor';
			}

		}


		return $sorted_menu_items;

	}

	public static function get_selected_classes() {

		return array(
			'current-menu-item',
			'current-menu-parent',
			'current-menu-ancestor',

			// Back compat
			'current_page_item',
			'current_page_parent',
			'current_page_ancestor'

		);

	}

}
