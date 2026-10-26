=== Ai0 Content Refresh ===
Contributors: ai0
Tags: seo, gutenberg, content, openai, search-console
Requires at least: 6.7
Requires PHP: 8.1
Tested up to: 7.1.3
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted SEO improvements for existing WordPress pages and posts, with preserved media, priorities and review history.

== Description ==

Review content you have already written. Ai0 Content Refresh uses your own OpenAI API key and chosen model to improve clarity, readability and useful structure while preserving existing media and Gutenberg layouts. Review proposals manually or enable scheduled publication after technical validation. The plugin changes post_content; titles, URLs, categories, featured images, Yoast fields and theme metadata are preserved.

English is the source language. Danish translations are bundled for PHP administration screens and JavaScript/Gutenberg controls. Administration follows the WordPress user's language, frontend labels follow the site language, and other locales fall back to English. New installation prompts preserve the article's existing language. Existing prompts, generated findings and historical content are not translated or overwritten by an interface-language change.

Requirements: PHP 8.1+ with OpenSSL and mbstring; WordPress 6.7+; your own OpenAI API key/credit and a model with Responses API and Structured Outputs. Gutenberg is the primary editor. Editable ordinary classic HTML is supported, with protected shortcodes and unknown elements. Not tested with Elementor or other page builders; builder metadata is not processed. Safe publishing requires InnoDB for posts, post metadata and history. Scheduling requires working WP-Cron. Minimum requirements are not a full test matrix; see TESTING.md.

== Installation ==

1. Download ai0-content-refresh-1.6.0.zip from https://github.com/andersburn/ai0-content-refresh/releases/latest. Choose the named installation asset rather than the generated source archive.
2. Open Plugins > Add New Plugin > Upload Plugin, upload the ZIP and activate it.
3. Open Content Refresh > Settings, enter your OpenAI API key and save.
4. Click Test key and retrieve models. Choose a compatible model from the dropdown or enter an ID under Other model. The list includes every model name on the account, including incompatible models.
5. Configure the prompt, content types, exclusions, media access, traffic source and monthly generation/token limits.
6. Generate a proposal for a page that has passed the six-month cooldown. Inspect the changes and preview in Generation history.
7. Enable scheduled reviews when ready. Automation is disabled initially; manual runs follow the selected publishing mode.

The recommended model is a starting point, subject to access and your review. Your existing model choice is retained. Use the recommendation button and then save to change it.

== Upgrades and GitHub updates ==

From versions 1.0–1.4, upload the latest installation ZIP once and choose to replace the existing plugin. Those versions have no update client and cannot discover this first upgrade. Settings, encrypted API credentials, Google connection, custom prompts, exclusions and history are retained. The older 1.0 migration replaces only its exact original shipped prompt; custom editorial prompts are retained.

From 1.5.0, updates use WordPress' native Plugins / Dashboard > Updates screen. The plugin checks the latest stable release at https://api.github.com/repos/andersburn/ai0-content-refresh/releases/latest and reads the matching update.json release asset. It accepts only the named installation ZIP and manifest URLs from this exact GitHub repository, with matching versions and valid WordPress/PHP compatibility metadata. Drafts, prereleases, missing assets, foreign URLs and invalid metadata are ignored. No GitHub token is needed.

Successful checks are cached for six hours; failures for five minutes. Opening Dashboard > Updates clears the plugin cache so WordPress can perform a fresh check. GitHub API rate limits can delay discovery. Your normal WordPress auto-update preference is preserved. To install future releases automatically, choose Enable auto-updates in WordPress. This is separate from automatic content reviews.

Historical 1.0–1.4 installation ZIPs remain unchanged and retain their original Danish interface. The repository documentation and release descriptions are maintained in English.

== Priorities and exclusions ==

Choose Google Search Console, the built-in view counter or both under Traffic source for priorities. Google-only stops internal counting. Internal-only stops automatic Google syncing. The separate view-counter setting also disables collection and use of internal counts. Switching sources retains existing data and Google credentials, subject to normal retention. Missing data is not silently substituted with another source.

Manual priority has the greatest weight, followed by Yoast cornerstone markers, traffic, measured declines in Google clicks, search opportunities and content age. The overview explains each score. Search Console data older than seven days is ignored. Missing rows do not prove zero traffic. Without traffic data, manual priorities and content age still apply.

