# MIDI Processing & File Handling

## Overview

MIDI (Musical Instrument Digital Interface) is a binary format for representing musical events. Sem Sets processes MIDI files to extract metadata, analyze structure, and use them as the basis for creating musical compositions with loops and effects.

**Key Files**:
- `src/Midi/` — MIDI processing classes
- `src/Entity/Asset.php` — MIDI file storage & metadata
- `src/Service/AssetStorage.php` — File upload handling
- `public/js/` — Client-side MIDI playback

---

## MIDI File Upload Flow

### Step 1: File Upload

**Controller**: `src/Controller/DocumentController.php` (line 315+)
**Form Field**: `DocumentTrackType::midiAsset`

```php
// User selects MIDI file via form
$uploadedFile = $trackForm->get('midiAsset')->getData(); // UploadedFile

// Optional: Analyzer scans the file for metadata
$midiAnalyzer = new MidiAnalyzer();
$summary = $midiAnalyzer->analyze($uploadedFile->getRealPath());
// Returns: MidiSummary(
//   barCount: 64,
//   timeSignatureNumerator: 4,
//   tempoMicrosecondsPerQuarter: 500000,
//   simultaneousNoteCount: 12
// )
```

### Step 2: Storage

**Class**: `src/Service/AssetStorage.php`

```php
$storage = new AssetStorage($uploadsFilesystem);

$asset = $storage->handleUpload(
    $uploadedFile,      // UploadedFile
    $document,          // Document
    $user               // User
);

// Returns: Asset entity with:
// - originalName: "my-melody.mid"
// - storagePath: "sets/42/assets/550e8400-e29b-41d4-a716-446655440000.mid"
// - mimeType: "audio/midi"
// - fileSize: 4096
// - createdBy: user
// - createdAt: now
```

**Storage Location**:
```
var/uploads/
├── sets/
│   └── {document_id}/
│       └── assets/
│           ├── {uuid1}.mid
│           ├── {uuid2}.mid
│           └── ...
```

### Step 3: Database Persistence

**Entity**: `src/Entity/Asset.php`

```php
$asset = new Asset();
$asset->setDocument($document);
$asset->setOriginalName('my-melody.mid');
$asset->setStoragePath('sets/42/assets/550e8400.mid');
$asset->setMimeType('audio/midi');
$asset->setFileSize(4096);
$asset->setCreatedBy($user);

$entityManager->persist($asset);
$entityManager->flush();
```

---

## MIDI Analysis

### MidiAnalyzer

**File**: `src/Midi/MidiAnalyzer.php`

Analyzes a MIDI file and extracts metadata.

**Public Method**:
```php
public function analyze(string $filePath): MidiSummary
```

**Returns** `src/Midi/Dto/MidiSummary.php`:
```php
class MidiSummary
{
    public int $barCount;                        // e.g., 64
    public int $timeSignatureNumerator;          // e.g., 4 (4/4 time)
    public int $tempoMicrosecondsPerQuarter;     // e.g., 500000 (120 BPM)
    public int $simultaneousNoteCount;           // e.g., 12 (max polyphony)
}
```

**How It Works**:

1. **Bar Count Detection**: Counts MIDI ticks and converts to bars using tempo
2. **Time Signature**: Reads from MIDI "Set Time Signature" meta events
3. **Tempo**: Reads from MIDI "Set Tempo" meta events (in microseconds per quarter note)
4. **Polyphony**: Tracks maximum simultaneous notes

**Example Usage**:
```php
$analyzer = new MidiAnalyzer();
$summary = $analyzer->analyze('/path/to/file.mid');

echo $summary->barCount;                // 64
echo $summary->timeSignatureNumerator;  // 4
echo (60000000 / $summary->tempoMicrosecondsPerQuarter); // BPM (~120)
```

**Uses**:
- UI suggests default loop length based on `barCount`
- Form pre-fills time signature from MIDI
- Shows polyphony information for user reference

---

## MIDI Track Processing

### MidiTrackSplitter

**File**: `src/Midi/MidiTrackSplitter.php`

Separates a multi-track MIDI file into individual tracks.

**Public Method**:
```php
public function split(string $filePath): array
```

