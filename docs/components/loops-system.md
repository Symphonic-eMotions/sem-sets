# The Complete Loop System

## Overview

The **Loop System** is the heart of Sem Sets' musical flexibility. It allows users to define multiple loop patterns within a single MIDI track and map them to grid positions for dynamic control.

**Key Concept**: A loop is a segment of the MIDI file that can be repeated independently. Multiple loops allow variations within the same track.

---

## Core Concepts

### Loop Length

**Definition**: The duration of a single loop in bars.

**Storage**: `DocumentTrack.loopLength` (JSON array)

```php
// Single loop (straight playback)
$track->setLoopLength([64]);  // 64 bars

// Two loops (A & B)
$track->setLoopLength([32, 32]);  // Each loop is 32 bars

// Three loops with different lengths
$track->setLoopLength([16, 24, 32]);  // Different variations
```

**User Perspective**:
- Loop A = First variation
- Loop B = Second variation
- Loop C = Third variation
- etc.

### Loop Base

**Definition**: The total duration from which loop segments are calculated.

**Storage**: `DocumentTrack.loopLengthOverride` (nullable integer)

```php
// If MIDI file is 64 bars but user wants only 48 bars of content
$track->setLoopLengthOverride(48);

// System auto-divides into equal segments when adding loops
// Base 48 ÷ 2 loops = [24, 24]
// Base 48 ÷ 3 loops = [16, 16, 16]
```

**When Used**:
- Auto-detection from MIDI is too long
- User wants custom base duration
- "Basis" button in UI resets to this value

### Grid Mapping (loopsToGrid)

**Definition**: Assignment of loops to grid cells.

**Storage**: `InstrumentPart.loopsToGrid` (JSON array)

```php
// 2×2 grid (4 cells) with alternating loops A and B
$part->setLoopsToGrid([0, 1, 0, 1]);

// Meaning:
// - Grid cell 0 → Loop A (index 0)
// - Grid cell 1 → Loop B (index 1)
// - Grid cell 2 → Loop A (index 0)
// - Grid cell 3 → Loop B (index 1)

// 3×3 grid (9 cells) with cycling through three loops
$part->setLoopsToGrid([0, 1, 2, 0, 1, 2, 0, 1, 2]);
```

**Grid Cell Indexing**:
```
For 2×2 grid:
┌───┬───┐
│ 0 │ 1 │
├───┼───┤
│ 2 │ 3 │
└───┴───┘

For 3×3 grid:
┌───┬───┬───┐
│ 0 │ 1 │ 2 │
├───┼───┼───┤
│ 3 │ 4 │ 5 │
├───┼───┼───┤
│ 6 │ 7 │ 8 │
└───┴───┴───┘
```

### Level Mapping (loopsToLevel)

**Definition**: Which loop plays in each temporal level.

**Storage**: `DocumentVersion` payload (computed, not persisted)

```php
// Document has 2 levels
// Default: both levels use Loop A (index 0)
$loopsToLevel = [0, 0];

// Could be: level 1 uses Loop A, level 2 uses Loop B
$loopsToLevel = [0, 1];
```

**Current Status**: Defaults to all zeros; custom mapping not yet implemented in UI.

---

## Data Storage Architecture

### Database Schema

```
Document (1)
  ↓
  ├─ DocumentTrack (N)
  │   ├─ loopLength: JSON → [32, 32, 16]
  │   ├─ loopLengthOverride: INT → 64
  │   └─ midiAsset: Asset → file.mid
  │
  └─ InstrumentPart (N per track)
      └─ loopsToGrid: JSON → [0, 1, 0, 1]
```

### DocumentTrack Entity

**File**: `src/Entity/DocumentTrack.php`

```php
class DocumentTrack
{
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $loopLength = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $loopLengthOverride = null;

    public function setLoopLength(string|array|null $value): self
    {
        // Handles multiple input formats:
        // - JSON string: "[32,32]"
        // - CSV string: "32,32"
        // - Array: [32, 32]

        if (is_string($value)) {
            // Try JSON first
            if (str_starts_with($value, '[')) {
                $value = json_decode($value, true);
            } else {
                // Try CSV
                $value = array_map('trim', explode(',', $value));
            }
        }

        // Normalize: keep only positive integers
        $this->loopLength = array_values(
            array_filter(
                array_map('intval', (array)$value),
                fn($v) => $v > 0
            )
        );

        return $this;
    }

    public function getLoopLength(): array
    {
        return $this->loopLength;
    }
}
```

