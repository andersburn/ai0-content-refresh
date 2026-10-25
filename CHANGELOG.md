# Versionshistorik

Alle fem versioner blev samlet på GitHub den 25. oktober 2026. Tags indeholder hver versions oprindelige pluginkilde; installations-ZIP-filer er udgivet uændret som release-assets.

## 1.4.0 – Billeder fra mediebiblioteket

- Separat adgang til at søge og genbruge eksisterende WordPress-billeder.
- Native WordPress-søgninger i titler, filnavne, alt-tekster og billedtekster ud fra sidens emner.
- Modellen får metadata om højst 12 relevante kandidater og kan tilføje højst to billeder.
- Nye billeder gemmes som almindelige core/image-blokke i Gutenberg med bibliotekets alt-tekst og billedtekst.
- Kendte dubletter, featured image, små billeder og billeder fra private/ikke-udgivne sider udelades.
- Valgte billeder, begrundelser, søgeord og antal vises i historikken.
- Billedadgang og tilgængelighed kontrolleres igen ved publicering; gendannelse sletter ingen mediefiler.

Billedfiler sendes ikke til OpenAI; billedvalget bygger på metadata. Billedadgang er slået til som standard og kan ændres under Indstillinger.

## 1.3.0 – Automatisk valuta og tydeligere oversigter

- Automatisk USD/DKK-omregning via Frankfurter/ECB med kursdato og daglig cache.
- Ved udfald bruges seneste kendte kurs med markering; uden kurs vises kun USD.
- Udelukkede sider og indlæg vises på separate linjer.
- Interne visninger vises for 90 dage / 1 år; 90-dages-tallet bruges i prioriteringen.
- Links til frontend og WordPress-editoren i historikoversigt og historikdetaljer.
- Eksisterende prisberegninger i historikken bevarer deres oprindelige kurs.

## 1.2.0 – Trafikkilder, modelvalg og prisestimat

- Vælg Search Console, indbygget besøgstæller eller begge til prioriteringen.
- Alle indloggede roller udelades fra tælleren, også på cachede sider, via cookiebevarende AJAX.
- Intern besøgssporing ældre end ét kalenderår ryddes op ved opgradering og dagligt via WP-Cron.
- Rigtig model-dropdown med kontoens modelnavne og feltet Anden model.
- Prisoverslag pr. side før generering og estimat fra rapporteret tokenforbrug bagefter.
- Egne modeltakster, manuel DKK-kurs og historiske snapshots af priser og kurs.

Ryd WordPress/CDN-cache efter opdatering, så den nye sporingskode bliver brugt.

## 1.1.0 – Bedre forbedringsforslag og valg

- Anbefalet model med valgknap.
- Søgbar dropdown med flervalg til udelukkede sider og blogindlæg.
- Mere aktiv redaktionel prompt og bedre kontekst omkring tekstfragmenter.
- Rettelse af manglende mellemrum ved inline-formatering.
- Faktiske ændringstal, tydelig besked ved identiske svar og mulighed for manuel gentagelse.
- Eksisterende API-nøgle, modelvalg, prompt og historik bevares ved opdatering.

## 1.0.0 – Første version

Første selvstændige version til AI-assisteret forbedring af eksisterende WordPress-sider og blogindlæg.

- OpenAI API-nøgle, modelvalg og redigerbar prompt.
- Planlagt månedlig gennemgang, forslag eller automatisk publicering og forbrugsgrænser.
- Search Console via Google OAuth, lokal besøgstæller og manuel prioritet.
- Bevaring af Gutenberg-blokke, medier, bannere og links; nye redigerbare indholdselementer.
- Seks kalendermåneders pause, historik, diff, publicering og gendannelse.

Dette er en historisk version. Brug 1.4.0 til nye installationer for de efterfølgende rettelser og funktioner.

