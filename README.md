# simple-accordion-wordpress

Simple WordPress accordion plugin with a wp-admin editor and shortcode rendering.

## Installation

1. In this repository, keep the plugin files inside `simple-accordion/`.
2. Create a ZIP with this folder structure:
   ```
   simple-accordion.zip
   └── simple-accordion/
       ├── simple-accordion.php
       └── assets/
           ├── admin.css
           ├── frontend.css
           └── frontend.js
   ```
3. In WordPress, go to **Plugins → Add New → Upload Plugin**.
4. Upload `simple-accordion.zip` and activate **Simple Accordion**.

## Admin workflow

1. Go to **Simple Accordion** in wp-admin.
2. Select a group to edit, or create a new group.
3. For each group, define:
   - Optional group title (metadata)
   - Repeatable accordion items with order, title, content, and open-by-default
4. Save the group.
5. Use the shortcode with the matching group ID.

## Shortcode examples

- Default group (backward compatible):
  ```
  [simple_accordion]
  ```
- Specific groups:
  ```
  [simple_accordion id="sedes"]
  [simple_accordion id="faq"]
  ```

## Group ID rules

- IDs are sanitized as WordPress slugs (`sanitize_title`).
- Use lowercase letters, numbers, and hyphens for predictable IDs.
- IDs must be unique per group.
- The `default` group is reserved and cannot be deleted.

## Backward compatibility

- Legacy data stored in `simple_accordion_items` is automatically migrated to `simple_accordion_groups` under the `default` group.
- Existing `[simple_accordion]` usage continues to render the migrated/default items.

## GitHub release auto-update requirements

This plugin includes GitHub release auto-update support.

To make updates discoverable in WordPress:

1. Publish a GitHub Release with a version tag (example: `v1.1.1`).
2. Attach a release asset named **exactly**:
   ```
   simple-accordion.zip
   ```
3. Ensure the ZIP contains the top-level `simple-accordion/` plugin folder.

If the ZIP asset name differs, WordPress update download will not be resolved by the plugin updater.