### InstrumentPart Entity

**File**: `src/Entity/InstrumentPart.php`

```php
class InstrumentPart
{
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $loopsToGrid = [];

    public function setLoopsToGrid(string|array|null $value): self
    {
        // Similar parsing to loopLength
        if (is_string($value)) {
            if (str_starts_with($value, '[')) {
                $value = json_decode($value, true);
            } else {
                $value = array_map('trim', explode(',', $value));
            }
        }

        // Normalize: keep non-negative integers
        $this->loopsToGrid = array_values(
            array_filter(
                array_map('intval', (array)$value),
                fn($v) => $v >= 0
            )
        );

        return $this;
    }

    public function getLoopsToGrid(): array
    {
        return $this->loopsToGrid;
    }
}
```

---

## User Interface Workflow

### The Loop Editor

**Location**: `templates/Document/_track_card.html.twig` (lines 143-185)

```twig
<div class="midi-rhythm-card loop-editor js-loop-editor"
     data-total-bars="{{ totalBars }}"
     data-timesig="{{ timeSig }}"
     data-input-id="{{ trackForm.loopLength.vars.id }}">

    <!-- Row A: Base Duration Setting -->
    <div class="loop-base-row">
        {{ form_widget(trackForm.loopLengthOverride) }}
        <button class="btn-mini js-loop-base-dec">– (bars)</button>
        <span class="loop-base-display">{{ baseLoops }} maten</span>
        <button class="btn-mini js-loop-base-inc">+ (bars)</button>
    </div>

    <!-- Row B: Loop Segments (Colored Chips) -->
    <div class="loop-chips-row">
        <div class="loop-chips js-loop-chips">
            <!-- JavaScript generates colored chips here -->
            <!-- [32] → Red "A" chip
                 [32, 32] → Red "A" + Blue "B"
                 [32, 32, 16] → Red "A" + Blue "B" + Green "C" -->
        </div>
    </div>

    <!-- Row C: Actions -->
    <div class="loop-actions-row">
        <button type="button" class="btn-mini js-loop-reset">Basis</button>
        <button type="button" class="btn-mini js-loop-add">+ Loop</button>
        <button type="button" class="btn-mini js-loop-remove">– Loop</button>
    </div>

    <!-- Hidden input stores JSON -->
    <input type="hidden" id="{{ trackForm.loopLength.vars.id }}"
           name="..." value="{{ loopLengthJson }}">
</div>
```

### The Grid Mapper

**Location**: `templates/Document/_track_card.html.twig` (lines 308-318)

```twig
<!-- Only shows on first InstrumentPart of the track -->
<div class="loops-grid-label">
    <span class="label">Loop-indeling in het grid</span>
    <span class="hint">(Klik op een cel om tussen loops te wisselen)</span>
</div>

<div id="loops-tiles-{{ index }}-{{ loop.index0 }}"
     class="loops-tiles"
     data-track-index="{{ loop.index0 }}"
     data-input-id="{{ partForm.loopsToGrid.vars.id }}">
    <!-- JavaScript generates grid buttons here -->
    <!-- For 2×2 with [0,1,0,1]:
         Button 0: "A"
         Button 1: "B"
         Button 2: "A"
         Button 3: "B" -->
</div>

<!-- Hidden input stores JSON -->
<input type="hidden" id="{{ partForm.loopsToGrid.vars.id }}"
       name="..." value="{{ loopsToGridJson }}">
```

---

## JavaScript Implementation

### loopLengthEditor.js

**File**: `public/js/loopLengthEditor.js`

Manages loop length creation and editing.

#### Key Functions

