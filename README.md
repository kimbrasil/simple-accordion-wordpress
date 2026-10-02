# Simple Accordion for WordPress

Simple Accordion is a lightweight WordPress plugin for creating and displaying multiple accessible accordion groups. Each accordion has its own shortcode ID, title, items, ordering, and default-open states.

## Features

- Create and manage multiple independent accordions from the WordPress admin.
- Give every accordion a unique ID for use in shortcodes.
- Add, edit, reorder, and remove accordion items.
- Mark individual items as initially open.
- Add safe HTML content to accordion panels.
- Render multiple accordion groups on the same page without their controls interfering with one another.
- Use accessible button and panel markup with `aria-expanded`, `aria-controls`, and `aria-labelledby` attributes.
- Load lightweight frontend CSS and JavaScript only when an accordion is rendered.
- Preserve compatibility with older single-accordion data by treating it as the `default` accordion.

## Installation

### Install from a downloaded ZIP

1. Download the repository ZIP from GitHub or download a packaged release ZIP.
2. Extract the ZIP archive.
3. If you downloaded the repository archive, open the extracted folder that contains `simple-accordion.php`.
4. Copy that plugin folder into `wp-content/plugins/`.
5. In WordPress admin, open **Plugins**.
6. Activate **Simple Accordion**.

### Install through WordPress admin

1. Open **Plugins → Add New Plugin → Upload Plugin**.
2. Select the plugin ZIP file.
3. Click **Install Now**.
4. Click **Activate Plugin**.

After activation, the **Simple Accordions** menu appears in the WordPress admin menu.

## Creating and managing accordions

1. Open **Simple Accordions** in WordPress admin.
2. Use the accordion selector to choose an existing accordion.
3. Click **Create new** to start another accordion.
4. Set a unique **Shortcode ID**, such as `faq`, `pricing`, or `support`.
   - IDs may contain letters, numbers, underscores, and hyphens.
   - Use a different ID for each accordion.
5. Enter an optional administrative title for the accordion.
6. Manage its rows:
   - **Order** controls the display order.
   - **Title** is the clickable accordion heading.
   - **Content** is the panel content. WordPress allows safe HTML according to its content filtering rules.
   - **Initially open** controls whether the item starts expanded.
7. Click **Add item** to add another row.
8. Click **Remove** to remove a row before saving.
9. Click **Save accordion**.

Each accordion is saved independently. Updating one accordion does not replace the items in another accordion.

## Shortcode usage

Use the `id` attribute to render a specific accordion:

```text
[simple_accordion id="faq"]
```

You can render several different accordions on the same page:

```text
[simple_accordion id="faq"]

[simple_accordion id="pricing"]

[simple_accordion id="support"]
```

The optional `title` attribute adds a frontend heading above that shortcode instance:

```text
[simple_accordion id="faq" title="Frequently Asked Questions"]
```

### Shortcode attributes

| Attribute | Required | Default | Description |
| --- | --- | --- | --- |
| `id` | No | `default` | The saved accordion ID to render. |
| `title` | No | Empty | An optional heading displayed above the accordion. |

If the requested ID does not exist or contains no items, the shortcode outputs nothing.

## Frontend behavior

- Each shortcode instance receives a unique HTML ID, so multiple instances can appear on the same page.
- Clicking a closed item opens it.
- Clicking an open item closes it.
- Opening an item closes other open items within that same accordion instance.
- Items in other accordion instances remain unchanged.
- The plus and minus indicators update with the expanded state.
- Panels use the HTML `hidden` state when collapsed.

## Data and compatibility

Accordion data is stored in the WordPress option `simple_accordion_items`.

The current format stores accordion groups by ID. Existing data from earlier single-accordion versions is automatically read as the `default` accordion, so existing content can continue to work after updating the plugin.

For the default accordion, either of these shortcodes can be used:

```text
[simple_accordion]
[simple_accordion id="default"]
```

## Development and customization

Important files:

- `simple-accordion.php` — plugin metadata, admin interface, data handling, shortcode rendering, and update integration.
- `assets/frontend.css` — frontend accordion styles.
- `assets/frontend.js` — frontend expand/collapse behavior.
- `assets/admin.css` — WordPress admin screen styles.

To customize the appearance, add theme or site-specific CSS targeting classes such as:

```css
.simple-accordion {}
.simple-accordion__item {}
.simple-accordion__button {}
.simple-accordion__panel {}
.simple-accordion__content {}
```

Keep customizations in a child theme or site-specific plugin rather than editing the plugin directly, so they are not lost during updates.

## Updates

The plugin includes GitHub-based update checking. Releases should include a ZIP asset named `simple-accordion.zip`.

## Security

Administrative changes require the `manage_options` capability and a WordPress nonce. Titles, IDs, and output attributes are escaped, while panel content is filtered with WordPress safe HTML handling.

## License

Simple Accordion is licensed under the GPL-2.0-or-later license. See [`LICENSE`](LICENSE).