Exclude pages and posts is a searchable selection list. Search by title or ID, select multiple entries and save. Choices are shared with the editor and priority overview. Filtering does not clear selections; selected pages appear on separate lines. Old IDs and per-page exclusion metadata are migrated.

Only published posts/pages without passwords are processed. The privacy policy page, Yoast noindex and manually excluded content are skipped. Custom post types are not processed. Select any additional pages that should not be edited.

== Publishing mode: review or automatic ==

Settings > When a proposal is ready has two separate choices with explanations. Save proposals for review leaves new manual and scheduled runs Pending review until you click Publish this proposal in history. There is no later automatic approval. Publish automatically after technical validation publishes new changes immediately after validation; no approval click is needed. Manual page, next-page and retry buttons show Generate and publish when this mode is saved.

Selecting automatic publishing does not enable the schedule. Enable scheduled reviews separately for unattended runs. Manual runs can publish automatically even when the schedule is disabled. Existing monthly limits, preservation checks and the six-month cooldown still apply.

The setting applies only to future runs. Earlier pending proposals still require manual approval. Your saved setting is retained on upgrade. A stale form cannot silently use a different publishing choice: reload the page if the choice changed. Switching off automatic publishing during a request retains the proposal for review. A failed automatic publication retains its proposal and records the reason in history.

== Six-month cooldown and scheduling ==

A page is eligible only six calendar months after its latest publication or content modification, including manual edits and content updates by this plugin. Updating the plugin software itself does not reset the cooldown. The rule applies to both generation and publication and cannot be bypassed with a prompt or manual action. Month lengths and leap years are handled. Restoring content counts as a content change.

Automation processes at most one page per hour from the selected day and hour of the month. The monthly generation limit includes manual attempts, errors and runs with no changes. A generation quota is not a promise of the same number of publications. Rejected or unnecessary proposals receive a pause; failures normally receive a seven-day pause. Interrupted runs are marked as errors after ten minutes.

The token limit reserves conservative capacity before a request and replaces it with reported usage after a response. Unknown usage retains the reservation. A token limit is not an exact currency budget. Generation has a 90-second HTTP timeout. Pages exceeding 250 KB or 600 editable segments are skipped. WordPress' time zone is used. WP-Cron depends on traffic; a hosting cron job invoking wp-cron.php improves reliability. Existing hosting cron settings are not changed.

== Google Search Console login ==

Google login requires a one-time setup of your own OAuth client. No shared hosted login service or shared Client Secret is included.

Create/select a Google Cloud project, enable Search Console API, configure consent with https://www.googleapis.com/auth/webmasters.readonly and create a Web application OAuth client. Copy the exact redirect URI displayed in Settings. Save Client ID/Secret, connect with Google and select a property covering this WordPress domain. Administration must use HTTPS and the Google Account must have access to the property.

External OAuth apps in testing mode may have short-lived refresh tokens and require repeated login. Configure publishing status and any verification requirements for your usage. Valid access tokens are refreshed automatically; the plugin cannot bypass Google's consent or revocation rules.

Daily synchronization compares two 28-day periods, excluding the latest three days to avoid preliminary data. Clicks, impressions, CTR and average position affect priority. Data older than seven days is ignored. The API may omit rows. Search clicks are organic Google clicks, rather than all website visits. Search queries are not collected, and raw Google statistics are not sent to OpenAI.

== Preservation and new elements ==

WordPress' HTML token processor changes text in known text blocks and ordinary classic HTML. Existing tags and attributes are retained. Link text, code, forms, media, dynamic/unknown blocks and registered shortcodes are protected. Some custom blocks therefore have no safely editable internal text.

New summaries, FAQs, checklists, comparison tables, callouts, contact boxes and related links use editable ai0-refresh/section blocks. At most four plugin text elements are allowed per page in total. The model is instructed to avoid duplicates, use existing facts and preserve caveats. There is no external research or fact-checking. Technical validation does not guarantee factual accuracy or rankings; FAQ elements do not promise FAQ schema or rich results.

These dynamic text elements require the plugin to remain active for frontend rendering. Their data is retained on deactivation and renders again when reactivated. Restore original content before permanent removal if desired. Ordinary core/image blocks do not require this plugin.

== WordPress media library images ==

Media library images has a separate permission setting, enabled by default. No new WordPress user, external image service or extra API key is created. The plugin reuses existing uploaded images; it does not generate or upload images/videos.

