# Database Schema & Entity Relationships

## Overview

The Sem Sets database uses **Doctrine ORM** to manage entities and relationships. This document describes each entity, its purpose, key fields, and relationships.

**Database**: MariaDB 10.11
**Language**: PHP with Doctrine Annotations
**Location**: `src/Entity/*.php`

---

## Entity Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                          USER                                   │
│  (id, email, hashedPin, apiKey, instanceId, roles, createdAt)  │
└────────────┬──────────────────────────┬──────────────────────────┘
             │ creates                  │ updates
             │                          │
             ▼                          ▼
       ┌──────────────────────────────────────────┐
       │         DOCUMENT                         │
       │ (id, slug, title, published, setBPM,    │
       │  timeSignature, gridColumns, gridRows,   │
       │  levelDurations, createdAt, updatedAt,   │
       │  headVersion → DocumentVersion)          │
       └──────────────┬─────────────────────┬────┘
                      │ contains            │
                      │                     │
          ┌───────────▼──────────┐  ┌──────▼──────────────┐
          │  DOCUMENT_TRACK      │  │ DOCUMENT_VERSION   │
          │ (id, position,       │  │ (id, versionNum,   │
          │  midiAsset→Asset,    │  │  payload JSON,     │
          │  levels[], loops[])  │  │  changelog, by)    │
          └────┬────────┬────────┘  └────────────────────┘
               │        │
       ┌───────▼──┐  ┌──▼──────────────────┐
       │ ASSET    │  │ DOCUMENT_TRACK_EFF  │
       │ (MIDI)   │  │ (position,          │
       └──────────┘  │  effectSettings)    │
                     └──────────────┬──────┘
                                    │
         ┌──────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  EFFECT_SETTINGS                    │
    │ (id, name, config JSON, keyvalues)  │
    └──────────┬──────────────────────────┘
               │
               │
         ┌─────▼──────────────────────────┐
         │ EFFECT_SETTINGS_KEY_VALUE      │
         │ (id, effectSettings, key, val) │
         └────────────────────────────────┘

                    ┌─────────────────────────────────┐
                    │  INSTRUMENT_PART                │
                    │  (id, partId, track,            │
                    │   areaOfInterest,               │
                    │   loopsToGrid,                  │
                    │   targetType, target param,     │
                    │   rampSpeed, minLevel)          │
                    └─────────────────────────────────┘

                    ┌─────────────────────────────────┐
                    │  PAYLOAD_BLOCK                  │
                    │  (id, name, description,        │
                    │   payload JSON)                 │
                    └─────────────────────────────────┘
