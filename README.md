# Divi Gravity Forms Swap

WordPress plugin that finds WP Tools Gravity Forms Divi 5 modules in post content and replaces them with the native Divi 5 Gravity Forms module.

Requires **Divi** (theme or Divi Builder plugin) and **Gravity Forms**. The swap screen only appears when both are active. If either is missing, an admin notice is shown and the plugin stays loaded without fatal errors.

Only the Gravity Forms form ID is copied. Styles and other WP Tools module settings are not migrated. Ajax is enabled on the replacement module by default. If Divi has a default Gravity Forms module preset, that preset is assigned so native preset styles apply without opening and resaving the page in Visual Builder.

## Install

1. Copy the `divi-gravity-forms-swap` folder into `wp-content/plugins/divi-gravity-forms-swap/`, or zip the folder and upload it under **Plugins > Add New > Upload Plugin**.
2. Activate **Divi Gravity Forms Swap**.

## Use

1. Go to **Divi > Gravity Forms Swap**.
2. Review the list of matching posts, pages, and Divi Theme Builder layouts.
3. Click **Replace with native Divi 5 Gravity Forms Module** on one item, confirm, then check the page on the front end.
4. Repeat, or use **Replace all listed items** after you are confident.

Each replacement saves a revision first, then swaps only the WP Tools Gutenberg block comments. Surrounding Divi sections, rows, and columns are left as they are. Divi static CSS is invalidated once per request (one page surgically, or once site-wide for a bulk replace) so default preset styles apply on the next view.

If a CDN sits in front of the site, purge that separately if a page still looks stale.

## When finished

Deactivate and delete the plugin. It stores no settings.

Requires WordPress 6.0+, PHP 7.4+, Divi (theme or Divi Builder plugin), and Gravity Forms.
