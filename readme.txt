=== Ai0 Content Refresh ===
Contributors: ai0
Tags: gutenberg, content, openai, search-console
Requires at least: 6.7
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later

Et separat plugin til at forbedre eksisterende sider og blogindlæg med OpenAI og Search Console.

== Installation ==
1. Upload ai0-content-refresh.zip under Plugins > Tilføj plugin > Upload plugin.
2. Aktivér pluginet og åbn Content Refresh > Indstillinger.
3. Indtast din egen OpenAI API-nøgle. Gem, og tryk Test nøgle og hent modeller.
4. Brug den anbefalede GPT-6.1 Sol (gpt-6.1-sol), eller vælg et andet kompatibelt model-ID. Modellen skal understøtte Responses API og Structured Outputs, og din konto skal have adgang til modellen.
5. Tilpas prompt, indholdstyper, månedligt antal genereringer og tokenloft.
6. Generér et forslag, og gennemgå historikkens ændringer og forhåndsvisning.
7. Aktivér planlagt gennemgang og vælg automatisk publicering, når du er klar.

Automatik er slået fra ved installation. Manuelle genereringer gemmer altid et forslag. Pluginet ændrer aldrig URL, titel, kategori, featured image, Yoast-felter eller temaets metadata. Kun post_content ændres. Det eksisterende AI Blog Writer-plugin berøres ikke.

== Opdatering fra 1.0.0 / 1.1.0 / 1.2.0 ==
Upload den nye ZIP, og vælg at erstatte den eksisterende pluginversion. API-nøgle, Google-forbindelse, modelvalg og historik bevares. Den oprindelige standardprompt erstattes af en mere aktiv forbedringsprompt; en prompt, du selv har ændret, bevares.

Indstillinger > Udeluk sider og indlæg er en søgbar dropdown med flervalg. Søg på titel eller ID, markér de sider og indlæg, der skal udelukkes, og gem. Valgene er fælles med editorens og prioriteringsoversigtens udelukkelse. Tidligere indtastede ID’er og per-side-markeringer overføres automatisk. Filtrering af listen rydder ikke valgte sider. Valgte sider vises med én side pr. linje under dropdownen. Hjælpeteksten er også opdelt på flere linjer.

Model vælges i en rigtig dropdown. Test nøgle og hent modeller henter alle model-ID’er fra din API-konto, inklusive modeller der ikke er kompatible med indholdsgenerering. Vælg en tekstmodel med Responses og Structured Outputs. Anden model åbner et frit Model-ID-felt.

Modelanbefalingen er et redaktionelt udgangspunkt, ikke en garanti for resultat eller adgang. GPT-6.1 Sol er valgt som balance mellem kvalitet og pris ud fra OpenAI Docs: https://developers.openai.com/api/docs/models (kontrolleret 25. oktober 2026). Et eksisterende modelvalg ændres ikke automatisk; knappen Brug den anbefalede model udfylder feltet, og du gemmer derefter indstillingerne.

== Search Console: Google-login ==
Login-knappen er inkluderet, men en egen OAuth-klient skal konfigureres én gang. Pluginet indeholder ikke en fælles hosted Google-login-service eller en indbygget fælles Client Secret.

Opret et Google Cloud-projekt, aktivér Search Console API, konfigurér samtykke og opret en OAuth-klient af typen Web application. Brug læseadgangen https://www.googleapis.com/auth/webmasters.readonly og redirect-URI’en, som vises i pluginets Indstillinger. Gem Client ID og Client Secret. Klik derefter Forbind med Google, godkend adgang og vælg website. WordPress-administrationen skal bruge HTTPS. Google-kontoen skal have adgang til ejendommen.

En ekstern OAuth-app i Testing kan få refresh-tokens, som udløber efter syv dage. Konfigurér publiceringsstatus og eventuel verifikation til dit konkrete brug. Pluginet fornyer gyldige adgangstokens automatisk, men kan ikke omgå Googles samtykke-, publicerings- eller tilbagekaldelsesregler.

Data hentes dagligt: to 28-dages perioder med tre dages forsinkelse. Søgeklik, eksponeringer, CTR og gennemsnitlig position bruges i prioriteringen. Forældede data (>7 dage) ignoreres. API’et returnerer primært topdata og garanterer ikke alle rækker. En manglende URL er ikke bevis for nul trafik. Klik er organiske Google-klik, ikke samlet trafik. Søgeord indsamles ikke, og rå Google-statistik sendes ikke til OpenAI.

