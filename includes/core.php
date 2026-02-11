<?php
/**
 * Core bootstrap for the Menu Item custom post type bundle.
 *
 * Purpose: Registers the custom post type, taxonomy, and helper metadata used by the Menu Item system.
 * Usage context: Loaded automatically by the `menu-cpt` MU plugin bootstrap on every request.
 * Author: André Marinho.
 * Website: andremarinho.me.
 * Version: 1.1.1
 *
 * @package WP_Menu_CPT
 */

defined('ABSPATH') || exit;

// Register custom post type for menu items.
add_action('init', function () {
  $labels = [
    'name'               => 'Menu Items',
    'singular_name'      => 'Menu Item',
    'add_new'            => 'Add new',
    'add_new_item'       => 'Add new item',
    'edit_item'          => 'Edit item',
    'new_item'           => 'New item',
    'view_item'          => 'View item',
    'search_items'       => 'Search items',
    'not_found'          => 'No items found',
    'menu_name'          => 'Menu',
  ];

  register_post_type('menu_item', [
    'labels'        => $labels,
    'public'        => true,
    'show_in_rest'  => true,
    'menu_icon'     => 'dashicons-food',
    'menu_position' => 20,
    'supports'      => ['title','editor','thumbnail','custom-fields','page-attributes'],
    'has_archive'   => false,
    'rewrite'       => ['slug' => 'menu'],
  ]);
});

// Register taxonomy for grouping menu items.
add_action('init', function () {
  $labels = [
    'name'          => 'Menu Categories',
    'singular_name' => 'Category',
    'search_items'  => 'Search categories',
    'all_items'     => 'All categories',
    'edit_item'     => 'Edit category',
    'update_item'   => 'Update category',
    'add_new_item'  => 'Add new category',
    'menu_name'     => 'Categories',
  ];

  register_taxonomy('menu_category', ['menu_item'], [
    'hierarchical'      => true,
    'labels'            => $labels,
    'show_ui'           => true,
    'show_in_rest'      => true,
    'show_admin_column' => true,
    'rewrite'           => ['slug' => 'menu-category'],
  ]);
});
