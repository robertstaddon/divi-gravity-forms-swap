# Divi Gravity Forms Swap

WordPress plugin that finds WP Tools Gravity Forms Divi 5 modules in post content and replaces them with the native Divi 5 Gravity Forms module.

Requires **Divi** (theme or Divi Builder plugin) and **Gravity Forms**. The swap screen only appears when both are active. If either is missing, an admin notice is shown and the plugin stays loaded without fatal errors.

Each replacement copies:

- Gravity Forms form ID
- Ajax on/off
- Mappable styles (module shell, title, description, labels, inputs, buttons, validation, confirmation, progress bar, required marker, and matching custom CSS)

Known gaps (native Divi has no equivalent):

- Hide title / hide description toggles (native always shows both)
- Field grid row/column gaps
- Wrapper spacing (`fieldWrapper`, `inputWrapper`, `footer`, and similar)
- Send-link button styles
- Product and list field styles

If Divi has a default Gravity Forms module preset, that preset is assigned so unset native defaults still apply; migrated attrs overlay it.

## Install

1. Copy the `divi-gravity-forms-swap` folder into `wp-content/plugins/divi-gravity-forms-swap/`, or zip the folder and upload it under **Plugins > Add New > Upload Plugin**.
2. Activate **Divi Gravity Forms Swap**.

## Use

1. Go to **Divi > Gravity Forms Swap**.
2. Review the list of matching posts, pages, Divi layouts, and Theme Builder layouts.
3. Click **Replace with native Divi 5 Gravity Forms Module** on one item, confirm, then check the page on the front end.
4. Repeat, or use **Replace all listed items** after you are confident.

Each replacement saves a revision first, then swaps only the WP Tools Gutenberg block comments. Surrounding Divi sections, rows, and columns are left as they are. The result notice lists form IDs plus compact mapped and skipped style groups. Divi static CSS is invalidated once per request (one page surgically, or once site-wide for a bulk replace) so styles apply on the next view.

If a CDN sits in front of the site, purge that separately if a page still looks stale.

## When finished

Deactivate and delete the plugin. It stores no settings.

Requires WordPress 6.0+, PHP 7.4+, Divi (theme or Divi Builder plugin), and Gravity Forms.

## Mapper fixtures

From the plugin directory:

```sh
php tests/run-mapper.php
```

This loads the mapper without WordPress and asserts the fixture cases under `tests/fixtures/`.
