# Plan van Aanpak: Track/Instrument Volume implementatie

## Context
De huidige codebase ondersteunt al een uitgebreid systeem voor het opslaan en serialiseren van track configuraties via DocumentTrack entities. Volume is echter nog niet geïmplementeerd. Dit plan voorziet in het toevoegen van volume support op track/instrument niveau met een dB-schaal.

Volume zal opgeslagen worden als een numerieke waarde per track en via de API beschikbaar gesteld worden.

Terminologie:
- In de Symfony codebase heet dit een `DocumentTrack`
- In de consumer van de JSON heet dit een `instrument`
- In dit plan worden "track" en "instrument" dus als uitwisselbare termen gebruikt

---

## Aandachtspunten

### dB Schaal
- **dB = 20 log₁₀(amplitude)**
- Bereik: -90 dB (stil) tot 12 dB (max)
- UI moet waardes accepteren als -90 tot 12, opgeslagen als float
- Alle invoer wordt defensief geclamped naar `-90 .. 12`
- Eventuele validator mag dit bereik ook expliciet afdwingen, maar de entity setter is de fail-safe grens

---

## Implementatieplan

### **Stap 1: Database Migratie**
**File:** `migrations/Version20260401XXXXXX.php` (nieuw)

Voeg `track_volume` kolom toe aan `document_tracks` tabel:

```sql
ALTER TABLE document_tracks ADD track_volume FLOAT DEFAULT 0 COMMENT 'Volume in dB (-90 to 12)';
```

**Details:**
- Kolom type: `FLOAT` (flexibiliteit voor dB values)
- Default: `0` (geen volumeverandering)
- Bereik: -90 tot 12 dB (via validator)
- Comment: dB bereik voor documentatie

**Belangrijk:**
- Er bestaat al een lokale migratie `migrations/Version20260401163548.php` voor `tone_preset`
- Voeg volume toe in een nieuwe migratie of werk alleen verder op een nog niet gepushte migratie als dat bewust is afgestemd
- Niet blind opnieuw genereren zonder eerst de bestaande migraties te controleren

**Genereer migratie:** `php bin/console doctrine:migrations:generate`

---

### **Stap 2: Entity uitbreiden (DocumentTrack)**
**File:** `src/Entity/DocumentTrack.php`

Voeg getter/setter toe:

```php
#[ORM\Column(type: 'float', options: ['default' => 0])]
private float $trackVolume = 0;

public function getTrackVolume(): float
{
    return $this->trackVolume;
}

public function setTrackVolume(float $volume): self
{
    // Fail-safe clamp; form/API mogen daarnaast ook valideren
    $this->trackVolume = max(-90, min(12, $volume));
    return $this;
}
```

**Locatie in file:** Voeg toe na `tonePreset` property (rond regel 55)

---

### **Stap 3: Form Field toevoegen**
**File:** `src/Form/DocumentTrackType.php`

Voeg volume field toe in `buildForm()` method (na tonePreset, rond regel 127):

```php
->add('trackVolume', NumberType::class, [
    'label'    => 'Volume (dB)',
    'required' => false,
    'mapped'   => true,
    'html5'    => true,
    'attr'     => [
        'class'  => 'js-track-volume-input',
        'min'    => -90,
        'max'    => 12,
        'step'   => 0.1,
        'type'   => 'range',  // slider in HTML5
    ],
    'constraints' => [
        new Assert\Range(
            min: -90,
            max: 12,
            notInRangeMessage: 'Volume must be between -90 and 12 dB'
        ),
    ],
])
```

**Gedrag:**
- Frontend min/max/step zijn UX-hints
- Form constraint geeft nette validatiefout
- `DocumentTrack::setTrackVolume()` blijft de laatste fail-safe en clamped altijd naar `-90 .. 12`

---

### **Stap 4: Template UI Update**
**File:** `templates/Document/_track_card.html.twig`

Voeg volume slider toe na Tone preset, rond regel 214:

```twig
<div class="subsection">
    <label class="label">Volume</label>
    <div class="volume-control">
        {{ form_widget(trackForm.trackVolume, {
            attr: trackForm.trackVolume.vars.attr|merge({
                class: ((trackForm.trackVolume.vars.attr.class|default('') ~ ' track-volume-slider')|trim)
            })
        }) }}
        <span class="volume-value" data-input="{{ trackForm.trackVolume.vars.id }}">
            {{ trackForm.trackVolume.vars.value ?? 0 }} dB
        </span>
    </div>
</div>
```