== Bevaring og nye elementer ==
Pluginet bruger WordPress’ HTML-tokenprocessor til at ændre tekst i kendte tekstblokke og klassisk HTML. Tags og attributter bevares; linktekster, kode, formularer, medier, dynamiske og ukendte blokke beskyttes. Registrerede shortcodes beskyttes i deres komplette HTML-fragment. Ikke alle specialblokke kan derfor få deres interne tekst omskrevet.

Nye resuméer, FAQ’er, tjeklister, tabeller, faktabokse, kontaktbokse og relaterede links gemmes som redigerbare ai0-refresh/section-blokke. Der tillades højst fire på en side i alt. Billeder og videoer bevares; pluginet genererer ikke nye billeder/videoer. Modellen instrueres til at undgå dubletter og til kun at bruge eksisterende fakta. Den har ikke web research. Teknisk kontrol er ikke faktatjek og garanterer ikke højere placeringer.

De nye elementer kræver det aktive plugin for frontend-rendering. Ved deaktivering bevares blokdata i indholdet, men de dynamiske elementer vises ikke før genaktivering. Gendan originalindholdet inden permanent fjernelse, hvis det ønskes.

== Prioritering og seks måneder ==
I Indstillinger > Trafikkilde til prioritering vælger du Kun Google Search Console, Kun indbygget besøgstæller eller begge. Kun Google stopper al intern optælling. Kun intern stopper den automatiske Google-synkronisering. Den separate tæller-afbryder kan også slå intern optælling og brug af tællerdata fra. Eksisterende data og Google-forbindelse bevares ved skift, med den normale retention. Ingen kilde erstattes i stilhed af den anden. Manuelle markeringer og alder bruges altid.

Manuel prioritet > Yoast-hjørnestensmarkering > trafik, tab af Google-klik, søgepotentiale og alder. Interne trafiktal vises for 90 dage og det seneste kalenderår; 90-dages-tallet indgår i prioriteringsscoren. Dagens dato og datoen ét kalenderår tilbage er inkluderet i årstallet. Tal fra før installationen genskabes ikke. Oversigten forklarer hver score. Manglende data giver prioritering efter manuelle markeringer og alder.

En side må først behandles seks kalendermåneder efter seneste udgivelse eller indholdsændring, også en manuel ændring. Efter en pluginopdatering går der igen mindst seks måneder. Reglen gælder både generering og publicering og kan ikke omgås med en manuel knap eller prompt. Månedslængder og skudår håndteres. En gendannelse tæller som en indholdsændring og omgår ikke pausen.

Kun udgivne posts/pages uden adgangskode behandles. Privatlivsside, Yoast-noindex og manuelt udelukket indhold springes over. Markér også vilkår, juridiske tekster, særligt følsomt indhold og øvrige sider, der skal udelukkes. Cases og andre custom post types behandles ikke i denne version.

== Planlægning og budget ==
Der behandles højst én side i timen fra den valgte dag og time i måneden. Månedens maksimum gælder alle API-genereringer, også manuelle forsøg, fejl og kørsler uden ændringer. En opdateringskvote er derfor ikke et løfte om samme antal publiceringer. Afviste/overflødige forslag får en pause; fejl får normalt syv dages pause. Uafsluttede kørsler markeres som fejl efter ti minutter.

Tokenloftet reserverer konservativt plads før API-kaldet og erstatter reservationen med kendt faktisk forbrug efter svaret. Ved afbrudt eller ukendt forbrug beholdes reservationen. Loftet er ikke en valutaangivelse; modelpriser og tokenisering kan variere. Generering bruger maksimalt 90 sekunders HTTP-timeout. Store sider (>250 KB eller 600 tekstfelter) springes over.

WP-Cron kræver trafik. Brug webhotellets system-cron mod wp-cron.php for stabil tidsstyring. Tidszone følger WordPress. Et eksisterende cron-setup ændres ikke automatisk.

== Prisestimat pr. side og indlæg ==
Oversigten viser et prisoverslag for hver side med den valgte model, prompt og svargrænse. Input anslås ud fra hele forespørgslen inklusive instruktioner og JSON-skema, ca. tre tegn pr. token. Output anslås ud fra indholdets længde. Det er en heuristik, ikke en rigtig tokenizer. Et separat forsigtigt scenario bruger den konservative inputreservation og hele svargrænsen; det er ikke et garanteret maksimum. Reasoning og faktisk svarlængde kan påvirke forbruget.

