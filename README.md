# Ai0 Content Refresh 1.2.0

Historisk version til AI-assisteret SEO-optimering og vedligeholdelse af eksisterende WordPress-indhold.

- Vælg Search Console, indbygget besøgstæller eller begge til prioriteringen.
- Alle indloggede roller udelades fra tælleren, også på cachede sider, via cookiebevarende AJAX.
- Intern besøgssporing ældre end ét kalenderår ryddes op ved opgradering og dagligt via WP-Cron.
- Rigtig model-dropdown med kontoens modelnavne og feltet Anden model.
- Prisoverslag pr. side før generering og estimat fra rapporteret tokenforbrug bagefter.
- Egne modeltakster, manuel DKK-kurs og historiske snapshots af priser og kurs.

Ryd WordPress/CDN-cache efter opdatering, så den nye sporingskode bliver brugt.

## Krav

PHP **8.1+**, WordPress **6.7+**, OpenSSL, mbstring og egen OpenAI API-nøgle. Primært Gutenberg; almindelig klassisk HTML understøttes. **Not tested with Elementor / Ikke testet med Elementor.** InnoDB kræves til publicering.

Se [readme.txt](readme.txt) for denne versions fulde vejledning, [seneste README](https://github.com/andersburn/ai0-content-refresh#readme) for den aktuelle udgave og [releases](https://github.com/andersburn/ai0-content-refresh/releases) for installations-ZIP-filer.

Licens: GPL-2.0-or-later.