**Initialization** (lines 1-50):
```javascript
class LoopLengthEditor {
    constructor(editorEl, hiddenInput, timeSig) {
        this.editorEl = editorEl;           // DOM element
        this.hiddenInput = hiddenInput;     // Where JSON is stored
        this.timeSig = timeSig;             // e.g., 4 for 4/4
        this.currentLoops = [];             // [32, 32, ...]

        this.init();
    }

    init() {
        this.parseValueFromHidden();        // Load from storage
        this.renderUI();                    // Draw chips
        this.attachEventListeners();        // Setup buttons
    }
}
```

**Parse Input** (lines 55-95):
```javascript
parseValueFromHidden() {
    const raw = this.hiddenInput.value;

    // Try JSON first: "[32,32]"
    if (raw.startsWith('[')) {
        this.currentLoops = JSON.parse(raw);
    } else if (raw.includes(',')) {
        // CSV: "32,32"
        this.currentLoops = raw.split(',')
                               .map(x => parseInt(x.trim()));
    } else if (raw) {
        // Single value: "64"
        this.currentLoops = [parseInt(raw)];
    } else {
        // Compute default from MIDI bar count
        const totalBars = this.editorEl.dataset.totalBars;
        this.currentLoops = [parseInt(totalBars)];
    }

    // Ensure all are positive
    this.currentLoops = this.currentLoops.filter(x => x > 0);
}
```

**Store Value** (lines 97-102):
```javascript
storeValue(loops) {
    this.currentLoops = loops;
    this.hiddenInput.value = JSON.stringify(loops);
    this.renderUI();
    this.notifyLoopsChanged();  // Update grid mapper
}
```

**Compute Base Duration** (lines 110-137):
```javascript
computeEffectiveBase() {
    // Check if user manually set a base
    const overrideInput = this.editorEl.querySelector('[name$="loopLengthOverride"]');
    if (overrideInput?.value) {
        return parseInt(overrideInput.value);
    }

    // Otherwise, use MIDI total bars
    const totalBars = this.editorEl.dataset.totalBars;
    return parseInt(totalBars);
}
```

**Recalculate on Loop Count Change** (lines 139-153):
```javascript
recalcForSegmentCount(newCount) {
    const base = this.computeEffectiveBase();
    const segmentBars = Math.floor(base / newCount);

    // Round to nearest time signature multiple
    const roundedBars = Math.round(segmentBars / this.timeSig) * this.timeSig;

    // Create array of equal segments
    const newLoops = Array(newCount).fill(roundedBars);

    this.storeValue(newLoops);
}
```

**Button Actions** (lines 155-200):
```javascript
attachEventListeners() {
    // "Basis" button: reset to auto-calculated base
    this.editorEl.querySelector('.js-loop-reset')
        ?.addEventListener('click', () => {
            const base = this.computeEffectiveBase();
            this.storeValue([base]);
        });

    // "+ Loop" button: add another loop
    this.editorEl.querySelector('.js-loop-add')
        ?.addEventListener('click', () => {
            const newCount = this.currentLoops.length + 1;
            this.recalcForSegmentCount(newCount);
        });

    // "– Loop" button: remove a loop
    this.editorEl.querySelector('.js-loop-remove')
        ?.addEventListener('click', () => {
            if (this.currentLoops.length > 1) {
                const newCount = this.currentLoops.length - 1;
                this.recalcForSegmentCount(newCount);
            }
        });

    // "+"/"-" buttons: increment/decrement base
    this.editorEl.querySelector('.js-loop-base-inc')
        ?.addEventListener('click', () => {
            this.adjustBase(this.timeSig);  // Add time sig increment
        });

    this.editorEl.querySelector('.js-loop-base-dec')
        ?.addEventListener('click', () => {
            this.adjustBase(-this.timeSig);
        });
}
```

**Notify Grid Mapper** (lines 202-208):
```javascript
notifyLoopsChanged() {
    // When loop count changes, notify grid mapper
    // so it can reset grid mapping to loop A (index 0)
    const event = new CustomEvent('loopsChanged', {
        detail: { loopCount: this.currentLoops.length }
    });
    this.editorEl.dispatchEvent(event);
}
```

### leveldurationsTracksAOI.js

**File**: `public/js/leveldurationsTracksAOI.js`

Manages grid-to-loop mapping.

#### Key Functions

