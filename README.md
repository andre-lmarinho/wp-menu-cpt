# WP Menu CPT

WP Menu CPT adds menu items, categories, prices, and shortcodes to WordPress. It also imports CSV files and links images by item code.

Use it for a restaurant, café, or other food-service menu.

## Features

- Custom Post Type (`menu_item`) with full admin UI
- Hierarchical taxonomy (`menu_category`) with sort order and layout options
- Price metabox with automatic numeric normalization
- Each menu item can belong to one category
- CSV bulk import with delimiter auto-detection
- Image linking by item code
- Shortcodes for rendering menus on the frontend
- Two layout modes: **Simple** (list) and **Media** (with thumbnails)
- Inline styles — no external CSS dependencies

## Installation

WordPress loads PHP files from the root of `wp-content/mu-plugins/`. It does not load PHP files inside subfolders. Install the plugin folder and add a root loader.

1. Copy this repository to `wp-content/mu-plugins/wp-menu-cpt/`.
2. Create `wp-content/mu-plugins/load-wp-menu-cpt.php` with this content:

```php
<?php
/**
 * Plugin Name: WP Menu CPT Loader
 * Description: Loads WP Menu CPT from its subfolder.
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/wp-menu-cpt/wp-menu-cpt.php';
```

### Confirm the plugin loaded

WordPress loads must-use plugins automatically. You do not need to activate this plugin in the dashboard.

1. In the dashboard, open **Plugins → Must-Use**. Confirm that **WP Menu CPT Loader** appears.
2. Confirm that **Menu** appears in the dashboard sidebar.

### Try it from end to end

1. Open **Menu → Categories** and add a category with the slug `appetizers`.
2. Open **Menu → Add New Item**. Add an item, assign it to `appetizers`, and publish it.
3. Add this shortcode to a page and publish the page:

   ```
   [menu_category_display slug="appetizers" show_title="1" layout="media"]
   ```

4. View the page. Confirm that it shows the category, item, price, and description.

### Uninstall

1. Delete `wp-content/mu-plugins/load-wp-menu-cpt.php` to stop loading the plugin.
2. Delete `wp-content/mu-plugins/wp-menu-cpt/`.

Uninstalling the plugin does not delete menu items or categories from the database.

## Shortcodes

### Display a single category

```
[menu_category_display slug="appetizers"]
[menu_category_display slug="appetizers" layout="media" show_title="1"]
```

Use one of `id`, `slug`, or `name` to choose a category. Set `show_title` and `show_price` to `0` or `1`.

Choose `simple` or `media` for `layout`. You can also set `limit`, `order` (`ASC` or `DESC`), and `orderby` (`menu_order` or `title`).

The category shortcode hides its title by default. Descriptions appear in the `media` layout and stay hidden in the `simple` layout.

### Display the full menu

```
[menu_full_display show_title="1" layout="simple"]
```

This shortcode supports `show_title`, `show_price`, `layout`, `limit`, `order`, and `orderby`. It shows category titles by default.

### Auto-generated per-category shortcodes

Each category gets a shortcode based on its slug. The plugin replaces hyphens with underscores:

```
[menu_appetizers]
[menu_main_courses]
```

## CSV Format

The importer accepts CSV files with these columns (order does not matter):

```
Category,Name,Description,Price
```

See the [sample CSV file](sample-data/sample-menu.csv) for an example.

The importer accepts `Value` as a synonym for `Price`.

## Configuration

### Constants

| Constant | Default | Description |
|---|---|---|
| `MENU_CPT_VERSION` | `1.1.1` | Plugin version |
| `MENU_CPT_DIR` | `__DIR__` | Plugin root directory |
| `MENU_CPT_INC_DIR` | `MENU_CPT_DIR/includes` | Includes directory |

### Category metadata

Each category supports:
- **Order** (integer) — controls display order in shortcodes
- **Layout** (`simple` or `media`) — default layout for the category

## Requirements

- WordPress 6.1+
- PHP 7.4+

## Author

**André Marinho** — [andremarinho.me](https://andremarinho.me)

## License

[GPL-2.0-or-later](LICENSE)