```

---

## Entities

### 1. **User**

**Purpose**: Represents application users with authentication and API credentials.

**Table**: `users`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `email` | VARCHAR(180) | No | Yes | User's email (login username) |
| `roles` | JSON | No | - | User roles (e.g., `ROLE_USER`) |
| `hashed_pin` | VARCHAR(255) | No | - | Hashed 6-digit PIN code |
| `api_key` | VARCHAR(255) | Yes | Yes | API key for `sem-sym` integration |
| `instance_id` | VARCHAR(255) | Yes | - | External instance identifier |
| `failed_login_attempts` | SMALLINT | No | - | Login attempt tracking (unsigned, default 0) |
| `created_at` | DATETIME | No | - | User creation timestamp |

**Constraints**:
- Email must be unique (one user per email)
- API key must be unique (if present)
- PIN code must be hashed (never stored plain text)

**Relationships**:
- `1:N` → **Document** (creator, updater)

**Example**:
```php
$user = new User();
$user->setEmail('composer@example.com');
$user->setHashedPin(password_hash('123456', PASSWORD_BCRYPT));
$user->setRoles(['ROLE_USER']);
```

---

### 2. **Document**

**Purpose**: Represents a single musical composition set. The main entity that users create and edit.

**Table**: `documents`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `slug` | VARCHAR(160) | No | Yes | URL-friendly identifier (e.g., `my-set-v1`) |
| `title` | VARCHAR(200) | No | - | Display title |
| `published` | BOOLEAN | No | - | Public/API accessible (default: false) |
| `sem_version` | ENUM (SemVersion) | No | - | Semantic version (e.g., `2.9.0`) |
| `created_by` | INTEGER | Yes | - | FK to User (creator) |
| `updated_by` | INTEGER | Yes | - | FK to User (last editor) |
| `created_at` | DATETIME | No | - | Creation timestamp |
| `updated_at` | DATETIME | No | - | Last update timestamp |
| `head_version_id` | INTEGER | Yes | - | FK to DocumentVersion (current snapshot) |
| `level_durations` | JSON | No | - | Array of integers (duration per level) |
| `grid_columns` | TINYINT | No | - | Grid width (1-4, default: 1) |
| `grid_rows` | TINYINT | No | - | Grid height (1-4, default: 1) |
| `set_bpm` | DECIMAL(5,2) | No | - | Tempo in beats per minute (20.0 - 999.99, default: 90.00) |
| `time_signature` | VARCHAR(8) | No | - | Time signature (e.g., `4/4`, default: `4/4`) |

**Key Concepts**:

- **Grid System**: Defines how many cells the document has
  - Minimum: 1x1, Maximum: 4x4
  - Each cell can contain effect parameters or sequencer parameters

- **Level Durations**: Array of integers representing time segments
  - Example: `[32, 32]` = 2 levels of 32 beats each
  - Defines the temporal structure of the document

- **Head Version**: Points to the current DocumentVersion snapshot
  - Allows tracking which version is currently "active"
  - Can be used to rollback by changing this reference

**Constraints**:
- Slug must be unique (one slug per document)
- Grid dimensions must be 1-4
- BPM must be between 20.0 and 999.99

**Relationships**:
- `N:1` → **User** (created_by, updated_by)
- `1:N` → **DocumentTrack** (tracks, cascade delete)
- `1:N` → **DocumentVersion** (versions)
- `1:1` → **DocumentVersion** (headVersion)

**Lifecycle Hooks**:
- `PrePersist`: Sets `createdAt` and `updatedAt` on first insert
- `PreUpdate`: Updates `updatedAt` on each save

**Example**:
```php
$doc = new Document();
$doc->setTitle('Symphony No. 1');
$doc->setSlug('symphony-1');
$doc->setGridColumns(3);
$doc->setGridRows(3);
$doc->setLevelDurations([16, 16, 16, 16]);
$doc->setSetBPM('120.00');
$doc->setTimeSignature('4/4');
$doc->setPublished(false);
```

---

### 3. **DocumentTrack**

**Purpose**: Represents a single track/instrument within a document. Each track contains a MIDI file and can have effects.

**Table**: `document_tracks`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `document_id` | INTEGER | No | - | FK to Document (parent) |
| `position` | SMALLINT | No | - | Track order in document (unsigned, default: 0) |
| `levels` | JSON | No | - | Array of booleans (active in each level) |
| `loop_length` | JSON | No | - | Array of integers (bars for each loop A, B, C...) |
| `midi_asset_id` | INTEGER | Yes | - | FK to Asset (uploaded MIDI file) |

**Key Concepts**:

- **Levels**: Boolean array indicating which levels this track plays in
  - Example: `[true, false, true, true]` = plays in levels 1, 3, 4

- **Loop Length**: Array of bar counts for different loops
  - Example: `[8, 16]` = Loop A is 8 bars, Loop B is 16 bars
  - Used for MIDI playback variations

**Relationships**:
- `N:1` → **Document** (document, cascade delete on parent)
- `1:N` → **Asset** (midiAsset, optional)
- `1:N` → **InstrumentPart** (instrumentParts)
- `1:N` → **DocumentTrackEffect** (effects)

**Example**:
```php
$track = new DocumentTrack();
$track->setDocument($document);
$track->setLevels([true, true, false]);
$track->setLoopLength([8, 16]);
$track->setMidiAsset($asset);
$track->setPosition(0);
```

---

### 4. **Asset**

**Purpose**: Represents an uploaded MIDI file. Assets are referenced by DocumentTracks.

**Table**: `assets`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `document_id` | INTEGER | No | - | FK to Document (owner) |
| `original_name` | VARCHAR(255) | No | - | Original file name (user-provided) |
| `storage_path` | VARCHAR(500) | No | Yes | Path in file storage system |
| `mime_type` | VARCHAR(50) | No | - | MIME type (e.g., `audio/midi`) |
| `file_size` | INTEGER | No | - | File size in bytes |
| `created_by_id` | INTEGER | Yes | - | FK to User (uploader) |
| `created_at` | DATETIME | No | - | Upload timestamp |

**Constraints**:
- Storage path must be unique (no duplicate files)
- MIME type should be `audio/midi`

**Relationships**:
- `N:1` → **Document** (document, cascade delete)
- `N:1` → **User** (createdBy, optional)

**File Storage**:
- Physical files stored in: `var/uploads/sets/{document_id}/assets/{asset_id}.mid`
- Uses Flysystem abstraction (configurable storage backend)

**Example**:
```php
$asset = new Asset();
$asset->setDocument($document);
$asset->setOriginalName('melody.mid');
$asset->setStoragePath('sets/42/assets/550e8400.mid');
$asset->setMimeType('audio/midi');
$asset->setFileSize(4096);
```

---

### 5. **DocumentTrackEffect**

**Purpose**: Associates an EffectSettings with a DocumentTrack, allowing effects to be applied to tracks.

**Table**: `document_track_effects`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `document_track_id` | INTEGER | No | - | FK to DocumentTrack (track) |
| `effect_settings_id` | INTEGER | No | - | FK to EffectSettings (effect config) |
| `position` | SMALLINT | No | - | Effect order (unsigned, default: 0) |

**Relationships**:
- `N:1` → **DocumentTrack** (track, cascade delete)
- `N:1` → **EffectSettings** (effectSettings)

**Example**:
```php
$trackEffect = new DocumentTrackEffect();
$trackEffect->setTrack($track);
$trackEffect->setEffectSettings($effectSettings);
$trackEffect->setPosition(0);
```

---

### 6. **EffectSettings**

**Purpose**: Defines an effect configuration with its parameters. Can be reused across multiple tracks.

**Table**: `effect_settings`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `name` | VARCHAR(255) | No | Yes | Effect name (e.g., `delay-effect-v1`) |
| `config` | JSON | No | - | Full effect configuration object |

**Key Concepts**:

- **Config**: JSON object containing all effect parameters
  - Example: `{"delay": 0.5, "feedback": 0.6, "wet": 0.8}`

- **Reusability**: Multiple tracks can reference the same EffectSettings
  - Changes to config affect all referencing tracks
  - To customize for one track, create a new EffectSettings

**Relationships**:
- `1:N` → **DocumentTrackEffect** (usages)
- `1:N` → **EffectSettingsKeyValue** (keyValues, cascade delete)

**Example**:
```php
$effect = new EffectSettings();
$effect->setName('reverb-large-room');
$effect->setConfig([
    'roomSize' => 0.9,
    'damping' => 0.5,
    'wet' => 0.4
]);
```

---

### 7. **EffectSettingsKeyValue**

**Purpose**: Stores individual key-value pairs for an EffectSettings. Allows granular parameter management.

**Table**: `effect_settings_key_values`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `effect_settings_id` | INTEGER | No | - | FK to EffectSettings (owner) |
| `key` | VARCHAR(255) | No | - | Parameter name (e.g., `roomSize`) |
| `value` | VARCHAR(500) | No | - | Parameter value (as string) |

**Relationships**:
- `N:1` → **EffectSettings** (effectSettings, cascade delete)
- `1:N` → **InstrumentPart** (targeted by grid mappings)

**Example**:
```php
$keyValue = new EffectSettingsKeyValue();
$keyValue->setEffectSettings($effect);
$keyValue->setKey('roomSize');
$keyValue->setValue('0.9');
```

---

### 8. **InstrumentPart**

**Purpose**: Maps a grid area to an effect parameter or sequencer parameter. Enables parameter modulation via grid.

**Table**: `instrument_parts`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `part_id` | VARCHAR(26) | No | Yes | ULID (e.g., `01ARZ3NDEKTSV4RRFFQ69G5FAV`) |
| `document_track_id` | INTEGER | No | - | FK to DocumentTrack (parent) |
| `area_of_interest` | JSON | No | - | Array of ints (grid cell indices) |
| `loops_to_grid` | JSON | No | - | Array mapping loops to grid cells |
| `target_type` | VARCHAR(20) | No | - | `none`, `effect`, or `sequencer` |
| `target_effect_param_id` | INTEGER | Yes | - | FK to EffectSettingsKeyValue (if type=effect) |
| `target_sequencer_param` | VARCHAR(50) | Yes | - | e.g., `velocity` (if type=sequencer) |
| `target_binding` | VARCHAR(255) | Yes | - | Binding expression or path |
| `target_range_low` | FLOAT | Yes | - | Minimum parameter value |
| `target_range_high` | FLOAT | Yes | - | Maximum parameter value |
| `minimal_level` | FLOAT | Yes | - | Threshold level |
| `ramp_speed` | FLOAT | Yes | - | Parameter ramp-up speed |
| `ramp_speed_down` | FLOAT | Yes | - | Parameter ramp-down speed |
| `position` | SMALLINT | No | - | Part order (unsigned, default: 0) |
| `created_at` | DATETIME | No | - | Creation timestamp |
| `updated_at` | DATETIME | No | - | Last update timestamp |

**Key Concepts**:

- **Area of Interest**: Which grid cells this part affects
  - For a 2x2 grid: indices `[0, 1, 2, 3]` represent each cell
  - Example: `[0, 2]` affects top-left and bottom-left cells

- **Loops to Grid**: Maps loop letter (A, B, C...) to grid cells
  - Example: `[0, 0, 1, 1]` = Loop A for cells 0-1, Loop B for cells 2-3

- **Target Type**: What this part modulates
  - `none` = No target (placeholder)
  - `effect` = Effect parameter (via `targetEffectParam`)
  - `sequencer` = MIDI sequencer parameter (e.g., velocity)

- **Range**: Min/max values for the target parameter
  - Example: `targetRangeLow=0.0, targetRangeHigh=1.0`

**Relationships**:
- `N:1` → **DocumentTrack** (track, cascade delete)
- `N:1` → **EffectSettingsKeyValue** (targetEffectParam, optional)

**Constants**:
```php
TARGET_TYPE_NONE = 'none'
TARGET_TYPE_EFFECT = 'effect'
TARGET_TYPE_SEQUENCER = 'sequencer'
```

**Example**:
```php
$part = new InstrumentPart();
$part->setPartId(new Ulid());
$part->setTrack($track);
$part->setAreaOfInterest([0, 1]); // affects first two cells
$part->setLoopsToGrid([0, 0, 1, 1]); // loops to cells
$part->setTargetType(InstrumentPart::TARGET_TYPE_EFFECT);
$part->setTargetEffectParam($keyValue); // which parameter
$part->setTargetRangeLow(0.0);
$part->setTargetRangeHigh(1.0);
$part->setRampSpeed(0.1);
```

---

### 9. **DocumentVersion**

**Purpose**: Stores versioned snapshots of a Document's state for history and rollback.

**Table**: `document_versions`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `document_id` | INTEGER | No | - | FK to Document (versioned entity) |
| `version_number` | INTEGER | No | - | Sequential version (1, 2, 3...) |
| `payload` | LONGTEXT | No | - | Full Document JSON snapshot |
| `changelog` | TEXT | Yes | - | User-provided change description |
| `by_id` | INTEGER | Yes | - | FK to User (version author) |
| `created_at` | DATETIME | No | - | Snapshot creation timestamp |

**Key Concepts**:

- **Payload**: Complete JSON representation of the document at this version
  - Includes tracks, effects, grid configuration, BPM, etc.
  - Can be used for rollback or export

- **Version Number**: Auto-incrementing per document
  - Document 1: versions 1, 2, 3...
  - Document 2: versions 1, 2, 3...

**Relationships**:
- `N:1` → **Document** (document, cascade delete)
- `1:1` → **Document** (headVersion back-reference)
- `N:1` → **User** (by, optional)

**File Storage**:
- Version payloads also stored on disk:
  ```
  var/uploads/sets/{document_id}/versions/{version_number}/document.json
  var/uploads/sets/{document_id}/latest/document.json
  ```

**Example**:
```php
$version = new DocumentVersion();
$version->setDocument($document);
$version->setVersionNumber(3);
$version->setPayload(json_encode($documentData));
$version->setChangelog('Updated BPM and track 2');
$version->setBy($user);
```

---

### 10. **PayloadBlock**

**Purpose**: Defines template structures (PayloadBlocks) used as defaults when building JSON payloads.

**Table**: `payload_blocks`

**Fields**:

| Field | Type | Nullable | Unique | Description |
|-------|------|----------|--------|-------------|
| `id` | INTEGER | No | Yes | Primary key, auto-increment |
| `name` | VARCHAR(255) | No | Yes | Block identifier (e.g., `niveauTrack`) |
| `description` | TEXT | Yes | - | Documentation/notes |
| `payload` | LONGTEXT | No | - | JSON template structure |

**Key Concepts**:

- **Template Merging**: Used by DocumentPayloadBuilder to merge data
  - Start with PayloadBlock as default structure
  - Overlay actual document data
  - Result is final JSON export

- **Canonical Blocks**: Predefined blocks for consistency
  - `niveauTrack` = Default track structure
  - Others can be added as needed

**Example**:
```php
$block = new PayloadBlock();
$block->setName('niveauTrack');
$block->setPayload(json_encode([
    'id' => null,
    'name' => '',
    'bpm' => 90.0,
    'effects' => [],
    'loops' => [],
]));
```

---

## Common Queries

### Get a Document with All Related Data

```php
$document = $documentRepository->find($id);

