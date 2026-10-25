# Ai0 Content Refresh 1.3.0

Historisk version til AI-assisteret SEO-optimering og vedligeholdelse af eksisterende WordPress-indhold.

- Automatisk USD/DKK-omregning via Frankfurter/ECB med kursdato og daglig cache.
- Ved udfald bruges seneste kendte kurs med markering; uden kurs vises kun USD.
- Udelukkede sider og indlæg vises på separate linjer.
- Interne visninger vises for 90 dage / 1 år; 90-dages-tallet bruges i prioriteringen.
- Links til frontend og WordPress-editoren i historikoversigt og historikdetaljer.
- Eksisterende prisberegninger i historikken bevarer deres oprindelige kurs.

## Krav

PHP **8.1+**, WordPress **6.7+**, OpenSSL, mbstring og egen OpenAI API-nøgle. Primært Gutenberg; almindelig klassisk HTML understøttes. **Not tested with Elementor / Ikke testet med Elementor.** InnoDB kræves til publicering.

Se [readme.txt](readme.txt) for denne versions fulde vejledning, [seneste README](https://github.com/andersburn/ai0-content-refresh#readme) for den aktuelle udgave og [releases](https://github.com/andersburn/ai0-content-refresh/releases) for installations-ZIP-filer.

Licens: GPL-2.0-or-later.