Efter hver kørsel gemmes et estimat ud fra API’ets input/output/cached-tokenforbrug i historikken. Output-tallet omfatter også eventuelle reasoning-tokens. Takster og beregningskurs gemmes sammen med kørslen og ændres ikke, når du senere vælger en anden model/pris. Ved afbrudt svar uden usage bevares et tydeligt mærket overslag; det vises ikke som nul kroner. Ældre historik uden prisdata får ikke opfundet en historisk pris.

Standardtakster pr. million tokens (USD), kontrolleret 25.10.2026:
Model | Input | Output | Cache-læsning | Cache-skrivning
GPT-6.1 Sol | 2 | 10 | 0,10 | 2,50
GPT-6 Astra | 10 | 50 | 1 | 12,50
GPT-6 Luna | 0,10 | 0,50 | 0,01 | 0,125
For disse modeller anvendes de dokumenterede tillæg ved over 272.000 inputtokens: input/cache 2x, output 1,5x. Forespørgsler vælger service_tier=default. Kilder:
https://developers.openai.com/api/docs/models/gpt-6.1-sol
https://developers.openai.com/api/docs/models/gpt-6-astra
https://developers.openai.com/api/docs/models/gpt-6-luna

Andre modeller og snapshots kræver egne takster. Slå Brug egne takster til, og udfyld input, output og cache-takster i USD pr. million tokens. Egne takster tilsidesætter indbyggede takster for den valgte model; uden markering anvendes indbyggede takster igen. Egne takster har ingen automatisk kontekstmultiplikator. Ændring af model nulstiller formularen til den models egne gemte takster eller standardtakster.

Fra version 1.3.0 vises Valutaomregning som automatisk USD → DKK. Ingen manuel kurs eller ekstra API-nøgle er nødvendig. Pluginet henter ECB’s seneste daglige referencekurs via Frankfurter:
https://api.frankfurter.dev/v2/providers/ecb/rate/usd/dkk
Dokumentation: https://frankfurter.dev/

Kursen hentes på WordPress-serveren og caches i et døgn. En selvstændig daglig WP-Cron-opgave opdaterer også kursen, når AI-automatikken er slået fra. Ved fejl forsøges højst én gang i timen. Kilde og kursdato vises i Indstillinger, oversigten og historikkens detaljer. Weekender og helligdage bruger seneste offentliggjorte kurs. Ved nedbrud bruges seneste gyldige kurs med en tydelig markering; uden tidligere kurs vises USD alene. Fejl i valutakilden blokerer ikke indholdsgenerering. Der sendes ingen API-nøgle, besøgstal eller sideindhold til valutakilden. Tjenesten modtager serverens normale netværksforespørgsel om USD/DKK.

Historikkens eksisterende priser genberegnes ikke. Tidligere manuelt beregnede beløb bevares og markeres som en tidligere manuel kurs. Nye historikposter gemmer den anvendte onlinekurs, kursdato og kilde sammen med beløbet. Eksempel med GPT-6.1 Sol: 10.000 input + 4.000 output uden cacherabat = $0,06; DKK-beløbet beregnes med den hentede kurs. Alle beløb er estimater uden moms, gebyrer og aftalerabatter. Priser kan ændre sig; opdatér egne takster ved behov. API-fakturaen er afgørende. Prisestimatet er ikke et valutabudget eller en garanti for, at et forslag forbedrer indholdet.

== Historik og gendannelse ==
Både historikoversigten og den enkelte kørsel har Se siden til frontenden og Redigér i WordPress til editoren. Links åbner i en ny fane. Slettet indhold får ingen brudte links; ikke-udgivet indhold har kun editorlink.

Historikken gemmer model, redaktionel prompt, før/efter-indhold, hashes, datoer, status og tokens/reservationer. Forslag kan gennemgås, publiceres eller afvises. Publiceret indhold kan gendannes, hvis indholdet ikke er ændret siden. En nyere manuel ændring bliver ikke overskrevet. Seks måneders regel kontrolleres igen ved publicering. Ved ændringer i WordPress’ lagringsfiltre annulleres opdateringen, hvis indholdet ikke længere matcher det validerede forslag.

Fra version 1.1.0 gemmes også faktiske antal tekstændringer og nye elementer samt modellens konkrete vurdering. Identiske tekstforslag tæller ikke som ændringer. Et svar uden effektive ændringer vises som Ingen ændringer foreslået, med begrundelse og mulighed for en ny manuel gennemgang. Det publiceres ikke, starter ikke en ny seksmånedersperiode og vises ikke som to identiske før/efter-versioner. Månedens kvoter gælder også ved en ny gennemgang. Modellen kan fortsat vælge ingen ændringer; pluginet tvinger ikke en unyttig omskrivning igennem.