**Get Loop Count** (lines 304-316):
```javascript
function getTrackLoopCount(trackCard) {
    const hiddenInput = trackCard.querySelector('[name$="loopLength"]');
    const raw = hiddenInput.value;

    let loops;
    if (raw.startsWith('[')) {
        loops = JSON.parse(raw);
    } else if (raw.includes(',')) {
        loops = raw.split(',').map(x => parseInt(x));
    } else {
        loops = [parseInt(raw)];
    }

    return loops.length;
}
```

**Parse Grid Mapping** (lines 318-353):
```javascript
function parseRawLoopsGrid(inputEl, loopCount, targetLen) {
    const raw = inputEl.value;
    let mapping = [];

    // Parse from multiple formats
    if (raw.startsWith('[')) {
        mapping = JSON.parse(raw);
    } else if (raw.includes(',')) {
        mapping = raw.split(',').map(x => parseInt(x));
    }

    // Ensure correct length
    if (mapping.length !== targetLen) {
        // Pad with zeros (all grid cells use Loop A by default)
        mapping = Array(targetLen).fill(0);
    }

    // Ensure all indices are valid (< loopCount)
    mapping = mapping.map(idx => idx % loopCount);

    return mapping;
}
```

**Render Grid Buttons** (lines 355-387):
```javascript
function buildLoopsGridTiles(partBlock, trackCard, cols, rows, resetToFirstLoop) {
    const targetLen = cols * rows;
    const loopCount = getTrackLoopCount(trackCard);

    // Get stored mapping or create new
    const hiddenInput = partBlock.querySelector('[name$="loopsToGrid"]');
    let mapping = resetToFirstLoop
        ? Array(targetLen).fill(0)
        : parseRawLoopsGrid(hiddenInput, loopCount, targetLen);

    // Render button for each cell
    for (let i = 0; i < targetLen; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'loop-grid-btn';

        // Show loop letter (A, B, C, ...)
        const loopIdx = mapping[i];
        const loopLetter = String.fromCharCode(65 + loopIdx);  // A, B, C, ...
        btn.textContent = loopLetter;
        btn.dataset.cellIdx = i;
        btn.dataset.loopIdx = loopIdx;

        // Color coding
        btn.style.backgroundColor = this.getLoopColor(loopIdx);

        // Click to cycle to next loop
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const current = mapping[i];
            const next = (current + 1) % loopCount;
            mapping[i] = next;
            btn.textContent = String.fromCharCode(65 + next);
            btn.dataset.loopIdx = next;
            btn.style.backgroundColor = this.getLoopColor(next);
            storeRawLoopsGrid(hiddenInput, mapping);
        });

        partBlock.appendChild(btn);
    }
}
```

**Store Grid Mapping** (lines 389-395):
```javascript
function storeRawLoopsGrid(inputEl, mapping) {
    inputEl.value = JSON.stringify(mapping);
}
```

**Reset on Loop Count Change** (lines 397-420):
```javascript
// Listen for loop count changes
trackCard.addEventListener('loopsChanged', (e) => {
    const newLoopCount = e.detail.loopCount;

    // Rebuild grid with new loop count
    const partBlock = trackCard.querySelector('.loops-tiles');
    partBlock.innerHTML = '';  // Clear existing

    // Get grid dimensions
    const cols = parseInt(document.documentElement.style.getPropertyValue('--grid-cols'));
    const rows = parseInt(document.documentElement.style.getPropertyValue('--grid-rows'));

    // Rebuild, resetting all to loop A
    buildLoopsGridTiles(partBlock, trackCard, cols, rows, true);
});
```

---

## Data Flow Examples

### Example 1: Single Loop (Straight Playback)

**User Action**: Uploads MIDI file (64 bars)

**Step 1: UI Initialization**
```
MIDI Analysis:
  - barCount: 64
  - timeSignature: 4/4

Loop Editor:
  - Auto-detect base: 64 bars
  - Loop count: 1
  - loopLength = [64]
```

**Step 2: Form Data**
```
Submitted data:
  - loopLength: "[64]" (JSON string)
  - loopsToGrid: "[0, 0, 0, 0]" (all use loop A)
```

