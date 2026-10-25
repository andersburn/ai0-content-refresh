# Validation for 1.2.0

Run date: 25 October 2026 (Europe/Copenhagen).

- PHP 8.5.2 syntax checks: all PHP files pass.
- JavaScript syntax: all scripts pass Node.js `--check`.
- 67 offline regression checks with WordPress 7.1.3's actual block parser, HTML token processor and escaping functions.
- 72 integration checks in an isolated WordPress 7.1.3 installation using the official WordPress SQLite Database Integration drop-in. This exercises actual options, metadata, database tables, `wp_update_post`, WordPress revisions, transactions, block rendering, REST types, admin output and cron callbacks.
- 7 real HTTP checks against the disposable PHP server: anonymous AJAX recording; authenticated administrator and subscriber exclusion with the exact same cached anonymous token; legacy REST no-op; Google-only disabling and internal re-enabling; DNT. Real WordPress login cookies and session-token validation are used, without exposing cookies.
- Browser verification of native model dropdown, Other field, saving a custom model with its rates/source, persistence, and switching to the recommendation without carrying another model's rates.
- Previous-version browser verification of the recommendation button, dropdown search, selecting results, no-result message, preserving choices across filters, Escape to close, saving, and persistence of both the model and selected exclusions. API fields remain empty when previously stored credentials exist.

The supplied hosting-page example is a dedicated regression fixture. Tests verify the missing space after bold text can actually be fixed, exact prices/media/shared banner references survive, text fragments have local context, repeated identical suggestions count as zero changes, and the complete pipeline accepts visible text changes and an added section on that page. A simulated model reply is not an evaluation of live model output quality.

Upgrade coverage verifies nullable history-column migration, old per-page exclusions, preservation of history and API credentials/model choice, upgrading only the shipped default prompt, and retaining a custom prompt. Failed database migration blocks generation before any provider request.

Integration coverage includes preview-only generation, automatic publishing, scheduled worker selection, exact restore, stale snapshot rejection, rollback when a post-save filter alters content, six-month cooldown, no-change handling, network/incomplete output handling, monthly quotas, conservative token reservations, Google token refresh and two-period data synchronization, model discovery, secret redaction, sandboxed history preview, pageview recording, unsigned view rejection, DNT and administrator exclusions.

OpenAI and Google responses were simulated locally; no real keys, live API requests or production content were used. The Google consent screen and real model output quality still need a connection test after configuration. The database test validates WordPress behavior through the SQLite compatibility layer; it is not a concurrent MySQL load test. InnoDB is required by the publishing path.

Commands (only against a disposable local WordPress):

```sh
php tests/run.php /absolute/path/to/wordpress
php tests/integration.php /absolute/path/to/disposable/wordpress
# With a local PHP server running on 127.0.0.1:8093:
php tests/http.php /absolute/path/to/disposable/wordpress
```

The integration runner refuses websites whose home URL is not `http://127.0.0.1:8093`. It writes test content and resets the plugin's local test history. Test files are excluded from the distributable ZIP.

Price tests cover known model rates, cached reads/writes, the documented long-context multipliers, unknown-model handling, custom rates, preserving rate/exchange snapshots, usage-based history costs, and retaining an estimate rather than claiming zero cost on timeout. Retention tests cover leap-year boundaries, exact cutoff preservation, expired plugin abuse-counter cleanup, history preservation, and an independent daily job while generation is disabled. Source selection is checked in ranking, recording and automatic Google syncing. Price forecasts remain heuristic and are not validated against live invoices.
