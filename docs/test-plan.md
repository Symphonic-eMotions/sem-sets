# Testplan Sem Sets

## Doel

Een teststrategie opzetten die snel regressies vangt in:

- domeinlogica en MIDI/payload-bouwers
- Symfony controllers, forms, security en Doctrine-integratie
- JavaScript-gedrag in het document-edit scherm
- end-to-end gebruikersflows in de browser
- public API- en exportcontracten

Dit plan is afgestemd op de huidige codebase:

- Symfony 6.4 met Doctrine en Twig
- bestaande PHPUnit + BrowserKit setup
- Docker Compose met `database` en `mailer`
- JavaScript in `public/js` en AssetMapper
- nog geen Playwright- of Node-testsetup aanwezig

## Huidige situatie

Wat er al is:

- PHPUnit is aanwezig via `phpunit/phpunit`
- BrowserKit en CSS Selector zijn aanwezig
- er bestaat 1 controller test in [`tests/Controller/DocumentControllerTest.php`](/Volumes/Storage/Active/GitHub/sem-sets/tests/Controller/DocumentControllerTest.php)

Belangrijkste gaten:

- nauwelijks testdekking op services en domeinlogica
- vrijwel geen coverage op forms, validatie en security
- geen dekking op API-endpoints
- geen dekking op JavaScript in `public/js`
- geen end-to-end tests voor de belangrijkste gebruikersflows
- geen vaste testdata-strategie voor reproduceerbare functionele tests

## Testpiramide

Aanbevolen verdeling:

- 60% unit tests
- 25% Symfony functionele/integratietests
- 15% end-to-end tests met Playwright

Reden:

- de businesslogica zit vooral in services, MIDI-verwerking, payload-opbouw en entity-relaties
- BrowserKit-tests zijn goedkoop en snel voor controllers/forms/security
- Playwright moet selectief worden ingezet op flows waar echte browserinteractie en JavaScript relevant zijn

## Testtypes

### 1. Unit tests

Doel:

- pure logica snel en geïsoleerd testen

Prioriteit hoog voor:

- [`src/Service/EffectConfigExtractor.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/EffectConfigExtractor.php)
- [`src/Service/EffectConfigMerger.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/EffectConfigMerger.php)
- [`src/Service/PayloadBlockFactory.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/PayloadBlockFactory.php)
- [`src/Service/DocumentPayloadBuilder.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/DocumentPayloadBuilder.php)
- [`src/Service/TrackEffectParamChoicesBuilder.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/TrackEffectParamChoicesBuilder.php)
- [`src/Midi/MidiAnalyzer.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Midi/MidiAnalyzer.php)
- [`src/Midi/MidiTrackSplitter.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Midi/MidiTrackSplitter.php)
- [`src/Midi/MidiNoteStaggerer.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Midi/MidiNoteStaggerer.php)

Voorbeelden van assertions:

- payload bevat verwachte blocks en structuur
- merge-logica overschrijft alleen bedoelde velden
- MIDI-analyse levert stabiele uitkomst voor bekende fixture-bestanden
- edge cases met lege arrays, ontbrekende params en out-of-range waarden worden goed afgehandeld

Implementatie:

- maak testfixtures klein en expliciet
- gebruik data providers voor combinaties van effect- en parameterconfiguraties
- vermijd database of kernel boot in unit tests

### 2. Symfony functionele tests

Doel:

- HTTP-laag, routing, security, forms en databasegedrag valideren

Tools:

- PHPUnit
- `WebTestCase`
- BrowserKit
- test database

Scope:

- dashboard en documentroutes
- login en autorisatie
- API endpoints
- file download / export
- form submits met validatie

Concrete suites:

- `tests/Controller/DocumentControllerTest.php`
- `tests/Controller/ApiControllerTest.php`
- `tests/Controller/SecurityControllerTest.php`
- `tests/Controller/SemSymAuthControllerTest.php`
- `tests/Form/DocumentFormTypeTest.php`

Belangrijke scenario's:

- anonieme gebruiker wordt naar login geweigerd op protected routes
- viewer/editor/admin ziet alleen toegestane routes
- nieuw document aanmaken redirect naar edit
- document edit submit bewaart slug, grid en tracks correct
- bundle download retourneert ZIP met correcte headers
- `/api/sets` retourneert alleen gepubliceerde sets
- `/api/sets/{slug}.json` geeft 404 bij ontbrekende head version of file
- `PATCH /api/documents/{id}/tracks/{trackId}/parts/{partId}` valideert input, version conflict en succesvolle update
- `POST /api/sem-sym/auth/login` geeft 400, 401 en 200 in de juiste gevallen

Aanpak voor testdata:

- gebruik fixtures of factories voor `User`, `Document`, `DocumentTrack`, `InstrumentPart`, `PayloadBlock`
- gebruik per test een transaction rollback of database reset
- houd testdata deterministic; geen verborgen afhankelijkheid op handmatige lokale data

### 3. Integratietests voor persistence en storage

Doel:

- interactie met Doctrine, Flysystem en snapshot/exportservices valideren

Prioriteit:

- [`src/Service/DocumentSnapshotService.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/DocumentSnapshotService.php)
- [`src/Service/DocumentExportService.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/DocumentExportService.php)
- [`src/Service/AssetStorage.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/AssetStorage.php)
- repositories met custom querygedrag

Scenario's:

- snapshot creëert nieuwe versie en bewaart verwachte data
- exportservice schrijft JSON/ZIP naar verwachte locaties
- asset-opslag weigert of verwerkt onverwachte bestandsnamen correct
- custom repository methods geven juiste filtering op published/head-versies

Pragmatische keuze:

- gebruik hier liever integratietests dan zware mocking
- test met tijdelijke storage of test-specifieke Flysystem-config

### 4. Browser E2E-tests met Playwright

Doel:

- echte gebruikersflows en JavaScript-gedrag afdekken

Waarom hier nodig:

- het edit-scherm gebruikt client-side logica in onder meer [`public/js/effectsSettings.js`](/Volumes/Storage/Active/GitHub/sem-sets/public/js/effectsSettings.js)
- BrowserKit ziet geen echte browser-events, DOM-updates of netwerkintercepties

Inrichten:

- voeg Node + Playwright toe
- maak een aparte testomgeving met voorspelbare database en storage
- start app en database via Docker of een dedicated test compose-profiel

Aanbevolen eerste Playwright-scenario's:

- login-flow werkt voor geldige gebruiker
- document aanmaken via UI en redirect naar edit
- document edit: tracks/effecten aanpassen en opslaan
- dynamic parameter dropdowns worden gevuld op basis van geselecteerde effectdata
- ramp/override sliders wijzigen hidden/state velden correct
- bundle of JSON-export kan vanuit UI worden gestart
- foutmeldingen en validatieberichten zijn zichtbaar bij ongeldige input

Specifieke browserchecks:

- selectors stabiel maken met `data-testid`
- file upload flows testen
- download assertions gebruiken voor ZIP-export
- API-calls rond patch/save kunnen worden geobserveerd of gestubd waar nuttig

Advies:

- gebruik Playwright niet voor alle combinatoriek
- beperk E2E tot de kritieke happy paths en een paar risicovolle foutpaden

### 5. API contracttests

Doel:

- voorkomen dat consumers van `/api` onverwacht breken

Relevant voor:

- [`src/Controller/ApiController.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Controller/ApiController.php)
- [`src/Controller/SemSymAuthController.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Controller/SemSymAuthController.php)

Aanpak:

- assert op response status, content type en JSON-structuur
- leg minimale contractvelden vast
- voeg regressietests toe voor foutresponses en conflictgevallen

Extra suggestie:

- overweeg JSON schema assertions voor publieke responses

### 6. Smoke tests / deployment checks

Doel:

- snel vaststellen dat een deployment fundamenteel werkt

Minimale checks:

- app start zonder fatal errors
- databaseverbinding werkt
- homepage geeft geen 500
- loginpagina laadt
- `/api/sets` geeft geldige JSON

Deze tests mogen klein zijn maar moeten in CI altijd draaien.

## Omgevingen en tooling

### PHPUnit backend

Aanbevolen toevoegingen:

- aparte `.env.test`
- test database, los van development data
- helper voor fixture loading of factories

Sterke suggestie:

- voeg Foundry toe voor factories en scenario's
- alternatief: Doctrine fixtures specifiek voor tests

### Playwright frontend/E2E

Aanbevolen toevoegingen:

- `package.json`
- `@playwright/test`
- `playwright.config.*`
- `tests/e2e/`

Aanbevolen commando's:

- `npm run test:e2e`
- `npm run test:e2e:ui`
- `npm run test:e2e:headed`

Browsermatrix:

- start met Chromium
- voeg later Firefox/WebKit toe als regressies of doelgroep dat rechtvaardigen

## Datamanagement

Een zwakke teststrategie valt meestal op onbetrouwbare testdata. Dit project heeft daarom baat bij:

- factories voor users, documents, tracks, parts en payload blocks
- vaste MIDI testbestanden in een aparte `tests/Fixtures/` map
- tijdelijke storage per test-run
- expliciete cleanup van uploads/exports

Vermijd:

- hergebruik van development database
- afhankelijkheid op handmatig aangemaakte gebruikers
- tests die falen door volgorde-afhankelijkheid

## Aanbevolen fasering

### Fase 1: basis op orde

- testmappen structureren per type
- `.env.test` en test database inrichten
- factories of test fixtures toevoegen
- bestaande `DocumentControllerTest` opschonen en uitbreiden
- CI-commando voor PHPUnit toevoegen

Resultaat:

- betrouwbare backend testbasis

### Fase 2: domein- en servicecoverage

- unit tests voor payload-, versioning- en MIDI-services
- integratietests voor snapshot/export/storage
- API tests voor read/write endpoints

Resultaat:

- meeste regressies worden snel en goedkoop gevonden

### Fase 3: Playwright inrichten

- Node/Playwright installeren
- app-start voor E2E standaardiseren
- `data-testid` toevoegen waar selectors nu te fragiel zijn
- eerste 3 tot 5 kritieke browserflows automatiseren

Resultaat:

- vertrouwen in echte gebruikersflows en JavaScript-interactie

### Fase 4: hardening

- extra foutpaden toevoegen
- smoke tests in CI afdwingen
- coverage meten en gaten gericht dichten

Resultaat:

- stabiele regressiebescherming zonder overmatige onderhoudslast

## Concrete eerste backlog

Aanbevolen eerste tickets:

1. Testinfrastructuur voor `.env.test`, database reset en fixtures/factories opzetten.
2. Bestaande controller test herschrijven naar een kleine, betrouwbare baseline zonder verborgen aannames.
3. Unit tests toevoegen voor payload-builder en MIDI-services.
4. Functionele tests toevoegen voor login, document create/edit en API `/api/sets`.
5. Playwright installeren en een eerste end-to-end flow voor login plus document aanmaken bouwen.
6. Selectors in Twig/templates stabiliseren met `data-testid`.
7. ZIP-export en patch-endpoint regression tests toevoegen.

## CI-advies

Minimale CI-pipeline:

- `composer validate`
- `php bin/phpunit`
- Playwright smoke subset op pull requests
- volledige Playwright-suite op main of nightly

Sterke extra suggesties:

- `phpstan` toevoegen voor statische analyse
- `php-cs-fixer` of vergelijkbare formatter/linter toevoegen

Deze tools vervangen geen tests, maar halen wel veel goedkope fouten vroeg naar voren.

## Extra testtypes die ik aanraad

Naast unit, functioneel en Playwright:

- statische analyse: goed rendement voor Symfony/Doctrine code
- repository/integratietests: omdat veel risico in persistence en querygedrag zit
- contracttests voor publieke API-responses
- smoke tests voor deployment

Optioneel later:

- performance smoke test op zware export- of MIDI-routes
- mutation testing op kritieke pure services als de unit testbasis volwassen is

## Praktische uitgangspunten

- test gedrag, niet implementatiedetails
- mock alleen externe grenzen; niet de hele applicatie doodmocken
- houd Playwright-scenarios klein en taakgericht
- maak bugs eerst reproduceerbaar in een test en fix daarna de code
- elke bugfix op controller/service/API-niveau hoort idealiter een regressietest mee te krijgen

## Succescriteria

Dit plan is geslaagd als:

- ontwikkelaars lokaal met 1 commando backend tests kunnen draaien
- kritieke browserflows reproduceerbaar automatisch getest worden
- regressies in export, payload-opbouw en security snel worden gedetecteerd
- testdata stabiel en voorspelbaar is
- CI duidelijk onderscheid maakt tussen snelle feedback en zwaardere E2E-validatie