**Step 3: Entity Storage**
```php
$track->setLoopLength("[64]");
// Stored in DB: loopLength = [64] (JSON)

$part->setLoopsToGrid("[0, 0, 0, 0]");
// Stored in DB: loopsToGrid = [0, 0, 0, 0] (JSON)
```

**Step 4: JSON Export**
```php
$loopLengthBars = [64];
$beatsPerBar = 4;
$loopLengthBeats = [256];  // 64 × 4

$loopsToGrid = [0, 0, 0, 0];
$loopsToLevel = [0];  // 1 level, use loop A

// Result in JSON:
{
  "midi": [{
    "loopLength": [256],      // 64 bars in beats
    "loopsToGrid": [0, 0, 0, 0],  // All grid cells use Loop A
    "loopsToLevel": [0]           // Level uses Loop A
  }]
}
```

---

### Example 2: Multiple Loops with Grid Variation

**User Action**:
1. Uploads MIDI (64 bars)
2. Clicks "+ Loop" → creates [32, 32]
3. Clicks grid cells to alternate: [0, 1, 0, 1]

**Step 1: Loop Length Calculation**
```javascript
// User clicks "+ Loop"
recalcForSegmentCount(2);  // Now 2 loops

// Calculation:
base = 64 bars
newCount = 2
segmentBars = 64 / 2 = 32 bars
roundedBars = 32 (already multiple of 4)

Result: loopLength = [32, 32]
```

**Step 2: Grid Mapping**
```javascript
// Grid is 2×2 (4 cells)
// User clicks each cell in sequence:
// Cell 0: default Loop A (index 0)
// Cell 1: click → Loop B (index 1)
// Cell 2: click → Loop A (index 0)
// Cell 3: click → Loop B (index 1)

Result: loopsToGrid = [0, 1, 0, 1]
```

**Step 3: JSON Export**
```php
$loopLengthBars = [32, 32];
$beatsPerBar = 4;
$loopLengthBeats = [128, 128];  // 32 × 4

$loopsToGrid = [0, 1, 0, 1];
$loopsToLevel = [0];  // Uses Loop A in playback

// Result in JSON:
{
  "midi": [{
    "loopLength": [128, 128],  // Two 32-bar loops (128 beats each)
    "loopsToGrid": [0, 1, 0, 1],  // Grid cells alternate A-B-A-B
    "loopsToLevel": [0]           // Playback starts with Loop A
  }]
}
```

**How It's Played**:
```
Play instruction:
  - Start with loopsToLevel[0] = 0 (Loop A)
  - Loop A is 128 beats (32 bars at 4/4)
  - Cover grid cells [0, 2] (all Loop A cells)

  - Move to Grid cell [1] which requires Loop B
  - Switch to Loop B (index 1)
  - Loop B is also 128 beats
  - Cover grid cells [1, 3] (all Loop B cells)
```

---

### Example 3: Three Different Loop Lengths

**User Action**:
1. Set base: 48 bars
2. Add Loop → [24, 24]
3. Add Loop → [16, 16, 16]
4. Map to 3×3 grid in pattern: A-B-C-A-B-C-A-B-C

**Step 1: Loop Lengths**
```javascript
// Base = 48 bars
// 3 loops: recalcForSegmentCount(3)
// 48 / 3 = 16 bars per loop
// Result: [16, 16, 16]
```

**Step 2: Grid Mapping** (9 cells)
```javascript
// User manually creates cycling pattern:
loopsToGrid = [0, 1, 2, 0, 1, 2, 0, 1, 2]

// Visual (3×3 grid):
┌───┬───┬───┐
│ A │ B │ C │
├───┼───┼───┤
│ A │ B │ C │
├───┼───┼───┤
│ A │ B │ C │
└───┴───┴───┘
```

**Step 3: JSON Export**
```php
$loopLengthBars = [16, 16, 16];
$loopLengthBeats = [64, 64, 64];  // 16 × 4

$loopsToGrid = [0, 1, 2, 0, 1, 2, 0, 1, 2];

// Result:
{
  "midi": [{
    "loopLength": [64, 64, 64],  // Three 16-bar loops
    "loopsToGrid": [0, 1, 2, 0, 1, 2, 0, 1, 2],  // Cycling pattern
    "loopsToLevel": [0]
  }]
}
```

