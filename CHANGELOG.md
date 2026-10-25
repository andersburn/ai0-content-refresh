# Changelog

The original five versions were imported to GitHub on 25 October 2026. Historical 1.0–1.4 installation ZIPs and tags remain unchanged. Version 1.5.0 introduces English source strings, Danish translations and native WordPress updates.

## 1.5.0 — English, Danish translation and WordPress updates

- English source strings throughout the administration screens, Gutenberg controls and runtime messages.
- Bundled Danish PO/MO and JavaScript JSON translations, plus a POT template for additional languages.
- The interface follows the WordPress user's language, with English fallback. Existing articles, saved prompts, credentials and history are retained.
- New installation prompts preserve the article's existing language; fixed model instructions no longer force Danish. English stop words improve media search.
- Locale-aware numeric display and explicit DKK currency labels.
- Native WordPress update discovery from this public GitHub repository, using the named plugin ZIP and matching update.json compatibility metadata.
- Administrator-controlled auto-updates, bounded caching and rejection of drafts, prereleases, foreign package URLs and invalid metadata.
- English documentation and release descriptions.

**Upgrading from 1.0–1.4:** install the 1.5.0 ZIP manually once. Older versions have no updater and cannot discover this first upgrade. Future updates appear in WordPress after 1.5.0 is installed. Use WordPress' normal Enable auto-updates control if desired; the plugin does not enable it for you.

Validation: 76 regression checks, 107 WordPress integration checks and 33 language/update checks, with simulated provider/update responses. PHP and JavaScript syntax checks pass. See TESTING.md for limitations.

## 1.4.0 — WordPress media library images

- Search and reuse existing WordPress images with a separate media permission.
- Native searches across attachment titles, filenames, alt text and captions using the page's topics.
- Metadata for up to 12 candidates; at most two new images with placement reasons.
- Native Gutenberg core/image blocks using existing library alt text and captions.
- Protection against known duplicates, featured images, tiny images and images attached to private/unpublished content.
- Image choices, reasons, search terms and counts in history.
- Recheck image access and availability before publication; restore never deletes media files.

Image files are not sent to OpenAI. Selection is based on metadata. Media access is enabled by default and can be changed in Settings.

## 1.3.0 — Online conversion and clearer overviews

- Automatic USD/DKK conversion through Frankfurter/ECB with rate dates and daily caching.
- Last-known-rate fallback with a warning; USD-only display without a valid rate.
- Excluded pages and posts on separate lines.
- Internal views for 90 days / 1 year, with 90-day counts used in priority scores.
- Frontend and WordPress editor links in history lists and details.
- Existing historical cost calculations retain their original exchange rates.

## 1.2.0 — Traffic sources, model selection and costs

- Choose Search Console, the built-in view counter or both for priorities.
- Exclude every logged-in role from counting, including cached pages, through cookie-aware AJAX.
- Delete internal counts older than one calendar year on upgrade and through daily WP-Cron cleanup.
- Model dropdown with account model names and an Other model field.
- Per-page cost forecasts and estimates based on reported token usage.
- Custom model rates, manual DKK conversion and historic price/exchange snapshots.

Clear WordPress/CDN caches after upgrading so the updated counting script is used.

## 1.1.0 — Better editorial proposals and selections

- Recommended model with a selection button.
- Searchable multi-selection list for excluded pages and posts.
- More active editorial prompt and context around editable segments.
- Missing-space fixes at rich-text boundaries.
- Actual change counts, clear no-change results and manual reassessment.
- Retain existing credentials, model choice, custom prompts and history on upgrade.

## 1.0.0 — Initial standalone release

The first standalone plugin for AI-assisted improvements to existing WordPress pages and posts.

- Your own OpenAI API key, model selection and editable prompt.
- Monthly scheduling, review or automatic publication, and usage limits.
- Google Search Console OAuth, internal view counter and manual priorities.
- Preserve Gutenberg blocks, media, banners and links; add editable content elements.
- Six-calendar-month cooldown, history, diff, publishing and restore.
