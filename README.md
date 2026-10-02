# Simple Accordion for WordPress

Simple Accordion is a lightweight WordPress plugin that lets administrators manage accordion items in a table-style admin screen and render them on the frontend with a shortcode.

## What the plugin does

- Adds a **Simple Accordion** admin menu in WordPress.
- Lets you create, edit, order, remove, and mark items as open by default.
- Stores accordion items in a single WordPress option (`simple_accordion_items`).
- Renders items on the frontend with accessible button/panel markup.
- Loads small frontend CSS/JS assets for styling and toggle behavior.

## Installation

### Option 1: Install from a downloaded ZIP (file copy)

1. Download the repository ZIP from GitHub (or a packaged release ZIP).
2. Extract it.
3. Copy the extracted plugin folder (the folder that contains `simple-accordion.php`) into:
   - `wp-content/plugins/`
4. In WordPress admin, go to **Plugins** and activate **Simple Accordion**.

### Option 2: Install from WordPress admin (Upload Plugin)

1. In WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Upload the plugin ZIP.
3. Click **Install Now**, then **Activate**.

## Basic usage

Use this shortcode in posts, pages, or template content:

```text
[simple_accordion]
```

### Shortcode attributes

The implementation currently accepts no functional shortcode options. Use `[simple_accordion]` as shown above.

## Managing accordion content in wp-admin

After activation:

1. Open **Simple Accordion** from the admin menu.
2. Use the table to manage rows:
   - **Order**: numeric sort order.
   - **Title**: accordion button label.
   - **Content**: accordion panel content (safe HTML allowed).
   - **Open**: whether an item starts expanded.
3. Click **Add item** to append a row.
4. Click **Remove** on a row to delete it.
5. Click **Save accordions** to persist changes.

## Expected frontend output

- Each row renders as one accordion item.
- Clicking a closed item opens it.
- Clicking an open item closes it.
- When opening an item, other currently open items in the same accordion are closed by the frontend script.
- Icons switch between `+` (closed) and `−` (open).

## Development and customization

Repository/plugin structure:

- `simple-accordion.php` — main plugin file (WordPress plugin header + all plugin logic)
- `assets/frontend.css` — frontend styles
- `assets/frontend.js` — frontend interaction logic
- `assets/admin.css` — admin screen styling

Tips:

- Override visual styles by adding CSS in your theme that targets `.simple-accordion` classes.
- Keep custom code in a child theme or site-specific plugin instead of editing plugin files directly.

## Updates

The plugin includes GitHub-based update checks and expects release assets to include a ZIP named `simple-accordion.zip`.

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
