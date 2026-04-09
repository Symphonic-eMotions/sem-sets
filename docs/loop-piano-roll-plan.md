# Plan van aanpak: Piano Roll Loop Editor

## Feature omschrijving

Op de Set (Document) Edit pagina, in het **Maten & Loops** gedeelte van elke track, wordt naast de bestaande play-knop per loop een **edit-knop** toegevoegd. Deze opent een modal met een **custom piano roll** weergave.

In de piano roll geldt de **tape-metafoor**: de loop is een vast venster met een vaste lengte (zoals ingesteld in de loop editor), en de gehele MIDI-inhoud schuift er als een tape onderdoor. Door de tape te verschuiven bepaal je welk deel van de MIDI-file de loop bevat. De beweging is gekwantiseerd op kwartnoten.

```
[MIDI tape: ████████████████████████████████████████████]
                    ┌──────────────┐
                    │  LOOP VENSTER│  ← vaste positie & lengte op scherm
                    │  (7 maten)   │
                    └──────────────┘
           ◄── sleep tape links/rechts ──►
                kwartnoot-gekwantiseerd
```

Nieuwe noten bedenken is **niet** mogelijk — je selecteert alleen welk deel van de bestaande MIDI-file in de loop zit.

---

## Data model wijziging

### Huidig formaat (alleen lengtes, impliciet aaneengesloten)
```json
[48, 48, 32]
```

### Nieuw formaat (offset + lengte per loop, eenheden: kwartnoten)
```json
[
  { "offset": 0,   "length": 48 },
  { "offset": 96,  "length": 48 },
  { "offset": 32,  "length": 32 }
]
```

Loops zijn nu volledig onafhankelijk van elkaar — geen verplichting meer dat ze aaneensluiten.

**Eenheden:** kwartnoten (quarter notes), zodat de ontvangende API er onafhankelijk van BPM en MIDI ticks mee kan rekenen.

**Backwards compatibility:** bestaande `[48, 48, 32]` arrays worden bij inladen automatisch omgezet naar het nieuwe formaat met sequentiële offsets (`0`, `48`, `96`).

---

## Betrokken bestanden

| Bestand | Wijziging |
|---|---|
| `src/Entity/DocumentTrack.php` | Upgrade `loopLength` naar `array[]` (objecten met `offset` en `length`), migratie in `getLoopLength()` |
| `src/Form/DocumentTrackType.php` | Hidden input (bestaande `js-loop-length-raw`) geschikt voor offset-data array |
| `src/Controller/DocumentController.php` | `GET` prefill fixen (via `json_encode`), offset verwerking bij save |
| `src/Service/DocumentPayloadBuilder.php` | JSON output fixen; nieuwe quarter-note array zonder extra bars->beats conversie exporteren |
| `templates/Document/_track_card.html.twig` | Edit knop per loop chip |
| `templates/Document/edit.html.twig` | Modal HTML (eenmalig in de DOM) |
| `public/js/loopPianoRoll.js` | Nieuw — modal logica + canvas renderer |
| `public/js/loopLengthEditor.js` | Offsets bijhouden bij add/remove loops |
| `public/js/midiLoopPlayback.js` | Offset meenemen in starttijd berekening |
| `public/css/` of inline styles | Modal + piano roll styling |

---

## Implementatiefasen

### Fase 1 — Data model & backend

**`src/Entity/DocumentTrack.php`**
- `loopLength` veld migreren: formaat in codebase verandert van `int[]` naar `array[]` (DB blijft json type)
- `getLoopLength()` uitbreiden: Herkent on-the-fly bij het inlezen of er oude maten (bijv. `[48]`) of nieuwe kwartnoten-objecten in staan. Oude maten worden omgezet naar `[{offset, length}]` in kwartnoten (via `maten * getTimeSignatureNumerator()`).
- `setLoopLength()` aanpassen om de objects-array netjes te valideren en weg te schrijven.

**`src/Controller/DocumentController.php`**
- Verwerking van prefill bij `edit` (GET): data inladen met `json_encode()` in plaats van implode-map(intval).
- Verwerking van het nieuwe json formaat bij form save.

**`src/Service/DocumentPayloadBuilder.php`**
- `loopLengthBarsToBeats` uit filter logica halen voor deze track property, aangezien we direct in kwartnoten opslaan.
- Export-JSON updaten zodat object-data direct ondersteund is.

---

### Fase 2 — Edit knop in template

**`templates/Document/_track_card.html.twig`**

Naast elke bestaande `.loop-play-btn` een edit-knop toevoegen:

```html
<button type="button"
        class="loop-edit-btn"
        data-loop-index="{{ loopIndex }}"
        title="Loop bewerken">✎</button>
```