Databasens posts-, postmeta- og historiktabeller skal understøtte transaktioner (InnoDB) for sikker publicering. Historik slettes ikke automatisk ved deaktivering eller fjernelse. Nye WordPress-revisioner oprettes også, hvis revisioner er aktiveret. Metadata ændres ikke; der er derfor ingen metadata, som skal gendannes.

== Besøgstæller og eksterne tjenester ==
Tælleren gemmer kun indholds-ID, dato og antal visninger på din egen server. Pluginet sætter ingen sporingscookies og gemmer ingen besøgsprofiler, fulde URL’er eller referrers. Eksisterende WordPress-login-cookies medsendes til WordPress for at kunne frasortere indloggede brugere. Indloggede brugere, forhåndsvisninger og kendte bots frasorteres. Browserens DNT/GPC respekteres. En kortvarig misbrugsbegrænsning gemmer en envejsværdi af IP-adressen i op til to minutter. Webserveren modtager som normalt IP og user agent; dens egne logs styres ikke af pluginet. Summer ældre end ét kalenderår slettes ved installation/opgradering og dagligt via en selvstændig WP-Cron-opgave, også når AI-automatik og tæller er slået fra. Skudår håndteres. Genereringshistorikken bevares. Oprydningen kræver, at pluginet er aktivt og WP-Cron kører.

Cachevenligt browserscript kalder admin-ajax.php med et signeret indholds-ID. Både administratorer, redaktører, abonnenter og øvrige indloggede roller udelades på serveren, også hvis de har fået en cachet anonym side. Den gamle REST-sporingsrute tæller ikke længere, så gamle cachede scripts ikke tæller indloggede brugere. Ryd WordPress- og Cloudflare-cache efter aktivering, saltændring eller ændring af tælleren. Browserblokering, ukendte bots og manipulerede forespørgsler betyder, at tælleren kun er et omtrentligt mål. Data bruges ikke til sikkerhedsbeslutninger.

For samtykke-/privacyintegration kan filteret ai0r_tracking_allowed bruges til at styre både script og endpoint. Hvis samtykke varierer pr. besøgende på cachede sider, skal CMP’en også blokere scriptet, indtil passende samtykke foreligger. Pluginet afgør ikke kravene til dit konkrete website.

OpenAI: Der sendes ved generering den valgte sides tekst, titel, URL, redaktionelle prompt og et begrænset katalog af offentlige interne links til https://api.openai.com/v1/responses med store=false. API-nøglen sendes kun til api.openai.com. Modeltesten bruger /v1/models. Googles OAuth- og Search Console-endpoints bruges kun efter opsætning og godkendelse. Nøgler og tokens gemmes krypteret med en nøgle afledt af WordPress’ auth-salt; en ændring af salts kræver genindtastning og nyt Google-login. Databasekopier bør stadig beskyttes.

Udvikler-hook: ai0r_content_applied($post_id, $history_id, $is_restore) til eksplicit cachepurge eller andre lokale integrationer. WordPress’ normale post-update-hooks køres også.

== Changelog ==
= 1.3.0 =
Automatisk USD/DKK-omregning via Frankfurter/ECB med kursdato, cache, fejlhåndtering og gemte historikkurser; udelukkede sider som liste med linjeskift; visninger for 90 dage og ét kalenderår; frontend- og editorlinks i historikoversigt og historikdetaljer.

= 1.2.0 =
Valg af trafikkilde; frasortering af alle indloggede roller via cookiebevarende AJAX, også på cachede sider; ét kalenderårs retention med selvstændig daglig oprydning; model-dropdown med alle kontoens model-ID’er og Anden model-felt; prisoverslag pr. side og tokenbaseret estimat i historikken; gemte takst/valutakurssnapshots og egne modelpriser.

= 1.1.0 =
Anbefalet model med valgknap; søgbar dropdown med flervalg og fælles udelukkelsesliste; mere aktiv forbedringsprompt og lokal tekstkontekst; rettelse af manglende mellemrum ved inline-formatering; ændringstal og klarere ingen-ændring-historik; manuel gentagelse af gennemgange uden ændringer; bevaring af egne indstillinger ved opgradering.

= 1.0.0 =
Separat plugin med brugerindtastet OpenAI-nøgle, modelvalg, prompt, planlægning, Search Console OAuth, lokal tæller, prioritering, bevaring af Gutenberg/klassisk HTML, nye redigerbare elementer, historik og gendannelse.
