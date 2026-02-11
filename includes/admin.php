<?php
/**
 * Administrative helpers for the Menu Item custom post type.
 *
 * Purpose: Registers metaboxes, admin column customizations, and CSV/image helpers for menu items.
 * Usage context: Load in the WordPress admin alongside the `WP Menu CPT` Menu Item custom post type.
 * Author: André Marinho
 * Website: andremarinho.me
 * Version: 1.1.1
 *
 * @package WP_Menu_CPT
 */

defined('ABSPATH') || exit;

// 3) Price metabox (single field)
add_action('add_meta_boxes', function () {
  add_meta_box('menu_item_price', 'Price', function ($post) {
    $price = get_post_meta($post->ID, '_menu_price', true);
    ?>
    <p>
      <label for="menu_price">Price (use comma or period):</label><br>
      <input style="width:100%" type="text" id="menu_price" name="menu_price"
             value="<?php echo esc_attr($price); ?>" placeholder="Example: 32.00" />
    </p>
    <?php
  }, 'menu_item', 'side', 'high');
});

add_action('admin_footer-post.php', 'menu_item_single_category_enforcer');
add_action('admin_footer-post-new.php', 'menu_item_single_category_enforcer');
add_action('admin_footer-edit.php', 'menu_item_single_category_enforcer');
if (!function_exists('menu_item_single_category_enforcer')) {
  function menu_item_single_category_enforcer() {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== 'menu_item') return;
  ?>
  <script>
  (function () {
    var inputName = 'tax_input[menu_category][]';

    function collectLists() {
      var seen = [];
      document.querySelectorAll('input[type="checkbox"][name="' + inputName + '"]').forEach(function (box) {
        var list = box.closest('.categorychecklist');
        if (list && seen.indexOf(list) === -1) {
          seen.push(list);
        }
      });
      return seen;
    }

    function enforceSingle(list) {
      if (!list) return;
      var firstChecked = null;
      list.querySelectorAll('input[type="checkbox"][name="' + inputName + '"]').forEach(function (box) {
        if (box.checked) {
          if (!firstChecked) {
            firstChecked = box;
          } else {
            box.checked = false;
          }
        }
      });
    }

    document.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || target.tagName !== 'INPUT' || target.type !== 'checkbox') return;
      if (target.name !== inputName) return;
      if (!target.checked) return;
      var list = target.closest('.categorychecklist');
      if (!list) return;
      list.querySelectorAll('input[type="checkbox"][name="' + inputName + '"]').forEach(function (box) {
        if (box !== target) box.checked = false;
      });
    });

    document.addEventListener('DOMContentLoaded', function () {
      collectLists().forEach(enforceSingle);
    });

    document.addEventListener('click', function (event) {
      if (!event.target || event.target.id !== 'menu_category-add-submit') return;
      window.setTimeout(function () {
        collectLists().forEach(enforceSingle);
      }, 300);
    });
  })();
  </script>
  <?php
  }
}

add_filter('manage_edit-menu_category_columns', function ($columns) {
  $columns['menu_category_order'] = 'Order';
  $columns['menu_category_layout'] = 'Layout';
  return $columns;
});

add_filter('manage_menu_category_custom_column', function ($content, $column, $term_id) {
  if ($column === 'menu_category_order') {
    $order = get_term_meta($term_id, 'menu_category_order', true);
    $order = is_numeric($order) ? (int)$order : 0;
    return '<span class="menu-category-order" data-order="' . esc_attr($order) . '">' . esc_html($order) . '</span>';
  }
  if ($column === 'menu_category_layout') {
    $layout = get_term_meta($term_id, 'menu_category_layout', true);
    $layout = in_array($layout, ['simple', 'media'], true) ? $layout : 'simple';
    $label = ($layout === 'media') ? 'Media' : 'Simple';
    return '<span class="menu-category-layout" data-layout="' . esc_attr($layout) . '">' . esc_html($label) . '</span>';
  }
  return $content;
}, 10, 3);

add_action('admin_footer-edit-tags.php', function () {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->taxonomy !== 'menu_category') return;
  ?>
  <script>
  (function ($) {
    function ensureQuickEditFields() {
      var $template = $('#inline-edit');
      if (!$template.length) {
        return;
      }
      var $container = $template.find('.inline-edit-col');
      if (!$container.length) {
        return;
      }
      if ($container.find('.menu-category-extra-field').length) {
        return;
      }

      var markup = ''
        + '<label class="menu-category-extra-field menu-category-order-wrap">'
        + '  <span class="title">Order</span>'
        + '  <span class="input-text-wrap"><input type="number" name="menu_category_order" value="0" step="1" /></span>'
        + '</label>'
        + '<label class="menu-category-extra-field menu-category-layout-wrap">'
        + '  <span class="title">Layout</span>'
        + '  <span class="input-text-wrap"><select name="menu_category_layout"><option value="simple">Simple</option><option value="media">Media</option></select></span>'
        + '</label>';

      $container.append(markup);
    }

    $(document).ready(function () {
      ensureQuickEditFields();
    });

    $(document).on('click', 'button.editinline', function () {
      ensureQuickEditFields();
    });

    var inlineEditTax = window.inlineEditTax;
    if (!inlineEditTax || !inlineEditTax.edit) {
      return;
    }

    var wpInlineEditTaxEdit = inlineEditTax.edit;
    inlineEditTax.edit = function (id) {
      ensureQuickEditFields();
      wpInlineEditTaxEdit.apply(this, arguments);

      var termId;
      if (typeof id === 'object') {
        termId = parseInt(this.getId(id), 10);
      } else {
        termId = parseInt(id, 10);
      }
      if (!termId) {
        return;
      }

      var $row = $('#tag-' + termId);
      if (!$row.length) {
        return;
      }

      var order = $row.find('.menu-category-order').data('order');
      if (typeof order === 'undefined') {
        order = 0;
      }

      var layout = $row.find('.menu-category-layout').data('layout');
      if (typeof layout === 'undefined') {
        layout = 'simple';
      }

      var $editRow = $('#edit-' + termId);
      if (!$editRow.length) {
        return;
      }

      $editRow.find('input[name="menu_category_order"]').val(order);
      $editRow.find('select[name="menu_category_layout"]').val(layout);
    };
  })(jQuery);
  </script>
  <?php
});
add_action('menu_category_add_form_fields', function () {
  ?>
  <div class="form-field">
    <label for="menu_category_order">Order</label>
    <input type="number" name="menu_category_order" id="menu_category_order" value="0" step="1" />
    <p class="description">Defines the order used when displaying this category on the front end.</p>
  </div>
  <div class="form-field">
    <label for="menu_category_layout">Layout</label>
    <select name="menu_category_layout" id="menu_category_layout">
      <option value="simple" selected>Simple</option>
      <option value="media">Media</option>
    </select>
    <p class="description">Selects the default layout applied to the shortcode.</p>
  </div>
  <?php
});

