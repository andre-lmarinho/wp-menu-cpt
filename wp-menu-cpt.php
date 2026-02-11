<?php
/**
 * Plugin bootstrap for the WP Menu CPT plugin.
 *
 * @wordpress-plugin
 * Plugin Name: WP Menu CPT
 * Description: Registers a Menu Item custom post type with categories, pricing, CSV import, image linking, and shortcode rendering.
 * Author: André Marinho
 * Website: andremarinho.me
 * Version: 1.1.1
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Text Domain: wp-menu-cpt
 *
 * @package WP_Menu_CPT
 */

defined('ABSPATH') || exit;

if (!defined('MENU_CPT_VERSION')) {
  define('MENU_CPT_VERSION', '1.1.1');
}

if (!defined('MENU_CPT_DIR')) {
  define('MENU_CPT_DIR', __DIR__);
}

if (!defined('MENU_CPT_INC_DIR')) {
  define('MENU_CPT_INC_DIR', MENU_CPT_DIR . '/includes');
} // Replace the CLIENT_* prefix with the actual client slug before reusing this bundle.

if (!function_exists('menu_cpt_require')) {
  /**
   * Require a module from the MU plugin includes directory.
   *
   * @param string $relative Relative path within the includes folder.
   * @return bool Whether the file was loaded.
   */
  function menu_cpt_require($relative) {
    $relative = ltrim((string)$relative, '/');
    $path = MENU_CPT_INC_DIR . '/' . $relative;

    if (file_exists($path)) {
      require_once $path;
      return true;
    }

    if (is_admin()) {
      add_action('admin_notices', function () use ($relative, $path) {
        if (!current_user_can('manage_options')) {
          return;
        }
        printf(
          '<div class="notice notice-error"><p>%s</p></div>',
          esc_html(sprintf('WP Menu CPT: missing module "%s" (expected at %s).', $relative, $path)) /* Replace `WP Menu CPT` before publishing. */
        );
      });
    }

    return false;
  }
}

menu_cpt_require('core.php');

if (is_admin()) {
  menu_cpt_require('admin.php');
  menu_cpt_require('admin-bulk.php');
}

menu_cpt_require('frontend.php');