Native get_posts/WP_Query searches use up to four topic terms from the page title/headings, with introductory text as a fallback, against attachment titles, captions, filenames and alt text. English and Danish stop words are excluded. Up to 12 candidates are sent as metadata: attachment ID, title, filename, alt text, caption and dimensions. Image files and image URLs are not sent to OpenAI. Selection uses metadata, not visual image analysis; good library titles and alt text help.

The model may select zero, one or two images and must explain relevance and placement. The server accepts only IDs from its actual shortlist. JPEG, PNG, WebP, GIF and AVIF are allowed when WordPress can provide at least 300 x 180 pixels. Known logo/icon names and images attached to private, unpublished or password-protected pages are skipped. Unattached images are treated as available, as in the regular media library. Use ai0r_media_allowed($allowed, $attachment_id) for additional local rules.

Existing media, featured image, files and metadata are not modified. Known image IDs/URLs, including reusable-block images, prevent duplicates; media hidden behind custom blocks/shortcodes cannot always be recognized. New images are ordinary core/image blocks using WordPress' large size and existing alt text/caption. They remain editable/renderable without this plugin. The two-image limit is separate from the four-text-element limit.

History shows image counts, thumbnails, titles, alt text, placement reasons and search terms. An image-only proposal counts as a change. Permissions, availability and URLs are rechecked before publication. A newly private or removed image is not published. Restore removes added blocks without deleting library assets. Metadata is included in token/cost forecasts. The six-month cooldown still applies.

== Cost estimates and conversion ==

Before generation, each page has a forecast using the chosen model, full request including instructions/schema, saved prompt and output limit. Input uses approximately three characters per token; output uses article length. This is a heuristic, not a tokenizer. A separate conservative scenario uses the input reservation and full output limit; it is not a guaranteed maximum. Reasoning and actual response length affect usage.

History stores a token-based estimate after reported input/output/cached usage, including any reasoning tokens in reported output. Rates and exchange observations are saved with the run and are not recalculated after settings changes. Unknown usage keeps a clearly labelled forecast rather than claiming zero cost. Older history without cost data receives no invented historical cost.

Built-in USD rates per million tokens, dated 25 October 2026:
Model | Input | Output | Cached input | Cache writes
GPT-6.1 Sol | 2 | 10 | 0.10 | 2.50
GPT-6 Astra | 10 | 50 | 1 | 12.50
GPT-6 Luna | 0.10 | 0.50 | 0.01 | 0.125
The built-in long-context multipliers above 272,000 input tokens are 2x input/cache and 1.5x output. Requests use service_tier=default. Rates can change; check your provider pricing and invoice. Original rate sources:
https://developers.openai.com/api/docs/models/gpt-6.1-sol
https://developers.openai.com/api/docs/models/gpt-6-astra
https://developers.openai.com/api/docs/models/gpt-6-luna

Other models/snapshots need custom rates to show an estimate. Custom rates override built-in rates for that model and have no automatic long-context multiplier. Model changes restore that model's saved custom rates or standard defaults in the form.

USD/DKK conversion uses Frankfurter's latest daily ECB rate:
https://api.frankfurter.dev/v2/providers/ecb/rate/usd/dkk
https://frankfurter.dev/

No extra key or manual rate is needed. Results are cached for one day and refreshed by an independent daily WP-Cron task. Failures retry at most hourly. Weekends/holidays use the latest published observation. Outages keep the last valid rate with a warning; without one, prices show USD only. Currency failure does not block generation. No key, content or view count is sent to this service. Historic manual conversions are retained and labelled.

Example: 10,000 input + 4,000 output tokens on GPT-6.1 Sol without caching is $0.06; DKK uses the retrieved rate. Estimates exclude VAT, fees and negotiated discounts. The API invoice is authoritative. The forecast is not a currency budget or a guarantee of editorial improvement.

== History and restore ==

Generation history and each detail show View page and Edit in WordPress links in new tabs. Deleted content has no broken links; unpublished content has only an editor link.

History retains model, editorial prompt, before/after content, hashes, dates, status and usage/reservations. Review, publish or reject proposals. Published changes can be restored only if the page has not changed since. Newer manual edits are not overwritten. Cooldown and image permissions are checked again on publication. If WordPress save filters modify the validated content, the update is cancelled and rolled back.

