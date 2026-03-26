# MIDI afspelen & piano roll in de browser

## Opties voor afspelen

### 1. `html-midi-player` — aanbevolen voor snelle integratie

Web component van Google/Magenta. Twee HTML-tags, inclusief ingebouwde piano roll.

```html
<script src="https://cdn.jsdelivr.net/combine/npm/tone@14,npm/@magenta/music@1.23.1/es6/core.js,npm/html-midi-player@1.5.0"></script>

<midi-player src="/pad/naar/bestand.mid" sound-font visualizer="#myViz"></midi-player>
<midi-visualizer id="myViz" type="piano-roll" src="/pad/naar/bestand.mid"></midi-visualizer>
```

| | |
|---|---|
| **Voordelen** | Nul setup, soundfonts (echte instrumentsamples), piano roll ingebouwd |
| **Nadelen** | Laadt Magenta.js (~3–5 MB), beperkte styling, vereist HTTPS voor soundfonts |
| **Soundfont-base** | `https://storage.googleapis.com/magentadata/js/soundfonts/` |
| **npm** | `html-midi-player` |
| **Docs** | https://cifkao.github.io/html-midi-player/ |

---

### 2. `@tonejs/midi` + `Tone.js` — meeste controle

MIDI parsen + Web Audio synthesis. Meer code, volledige vrijheid.

```js
import * as Tone from 'tone';
import { Midi } from '@tonejs/midi';

const midi = await Midi.fromUrl('/pad/naar/bestand.mid');
const synth = new Tone.PolySynth(Tone.Synth).toDestination();

midi.tracks.forEach(track => {
    track.notes.forEach(note => {
        synth.triggerAttackRelease(note.name, note.duration, note.time);
    });
});
Tone.Transport.start();
```

| | |
|---|---|
| **Voordelen** | Lichtgewicht, volledig controleerbaar, actief onderhouden |
| **Nadelen** | Synthetisch geluid (zonder extra Sampler), piano roll zelf bouwen |
| **npm** | `tone`, `@tonejs/midi` |
| **Docs** | https://tonejs.github.io/ |

Voor echte instrumentsamples: gebruik `Tone.Sampler` met een soundfont-set (bijv. Salamander Grand Piano).

---

### 3. Web MIDI API (native browser)

Stuurt MIDI direct naar een aangesloten synth of DAW via `navigator.requestMIDIAccess()`.

| | |
|---|---|
| **Voordelen** | Nul latency, echte instrumenten |
| **Nadelen** | Alleen Chrome/Edge, vereist HTTPS + gebruikerspermissie, gebruiker heeft MIDI-device nodig |
| **Geschikt voor** | Gevorderde tools; niet voor gewone preview |

---

### 4. `MIDI.js` (soundfonts, legacy)

Oudere library met soundfont-gebaseerd afspelen.

| | |
|---|---|
| **Status** | Minder actief onderhouden |
| **Advies** | Niet aanbevolen voor nieuwe projecten; gebruik `html-midi-player` of `@tonejs/midi` |

---

## Piano roll weergave

| Optie | Inspanning | Resultaat |
|---|---|---|
| `html-midi-player` `type="piano-roll"` | Geen | Direct bruikbaar, beperkt configureerbaar |
| `html-midi-player` `type="waterfall"` | Geen | Vallende noten weergave |
| `@tonejs/midi` + `<canvas>` zelf tekenen | Hoog | Volledig maatwerk |
| OSMD / Verovio | Hoog | Notenbalk (geen piano roll) |

---

## Integratie in dit project

MIDI-assets worden geserveerd via de route `doc_asset_download`:

```twig
{{ path('doc_asset_download', { id: document.id, assetId: a.id }) }}
```

Deze URL kan direct als `src` worden doorgegeven aan `<midi-player>` en `<midi-visualizer>`.