**Opmerking:**
- Styling valt buiten scope van deze wijziging; functionele rendering is voldoende

**JavaScript toevoegen** (voor real-time value display):
```javascript
function bindTrackVolumeInput(root = document) {
    root.querySelectorAll('.track-volume-slider').forEach((input) => {
        if (input.dataset.volumeBound === '1') {
            return;
        }

        input.dataset.volumeBound = '1';

        const valueDisplay = input.parentElement.querySelector('.volume-value');
        if (!valueDisplay) {
            return;
        }

        const sync = () => {
            valueDisplay.textContent = `${input.value} dB`;
        };

        input.addEventListener('input', sync);
        sync();
    });
}

bindTrackVolumeInput(document);

// Belangrijk: ook opnieuw aanroepen nadat een nieuwe track-card/prototype is toegevoegd
// Bijvoorbeeld in bestaande addTrack()-flow of na DOM insert van een nieuwe card
```

**Belangrijk:**
- `_track_card.html.twig` wordt ook als prototype gebruikt
- De slider initialisatie moet dus ook werken voor dynamisch toegevoegde tracks, niet alleen bij page load
- Gebruik daarom herbruikbare initialisatie of event delegation; geen eenmalige init zonder herbinding

---

### **Stap 5: Payload Blocks Default Value Update**
**File:** Database (via migration of SQL update)

Update de `payload_blocks` tabel waar `name = 'niveauTrack'`:

Het huidige `payload` JSON bevat:
```json
{"midiGroup":[],"muted":false,"noteNumbersClips":[],"noteSource":"midiFile",...,"instrumentVolume":1}
```

Dit moet verwijderd worden (de `instrumentVolume` key), omdat deze nu per track wordt opgeslagen in DocumentTrack entity.

**Via migratie (recommended, fail-safe):**
```php
$row = $this->connection->fetchAssociative(
    "SELECT id, payload FROM payload_blocks WHERE name = 'niveauTrack' LIMIT 1"
);

if ($row) {
    $payloadRaw = $row['payload'] ?? null;

    if (is_string($payloadRaw) && trim($payloadRaw) !== '') {
        $decoded = json_decode($payloadRaw, true);

        if (is_array($decoded) && array_key_exists('instrumentVolume', $decoded)) {
            unset($decoded['instrumentVolume']);

            $json = json_encode(
                $decoded,
                JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            );

            $this->addSql(
                "UPDATE payload_blocks SET payload = :payload WHERE id = :id",
                [
                    'payload' => $json,
                    'id' => (int) $row['id'],
                ]
            );
        }
    }
}
```

**Details:**
- Gebruik `payload`, niet `config`
- Haal eerst de data op, decodeer JSON, wijzig gericht de ene key, en schrijf het resultaat terug
- Dit is veiliger en voorspelbaarder op productie dan vertrouwen op DB-specifieke JSON-functies
- Verwijdert alleen de `instrumentVolume` key uit het niveauTrack default template
- Andere keys in de JSON blijven intact
- Dit voorkomt conflict tussen template default en per-track volume

---

### **Stap 6: JSON Serialisatie (DocumentPayloadBuilder)**
**File:** `src/Service/DocumentPayloadBuilder.php`

In `buildPayloadJson()` method, voeg volume toe aan track config (rond regel 100):

```php
$trackConfig = [
    'onlineTrackId'  => $t->getId(),
    'trackId'        => $t->getTrackId(),
    'levels'         => $levels,
    'midiFiles'      => $midi,
    'trackVolume'    => (float) $t->getTrackVolume(),  // ← nieuw
    // ... rest van track config
];
```

**Locatie:** Voeg toe in de track loop waar andere track-level properties worden toegekend.

**Belangrijk:**
- In de huidige implementatie wordt `niveauTrack` eerst als defaults geladen en daarna overschreven met track-specifieke waarden via `array_merge`
- `trackVolume` moet dus expliciet in `$trackConfig` staan zodat het altijd de default overschrijft
- Na deze wijziging moet `instrumentVolume` niet meer uit `niveauTrack` komen

---

### **Stap 7: Controllers updaten**

#### **DocumentController** (`src/Controller/DocumentController.php`)
Geen wijzigingen nodig - form handling is al voorzien door DocumentTrackType.

