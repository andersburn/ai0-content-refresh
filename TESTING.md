# Validation for 1.0.0

Run date: 25 October 2026 (Europe/Copenhagen).

- PHP 8.5.2 syntax checks: all PHP files pass.
- JavaScript syntax: both scripts pass Node.js `--check`.
- 42 offline regression checks with WordPress 7.1.3's actual block parser, HTML token processor and escaping functions.
- 43 integration checks in an isolated WordPress 7.1.3 installation using the official WordPress SQLite Database Integration drop-in. This exercises actual options, metadata, database tables, `wp_update_post`, WordPress revisions, transactions, block rendering, REST types, admin output and cron callbacks.
- Browser inspection of the queue and settings pages, including successful form submission without an API key. API fields remain empty when previously stored credentials exist.

Integration coverage includes preview-only generation, automatic publishing, scheduled worker selection, exact restore, stale snapshot rejection, rollback when a post-save filter alters content, six-month cooldown, no-change handling, network/incomplete output handling, monthly quotas, conservative token reservations, Google token refresh and two-period data synchronization, model discovery, secret redaction, sandboxed history preview, pageview recording, unsigned view rejection, DNT and administrator exclusions.

OpenAI and Google responses were simulated locally; no real keys, live API requests or production content were used. The Google consent screen and real model output quality still need a connection test after configuration. The database test validates WordPress behavior through the SQLite compatibility layer; it is not a concurrent MySQL load test. InnoDB is required by the publishing path.

Commands (only against a disposable local WordPress):

```sh
php tests/run.php /absolute/path/to/wordpress
php tests/integration.php /absolute/path/to/disposable/wordpress
```

The integration runner refuses websites whose home URL is not `http://127.0.0.1:8093`. It writes test content and resets the plugin's local test history. Test files are excluded from the distributable ZIP.