**Returns**: Array of MidiTrack objects
```php
[
    new MidiTrack(
        trackNumber: 1,
        trackName: 'Piano',
        noteCount: 64,
        notes: [Note(...), Note(...), ...]
    ),
    new MidiTrack(
        trackNumber: 2,
        trackName: 'Bass',
        noteCount: 48,
        notes: [...]
    ),
    // ...
]
```

**Use Case**:
- Allow users to select which tracks to use
- Show track metadata (name, note count) in UI
- Could enable per-track manipulation

**Status**: Currently implemented but not fully integrated in UI

### MidiNoteStaggerer

**File**: `src/Midi/MidiNoteStaggerer.php`

Manipulates individual notes within a MIDI file (velocity, timing).

**Public Methods**:
```php
public function stagger(string $filePath, StaggerSettings $settings): string
```

**Settings Available**:
- `velocityVariation`: Add randomness to note velocity
- `timingJitter`: Add randomness to note timing
- `noteGate`: Shorten/lengthen notes

**Use Case**:
- Make MIDI playback more "human" and less robotic
- Available via controller: `DocumentAssetStaggerNotesController`
- Saves modified MIDI back to storage

**Example**:
```php
// User clicks "Add variation" button
// Stagger makes notes slightly irregular
$staggerer = new MidiNoteStaggerer();
$newPath = $staggerer->stagger($currentPath, new StaggerSettings(
    velocityVariation: 0.1,  // ±10% velocity
    timingJitter: 0.05,      // ±5% timing
));
```

---

## Loop System Integration

### DocumentTrack & Loops

**Entity**: `src/Entity/DocumentTrack.php`

Each track stores loop information:

```php
class DocumentTrack
{
    #[ORM\Column(type: 'json')]
    private array $loopLength = [];  // e.g., [32, 32] in bars

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $loopLengthOverride = null;  // Manual base override
}
```

**Loop Concepts**:

1. **loopLength**: Array of integers representing bars for each loop
   - `[48]` = Single loop of 48 bars
   - `[32, 32]` = Two loops (A & B) of 32 bars each
   - `[16, 24, 32]` = Three loops of 16, 24, 32 bars

2. **loopLengthOverride**: Manual base loop length in bars
   - Overrides auto-detection from MIDI file
   - Used when UI "Base" button is clicked
   - Auto-divided into segments when loops are added/removed

### InstrumentPart & Grid Mapping

**Entity**: `src/Entity/InstrumentPart.php`

Maps loops to grid cells:

```php
class InstrumentPart
{
    #[ORM\Column(type: 'json')]
    private array $loopsToGrid = [];  // e.g., [0, 1, 0, 1]
}
```

**Grid Mapping**:
- Array length = grid columns × rows
- Each value = loop index (0=A, 1=B, 2=C, etc.)
- Example for 2×2 grid: `[0, 1, 0, 1]` alternates loops A and B

---

## Bars to Beats Conversion

### Why Convert?

MIDI internally uses beats (quarter notes), not bars. Sem Sets uses bars for user-friendliness but converts to beats for JSON export.

**Formula**:
```
beats = bars × timeSignatureNumerator
```

### Implementation

**File**: `src/Service/DocumentPayloadBuilder.php` (line 453)

```php
private function loopLengthBarsToBeats(array $bars, int $beatsPerBar): array
{
    return array_map(
        fn(int $bar) => $bar * $beatsPerBar,
        $bars
    );
}
```

**Example**:
```
Input:  $bars = [32, 32], $beatsPerBar = 4
Output: [128, 128]

// Because: 32 bars × 4 beats/bar = 128 beats
```

**Context in Payload Building**:
```php
$loopLengthBars = $track->getLoopLength();      // [32, 32]
$beatsPerBar = (int) $doc->getTimeSignatureNumerator();  // 4
$loopLengthBeats = $this->loopLengthBarsToBeats(
    $loopLengthBars,
    $beatsPerBar
);  // [128, 128]

// Used in JSON:
$midi[] = [
    'loopLength' => $loopLengthBeats,  // [128, 128]
    // ...
];
```

---

## JSON Payload Export

### DocumentPayloadBuilder

**File**: `src/Service/DocumentPayloadBuilder.php`