---

## Form Handling

### DocumentController Edit (GET)

**File**: `src/Controller/DocumentController.php` (lines 106-210)

```php
public function edit(int $id, Request $req): Response
{
    $doc = $this->documentRepository->find($id);

    // For each track, prepare loop data for form
    foreach ($doc->getTracks() as $track) {
        // Convert loop array to JSON string for form display
        $loopLengthJson = json_encode($track->getLoopLength());

        // For each InstrumentPart on this track
        foreach ($track->getInstrumentParts() as $part) {
            $loopsToGridJson = json_encode($part->getLoopsToGrid());

            // Store in form data context
            // Form will display these as hidden inputs
        }
    }

    // Create form with pre-filled data
    $form = $this->createForm(DocumentFormType::class, $doc);

    return $this->render('Document/edit.html.twig', [
        'document' => $doc,
        'form' => $form,
    ]);
}
```

### DocumentController Edit (POST)

**File**: `src/Controller/DocumentController.php` (lines 215-347)

```php
public function edit(int $id, Request $req): Response
{
    // ... form submission handling ...

    if ($form->isSubmitted() && $form->isValid()) {

        // For each track, extract loop data from form
        foreach ($doc->getTracks() as $track) {
            $trackForm = $form->get('tracks')->get($trackIndex);

            // Get raw loop length from form (unmapped field)
            $rawLoop = $trackForm->get('loopLength')->getData();
            $track->setLoopLength($rawLoop);  // Entity handles parsing

            // For each InstrumentPart on this track
            foreach ($track->getInstrumentParts() as $part) {
                $partForm = $trackForm->get('instrumentParts')->get($partIndex);

                // Get raw loops-to-grid from form
                $rawLoops = $partForm->get('loopsToGrid')->getData();
                $part->setLoopsToGrid($rawLoops);  // Entity handles parsing
            }
        }

        // Persist to database
        $this->em->persist($doc);
        $this->em->flush();

        // Create version snapshot with new loop data
        $this->versioningService->saveVersion($doc, 'Updated loop configuration');

        return $this->redirect($this->generateUrl('doc_edit', ['id' => $id]));
    }

    return $this->render('Document/edit.html.twig', [
        'form' => $form,
    ]);
}
```

---

## Form Type Configuration

### DocumentTrackType

**File**: `src/Form/DocumentTrackType.php`

```php
public function buildForm(FormBuilder $builder, array $options)
{
    // ...other fields...

    $builder->add('loopLength', TextType::class, [
        'label' => 'Looplengte (maten)',
        'required' => false,
        'mapped' => false,  // ← Handled manually in controller
        'attr' => [
            'class' => 'js-loop-length-raw',
            'placeholder' => '[32, 32]',
        ],
    ]);

    $builder->add('loopLengthOverride', IntegerType::class, [
        'label' => 'Basis (maten)',
        'required' => false,
        'mapped' => true,  // ← Direct to entity
    ]);
}
```

**Why unmapped for loopLength?**
- JavaScript dynamically updates the value
- Controller needs to validate and parse it
- Setter method on entity handles normalization

### InstrumentPartType

**File**: `src/Form/InstrumentPartType.php`

```php
public function buildForm(FormBuilder $builder, array $options)
{
    // ...other fields...

    $builder->add('loopsToGrid', TextType::class, [
        'label' => false,
        'required' => false,
        'mapped' => false,  // ← Handled manually in controller
        'attr' => [
            'class' => 'js-loops-grid-raw',
            'placeholder' => '[0, 1, 0, 1]',
        ],
    ]);
}
```

---

## Validation & Constraints

### Data Validation

```php
// In DocumentTrack setter:
public function setLoopLength($value): self
{
    // Ensure all values are positive integers
    $this->loopLength = array_values(
        array_filter(
            array_map('intval', (array)$value),
            fn($v) => $v > 0  // ← Only positive
        )
    );
    return $this;
}

// In InstrumentPart setter:
public function setLoopsToGrid($value): self
{
    // Ensure all values are non-negative integers
    $this->loopsToGrid = array_values(
        array_filter(
            array_map('intval', (array)$value),
            fn($v) => $v >= 0  // ← Non-negative
        )
    );
    return $this;
}
```

