# Divi Gravity Forms Swap

WordPress plugin that finds WP Tools Gravity Forms Divi 5 modules in post content and replaces them with the native Divi 5 Gravity Forms module.

Only the Gravity Forms form ID is copied. Styles and other WP Tools module settings are not migrated. Ajax is enabled on the replacement module by default.

## Install

1. Copy the `divi-gravity-forms-swap` folder into `wp-content/plugins/divi-gravity-forms-swap/`, or zip the folder and upload it under **Plugins > Add New > Upload Plugin**.
2. Activate **Divi Gravity Forms Swap**.

## Use

1. Go to **Tools > Divi Gravity Forms Swap**.
2. Review the list of matching posts, pages, and Divi Theme Builder layouts.
3. Click **Replace with native Divi 5 Gravity Forms Module** on one item, confirm, then check the page in Divi.
4. Repeat, or use **Replace all listed items** after you are confident.
5. Purge any page cache for updated items.

Each replacement saves a revision first, then swaps only the WP Tools Gutenberg block comments. Surrounding Divi sections, rows, and columns are left as they are.

## When finished

Deactivate and delete the plugin. It stores no settings.

Requires WordPress 6.0+ and PHP 7.4+.