Builds the complete JSON structure for export.

**Method**:
```php
public function buildPayloadJson(Document $doc): string
```

**MIDI File Section** (lines 30-82):

```php
foreach ($doc->getTracks() as $track) {
    // 1) Get loop lengths (bars)
    $loopLengthBars = $track->getLoopLength() ?? [];
    $loopLengthBeats = $this->loopLengthBarsToBeats($loopLengthBars, $beatsPerBar);

    // 2) Get grid mapping from first InstrumentPart
    $loopsToGrid = [];
    $firstPart = $track->getInstrumentParts()->first();
    if ($firstPart) {
        $loopsToGrid = $firstPart->getLoopsToGrid() ?? [];
    }

    // 3) Create level mapping (default: all zeros = loop A)
    $levels = $track->getLevels() ?? [];
    $loopsToLevel = array_fill(0, count($levels), 0);

    // 4) Get effect settings merged
    $effectSettings = $this->getEffectConfigForTrack($track);

    // 5) Build MIDI entry
    $midi[] = [
        'midiFileName'  => $asset->getOriginalName(),
        'midiFileExt'   => 'mid',
        'loopLength'    => $loopLengthBeats,     // [128, 128]
        'loopsToGrid'   => $loopsToGrid,         // [0, 1, 0, 1]
        'loopsToLevel'  => $loopsToLevel,        // [0, 0]
        'effects'       => $effectSettings,
        'instrumentParts' => $this->buildInstrumentParts($track),
    ];
}
```

**Final JSON Structure**:
```json
{
  "version": "2.9.0",
  "bpm": 120.0,
  "timeSignature": "4/4",
  "grid": {
    "columns": 2,
    "rows": 2,
    "levelDurations": [32, 32]
  },
  "midi": [
    {
      "midiFileName": "melody.mid",
      "midiFileExt": "mid",
      "loopLength": [128, 128],
      "loopsToGrid": [0, 1, 0, 1],
      "loopsToLevel": [0, 0],
      "effects": {
        "delay": { "time": 0.5 },
        "reverb": { "size": 0.8 }
      }
    }
  ]
}
```

---

## MIDI Playback in Browser

### Options

Three main approaches for playing MIDI in the browser:

#### 1. **html-midi-player** (Recommended)

Google's web component with built-in piano roll visualization.

```html
<script src="https://cdn.jsdelivr.net/combine/npm/tone@14,npm/@magenta/music@1.23.1/es6/core.js,npm/html-midi-player@1.5.0"></script>

<midi-player src="{{ path('doc_asset_download', { id: doc.id, assetId: asset.id }) }}"
             sound-font>
</midi-player>

<midi-visualizer id="vis" type="piano-roll"
                  src="{{ path('doc_asset_download', { id: doc.id, assetId: asset.id }) }}">
</midi-visualizer>
```

**Pros**: Zero setup, piano roll included, real instrument samples
**Cons**: Loads Magenta.js (~3-5MB), HTTPS required for soundfonts

#### 2. **@tonejs/midi + Tone.js**

Web Audio API synthesis with full control.

```javascript
import * as Tone from 'tone';
import { Midi } from '@tonejs/midi';

const midi = await Midi.fromUrl('/path/to/file.mid');
const synth = new Tone.PolySynth(Tone.Synth).toDestination();

midi.tracks.forEach(track => {
    track.notes.forEach(note => {
        synth.triggerAttackRelease(
            note.name,
            note.duration,
            note.time
        );
    });
});

Tone.Transport.start();
```

**Pros**: Lightweight, full control, good for integration
**Cons**: Synthetic sound (need Sampler for real instruments)

#### 3. **Web MIDI API**

Send MIDI to connected hardware synthesizers.

```javascript
const midiAccess = await navigator.requestMIDIAccess();
const output = midiAccess.outputs.values().next().value;

// Send note on
output.send([0x90, 60, 100]);  // Note On, Middle C, velocity 100

// Send note off
output.send([0x80, 60, 0]);    // Note Off, Middle C
```

**Pros**: Zero-latency, real instruments, professional setup
**Cons**: Requires hardware, HTTPS + permission, Chrome/Edge only

### Current Implementation