// Doctrine lazy-loads these on access:
$tracks = $document->getTracks(); // DocumentTrack collection
$versions = $documentVersionRepository->findBy(['document' => $document]);
$currentVersion = $document->getHeadVersion(); // DocumentVersion
```

### Get All Tracks for a Document

```php
$tracks = $documentRepository->find($id)->getTracks();

// Or via repository:
$tracks = $trackRepository->findBy(
    ['document' => $document],
    ['position' => 'ASC']
);
```

### Get All Versions of a Document

```php
$versions = $versionRepository->findBy(
    ['document' => $document],
    ['versionNumber' => 'DESC']
);
```

### Get Effect Parameters for a Track

```php
$track = $trackRepository->find($id);
$effects = $track->getEffects(); // DocumentTrackEffect collection
foreach ($effects as $trackEffect) {
    $settings = $trackEffect->getEffectSettings(); // EffectSettings
    $keyValues = $settings->getKeyValues(); // EffectSettingsKeyValue
}
```

---

## Constraints & Validations

| Entity | Field | Constraint |
|--------|-------|-----------|
| User | email | Unique, required |
| User | hashed_pin | Required, bcrypt hashed |
| User | api_key | Unique if present |
| Document | slug | Unique, required |
| Document | title | Required |
| Document | grid_columns | 1-4 |
| Document | grid_rows | 1-4 |
| Document | set_bpm | 20.0-999.99 |
| Asset | storage_path | Unique, required |
| Asset | mime_type | Should be `audio/midi` |
| InstrumentPart | part_id | Unique ULID |
| EffectSettings | name | Unique, required |

---

## Lifecycle Hooks

### Document
- `PrePersist`: Auto-set `createdAt` and `updatedAt`
- `PreUpdate`: Auto-update `updatedAt`

### InstrumentPart
- `PrePersist`: Auto-set `createdAt`
- `PreUpdate`: Auto-update `updatedAt`

---

## Cascade Delete Behavior

- **Document** → **DocumentTrack**: Cascade delete (orphan removal)
  - Deleting a document removes all its tracks
- **Document** → **DocumentVersion**: Cascade delete
  - Deleting a document removes all its versions
- **DocumentTrack** → **InstrumentPart**: Cascade delete
  - Deleting a track removes all its instrument parts
- **DocumentTrack** → **DocumentTrackEffect**: Cascade delete
  - Deleting a track removes all its effects
- **EffectSettings** → **EffectSettingsKeyValue**: Cascade delete
  - Deleting effect settings removes all its key-values

---

## Data Integrity

### Grid System Constraints
- Grid cells are 0-indexed: `[0, 1, 2, 3]` for 2x2, `[0...15]` for 4x4
- Area of Interest arrays must contain valid cell indices
- Loops to Grid must have length = number of grid cells

### Version Numbering
- Auto-incremented per document (not global)
- Cannot skip numbers (sequential)
- Version 0 does not exist (starts at 1)

---

## Next Steps

For implementation details, see:
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — System design & layers
- **[components/entities.md](components/entities.md)** — More detailed entity documentation
- **[workflows/create-document.md](workflows/create-document.md)** — Entity creation flow
