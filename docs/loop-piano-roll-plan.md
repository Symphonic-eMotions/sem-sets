# Plan van aanpak: Piano Roll Loop Editor

## Voortgang

✅ **Fase 4 (Basis)** — Piano roll canvas renderer, drag & drop tape-offset werkt (begin van loop goed instelbaar)  
⏳ **Fase 4 (Uitbreiding)** — Loop-lengte aanpassing in tellen (einde van loop instelbaar)  
⏳ **Fase 4 (Integratie)** — Piano roll + Tone.js player samen bij MIDI files interface plaatsen  
⏳ **Fase 5** — Loop naar MIDI-bestand kopieren (export-functie)  
⏳ **Fase 6** — Loops aaneenschakelen in geëxporteerd bestand

---

## Feature omschrijving

Op de Set (Document) Edit pagina, in het **Maten & Loops** gedeelte van elke track, wordt naast de bestaande play-knop per loop een **edit-knop** toegevoegd. Deze opent een modal met een **custom piano roll** weergave.

In de piano roll geldt de **tape-metafoor**: de loop is een venster waarvan het **begin** vast staat (d.m.v. drag & drop van de tape) en de **lengte** aanpasbaar is. De gehele MIDI-inhoud schuift als een tape onderdoor. 

- **Begin instelbaar**: door de tape te slepen verschuif je het startpunt van de loop (gekwantiseerd op kwartnoten)
- **Lengte instelbaar**: door de rechterkant van het loop-venster te slepen of rechtstreeks in tellen in te voeren, bepaal je waar de loop eindigt

De beweging is gekwantiseerd op kwartnoten.

```
[MIDI tape: ████████████████████████████████████████████]
                    ┌──────────────┐
                    │  LOOP VENSTER│  ← positie op scherm
                    │  (7 maten)   │
                    └──────────────┘
           ◄─ sleep tape (begin) ►  ◄─ sleep rechterkant (einde/lengte) ►
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
      <div class="loop-modal-info">
        <span class="loop-modal-offset-display">Start: maat 1, tel 1</span>
        <label>Lengte (tellen): 
          <input type="number" class="js-loop-length-input" value="48" min="1">
        </label>
      </div>
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
- **Begin slepen** (tape): `mousedown` op canvas-achtergrond + bewegen → tape verschuift, loop-venster-begin verplaatst zich
- **Lengte stepper**: +/− knoppen en invoerveld in de header voor loop-lengte in tellen (kwartnoten)
  - Invoer bijwerkt canvas realtime
  - Min 1 tel, geen maximaal
- **Snapping**: beweging gekwantiseerd op 1 kwartnoot (= breedte van 1 kwartnoot in pixels)
- **Context**: noten 4 maten voor en na het loop-venster zichtbaar (gedempt)
- **Keyboard**: `←` / `→` voor verschuiving tape per kwartnoot
- **Offset display**: `"Start: maat 3, tel 2 | Lengte: 48 tellen"` realtime bijwerken in modal-header

#### Data laden
Hergebruik van de bestaande MIDI-fetch en parse logica uit `midiLoopPlayback.js` (of gedeelde helper extraheren).

---

### Fase 5 — Loop naar MIDI-bestand kopieren

**Doel:** Vanuit de piano roll loop-editor een geselecteerde loop naar een (nieuw of bestaand) MIDI-bestand exporteren.

**UI in modal:**
- Export-knop in footer: "Loop kopiëren naar bestand"
- Dialog voor bestandskeuze:
  - Nieuw bestand aanmaken (invoer voor bestandsnaam)
  - OF bestaand MIDI-bestand selecteren

**Logica:**
- Extract notaten uit originele MIDI-file binnen het offset + length bereik
- Schrijf notaten naar nieuw bestand (of voeg toe aan bestaand)
- Omdat loops gekwantiseerd zijn op tellen, klopt de maat automatisch in het nieuwe bestand
- Returneer link/ID van het nieuwe/gewijzigde MIDI-bestand

**Betrokken:**
- `public/js/loopPianoRoll.js` — export-functie, bestandskeuze-dialog
- Backend API-endpoint: `POST /api/midi/export-loop` met offset, length, target-file

---

### Fase 6 — Loops aaneenschakelen in geëxporteerd bestand

**Doel:** Meerdere loops kunnen achter elkaar aan het geëxporteerde bestand worden toegevoegd.

**Workflow:**
1. Eerste loop kopiëren naar nieuw bestand (Fase 5)
2. Volgende loop uit dezelfde/andere track selecteren
3. "Toevoegen aan [huidge bestand]" optie in export-dialog
4. Loop wordt achter de vorige loop geplakt (offset = einde vorige loop)

**Betrokken:**
- Backend API: uitbreiden om append-logica te ondersteunen
- Frontend: geselecteerde bestand in sessie onthouden/tonen

---

## Volgorde van implementatie

1. **Data model** (entity + controller) — fundament voor alles ✅
2. **Edit knop** in template — zichtbaar, nog niet functioneel ✅
3. **Modal + basis canvas** — venster opent, toont lege piano roll ✅
4. **MIDI data laden** in piano roll — noten zichtbaar, geen interactie ✅
5. **Slepen tape (begin)** — offset aanpassen via drag & drop ✅
6. **Lengte-aanpassing met stepper** — stepper met +/- knoppen en invoerveld voor loop-lengte in tellen
7. **Export-functie** — geselecteerde loop kopiëren naar nieuw/bestaand MIDI-bestand
8. **Aaneenschakelen** — meerdere loops achter elkaar toevoegen
9. **Integratie MIDI files** — piano roll + Tone.js player samen op interface
10. **Polish** — context-dimming, keyboard shortcuts, offset display

---

## Fase 7 — Integratie met MIDI files interface & Tone.js player

**Doel:** Piano roll + Tone.js player samengetrokken in de MIDI files interface (niet in modal gescheiden).

**Bestanden:**
- `templates/Document/midi-files.html.twig` — Piano roll canvas (niet in modal) integreren naast Tone.js player
- `public/js/loopPianoRoll.js` — Aanpassen voor standalone canvas (niet-modal versie)
- CSS voor layout: canvas links, playback controls rechts

**Wijzigingen:**
- Loop-lengte invoerveld blijft beschikbaar voor directe aanpassing
- Drag & drop tape-offset blijft werken (loop-begin)
- Rechterkant-slepen of lengte-invoer voor loop-einde aanpassingen
- Tone.js playback kan direct uit de piano roll gebruikt worden

---

## Open punten / beslissingen

- Worden overlappende loops toegestaan (twee loops die hetzelfde MIDI-gebied bevatten)? Vooralsnog: ja, geen validatie.
- Eenheid voor opslag: kwartnoten zijn gekozen boven MIDI ticks voor leesbaarheid in de API. Bij omrekening geldt: `ticks = quarterNotes * ticksPerQuarterNote` (uit MIDI header).
- Export-logica: notaten worden geëxtraheerd op basis van offset + length en weggeschreven; maat/time signature wordt automatisch correct omdat loops gekwantiseerd zijn.
- Aaneenschakelen: loops worden achter elkaar geplakt met offset = einde vorige loop, geen overlap of gaten.
- Integratie MIDI files interface: canvas wordt niet-modal versie, integreert met bestaande Tone.js player UI.