Actual text/image/section counts are recorded, rather than trusting the model summary. Identical proposals do not count as changes. No-change runs show a reason and permit another manual assessment when eligible. They are not published and do not start a new six-month period. Monthly quotas still apply. The model may choose no changes; the plugin does not force unnecessary rewriting.

Publishing requires transactional InnoDB tables. History is not automatically erased by deactivation/removal. WordPress revisions are also created when enabled. Original metadata remains unchanged.

== View counter and external services ==

The internal counter stores only content ID, date and count on your server. It sets no tracking cookies and stores no visitor profiles, full URLs or referrers. Existing WordPress login cookies are sent back to WordPress to exclude every logged-in role, including users served cached anonymous pages. Known bots and previews are excluded; DNT/GPC are respected. An abuse limit stores a one-way IP-derived value for up to two minutes. Hosting logs are outside this plugin's control.

The cache-friendly script calls admin-ajax.php with a signed content ID. The legacy REST endpoint no longer counts, preventing old cached scripts from counting logged-in users. Clear WordPress/CDN caches after activation, salt changes or tracking changes. Unknown bots, blockers and manipulated requests make the count approximate; it is not used for security decisions.

Counts older than one calendar year are removed at install/upgrade and daily, including when AI automation or counting is disabled. Leap years are handled. History is retained. Cleanup requires an active plugin and working WP-Cron.

Use ai0r_tracking_allowed to control both script and endpoint for local consent/privacy integration. On cached pages where consent varies per visitor, your CMP must also block the script until appropriate consent. The website owner determines applicable requirements.

OpenAI receives the selected page text, title, URL, prompt, bounded public internal links and optional image metadata at https://api.openai.com/v1/responses with store=false. The key is sent only to api.openai.com; model discovery uses /v1/models. Google OAuth/Search Console endpoints are used after setup/consent. Credentials and tokens are encrypted with a key derived from WordPress auth salts; changing salts requires new credentials/login. Protect database backups.

GitHub update checks send a plugin-version user agent and ordinary server network information, without article content, OpenAI keys, Google tokens or view data. Compatibility manifests and installation assets are retrieved from release URLs in https://github.com/andersburn/ai0-content-refresh. No GitHub credentials are stored.

Developer hooks: ai0r_content_applied($post_id, $history_id, $is_restore) for cache purging/local integrations; ai0r_media_allowed($allowed, $attachment_id) for media access; ai0r_tracking_allowed($allowed, $post_id) for counting. Normal WordPress post-update hooks also run.

== Changelog ==

= 1.6.0 =
Publishing mode applies to new manual and scheduled runs. Clear separate radio options explain approval and immediate automatic publication. Manual buttons disclose Generate and publish in auto mode. Completion notices report actual status. Pending entries explain required approval. Existing pending proposals are never published retroactively. Stale forms and a mode change during generation cannot silently publish; automatic publication failures retain the proposal and reason.

= 1.5.0 =
English interface and documentation; bundled Danish PHP/JavaScript translations and POT template; article-language-preserving new default prompt; English media-search stop words; locale-aware numeric display; native GitHub release updates with matching compatibility metadata and administrator-controlled auto-updates. Existing saved prompts/settings/history are retained.

= 1.4.0 =
Native media library search and reuse; separate media permission; at most two new Gutenberg image blocks; bounded metadata-only input; duplicate/private-image protection; image choices and reasons in history; checks before publication.

= 1.3.0 =
Automatic USD/DKK conversion via Frankfurter/ECB with dates, cache, fallback and immutable historic rates; exclusions on separate lines; 90-day/year views; public-page/editor history links.

= 1.2.0 =
Traffic-source selection; logged-in exclusion through cookie-aware AJAX, including cached pages; one-calendar-year retention and daily cleanup; model dropdown with account models and Other field; forecasts and usage-based estimates; saved rate/exchange snapshots and custom model rates.

= 1.1.0 =
Model recommendation; searchable shared exclusions; more active prompt and local context; missing-space fixes at inline boundaries; actual change counts; clear no-change history and manual retry; upgrade preservation.

= 1.0.0 =
Initial standalone plugin with own OpenAI key, model/prompt, monthly scheduling, Search Console OAuth, internal counter, priorities, Gutenberg/classic HTML preservation, editable additions, history and restore.
