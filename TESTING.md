# Validation for 1.6.0

Run date: 25 October 2026 (Europe/Copenhagen).

- PHP 8.5.2 syntax checks: all PHP files pass.
- JavaScript syntax: all scripts pass Node.js `--check`.
- 76 offline regression checks with WordPress 7.1.3's actual block parser, HTML token processor and escaping functions.
- 107 integration checks in an isolated WordPress 7.1.3 installation using the official WordPress SQLite Database Integration drop-in. This exercises actual options, metadata, database tables, `wp_update_post`, WordPress revisions, transactions, block rendering, REST types, admin output and cron callbacks.
- 7 real HTTP checks against the disposable PHP server: anonymous AJAX recording; authenticated administrator and subscriber exclusion with the exact same cached anonymous token; legacy REST no-op; Google-only disabling and internal re-enabling; DNT. Real WordPress login cookies and session-token validation are used, without exposing cookies.
- Browser verification of native model dropdown, Other field, saving a custom model with its rates/source, persistence, and switching to the recommendation without carrying another model's rates.
- Previous-version browser verification of the recommendation button, dropdown search, selecting results, no-result message, preserving choices across filters, Escape to close, saving, and persistence of both the model and selected exclusions. API fields remain empty when previously stored credentials exist.

The supplied hosting-page example is a dedicated regression fixture. Tests verify the missing space after bold text can actually be fixed, exact prices/media/shared banner references survive, text fragments have local context, repeated identical suggestions count as zero changes, and the complete pipeline accepts visible text changes and an added section on that page. A simulated model reply is not an evaluation of live model output quality.

Upgrade coverage verifies nullable history-column migration, old per-page exclusions, preservation of history and API credentials/model choice, upgrading only the shipped default prompt, and retaining a custom prompt. Failed database migration blocks generation before any provider request.

Integration coverage includes preview-only generation, automatic publishing, scheduled worker selection, exact restore, stale snapshot rejection, rollback when a post-save filter alters content, six-month cooldown, no-change handling, network/incomplete output handling, monthly quotas, conservative token reservations, Google token refresh and two-period data synchronization, model discovery, secret redaction, sandboxed history preview, pageview recording, unsigned view rejection, DNT and administrator exclusions.

OpenAI and Google responses were simulated locally; no real keys, live OpenAI/Google API requests or production content were used. The Google consent screen and real model output quality still need a connection test after configuration. The database test validates WordPress behavior through the SQLite compatibility layer; it is not a concurrent MySQL load test. InnoDB is required by the publishing path.

Commands (only against a disposable local WordPress):

```sh
php tests/run.php /absolute/path/to/wordpress
php tests/integration.php /absolute/path/to/disposable/wordpress
# With a local PHP server running on 127.0.0.1:8093:
php tests/http.php /absolute/path/to/disposable/wordpress
```

The integration runner refuses websites whose home URL is not `http://127.0.0.1:8093`. It writes test content and resets the plugin's local test history. Test files are excluded from the distributable ZIP.

Price tests cover known model rates, cached reads/writes, the documented long-context multipliers, unknown-model handling, custom rates, preserving rate/exchange snapshots, usage-based history costs, and retaining an estimate rather than claiming zero cost on timeout. Retention tests cover leap-year boundaries, exact cutoff preservation, expired plugin abuse-counter cleanup, history preservation, and an independent daily job while generation is disabled. Source selection is checked in ranking, recording and automatic Google syncing. Price forecasts remain heuristic and are not validated against live invoices.

Version 1.3.0 adds real database checks for 90-day/year cutoff inclusivity, excluding older and future rows, and updating the queue label. History frontend/editor targets are verified on the list and detail; deleted content has no broken links. Browser verification confirms separate list items and help-text line breaks, online-rate status, and public/editor links.

Currency tests cover validation, exact base/quote, invalid numbers/dates, daily caching, throttled retry, keeping the last valid rate during outages, USD-only fallback, preservation of prior manual historical prices and immutable exchange snapshots. OpenAI/Google and the repeatable currency outage cases use simulated responses. A separate live, public request was also validated through WordPress HTTP against Frankfurter’s ECB USD/DKK endpoint; it returned a valid dated observation without any API key. No production installation/content or real OpenAI/Google calls were used.

Version 1.4.0 adds native WordPress media fixtures, using copies of a bundled Twenty Twenty-Five image only in the disposable uploads directory. Coverage includes title/alt search, introduction keyword fallback, excluding private-parent/tiny/logo/unrelated/featured images, disabled search, bounded metadata-only input, native image-only suggestions, preservation of attachment alt/caption metadata, rejecting unknown IDs/duplicate IDs/over-limit/disabled insertions, excluding existing legacy/Gutenberg/reusable-block images, history display, rechecking privacy and permission before publish, transactional image publication, and exact restoration without deleting media. The model's image-choice responses were simulated; metadata relevance is not a live-model visual-quality evaluation.

Browser checks confirm image choice/reason/alt in history and recognition as a native Image block in Gutenberg's document overview, with no block-validation errors logged. The browser's blob-based editor canvas did not render, so the editor canvas itself was not visually verified; the history image preview rendered normally. No test-only compatibility plugin is included in the ZIP.

## Version 1.5.0: language and release updates

- 76 regression checks and 107 existing WordPress integration checks pass. The integration suite also runs against the bundled Danish catalogue to preserve existing translated behavior.
- 33 additional checks exercise actual WordPress English/Danish locale switching, fallback for an untranslated locale, administration/model labels, WordPress loading both JavaScript JSON catalogues, language-neutral new editorial defaults and preservation of saved settings.
- Update checks exercise WordPress core's actual `wp_update_plugins()` discovery, same-version no-update behavior, the native plugin-information popup, version-specific compatibility metadata, correct installation asset selection, positive/negative caching, network errors, rate limits, draft/prerelease exclusion, foreign URL rejection and mismatched/malformed manifest rejection.
- The updater does not enable auto-updates; WordPress retains the administrator's preference. Sites running 1.0–1.4 require a one-time manual ZIP upgrade because they have no updater.
- GitHub and manifest responses in repeatable tests are simulated. This does not claim a live unattended future upgrade or a complete PHP/WordPress version matrix. No production WordPress installation was modified.
- The English default prompt applies to new installs; existing saved prompts, article text and historic reports are not translated by an interface update.

Additional command:

```sh
php tests/i18n-updates.php /absolute/path/to/disposable/wordpress
```

## Version 1.6.0: publishing choices

Validation on 26 October 2026 used PHP 8.5.2 and WordPress 7.1.3 with the official SQLite Database Integration 3.0.2 drop-in. The 76 regression checks, 107 existing integration checks and 33 language/update checks still pass. PHP and JavaScript syntax checks also pass.

28 additional publishing checks exercise the same server-side manual-generation method used by page, next-page and retry actions. They cover review-only proposals, automatic manual publication with scheduling disabled, accurate outcome notices, publishing labels and submitted mode, six-month enforcement, unchanged replies, stale/legacy form refusal without provider requests, switching to review during generation, transactional rollback and error retention after a save-filter failure, scheduled auto/review behavior and keeping earlier pending entries pending.

Both setting choices and the new explanations are available in English and Danish. Provider responses were simulated; no production content or real API credentials were used. The SQLite compatibility layer does not constitute a concurrent InnoDB load test. Production publishing still requires InnoDB. The test files and disposable WordPress environment are excluded from the installation ZIP.

```sh
php tests/publishing.php /absolute/path/to/disposable/wordpress
```

This runner includes the 107 existing integration checks before its 28 additional checks; do not count the included suite twice.
