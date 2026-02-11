<?php
/**
 * Bulk admin tools for the Menu Item custom post type.
 *
 * Purpose: Provides CSV import and image linking utilities for menu items.
 * Usage context: Load in the WordPress admin to support mass menu updates for the `WP Menu CPT` menu system.
 * Author: André Marinho
 * Website: andremarinho.me
 * Version: 1.1.1
 *
 * @package WP_Menu_CPT
 */

defined('ABSPATH') || exit;

// 5) Submenus: Import CSV and Link Images
add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=menu_item',
    'Import CSV',
    'Import CSV',
    'manage_options',
    'menu_item_import_csv',
    'menu_item_import_csv_page'
  );
  add_submenu_page(
    'edit.php?post_type=menu_item',
    'Link Images',
    'Link Images',
    'manage_options',
    'menu_item_link_images',
    'menu_item_link_images_page'
  );
});

// Admin page: Import CSV
if (!function_exists('menu_item_import_csv_page')) {
function menu_item_import_csv_page() {
  if (!current_user_can('manage_options')) return;

  $action = isset($_POST['menu_item_import_action']) ? sanitize_text_field($_POST['menu_item_import_action']) : '';
  ?>
  <div class="wrap">
    <h1>Import Menu via CSV</h1>
    <p>Expected header format: <code>Category,Name,Description,Price</code></p>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('menu_item_import_csv_nonce', 'menu_item_import_csv_nonce_field'); ?>
      <input type="hidden" name="menu_item_import_action" value="do_import" />
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="csv_file">CSV file</label></th>
          <td><input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required /></td>
        </tr>
        <tr>
          <th scope="row"><label for="delimiter">Delimiter</label></th>
          <td>
            <select id="delimiter" name="delimiter">
              <option value=",">Comma (,)</option>
              <option value=";">Semicolon (;)</option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row">Update existing items?</th>
          <td>
            <label><input type="checkbox" name="update_existing" value="1" checked /> Yes, update existing items</label>
          </td>
        </tr>
      </table>
      <?php submit_button('Import'); ?>
    </form>
  <?php

  if ($action === 'do_import') {
    if (!isset($_POST['menu_item_import_csv_nonce_field']) || !wp_verify_nonce($_POST['menu_item_import_csv_nonce_field'], 'menu_item_import_csv_nonce')) {
      echo '<div class="notice notice-error"><p>Security check failed.</p></div></div>';
      return;
    }
    if (empty($_FILES['csv_file']['tmp_name'])) {
      echo '<div class="notice notice-error"><p>No file uploaded.</p></div></div>';
      return;
    }

    $delimiter = isset($_POST['delimiter']) && $_POST['delimiter'] === ';' ? ';' : ',';
    $update_existing = !empty($_POST['update_existing']);

    $tmp = $_FILES['csv_file']['tmp_name'];
    $result = menu_item_process_csv($tmp, $delimiter, $update_existing);

    echo '<h2>Result</h2>';
    if (is_wp_error($result)) {
      echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
    } else {
      echo '<div class="notice notice-success"><p><strong>Import completed.</strong></p></div>';
      echo '<ul>';
      echo '<li>Created: <strong>' . intval($result['created']) . '</strong></li>';
      echo '<li>Updated: <strong>' . intval($result['updated']) . '</strong></li>';
      echo '<li>Skipped: <strong>' . intval($result['skipped']) . '</strong></li>';
      echo '<li>Errors: <strong>' . intval($result['errors']) . '</strong></li>';
      echo '</ul>';

      if (!empty($result['log'])) {
        echo '<h3>Log</h3><ol>';
        foreach ($result['log'] as $line) {
          echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ol>';
      }
    }
    echo '</div>'; // .wrap
    return;
  }

  echo '</div>'; // .wrap
}
}

/** Image linker (manual) */

// Extract the leading numeric code from the title (digits at the beginning only)
if (!function_exists('menu_item_extract_code_from_title')) {
  function menu_item_extract_code_from_title($title) {
    $title = trim((string)$title);
    if (preg_match('/^\s*(\d{1,12})\b/u', $title, $m)) {
      return $m[1];
    }
    return '';
  }
}

// Locate the attachment ID by code, trying the slug and file path
if (!function_exists('menu_item_find_image_by_code')) {
  function menu_item_find_image_by_code($code) {
    if ($code === '') return 0;

    // 1) By attachment slug (file name without extension)
    $by_slug = get_page_by_path(sanitize_title($code), OBJECT, 'attachment');
    if ($by_slug && isset($by_slug->ID) && wp_attachment_is_image($by_slug->ID)) {
      return (int)$by_slug->ID;
    }

    // 2) Fallback: meta _wp_attached_file contendo "/CODE." ou "/CODE-"
    $meta_query = [
      'relation' => 'OR',
      [
        'key'     => '_wp_attached_file',
        'value'   => '/' . $code . '.',
        'compare' => 'LIKE',
      ],
      [
        'key'     => '_wp_attached_file',
        'value'   => '/' . $code . '-',
        'compare' => 'LIKE',
      ],
    ];

    $q = new WP_Query([
      'post_type'      => 'attachment',
      'post_status'    => 'inherit',
      'post_mime_type' => 'image',
      'posts_per_page' => 1,
      'orderby'        => 'date',
      'order'          => 'DESC',
      'meta_query'     => $meta_query,
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);

    if (!empty($q->posts)) {
      $aid = (int)$q->posts[0];
      if (wp_attachment_is_image($aid)) return $aid;
    }
    return 0;
  }
}

// Run the linking scan
if (!function_exists('menu_item_run_link_images')) {
  function menu_item_run_link_images($overwrite = false) {
    $paged = 1; $per_page = 200;
    $linked = $skipped = $not_found = 0;
    $log = [];

    do {
      $q = new WP_Query([
        'post_type'      => 'menu_item',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
      ]);

      if (!$q->have_posts()) break;
      foreach ($q->posts as $pid) {
        $title = get_the_title($pid);
        $code  = menu_item_extract_code_from_title($title);
        if ($code === '') { $skipped++; $log[] = "ID {$pid}: missing code at the start of the title ('{$title}')"; continue; }

        $has_thumb = has_post_thumbnail($pid);
        if ($has_thumb && !$overwrite) { $skipped++; $log[] = "ID {$pid} ({$code}): already has an image, skipped"; continue; }

        $aid = menu_item_find_image_by_code($code);
        if ($aid) {
          set_post_thumbnail($pid, $aid);
          $linked++; $log[] = "ID {$pid} ({$code}): linked to attachment #{$aid}";
        } else {
          $not_found++; $log[] = "ID {$pid} ({$code}): image not found";
        }
      }
      $paged++;
      wp_reset_postdata();
    } while ($q->max_num_pages >= $paged);

    return [
      'linked'    => $linked,
      'skipped'   => $skipped,
      'not_found' => $not_found,
      'log'       => $log,
    ];
  }
}

// Admin page: Link Images
if (!function_exists('menu_item_link_images_page')) {
function menu_item_link_images_page() {
    if (!current_user_can('manage_options')) return;

    $action = isset($_POST['menu_item_link_images_action']) ? sanitize_text_field($_POST['menu_item_link_images_action']) : '';
    ?>
    <div class="wrap">
      <h1>Link Images by Code</h1>
      <p>Rules:</p>
      <ul>
        <li>The item title must start with the numeric code (e.g., <code>701 - Juice...</code>).</li>
        <li>In the Media Library, the corresponding image file should be named <code>701.jpg</code> (or <code>701.png</code>, etc.).</li>
        <li>The year/month directory does not matter. The search checks the attachment slug and the base file name.</li>
      </ul>
      <form method="post">
        <?php wp_nonce_field('menu_item_link_images_nonce', 'menu_item_link_images_nonce_field'); ?>
        <input type="hidden" name="menu_item_link_images_action" value="do_link" />
        <p>
          <label><input type="checkbox" name="overwrite" value="1" /> Overwrite items that already have a featured image</label>
        </p>
        <?php submit_button('Check and Link'); ?>
      </form>
    <?php

    if ($action === 'do_link') {
      if (!isset($_POST['menu_item_link_images_nonce_field']) || !wp_verify_nonce($_POST['menu_item_link_images_nonce_field'], 'menu_item_link_images_nonce')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div></div>';
        return;
      }
      $overwrite = !empty($_POST['overwrite']);
      $result = menu_item_run_link_images($overwrite);
      echo '<h2>Result</h2>';
      echo '<ul>';
      echo '<li>Linked: <strong>' . intval($result['linked']) . '</strong></li>';
      echo '<li>Skipped: <strong>' . intval($result['skipped']) . '</strong></li>';
      echo '<li>Not found: <strong>' . intval($result['not_found']) . '</strong></li>';
      echo '</ul>';

      if (!empty($result['log'])) {
        echo '<h3>Log</h3><ol>';
        foreach ($result['log'] as $line) {
          echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ol>';
      }
      echo '</div>';
      return;
    }

    echo '</div>';
  }
}

if (!function_exists('menu_item_pick_csv_delimiter')) {
  function menu_item_pick_csv_delimiter($filepath, $preferred = ',') {
    $candidates = array_values(array_unique([$preferred, ',', ';', "	"]));

    $handle = fopen($filepath, 'r');
    if (!$handle) {
      return $preferred;
    }

    $samples = [];
    while (!feof($handle) && count($samples) < 5) {
      $line = fgets($handle);
      if ($line === false) {
        break;
      }
      if (trim($line) === '') {
        continue;
      }
      $samples[] = $line;
    }
    fclose($handle);

    if (empty($samples)) {
      return $preferred;
    }

    $best = $preferred;
    $best_score = 1;
    foreach ($candidates as $candidate) {
      $score = 0;
      foreach ($samples as $line) {
        $fields = str_getcsv($line, $candidate);
        $score = max($score, count($fields));
      }
      if ($score > $best_score) {
        $best_score = $score;
        $best = $candidate;
      }
    }

    return $best;
  }
}

// Helper to normalize CSV column names (admin)
if (!function_exists('menu_item_normalize_header')) {
  function menu_item_normalize_header($s) {
    $s = trim((string)$s);
    $s = mb_strtolower($s);
    if (function_exists('remove_accents')) {
      $s = remove_accents($s); // normalizes accented chars (e.g., preço → preco)
    }
    // replace any non [a-z0-9] characters with _
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
  }
}

// CSV import process

}

}
}
if (!function_exists('menu_item_process_csv')) {
function menu_item_process_csv($filepath, $delimiter = ',', $update_existing = true) {
  if (!file_exists($filepath) || !is_readable($filepath)) {
    return new WP_Error('csv_read_error', 'CSV file not found or unreadable.');
  }

  $delimiter = menu_item_pick_csv_delimiter($filepath, $delimiter);

  $handle = fopen($filepath, 'r');
  if (!$handle) return new WP_Error('csv_open_error', 'Could not open the CSV file.');

  // Remove UTF-8 BOM if present
  $bom = fread($handle, 3);
  if ($bom !== "ï»¿") rewind($handle);

  // Read header
  $header = fgetcsv($handle, 0, $delimiter);
  if ($header === false) {
    fclose($handle);
    return new WP_Error('csv_header_error', 'Could not read the CSV header.');
  }

  // Map normalized columns to indexes
  $map = [];
  foreach ($header as $i => $col) {
    $norm = menu_item_normalize_header($col);
    if ($norm !== '') $map[$norm] = $i;
  }

  // Accepted synonyms
  $aliases = [
    'category'    => ['categoria', 'categorias', 'category', 'categories'],
    'description' => ['descricao', 'descr', 'description', 'descricao_do_produto'],
    'price'       => ['preco', 'valor', 'price', 'preco_r'],
  ];

  // Resolve required indexes
  $idx = [];
  foreach ($aliases as $key => $cands) {
    $found = null;
    foreach ($cands as $cand) {
      if (isset($map[$cand])) { $found = $map[$cand]; break; }
    }
    if ($found === null) {
      fclose($handle);
      return new WP_Error(
        'csv_columns_error',
        'Invalid CSV. Expected a header with columns equivalent to: Category, Name, Description, Price (or synonyms such as Value/Price).'
      );
    }
    $idx[$key] = $found;
  }

  $created = $updated = $skipped = $errors = 0;
  $log = [];
  $rownum = 1;
  $log[] = 'Using delimiter "' . ($delimiter === "	" ? 'tab' : $delimiter) . '".';

  while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $rownum++;

    if (count(array_filter($row, 'strlen')) === 0) { $skipped++; $log[] = "Row {$rownum}: empty -> skipped"; continue; }

    $category    = trim($row[$idx['category']] ?? '');
    $name        = trim($row[$idx['name']] ?? '');
    $description = trim($row[$idx['description']] ?? '');
    $price_raw   = trim($row[$idx['price']] ?? '');

    if ($category === '' || $name === '') { $skipped++; $log[] = "Row {$rownum}: missing category/name -> skipped"; continue; }

    // Normalize price (display and numeric)
    $display_price = preg_replace('/^\s*R\$\s*/i', '', $price_raw);
    $display_price = trim($display_price);
    $numeric = preg_replace('/[^\d,.\-]/', '', $display_price);
    $numeric = str_replace(['.', ' '], ['', ''], $numeric);
    $numeric = str_replace(',', '.', $numeric);
    $numf = is_numeric($numeric) ? (float)$numeric : null;

    // Category (term)
    $term = term_exists($category, 'menu_category');
    if (!$term) {
      $inserted = wp_insert_term($category, 'menu_category', [
        'slug' => sanitize_title($category),
      ]);
      if (is_wp_error($inserted)) {
        $errors++; $log[] = "Row {$rownum}: error creating category '{$category}': " . $inserted->get_error_message();
        continue;
      }
      $term_id = $inserted['term_id'];
    } else {
      $term_id = is_array($term) ? $term['term_id'] : $term;
    }

    $term_obj = get_term($term_id, 'menu_category');
    $cat_slug = $term_obj ? $term_obj->slug : sanitize_title($category);
    $unique_key = strtolower($cat_slug . '|' . $name);

    // Look up existing record by _menu_unique_key
    $existing = new WP_Query([
      'post_type'      => 'menu_item',
      'meta_key'       => '_menu_unique_key',
      'meta_value'     => $unique_key,
      'fields'         => 'ids',
      'posts_per_page' => 1,
      'no_found_rows'  => true,
    ]);

    $postarr = [
      'post_title'   => $name,
      'post_content' => $description,
      'post_status'  => 'publish',
      'post_type'    => 'menu_item',
      'tax_input'    => ['menu_category' => [$term_id]],
    ];

    if ($existing->have_posts()) {
      $post_id = (int)$existing->posts[0];
      if ($update_existing) {
        $postarr['ID'] = $post_id;
        wp_update_post($postarr);
        update_post_meta($post_id, '_menu_price', $display_price);
        if ($numf !== null) update_post_meta($post_id, '_menu_price_number', $numf);
        $updated++; $log[] = "Row {$rownum}: updated '{$name}' in '{$category}' (ID {$post_id}).";
      } else {
        $skipped++; $log[] = "Row {$rownum}: existing and updates disabled -> skipped '{$name}'.";
      }
    } else {
      $post_id = wp_insert_post($postarr, true);
      if (is_wp_error($post_id) || !$post_id) {
        $errors++; $msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'unknown error';
        $log[] = "Row {$rownum}: error creating '{$name}': {$msg}";
        continue;
      }
      update_post_meta($post_id, '_menu_unique_key', $unique_key);
      update_post_meta($post_id, '_menu_price', $display_price);
      if ($numf !== null) update_post_meta($post_id, '_menu_price_number', $numf);
      wp_set_post_terms($post_id, [$term_id], 'menu_category', false);
      $created++; $log[] = "Row {$rownum}: created '{$name}' in '{$category}' (ID {$post_id}).";
    }
    wp_reset_postdata();
  }

  fclose($handle);

  return [
    'created' => $created,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
    'log'     => $log,
    'delimiter' => $delimiter,
  ];
}
}