### Grid Cell Validation

```javascript
// In JavaScript grid handler:
if (mapping.length !== expectedCellCount) {
    // Pad or truncate to match grid size
    mapping = Array(expectedCellCount).fill(0);
}

// Ensure all indices are valid
mapping = mapping.map(idx => idx % loopCount);
```

---

## Error Handling

### Common Issues

| Issue | Cause | Fix |
|-------|-------|-----|
| loopLength shows empty | No MIDI file uploaded | Show default or ask user |
| Grid not updating when loops added | loopsChanged event not fired | Check JavaScript console |
| Invalid loop indices in grid | User manual JSON edit | Reset grid to loop A |
| Loop longer than MIDI file | User sets base > MIDI duration | Warn user, clamp to MIDI length |
| loopsToGrid wrong length | Grid size changed | Reset to [0, 0, 0, 0] for new grid |

### Error Handling Code

```php
// In DocumentPayloadBuilder:
try {
    $loopLengthBars = $track->getLoopLength() ?? [];
    if (empty($loopLengthBars)) {
        throw new LogicException("Track has no loop length defined");
    }

    $loopLengthBeats = $this->loopLengthBarsToBeats($loopLengthBars, $beatsPerBar);
} catch (Exception $e) {
    // Log error, use sensible default
    $loopLengthBeats = [128];  // Default to 32 bars × 4
}
```

---

## Performance Considerations

### Optimization

1. **Cache Time Signature**: Avoid re-parsing document repeatedly
   ```php
   $beatsPerBar = $this->cache->get('timesig_' . $doc->getId(),
       fn() => $doc->getTimeSignatureNumerator() ?? 4
   );
   ```

2. **Lazy Load Tracks**: Don't fetch if not needed
   ```php
   $tracks = $doc->getTracks();  // Lazy-loaded on access
   ```

3. **Batch Process**: Update multiple loops in one flush
   ```php
   foreach ($doc->getTracks() as $track) {
       $track->setLoopLength($newLength);
   }
   $this->em->flush();  // ← One flush for all
   ```

---

## Testing

### Unit Tests

```php
// Test loop length parsing
public function testLoopLengthParsing()
{
    $track = new DocumentTrack();

    // Test JSON
    $track->setLoopLength("[32, 32]");
    $this->assertEquals([32, 32], $track->getLoopLength());

    // Test CSV
    $track->setLoopLength("32, 32");
    $this->assertEquals([32, 32], $track->getLoopLength());

    // Test array
    $track->setLoopLength([32, 32]);
    $this->assertEquals([32, 32], $track->getLoopLength());
}

// Test grid mapping
public function testLoopsToGridValidation()
{
    $part = new InstrumentPart();

    $part->setLoopsToGrid("[0, 1, 0, 1]");
    $this->assertEquals([0, 1, 0, 1], $part->getLoopsToGrid());

    // Test filtering negative values
    $part->setLoopsToGrid("[0, -1, 2]");
    $this->assertEquals([0, 2], $part->getLoopsToGrid());
}
```

### Integration Tests

```php
// Test full loop workflow
public function testLoopEditingWorkflow()
{
    // 1. Create document with track
    $doc = $this->createDocument();
    $track = $this->addTrack($doc, 'test.mid');

    // 2. Edit loops
    $track->setLoopLength([32, 32]);
    $this->em->flush();

    // 3. Export payload
    $payload = $this->payloadBuilder->buildPayloadJson($doc);
    $data = json_decode($payload, true);

    // 4. Verify in JSON
    $this->assertEquals([128, 128], $data['midi'][0]['loopLength']);
}
```

---

## Next Steps

For related documentation:
- **[midi-processing.md](midi-processing.md)** — MIDI file handling and analysis
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** — System overview
- **[DATABASE_SCHEMA.md](../DATABASE_SCHEMA.md)** — Entity details
- **[workflows/export-payload.md](../workflows/export-payload.md)** — Export process