---

### Fase 3 — Modal HTML

**`templates/Document/edit.html.twig`** — eenmalig in de DOM, gevuld via JS:

```html
<div id="loop-piano-roll-modal" class="loop-modal" hidden>
  <div class="loop-modal-backdrop"></div>
  <div class="loop-modal-dialog">
    <header>
      <span class="loop-modal-title">Loop A — Track naam</span>
      <span class="loop-modal-offset-display">Start: maat 1, tel 1</span>
    </header>
    <div class="loop-piano-roll-wrap">
      <canvas id="loop-piano-roll-canvas"></canvas>
    </div>
    <footer>
      <button class="js-loop-modal-cancel">Annuleren</button>
      <button class="js-loop-modal-save">Opslaan</button>
    </footer>
  </div>
</div>
```

Sluiten via: backdrop-klik, Escape-toets, Annuleren-knop.

---

### Fase 4 — Piano Roll canvas renderer (`loopPianoRoll.js`)

#### Visuele structuur

```
┌─────────────────────────────────────────────────────────────┐
│ C5 │░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │
│ B4 │  ██████      ████       ██████                         │  ← MIDI noten (tape)
│ A4 │        ████████   █████                                │
│ G4 │                                                        │
│ F4 │░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │
│    └──┬─────────────────────────────────────────┬───────────┘
│       │       ← LOOP VENSTER (vast) →           │           │
│       └─────────────────────────────────────────┘           │
│       ▲ begin loop                              ▲ einde loop │
└─────────────────────────────────────────────────────────────┘
```

#### Canvas rendering pipeline
1. Bereken `pixels-per-kwartnoot` (vaste waarde, bijv. 40px)
2. Teken Y-as: piano keys patroon (wit/zwart), bereik gebaseerd op noten in MIDI-file
3. Teken achtergrondgrid: kwartnoot-lijnen (licht), maatlijnen (donker)
4. Teken **loop-venster**: semi-transparante overlay met borders, vaste X-positie op canvas
5. Teken MIDI-noten op positie `x = (noteTime - tapeOffset) * pxPerQuarterNote`
6. Noten **buiten** loop-venster → opacity 30%, **binnen** → 100%

#### Interactie
- **Slepen**: `mousedown` op canvas + bewegen → tape verschuift, loop-venster blijft op vaste positie
- **Snapping**: beweging gekwantiseerd op 1 kwartnoot (= breedte van 1 kwartnoot in pixels)
- **Context**: noten 4 maten voor en na het loop-venster zichtbaar (gedempt)
- **Keyboard**: `←` / `→` voor verschuiving per kwartnoot
- **Offset display**: `"Start: maat 3, tel 2"` realtime bijwerken in modal-header

#### Data laden
Hergebruik van de bestaande MIDI-fetch en parse logica uit `midiLoopPlayback.js` (of gedeelde helper extraheren).

---

### Fase 5 — Playback engine updaten

**`midiLoopPlayback.js`** — `calculateLoopBars()` aanpassen:

```js
// Huidig: start = som van lengtes van alle vorige loops
// Nieuw:  start = loop.offset  (direct uit het nieuwe formaat)
```

---

### Fase 6 — Loop editor JS updaten

**`loopLengthEditor.js`**:
- Bij `+ Loop`: nieuw object `{ offset: <einde vorige loop>, length: <basiswaarde> }` toevoegen
- Bij `– Loop`: laatste object verwijderen
- Bij inladen: oud integer-array formaat automatisch migreren naar object-array

---

## Volgorde van implementatie

1. **Data model** (entity + controller) — fundament voor alles
2. **Edit knop** in template — zichtbaar, nog niet functioneel
3. **Modal + basis canvas** — venster opent, toont lege piano roll
4. **MIDI data laden** in piano roll — noten zichtbaar, geen interactie
5. **Slepen + snapping** — kern-interactie
6. **Opslaan** — offset terugschrijven naar hidden input
7. **Playback updaten** — play knop gebruikt nieuwe offset
8. **Polish** — context-dimming, keyboard shortcuts, offset display

---

## Open punten / beslissingen

- Worden overlappende loops toegestaan (twee loops die hetzelfde MIDI-gebied bevatten)? Vooralsnog: ja, geen validatie.
- Eenheid voor opslag: kwartnoten zijn gekozen boven MIDI ticks voor leesbaarheid in de API. Bij omrekening geldt: `ticks = quarterNotes * ticksPerQuarterNote` (uit MIDI header).
- De ontvangende app moet het nieuwe `{ offset, length }` formaat nog gaan interpreteren — dat is een separate stap.
