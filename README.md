# Ai0 Content Refresh

**AI-assisteret SEO-optimering og vedligeholdelse af eksisterende indhold i WordPress.**

Ai0 Content Refresh hjælper dig med at forbedre sider og blogindlæg, du allerede har skrevet. Pluginet finder indhold, der bør gennemgås, bruger din valgte OpenAI-model til at foreslå konkrete forbedringer og gemmer en ændringshistorik, så du kan se, godkende og gendanne resultatet.

Det er lavet til websites, der vil arbejde løbende med indholdsbaseret SEO: tydeligere tekster, bedre struktur, færre gentagelser, mere brugbare svar og relevante interne links. Eksisterende billeder, videoer, bannere, links og Gutenberg-layout bevares.

**Seneste version: 1.4.0.** [Hent installations-ZIP](https://github.com/andersburn/ai0-content-refresh/releases/latest) · [Alle releases](https://github.com/andersburn/ai0-content-refresh/releases) · [Versionshistorik](CHANGELOG.md)

## Hvad pluginet kan

- **Forbedre eksisterende tekster:** mere konkrete indledninger, bedre læsbarhed, sprog og struktur uden at opfinde nye fakta.
- **Tilføje nyttige elementer:** resumé, FAQ, tjekliste, sammenligningstabel, faktaboks og relevante interne links. Højst fire nye tekstelementer pr. side i alt.
- **Genbruge billeder fra WordPress:** lokal søgning i mediebiblioteket med højst 12 kandidater og to nye billeder pr. gennemgang. Billeder tilføjes som native Gutenberg-blokke. Modellen vælger ud fra titler, filnavne, alt-tekster og billedtekster; billedfiler sendes ikke til OpenAI.
- **Prioritere indhold:** vælg Google Search Console, den indbyggede besøgstæller eller begge. Manuelle markeringer, Yoast-hjørnestensindhold, trafik, søgepotentiale og alder indgår.
- **Automatisere gennemgange:** vælg startdag, tidspunkt, månedligt antal og tokenloft. Gem forslag til gennemgang, eller vælg automatisk publicering efter teknisk kontrol.
- **Holde seks måneders pause:** en side behandles tidligst seks kalendermåneder efter seneste udgivelse eller indholdsændring, også manuelle ændringer.
- **Vise forbrug:** prisoverslag pr. side før kørsel og et estimat baseret på rapporterede tokens bagefter. Automatisk USD/DKK-omregning bruger Frankfurter/ECB med synlig kursdato.
- **Dokumentere ændringer:** før/efter-diff, prompt, model, tokens, billeder, begrundelser og status. Links til både frontend og editor. Gendan tidligere indhold, hvis siden ikke siden er ændret.
- **Udelukke indhold:** søgbar liste med flervalg samt udelukkelse i editoren. Privatlivssiden, adgangskodebeskyttet indhold og Yoast-noindex springes automatisk over.

### Formål og afgrænsning

SEO-målet er mere relevant, overskueligt og vedligeholdt indhold for læseren. Pluginet arbejder i `post_content`; URL, titel, kategori, featured image, Yoast-felter og temaets metadata bevares. Det foretager ikke ekstern research eller faktatjek og giver ingen garanti for højere placeringer. FAQ-blokke er indholdselementer; der loves ikke særskilt FAQ-schema eller rich results.

Teknisk kontrol sikrer format, bevaring og adgang til valgte billeder. Gennemgå de første forslag, især på sider med priser, garantier og andre vigtige faktuelle oplysninger. Pluginet kan vælge ingen ændringer, hvis modellen ikke leverer en nyttig forbedring; det vises tydeligt i historikken.

## Krav og kompatibilitet

| Område | Krav / status |
| --- | --- |
| PHP | **8.1 eller nyere**; OpenSSL og mbstring skal være tilgængelige |
| WordPress | **6.7 eller nyere** ifølge pluginets minimumskrav |
| Editor | **Gutenberg / WordPress-blokeditoren er det primære format** |
| Klassisk editor | Understøtter redigerbar, almindelig HTML-tekst i `post_content`; shortcodes og ukendte specialelementer beskyttes |
| Elementor | **Not tested with Elementor / Ikke testet med Elementor. Kompatibilitet garanteres ikke** |
| Andre page builders | Ikke testet; indhold gemt som builder-metadata behandles ikke |
| Database | InnoDB til WordPress-indhold, metadata og pluginets historik er nødvendigt for sikker publicering |
| OpenAI | Din egen API-nøgle, API-kredit og en model med **Responses API + Structured Outputs** |
| Automatik | Fungerende WP-Cron; webhotellets system-cron anbefales til stabil tidsstyring |
| Netværk | Udgående HTTPS til OpenAI og de valgte eksterne tjenester |
| Search Console | Valgfrit: Google-konto med adgang til websitet, egen OAuth Client ID/Secret og HTTPS-administration |

Minimumskrav er ikke det samme som en fuld testmatrix: den lokale validering blev kørt med **PHP 8.5.2 og WordPress 7.1.3**, inklusive WordPress' officielle SQLite-kompatibilitetslag. Produktionspublicering kræver InnoDB. Se [TESTING.md](TESTING.md) for den konkrete validering og dens begrænsninger.

## Installation og første kørsel

1. Hent **ai0-content-refresh-1.4.0.zip** fra [seneste release](https://github.com/andersburn/ai0-content-refresh/releases/latest). Brug release-asset'en frem for GitHubs automatisk genererede kildekode-ZIP.
2. Åbn **Plugins → Tilføj plugin → Upload plugin** i WordPress. Upload ZIP og aktivér.
3. Åbn **Content Refresh → Indstillinger**, indsæt din OpenAI API-nøgle og gem.
4. Tryk **Test nøgle og hent modeller**. Vælg en kompatibel tekstmodel i dropdownen, eller brug **Anden model**. Kontoen skal have adgang til den valgte model.
5. Tilpas prompt, indholdstyper, udelukkelser, billedadgang, trafikkilde og forbrugsgrænser.
6. Generér et forslag til en side, som har passeret seksmånedersgrænsen. Gennemgå ændringer og billedvalg i historikken.
7. Aktivér planlagt gennemgang, når opsætningen er klar. Automatik er som udgangspunkt slået fra; manuelle kørsler gemmer altid forslag.

### Google Search Console

Search Console er valgfrit. Forbindelsen bruger Google-login, men kræver en engangsopsætning af din egen OAuth-klient i Google Cloud. Indtast Client ID/Secret i pluginet og kopiér den viste redirect-URI. Forbind derefter Google og vælg det website, kontoen har adgang til.

Pluginet anmoder om læseadgang og sammenligner to perioder på 28 dage med tre dages forsinkelse. Data ældre end syv dage ignoreres i prioriteringen. Google-klik er organiske søgeklik og er ikke det samme som alle besøg på websitet. Der er ingen fælles hosted login-service eller fælles Google-nøgler indbygget.

### Lokal besøgstæller

- Viser sidevisninger for **90 dage / 1 år**, fra installationstidspunktet.
- Udelader alle indloggede brugere, også når de ser cachede sider, samt kendte bots og forhåndsvisninger.
- Respekterer DNT/GPC og sætter ingen sporingscookies.
- Gemmer dato, indholds-ID og antal visninger. En kortvarig misbrugsbegrænsning bruger en envejsværdi af IP-adressen.
- Sletter intern besøgssporing ældre end ét kalenderår ved opdatering og derefter dagligt via WP-Cron. Genereringshistorik bevares.

Tælleren er et omtrentligt mål og måler ikke unikke besøgende. Ryd WordPress/CDN-cache efter installation, opdatering eller ændring af sporingen. Websiteejeren afgør den nødvendige samtykke-/privatlivsopsætning; filteret `ai0r_tracking_allowed` kan bruges til integration.

## Opdatering og versioner

Hver version findes som et separat **Git-tag og en GitHub-release** med den oprindelige installations-ZIP. De historiske ZIP-filer er bevaret uændret. Brug den nyeste version til nye installationer.

| Version | Vigtigste ændringer |
| --- | --- |
| [1.0.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.0.0) | Første version: OpenAI, planlægning, Search Console, tæller, prioritering, historik og gendannelse |
| [1.1.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.1.0) | Modelanbefaling, søgbare udelukkelser og bedre forbedrings-/ingen-ændring-håndtering |
| [1.2.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.2.0) | Valg af trafikkilde, login-filtrering, ét års retention, model-dropdown og prisestimater |
| [1.3.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.3.0) | Automatisk valutaomregning, 90 dage / 1 år, linjeskift og frontend-/editorlinks |
| [1.4.0](https://github.com/andersburn/ai0-content-refresh/releases/tag/v1.4.0) | Billedsøgning og genbrug fra WordPress-mediebiblioteket |

Upload den nye release-ZIP i WordPress, og vælg **erstat eksisterende version**. Indstillinger, API-nøgle og historik bevares. GitHub-releases organiserer versionerne; denne udgave har **ingen indbygget automatisk GitHub-opdateringsklient i WordPress**.

Prisestimater er uden moms, gebyrer og aftalerabatter. API-fakturaen er afgørende. Ved ukendt modelpris kan du angive egne takster; historiske priser og valutakurser ændres ikke efterfølgende.

## Data og eksterne tjenester

Ved generering sendes sidens tekst, titel, URL, prompt, et begrænset katalog af offentlige interne links og eventuelle billedmetadata til OpenAI med `store=false`. Billedfiler og rå besøgstal sendes ikke. API-nøgler og Google-tokens gemmes krypteret i WordPress-databasen med en nøgle afledt af WordPress' salts; saltændring kræver ny opsætning.

Valutakurser hentes via Frankfurter/ECB uden ekstra nøgle. Google bruges kun efter opsætning. Se [readme.txt](readme.txt) for endpoints, begrænsninger, hooks og den detaljerede brugervejledning.

## Teststatus

1.4.0 har bestået **76 regressionstjek og 107 WordPress-integrationstjek** i det lokale testmiljø. OpenAI- og Google-svar var simulerede; dette er ikke en evaluering af live-modelkvalitet eller en Elementor-test. En offentlig valutakurs er også afprøvet gennem WordPress. Se [TESTING.md](TESTING.md).

## Licens

GNU GPL **version 2 eller senere** (`GPL-2.0-or-later`). Se [LICENSE](LICENSE).
