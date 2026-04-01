# Tone.js Presets Plan

## Context

De huidige browser-preview van de Document Track Loop Player gebruikt Tone.js met één vaste klank:

- `Tone.PolySynth(Tone.Synth)`
- `triangle` oscillator
- een eenvoudige amp ADSR-envelope

Dat zit in [`public/js/midiLoopPlayback.js`](/Volumes/Storage/Active/GitHub/sem-sets/public/js/midiLoopPlayback.js).

Daarnaast bestaat er al een apart sample-based/export-pad via `exsPreset` en `exsSampler` in onder meer:

- [`src/Form/DocumentTrackType.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Form/DocumentTrackType.php)
- [`src/Service/DocumentPayloadBuilder.php`](/Volumes/Storage/Active/GitHub/sem-sets/src/Service/DocumentPayloadBuilder.php)

Daarmee zijn er functioneel twee lagen:

1. Tone.js synth-preview in de browser
2. EXS/sample-based instrumentconfiguratie voor export/payload

## Doel

Een reeks meer diverse presets toevoegen voor de Tone.js loop preview, zodat de preview minder eenzijdig en minder uitgesproken FM-achtig klinkt. Daarbij variëren we expliciet op:

- amp ADSR
- pitch-karakter
- filter/filter-envelope waar relevant
- synth-engine type

Later kan eventueel een sample-based browser-preview met `Tone.Sampler` volgen.

## Plan Van Aanpak

### 1. Tone.js presetlaag definiëren

Een kleine preset-catalogus maken voor de previewspeler met verschillende klankfamilies, bijvoorbeeld:

- soft pad
- plucky keys
- warm analog
- hollow organ
- percussive bell
- bass mono
- airy lead
- noisy texture

Per preset leggen we minimaal vast:

- `engineType` zoals `Synth`, `AMSynth`, `FMSynth`, `MonoSynth`
- oscillator-configuratie
- amp envelope
- filter en `filterEnvelope` waar relevant
- pitch/modulatieparameters zoals `detune`, `harmonicity`, `modulationIndex` of `portamento`

> **Let op:** `DuoSynth` is niet bruikbaar als engine-optie binnen `Tone.PolySynth` — dit geeft een runtime-fout. `MonoSynth` werkt wel in `PolySynth`, maar klinkt anders dan verwacht omdat polyfonie dan meerdere monofonische stemmen stapelt.

### 2. Player architectuur preset-gedreven maken

De huidige hardcoded synth-opbouw in `midiLoopPlayback.js` (regel 30-42) vervangen door een factory of preset-resolver die op basis van een preset-id de juiste `Tone.PolySynth`-instantie bouwt.

Aandachtspunten bij de refactor:

- De huidige `isInitialized` flag blokkeert herinitialisatie — dit moet vervangen worden door een controle op de actieve preset-id, zodat de synth alleen opnieuw gebouwd wordt bij een presetwissel.
- Bij een presetwissel moet de oude synth gedisposed worden (`this.synth.dispose()`) vóór de nieuwe aangemaakt wordt.
- `stopPlayback()` gebruikt nu `this.synth.triggerRelease()`. Voor `PolySynth` is `releaseAll()` correcter — zeker bij pad-presets met lange release, om ophoping van noten bij stoppen te voorkomen.

Voordelen van de factory-aanpak:

- nieuwe presets kunnen later zonder grote refactor toegevoegd worden
- verschillende synth-types worden netjes afgevangen
- de afspeellogica blijft gescheiden van de klankdefinitie

### 3. Presets koppelen aan tracks in UI en data

Er zijn twee implementatieniveaus:

- preview-only: alleen een selector voor de browser-preview
- structureel: preset opslaan op `DocumentTrack`

Voorkeur:

De preset structureel opslaan op `DocumentTrack`, zodat de gekozen previewklank per track behouden blijft na refresh of heropenen van het document.

**Implementatiedetails:**

- Nieuw veld `tonePreset` toevoegen aan `DocumentTrack` entity (string, nullable) — zelfde patroon als het bestaande `exsPreset` veld.
- Getter/setter toevoegen en een Doctrine migration genereren en uitvoeren.
- `DocumentTrackType.php` uitbreiden met een `ChoiceType` voor `tonePreset`, analoog aan het bestaande `exsPreset` veld (regel 111-117).
- `templates/Document/_track_card.html.twig` aanpassen:
  - Preset-widget renderen (bij voorkeur naast de EXS preset sectie rond regel 204-207).
  - `data-tone-preset` attribuut toevoegen aan de `loop-editor` div (regel 144-152), zodat `midiLoopPreview.js` de waarde bij het afspelen kan lezen.
- `midiLoopPreview.js` aanpassen: preset-waarde uitlezen en meegeven bij de `playLoopSegment()`-aanroep.
- `playLoopSegment()` in `midiLoopPlayback.js` een `presetId` parameter geven die vóór het afspelen de juiste synth instantie selecteert of bouwt.

**Singleton lifecycle:**

In `midiLoopPreview.js` is één globale `playbackManager` voor alle tracks. Bij een presetwissel (andere track of andere preset) moet de playback gestopt en de synth vervangen worden vóór de nieuwe playback start.

### 4. Sample-based pad als aparte presetfamilie behandelen

De bestaande `exsPreset`/`exsSampler` configuratie niet vermengen met Tone synth presets, maar als aparte categorie blijven behandelen:

- browser preview synth presets
- sample/exs presets

Als echte sample-preview in de browser gewenst is, kan daar later een extra stap voor volgen met `Tone.Sampler` en bijbehorende sample-mapping.

### 5. Eerste presetset implementeren en balanceren

Starten met ongeveer 8 presets:

- 2 percussieve presets
- 2 sustain/pad presets
- 2 lead/bass presets
- 2 experimentele of karakter-presets

Belangrijke variatiepunten:

- korte vs. lange attack
- korte vs. lange release
- heldere vs. warme klank
- stabiele vs. bewegende filterrespons
- strak vs. zwevend pitch-karakter

### 6. Verifiëren in de loop preview

Controleren of:

- presets zonder klik of artefacts starten
- langere releases niet storend in de loop ophopen (aandacht voor `releaseAll()` bij stoppen)
- polyfonie beheersbaar blijft
- BPM en loopgedrag correct blijven
- preset correct bewaard blijft na formulieropslag en refresh

## Concrete Uitvoervolgorde

### Fase 1

1. Presetmodel en synth-factory toevoegen in `midiLoopPlayback.js`; `isInitialized`-vlag vervangen door preset-tracking; `triggerRelease()` vervangen door `releaseAll()`
2. Eerste set van circa 8 Tone.js presets definiëren (geen `DuoSynth`)
3. `tonePreset` veld toevoegen aan `DocumentTrack` entity + getter/setter
4. Doctrine migration genereren en uitvoeren (`doctrine:migrations:diff` + `migrate`)
5. `DocumentTrackType.php` uitbreiden met `ChoiceType` voor `tonePreset`
6. `_track_card.html.twig` aanpassen: preset-widget en `data-tone-preset` op de loop-editor div
7. `midiLoopPreview.js` aanpassen: preset uitlezen en doorgeven aan `playLoopSegment()`
8. Previewgedrag testen op bestaande MIDI-loops

### Fase 2

1. Presets inhoudelijk finetunen op basis van luistertest
2. Presets logisch groeperen of labelen in de UI
3. Beoordelen of `Tone.Sampler` voor browser-preview zinvol is

## Verwacht Resultaat

Na deze stap heeft de Document Track Loop Player niet meer één generieke synthklank, maar een kleine bibliotheek aan duidelijke klankvarianten voor preview in de browser. De EXS/sample-based laag blijft daarnaast bruikbaar als afzonderlijk instrumentspoor voor export of verdere playback-ketens.
