<?php
/**
 * Frontend rendering helpers for the Menu Item custom post type.
 *
 * Purpose: Supplies shortcodes and rendering logic for displaying menu categories and items.
 * Usage context: Load on the public site wherever the `WP Menu CPT` menu needs to appear.
 * Author: André Marinho.
 * Website: andremarinho.me.
 * Version: 1.1.1
 *
 * @package WP_Menu_CPT
 */

defined('ABSPATH') || exit;

if (!function_exists('menu_item_get_sorted_categories')) {
  function menu_item_get_sorted_categories($args = []) {
    $defaults = [
      'taxonomy'   => 'menu_category',
      'hide_empty' => false,
    ];
    $terms = get_terms(wp_parse_args($args, $defaults));
    if (is_wp_error($terms) || empty($terms)) return [];

    usort($terms, function ($a, $b) {
      $order_a = get_term_meta($a->term_id, 'menu_category_order', true);
      $order_b = get_term_meta($b->term_id, 'menu_category_order', true);
      $order_a = is_numeric($order_a) ? (int)$order_a : 0;
      $order_b = is_numeric($order_b) ? (int)$order_b : 0;

      if ($order_a === $order_b) {
        return strcasecmp($a->name, $b->name);
      }
      return ($order_a < $order_b) ? -1 : 1;
    });

    return $terms;
  }
}
// Shared renderer
if (!function_exists('menu_item_render_category')) {
  function menu_item_render_category($term, $atts = []) {
    if (!$term || is_wp_error($term)) return '';

    $atts = shortcode_atts([
      // Visibility
      'show_title' => '0', // default: hide the category title
      'show_desc'  => '1',
      'show_price' => '1',
      // Layout
      'layout'     => '', // simple | media
      'limit'      => '',
      'order'      => 'ASC',
      'orderby'    => 'menu_order', // or 'title'
    ], (array)$atts, 'menu_category_display');

    $layout = in_array($atts['layout'], ['simple','media'], true) ? $atts['layout'] : null;
    if ($layout === null) {
      $layout_meta = get_term_meta($term->term_id, 'menu_category_layout', true);
      $layout = in_array($layout_meta, ['simple','media'], true) ? $layout_meta : 'simple';
    }
    $category_order_meta = get_term_meta($term->term_id, 'menu_category_order', true);
    $category_order = is_numeric($category_order_meta) ? (int)$category_order_meta : 0;

    $limit = (is_numeric($atts['limit']) && (int)$atts['limit'] > 0) ? (int)$atts['limit'] : -1;
    $orderby = in_array($atts['orderby'], ['menu_order','title'], true) ? $atts['orderby'] : 'menu_order';
    $order   = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';

    $q = new WP_Query([
      'post_type'      => 'menu_item',
      'post_status'    => 'publish',
      'posts_per_page' => $limit,
      'no_found_rows'  => true,
      'orderby'        => $orderby,
      'order'          => $order,
      'tax_query'      => [[
        'taxonomy' => 'menu_category',
        'field'    => 'term_id',
        'terms'    => [$term->term_id],
      ]],
    ]);

    if (!$q->have_posts()) return '';

    // Inline CSS (printed once per page)
    static $printed_styles = false;
    $styles_html = '';
    if (!$printed_styles) {
      $printed_styles = true;
      $styles_html = '<style id="menu-items-styles">'
        .'.menu-category{margin:1.5rem 0}'
        .'.menu-category-title{margin:0 0 0.75rem 0;font-weight:700;color:#1f2531;font-size:1.25rem}'
        .'@media(min-width:768px){.menu-category-title{font-size:1.5rem}}'
        .'@media(min-width:1024px){.menu-category-title{font-size:2.2rem}}'
        .'.menu-items{list-style:none!important;margin:0;padding:0!important;display:grid;grid-template-columns:1fr;gap:1rem 2rem;color:#1f2531}'
        .'.menu-items li{list-style:none!important;margin:0;padding:0!important}'
        .'@media(min-width:768px){.menu-items{grid-template-columns:1fr 1fr}}'
        .'.menu-item{list-style:none;margin:0}'
        .'.menu-item-line{display:flex;align-items:baseline;gap:.5rem;color:#1f2531}'
        .'.menu-item-title{font-weight:600;color:#1f2531}'
        .'.menu-item-dots{flex:1 1 auto;border-bottom:1px dotted rgba(31,37,49,.25);transform:translateY(-2px)}'
        .'.menu-item-price{white-space:nowrap;font-weight:600;color:#1f2531}'
        .'.menu-items.simple .menu-item-desc{display:none}'
        .'.menu-items.media .menu-item{display:grid;grid-template-columns:86px 1fr;column-gap:12px;align-items:flex-start}'
        .'.menu-items.media .thumb{width:86px;height:86px;border-radius:999px;background:#f0f1f2;display:flex;align-items:center;justify-content:center;overflow:hidden}'
        .'.menu-items.media .thumb img{width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block}'
        .'.menu-items.media .thumb-placeholder{width:60%;height:60%;border-radius:50%;background:#d8dbde;display:block}'
        .'.menu-items.media .menu-item-content{display:flex;flex-direction:column;gap:.35rem;align-items:flex-start;width:100%}'
        .'.menu-items.media .menu-item-line{color:#1f2531;width:100%}'
        .'.menu-items.media .menu-item-title{color:#1f2531}'
        .'.menu-items.media .menu-item-desc{color:#1f2531;font-size:.95em;text-align:left;width:100%}'
        .'.menu-items.media .menu-item-desc p{margin:0}'
        .'</style>';
    }

    ob_start();
    ?>
    <?php echo $styles_html; ?>
    <div class="menu-category" data-category="<?php echo esc_attr($term->slug); ?>" data-layout="<?php echo esc_attr($layout); ?>" data-order="<?php echo esc_attr($category_order); ?>">
      <?php if ($atts['show_title'] === '1') : ?>
        <h2 id="<?php echo esc_attr($term->slug); ?>" class="menu-category-title"><?php echo esc_html($term->name); ?></h2>
      <?php endif; ?>
      <?php
        $ul_class = 'menu-items ' . $layout;
      ?>
      <ul class="<?php echo esc_attr($ul_class); ?>">
        <?php while ($q->have_posts()) : $q->the_post(); ?>
          <?php
            $pid   = get_the_ID();
            $price = get_post_meta($pid, '_menu_price', true);
            $description  = get_post_field('post_content', $pid);
            $has_description = !empty(trim($description));
          ?>
          <li class="menu-item" data-id="<?php echo esc_attr($pid); ?>">
            <?php if ($layout === 'media') : ?>
              <div class="thumb">
                <?php if (has_post_thumbnail($pid)) : ?>
                  <?php echo get_the_post_thumbnail($pid, 'thumbnail', ['class' => 'thumb-image', 'alt' => esc_attr(get_the_title())]); ?>
                <?php else : ?>
                  <span class="thumb-placeholder" aria-hidden="true"></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="menu-item-content">
              <div class="menu-item-line">
                <span class="menu-item-title"><?php echo esc_html(get_the_title()); ?></span>
                <span class="menu-item-dots" aria-hidden="true"></span>
                <?php if ($atts['show_price'] === '1' && $price) : ?>
                  <span class="menu-item-price">$ <?php echo esc_html($price); ?></span>
                <?php endif; ?>
              </div>

              <?php $should_show_description = ($layout === 'media') && $has_description; ?>
              <?php if ($should_show_description) : ?>
                <div class="menu-item-desc"><?php echo wpautop(esc_html($description)); ?></div>
              <?php endif; ?>
            </div>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>
    <?php
    wp_reset_postdata();
    return trim(ob_get_clean());
  }
}

