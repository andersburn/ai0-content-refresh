# Ai0 Content Refresh

**AI-assisted SEO improvements and ongoing maintenance for existing WordPress content.**

Ai0 Content Refresh reviews pages and blog posts you have already written. It uses your chosen OpenAI model to propose clearer writing, better structure and useful additions while preserving Gutenberg layouts, images, videos, banners and links. A complete history lets you inspect changes, publish proposals and restore previous content.

**Latest version: 1.6.0.** [Download the installation ZIP](https://github.com/andersburn/ai0-content-refresh/releases/latest) · [All releases](https://github.com/andersburn/ai0-content-refresh/releases) · [Changelog](CHANGELOG.md)

## What it does

- **Improve existing text:** clearer introductions, readability, grammar and explanations without inventing new facts.
- **Add useful content:** summaries, FAQs, checklists, comparison tables, callouts and relevant internal links. At most four plugin text elements per page in total.
- **Reuse your images:** search the WordPress media library, provide metadata for up to 12 relevant candidates and add at most two native Gutenberg image blocks. Image files are not sent to OpenAI.
- **Prioritize reviews:** use Google Search Console, the built-in view counter or both, alongside manual priorities, Yoast cornerstone markers, search opportunities and content age.
- **Schedule work:** choose a monthly start day, hour, generation limit and token limit. Save proposals for review or publish automatically after technical validation. Automation is off initially.
- **Enforce a six-month cooldown:** a page is eligible only six calendar months after its latest publication or content modification, including manual edits and content updates by this plugin.
- **Estimate costs:** forecasts before generation and token-based estimates afterwards, with automatic USD/DKK conversion using dated Frankfurter/ECB rates. Custom model rates are supported.
- **Keep a review history:** before/after differences, model, saved prompt, token usage, image choices, reasons and status. Direct links open the public page and WordPress editor. Restore is blocked if the content has changed since.
- **Exclude content:** a searchable selection list shared with the editor. Privacy policy pages, password-protected content and Yoast noindex pages are excluded automatically.
- **Use your preferred interface language:** English source strings with bundled Danish PHP and JavaScript translations. The interface follows the WordPress user's language; untranslated locales fall back to English. Existing custom prompts and content are preserved.
- **Receive WordPress updates from GitHub:** version 1.5.0 and later integrate with the native plugin update screen. No GitHub token is required for this public repository.

The SEO goal is useful, relevant and well-maintained content. The plugin changes `post_content`; titles, URLs, categories, featured images, Yoast fields and theme metadata are preserved. It does not perform external research or fact-checking, and it cannot guarantee higher rankings. FAQ elements do not promise FAQ schema or rich results. Technical validation checks format, preservation and permitted images. Review the first proposals, particularly pages with prices, guarantees or other important factual claims.

## Requirements and compatibility

| Area | Requirement / status |
| --- | --- |
| PHP | **8.1+**, with OpenSSL and mbstring |
| WordPress | **6.7+** |
| Main editor | **Gutenberg / the WordPress block editor** |
| Classic editor | Editable ordinary HTML in `post_content` is supported; shortcodes and unknown elements are protected |
| Elementor | **Not tested with Elementor. Compatibility is not guaranteed** |
| Other page builders | Not tested; content stored in builder metadata is not processed |
| Database | InnoDB for posts, post metadata and plugin history when publishing |
| OpenAI | Your own API key, API credit and access to a model supporting **Responses API + Structured Outputs** |
| Scheduling | Working WP-Cron; a hosting cron job is recommended for reliable scheduling |
| Network | Outbound HTTPS to OpenAI and enabled external services; GitHub for update checks/downloads |
| Search Console | Optional: an authorized Google Account, your own OAuth Client ID/Secret and HTTPS administration |

Minimum requirements are not a complete compatibility matrix. Local validation used **PHP 8.5.2 and WordPress 7.1.3**, including the official WordPress SQLite compatibility layer. Production publishing requires InnoDB. See [TESTING.md](TESTING.md) for evidence and limitations.

## Installation

1. Download **ai0-content-refresh-1.6.0.zip** from the [latest release](https://github.com/andersburn/ai0-content-refresh/releases/latest). Choose the named installation asset, rather than GitHub's generated source archive.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, upload the ZIP and activate it.
3. Open **Content Refresh → Settings**, enter your OpenAI API key and save.
4. Click **Test key and retrieve models**. Choose a compatible text model from the dropdown or enter an ID under **Other model**. The list includes all account model names, including models unsuitable for generation.
5. Configure the prompt, content types, exclusions, media access, traffic source and usage limits.
6. Generate a proposal for an eligible page and inspect its history, changes and image choices.
7. Enable scheduled reviews when ready. Manual generations follow the selected publishing mode.

The recommendation is a starting point, subject to model availability and your own review. New installations use an English editorial prompt that preserves the article's existing language. Updating the interface does not translate articles or replace existing saved prompts.

## Publishing mode: review or automatic

Under **Settings → When a proposal is ready**, choose one of two options and save:

- **Save proposals for review:** new manual and scheduled runs save a proposal with **Pending review** status. Open the history entry and click **Publish this proposal** to apply it. These proposals are not automatically approved later.
- **Publish automatically after technical validation:** new manual and scheduled runs publish changes immediately after validation. No approval click is needed. Manual buttons say **Generate and publish**, including the next-page and retry actions.

Enable **Scheduled reviews** for unattended generation; selecting automatic publishing alone does not start scheduled jobs. Manual generation can publish automatically even while the schedule is disabled. Monthly limits and the six-month cooldown still apply.

The setting applies to **future runs**. Existing pending entries remain available for manual approval and are not published retroactively. Upgrading retains your saved publishing setting. Stale forms are refused when the setting has changed since the button was displayed. Switching to review while a request is in progress retains its proposal rather than publishing it. Failed automatic publication retains the proposal and records the reason in history.

### Google Search Console

The optional connection uses Google login after a one-time setup of your own OAuth client in Google Cloud. Enable Search Console API, configure read-only consent, create a Web application client and copy the exact redirect URI shown in Settings. Save the Client ID/Secret, connect Google and select a property covering this site.

The plugin compares two 28-day periods with a three-day delay; data older than seven days is ignored in priorities. Missing rows do not prove zero traffic. Google clicks measure organic search clicks, rather than all website visits. There is no shared hosted login service or shared Google secret. Testing-mode OAuth apps may require frequent reconnection; Google's publishing and verification rules still apply.

### Built-in view counter

The counter shows **90 days / 1 year**, starting at installation. It excludes all logged-in users, including on cached pages, plus known bots and previews. It respects DNT/GPC and sets no tracking cookies. Stored data contains a date, content ID and count; a short-lived abuse limit uses a one-way value derived from the IP address. Data older than one calendar year is removed on upgrade and daily through a separate WP-Cron job. Generation history is retained.

Counts are approximate and are not unique visitors. Clear WordPress/CDN caches after installation, upgrades or tracking changes. The website owner determines consent requirements; `ai0r_tracking_allowed` supports local integration. See [readme.txt](readme.txt) for details.

## Updates and versions

**Upgrade from 1.0–1.4:** install the latest ZIP once using WordPress' replace-existing-plugin flow. Older versions do not contain an update client and therefore cannot discover this first upgrade themselves. Settings, encrypted credentials, custom prompts and history are retained.

**On version 1.5.0 or later:** WordPress checks this repository's latest stable release and uses the named plugin ZIP and matching `update.json` compatibility manifest. Checks are cached for six hours; opening **Dashboard → Updates** clears the plugin's cache for a fresh WordPress check. Failures are retried after a short delay. Drafts, prereleases, foreign packages and mismatched metadata are ignored. GitHub's public API limits can delay discovery.

New versions appear in **Plugins / Dashboard → Updates**. WordPress keeps the administrator's existing auto-update preference; this plugin does not enable auto-updates for you. To install future releases automatically, use WordPress' normal **Enable auto-updates** control. Content-review automation is a separate setting.

| Version | Main changes |
| --- | --- |
| [1.6.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.6.0) | Publishing mode applies to manual and scheduled runs, clear radio options and status messages |
| [1.5.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.5.0) | English interface and documentation, bundled Danish translation, language-preserving defaults and native GitHub updates |
| [1.4.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.4.0) | WordPress media library search and image reuse |
| [1.3.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.3.0) | Online currency conversion, 90-day/year views and frontend/editor links |
| [1.2.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.2.0) | Traffic-source selection, logged-in exclusions, one-year retention, model dropdown and cost estimates |
| [1.1.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.1.0) | Model recommendation, searchable exclusions and improved editorial/no-change handling |
| [1.0.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.0.0) | Initial standalone plugin |

Each release has a Git tag, release notes and installation asset. Historical 1.0–1.4 ZIP files remain unchanged, with their original Danish interface. Cost estimates exclude VAT, fees and negotiated discounts; the API invoice is authoritative. Historic rates and exchange observations are not recalculated.

## Languages and contributions

The source language is English. Danish (`da_DK`) is bundled in `languages/` as PO/MO and WordPress JavaScript JSON catalogues. A POT template is provided for additional languages. Choose a language under **Users → Profile → Language**, or use the site default under **Settings → General**. Frontend labels use the site's locale. Changing interface language does not rewrite saved history or prompts.

Translators can edit the PO file in Poedit or another gettext editor, compile the MO, and produce JSON catalogues for `ai0r-admin` and `ai0r-block`. The text domain is `ai0-content-refresh`. Keep `%s`, `%d` and numbered placeholders intact. See [languages/README.md](languages/README.md).

## Data and external services

Generation sends the selected page's text, title, URL, editorial prompt, a bounded list of public internal links and optional image metadata to OpenAI with `store=false`. Image files and raw view counts are not sent. Credentials and Google tokens are encrypted with a key derived from WordPress salts; changing salts requires reconfiguration.

Frankfurter/ECB provides exchange rates without a key. Google is used after configuration and consent. Update checks contact GitHub's public release API and download a small compatibility manifest; they send a plugin-version user agent and normal server network information, with no OpenAI key, Google token, article content or view statistics. No GitHub credentials are needed. See [readme.txt](readme.txt) for endpoints, limits and developer hooks.

## Validation

Version 1.6.0 passed **76 regression checks, 107 existing WordPress integration checks, 28 publishing checks and 33 language/update checks** locally, plus PHP and JavaScript syntax checks. The update suite exercises WordPress core's actual update discovery, English/Danish switching and JavaScript catalogues. OpenAI, Google and update-service responses were simulated; this is not a live-model quality evaluation or Elementor test. Earlier public currency access was also verified through WordPress. See [TESTING.md](TESTING.md).

## License

GNU GPL **version 2 or later** (`GPL-2.0-or-later`). See [LICENSE](LICENSE).