#### **ApiController** (`src/Controller/ApiController.php`)
**Optional enhancement:** Als je PATCH endpoint wilt uitbreiden voor volume updates:

```php
// In patchPartRamp method, na versie check:
$trackVolume = $data['trackVolume'] ?? null;
if ($trackVolume !== null) {
    if (!is_numeric($trackVolume)) {
        return $this->json(['error' => 'Invalid volume range (-90 to 12 dB)'], 422);
    }

    // Zelfde defensieve gedrag als de entity setter
    $track->setTrackVolume((float) $trackVolume);
}
```

**Keuze:**
- We clampen overal naar `-90 .. 12`
- API mag non-numeric input afwijzen
- Numeric input buiten bereik wordt door de setter veilig begrensd
- Deze stap blijft optioneel, want de bestaande read-only set JSON export werkt al via snapshots

---

## Verificatie & Testing

### **1. Database**
```bash
# Voer migratie uit
php bin/console doctrine:migrations:migrate

# Verifieer kolom
php bin/console doctrine:schema:validate
```

### **2. Entity & Form**
- Bewaar een Document via UI → controleer trackVolume in database
- Laad bestaand Document → volume veld is zichtbaar
- Pas volume aan → form accepteert waarden in het dB-bereik
- Voer bewust ook een grenswaarde buiten bereik in en controleer dat de uiteindelijke opgeslagen waarde geclamped is

### **3. JSON Output**
```bash
# Check API response
curl http://localhost:8000/api/sets/my-set.json | jq '.instrumentsConfig[0].trackVolume'
# Should return: 0 (or modified value)
```

### **4. Snapshot Versioning**
- Maak volume wijziging
- Verifieer dat nieuwe snapshot gemaakt wordt
- Check dat `sets/{docId}/latest/document.json` trackVolume bevat
- Version number incremented

### **5. Form Rendering**
- Controleer dat slider zichtbaar is in track card
- Waarde display werkt real-time
- Min/max constraints afdwingen (HTML5 + backend validator)
- Voeg een nieuwe track toe in de UI en controleer dat de slider daar ook correct werkt

---

## Onderliggende Architecture (ter referentie)

### Data Flow
```
UI Form Input
  ↓
DocumentTrackType form validation
  ↓
DocumentTrack::setTrackVolume() (clamping)
  ↓
Doctrine persists to database
  ↓
DocumentSnapshotService creates snapshot
  ↓
DocumentPayloadBuilder reads trackVolume from entity
  ↓
JSON serialization with trackVolume field
  ↓
API endpoint serves JSON with trackVolume
```

### JSON Example
```json
{
  "onlineTrackId": 12,
  "trackId": "01KMXP1MJVJKZQ9NAYKB9Y1XP5",
  "levels": [0, 1],
  "trackVolume": -6.5,
  "midiFiles": [...],
  "instrumentParts": [...]
}
```

---

## Bestanden die Gewijzigd/Aangemaakt Moeten Worden

| Bestand | Type | Actie |
|---------|------|-------|
| `migrations/Version20260401XXXXXX.php` | Nieuw | Migratie: kolom + payload_blocks update |
| `src/Entity/DocumentTrack.php` | Wijzig | Property + getter/setter |
| `src/Form/DocumentTrackType.php` | Wijzig | Form field toevoegen |
| `templates/Document/_track_card.html.twig` | Wijzig | UI slider + value display |
| `src/Service/DocumentPayloadBuilder.php` | Wijzig | JSON serialisatie |
| `src/Controller/ApiController.php` | Optioneel | PATCH enhancement |

---

## Opmerkingen

1. **Validator constraints** zijn reeds voorzien in FormType
2. **Clamping** gebeurt fail-safe in `setTrackVolume()` method
3. **Default value** is 0 dB (geen volume verandering)
4. **Snapshot versioning** werkt automatisch via DocumentSnapshotService
5. **API response** bevat trackVolume zonder extra wijzigingen nodig in ApiController
6. **UI scaling** is HTML5 native (range input) met dB weergave
7. **Prototype support** moet expliciet meegenomen worden in de JS initialisatie

---

## Potentiële Uitbreidingen (voor later)

- Track volume fade/automation (keyframes)
- Master volume/group volume
- Volume UI dB visualisatie (decibel meter)
- Volume presets
