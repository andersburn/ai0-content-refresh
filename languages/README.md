# Translating Ai0 Content Refresh

The source language is English. The text domain is `ai0-content-refresh`.

- `ai0-content-refresh.pot`: template for additional languages.
- `ai0-content-refresh-da_DK.po`: editable Danish translations.
- `ai0-content-refresh-da_DK.mo`: compiled PHP translations.
- `ai0-content-refresh-da_DK-ai0r-admin.json`: WordPress JavaScript translations for the administration screen.
- `ai0-content-refresh-da_DK-ai0r-block.json`: WordPress JavaScript translations for the Gutenberg editor block.

WordPress selects the user's administration language and the site's frontend language. Missing languages use English. Changing a language does not translate saved prompts, articles, generated findings or old history entries.

To add a language, copy the POT into a PO file named `ai0-content-refresh-LOCALE.po`, translate every string and compile a matching MO using Poedit or WP-CLI's i18n commands. Preserve placeholders such as `%s`, `%d`, `%1$s` and line breaks. Produce WordPress JSON catalogues with the same locale and the registered handles `ai0r-admin` and `ai0r-block`, or use WP-CLI's source-path hash filenames. Both scripts declare the `wp-i18n` dependency and register the bundled language directory with `wp_set_script_translations()`.

Validate PHP labels and both JavaScript catalogues after switching the WordPress profile language. Do not translate stored keys, block names, model IDs, URLs or API schema properties.