// Shortcode base
add_shortcode('menu_category_display', function ($atts) {
  $atts = shortcode_atts([
    'id'    => '',
    'slug'  => '',
    'name'  => '',
    'show_title' => '0',
    'show_desc'  => '1',
    'show_price' => '1',
    'layout'     => '',
    'limit'      => '',
    'order'      => 'ASC',
    'orderby'    => 'menu_order',
  ], (array)$atts, 'menu_category_display');

  $term = null;
  if ($atts['id'] !== '') {
    $term = get_term_by('id', (int)$atts['id'], 'menu_category');
  } elseif ($atts['slug'] !== '') {
    $term = get_term_by('slug', sanitize_title($atts['slug']), 'menu_category');
  } elseif ($atts['name'] !== '') {
    $term = get_term_by('name', $atts['name'], 'menu_category');
  }
  if (!$term || is_wp_error($term)) return '';
  return menu_item_render_category($term, $atts);
});

add_shortcode('menu_full_display', function ($atts = []) {
  $atts = shortcode_atts([
    'show_title' => '1',
    'show_desc'  => '1',
    'show_price' => '1',
    'layout'     => '',
    'limit'      => '',
    'order'      => 'ASC',
    'orderby'    => 'menu_order',
  ], (array)$atts, 'menu_full_display');

  $terms = menu_item_get_sorted_categories();
  if (empty($terms)) return '';

  $parts = [];
  foreach ($terms as $term) {
    $html = menu_item_render_category($term, $atts);
    if ($html !== '') {
      $parts[] = $html;
    }
  }

  return implode("\n", $parts);

});

// Automatic per-category shortcodes: [menu_{slug}] (slug with '-' becomes '_')
add_action('init', function () {
  $terms = menu_item_get_sorted_categories();
  if (empty($terms)) return;

  foreach ($terms as $t) {
    $tag = 'menu_' . str_replace('-', '_', $t->slug);
    add_shortcode($tag, function ($atts = []) use ($t) {
      $defaults = [
        'show_title' => '0',
        'show_desc'  => '1',
        'show_price' => '1',
        'layout'     => '',
        'limit'      => '',
        'order'      => 'ASC',
        'orderby'    => 'menu_order',
      ];
      $atts = shortcode_atts($defaults, (array)$atts, $tag);
      return menu_item_render_category($t, $atts);
    });
  }
}, 20);