add_action('menu_category_edit_form_fields', function ($term) {
  $order = get_term_meta($term->term_id, 'menu_category_order', true);
  $order_value = is_numeric($order) ? (int)$order : 0;
  $layout = get_term_meta($term->term_id, 'menu_category_layout', true);
  $layout = in_array($layout, ['simple', 'media'], true) ? $layout : 'simple';
  ?>
  <tr class="form-field">
    <th scope="row"><label for="menu_category_order">Order</label></th>
    <td>
      <input type="number" name="menu_category_order" id="menu_category_order" value="<?php echo esc_attr($order_value); ?>" step="1" />
      <p class="description">Defines the order used when displaying this category on the front end.</p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row"><label for="menu_category_layout">Layout</label></th>
    <td>
      <select name="menu_category_layout" id="menu_category_layout">
        <option value="simple" <?php selected($layout, 'simple'); ?>>Simple</option>
        <option value="media" <?php selected($layout, 'media'); ?>>Media</option>
      </select>
      <p class="description">Selects the default layout applied to the shortcode.</p>
    </td>
  </tr>
  <?php
}, 10, 2);

function menu_category_save_meta_fields($term_id) {
  if (!isset($_POST['taxonomy']) || $_POST['taxonomy'] !== 'menu_category') return;

  $order = isset($_POST['menu_category_order']) ? (int)sanitize_text_field(wp_unslash($_POST['menu_category_order'])) : 0;
  update_term_meta($term_id, 'menu_category_order', $order);

  if (isset($_POST['menu_category_layout'])) {
    $layout = sanitize_text_field(wp_unslash($_POST['menu_category_layout']));
    $layout = in_array($layout, ['simple', 'media'], true) ? $layout : 'simple';
  } else {
    $layout = 'simple';
  }
  update_term_meta($term_id, 'menu_category_layout', $layout);
}
add_action('created_menu_category', 'menu_category_save_meta_fields');
add_action('edited_menu_category', 'menu_category_save_meta_fields');

add_action('save_post_menu_item', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  if (isset($_POST['menu_price'])) {
    $display = sanitize_text_field(wp_unslash($_POST['menu_price']));
    update_post_meta($post_id, '_menu_price', $display);
    // also save the numeric value
    $num = preg_replace('/[^\d,.\-]/', '', $display);
    $num = str_replace(['.', ' '], ['', ''], $num); // remove thousands separator
    $num = str_replace(',', '.', $num);
    $numf = is_numeric($num) ? (float)$num : null;
    if ($numf !== null) update_post_meta($post_id, '_menu_price_number', $numf);
  }

  $new_terms = null;
  if (isset($_POST['tax_input']['menu_category'])) {
    $raw_terms = wp_unslash($_POST['tax_input']['menu_category']);
    if (!is_array($raw_terms)) {
      $raw_terms = explode(',', (string)$raw_terms);
    }
    $first = null;
    foreach ($raw_terms as $term) {
      $term = trim((string)$term);
      if ($term === '' || $term === '0') continue;
      $first = (int)$term;
      if ($first > 0) break;
    }
    $new_terms = ($first && $first > 0) ? [$first] : [];
  }

  if ($new_terms !== null) {
    wp_set_post_terms($post_id, $new_terms, 'menu_category', false);
  } else {
    $current_terms = wp_get_post_terms($post_id, 'menu_category');
    if (count($current_terms) > 1) {
      wp_set_post_terms($post_id, [$current_terms[0]->term_id], 'menu_category', false);
    }
  }
});

// 4) “Price” column in the custom post type list
add_filter('manage_menu_item_posts_columns', function ($cols) {
  $new = [];
  foreach ($cols as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') $new['menu_price'] = 'Price';
  }
  return $new;
});
add_action('manage_menu_item_posts_custom_column', function ($column, $post_id) {
  if ($column === 'menu_price') {
    $price = get_post_meta($post_id, '_menu_price', true);
    echo $price ? '$ ' . esc_html($price) : '—';
  }
}, 10, 2);
