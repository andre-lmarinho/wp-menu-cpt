# WP Menu CPT

A WordPress MU plugin that registers a **Menu Item** custom post type with categories, pricing, CSV import, image linking, and shortcode rendering. Ideal for restaurants, cafés, and food-service businesses.

## Features

- Custom Post Type (`menu_item`) with full admin UI
- Hierarchical taxonomy (`menu_category`) with sort order and layout options
- Price metabox with automatic numeric normalization
- Single-category enforcement per item
- CSV bulk import with delimiter auto-detection
- Automatic image linking by item code
- Shortcodes for rendering menus on the frontend
- Two layout modes: **Simple** (list) and **Media** (with thumbnails)
- Inline styles — no external CSS dependencies
- Fully sanitized and escaped (WPCS compliant)

## Installation

1. Copy the `wp-menu-cpt/` folder into `wp-content/mu-plugins/`.
2. WordPress will load it automatically on every request.

Alternatively, create a loader file at `wp-content/mu-plugins/load-wp-menu-cpt.php`:

```php
<?php
require_once __DIR__ . '/wp-menu-cpt/wp-menu-cpt.php';
```

## Shortcodes

### Display a single category

```
[menu_category_display slug="appetizers"]
[menu_category_display id="5" layout="media" show_title="1"]
```

**Attributes:** `id`, `slug`, `name`, `show_title` (0|1), `show_desc` (0|1), `show_price` (0|1), `layout` (simple|media), `limit`, `order` (ASC|DESC), `orderby` (menu_order|title).

### Display the full menu

```
[menu_full_display show_title="1" layout="simple"]
```

### Auto-generated per-category shortcodes

Each category automatically gets a shortcode based on its slug:

```
[menu_appetizers]
[menu_main_courses]
```

## CSV Format

The importer accepts CSV files with these columns (order does not matter):

```
Category,Name,Description,Price
```

See [sample-data/sample-menu.csv](sample-data/sample-menu.csv) for an example.

Synonyms are supported: `Title`/`Product`/`Item` for Name, `Value` for Price.

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