The project currently focuses on **upload and processing**, with playback being an optional future feature (see `docs/midi-browser-playback.md` for detailed comparison).

---

## File Operations

### Download MIDI File

**Route**: `src/Controller/DocumentController.php::downloadAsset()`

```php
#[Route('/{id}/asset/{assetId}/download', name: 'doc_asset_download')]
public function downloadAsset(int $id, int $assetId): Response
{
    $asset = $this->assetRepo->find($assetId);

    // Get file from storage
    $content = $this->uploadsStorage->read($asset->getStoragePath());

    // Return as download
    $response = new Response($content);
    $response->headers->set('Content-Type', 'audio/midi');
    $response->headers->set('Content-Disposition',
        'attachment; filename="' . $asset->getOriginalName() . '"'
    );

    return $response;
}
```

### Delete Asset

When a DocumentTrack is deleted, its Asset is cascade-deleted:

```php
// In DocumentTrack entity:
#[ORM\ManyToOne(targetEntity: Asset::class)]
#[ORM\JoinColumn(onDelete: 'SET NULL')]
private ?Asset $midiAsset = null;

// Set to null (no cascade delete needed)
// Asset remains in database but no track references it
```

**Cleanup**: Assets with no references can be removed via admin command

---

## Performance Considerations

### Optimization Tips

1. **Lazy Loading**: MIDI files aren't loaded until explicitly accessed
   ```php
   $track = $trackRepository->find($id);
   $asset = $track->getMidiAsset();  // Only loads when accessed
   ```

2. **Cache Analysis Results**: Store MidiSummary after first analysis
   ```php
   // Instead of re-analyzing each time:
   $summary = $cache->get('midi_analysis_' . $asset->getId(),
       fn() => $midiAnalyzer->analyze($path)
   );
   ```

3. **Stream Large Files**: For downloads > 10MB
   ```php
   return new StreamedResponse(function() use ($path) {
       $stream = $this->uploadsStorage->readStream($path);
       echo stream_get_contents($stream);
   });
   ```

4. **Chunk Processing**: For files with many notes
   - Process in batches (1000 notes at a time)
   - Avoid loading entire MIDI into memory

---

## Error Handling

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Invalid MIDI file | Corrupted upload | Validate MIME type, use php-midi validation |
| Time signature not detected | MIDI has no meta event | Default to 4/4, allow manual override |
| Bars not calculated correctly | Wrong tempo or time sig | Display warning, ask user to confirm |
| File not found | Storage path broken | Implement orphan detection, repair tool |
| Playback fails | Browser incompatibility | Graceful fallback, show supported browsers |

### Validation

```php
// In form or service:
if (!preg_match('/\.mid(i)?$/i', $file->getClientOriginalName())) {
    throw new InvalidArgumentException('Only .mid files allowed');
}

// Check file size
if ($file->getSize() > 10 * 1024 * 1024) {  // 10MB
    throw new InvalidArgumentException('File too large');
}

// Validate MIDI structure
try {
    $analyzer = new MidiAnalyzer();
    $summary = $analyzer->analyze($file->getRealPath());
} catch (Exception $e) {
    throw new InvalidArgumentException('Invalid MIDI file: ' . $e->getMessage());
}
```

---

## Integration Points

### Controllers
- `DocumentController::edit()` — Show MIDI file selector
- `DocumentController::download()` — Serve MIDI file
- `DocumentAssetSplitTracksController` — Split multi-track MIDI
- `DocumentAssetStaggerNotesController` — Apply note variation

### Services
- `AssetStorage` — Handle file upload/storage
- `MidiAnalyzer` — Analyze file structure
- `DocumentPayloadBuilder` — Include MIDI in JSON export

### Entities
- `Asset` — Store MIDI metadata
- `DocumentTrack` — Reference and configure MIDI
- `InstrumentPart` — Map loops to grid

### JavaScript
- `loopLengthEditor.js` — Interactive loop configuration
- `leveldurationsTracksAOI.js` — Grid to loop mapping

---

## Next Steps

For loop-specific details, see:
- **[loops-system.md](loops-system.md)** — Complete loop workflow and configuration
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** — System overview
- **[workflows/import-midi.md](../workflows/import-midi.md)** — Step-by-step import process
