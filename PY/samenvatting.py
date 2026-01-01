#!/usr/bin/env python3
"""
Generates a merged code summary of the Symfony project: src/ + templates/

Creates: project_samenvatting.txt (in project root)

✅ Ondersteunt nu een WHITELIST:
- Zet hier relatieve paden in t.o.v. project_root, bv:
  "src/Controller/RapportageController.php"
  "templates/document/edit.html.twig"
- Wildcards (*) zijn toegestaan, bv:
  "src/Report/*.php"
- Als WHITELIST leeg is → oude gedrag (alles uit src/templates met juiste extensies)

✅ OPTIONEEL "ruis" strippen:
- Zet STRIP_NOISE = True om o.a. te verwijderen:
  - lege regels
  - commentregels (//, /* */, #, {# #})
  - PHP use-regels (imports)
"""

import os
import fnmatch

# Files we care about
EXTENSIONS = (".php", ".twig", ".yaml", ".yml", ".json", ".js", ".css", ".scss", ".ts", ".md")

# ---- Optioneel ruis verwijderen om tokens te besparen -----------------------
STRIP_NOISE = False  # op False laten voor volledige inhoud
# ---------------------------------------------------------------------------

# ---- WHITELIST: alleen deze bestanden komen in de samenvatting ----
# Gebruik relatieve paden t.o.v. project_root.
WHITELIST = [
    "public/js/effectsSettings.js",
    "public/js/leveldurationsTracksAOI.js",
    "public/js/loopLengthEditor.js",
    "public/js/midiAssetSaveNotice.js",
    "src/Command/CreateUserCommand.php",
    "src/Controller/ApiController.php",
    "src/Controller/DocumentController.php",
    "src/Controller/EffectSettingsController.php",
    "src/Controller/SecurityController.php",
    "src/Controller/SessionDebugController.php",
    "src/Entity/Asset.php",
    "src/Entity/Document.php",
    "src/Entity/DocumentTrack.php",
    "src/Entity/DocumentTrackEffect.php",
    "src/Entity/DocumentVersion.php",
    "src/Entity/EffectSettings.php",
    "src/Entity/EffectSettingsKeyValue.php",
    "src/Entity/InstrumentPart.php",
    "src/Entity/PayloadBlock.php",
    "src/Entity/User.php",
    "src/Enum/SemVersion.php",
    "src/Form/DocumentFormType.php",
    "src/Form/DocumentTrackEffectType.php,"
    "src/Form/DocumentTrackType.php",
    "src/Form/EffectSettingsType.php",
    "src/Form/InstrumentPartType.php",
    "src/Form/MidiFileRefType.php",
    "src/Form/NewDocumentFormType.php",
    "src/Midi/Dto/MidiSummary.php",
    "src/Midi/MidiAnalyzer.php",
    "src/Midi/MidiFileInterface.php",
    "src/Midi/PhpMidiFile.php",
    "src/Repository/AssetRepository.php",
    "src/Repository/DocumentRepository.php",
    "src/Repository/DocumentTrackRepository.php",
    "src/Repository/DocumentVersionRepository.php",
    "src/Repository/EffectSettingsKeyValueRepository.php",
    "src/Repository/EffectSettingsRepository.php",
    "src/Repository/PayloadBlockRepository.php",
    "src/Repository/UserRepository.php",
    "src/Security/PinCodeAuthenticator.php",
    "src/Service/AssetStorage.php",
    "src/Service/DocumentPayloadBuilder.php",
    "src/Service/DocumentSnapshotService.php",
    "src/Service/EffectConfigExtractor.php",
    "src/Service/EffectConfigMerger.php",
    "src/Service/PayloadBlockFactory.php",
    "src/Service/TrackEffectParamChoicesBuilder.php",
    "src/Service/VersioningService.php",
    "templates/_partials/ui_styles.html.twig",
    "templates/Document/_track_card.html.twig",
    "templates/Document/edit.html.twig"
    "templates/Document/index.html.twig",
    "templates/Document/new.html.twig",
    "templates/Document/versions.html.twig",
    "templates/Effect/edit.html.twig",
    "templates/Effect/index.html.twig"
    "templates/forms/_theme.html.twig",
    "templates/security/login.html.twig",
    "templates/base.html.twig",
    "translations/security.nl.yaml"
]
# ------------------------------------------------------------------


def collect_files(base_path, subfolders):
    files = []
    for folder in subfolders:
        full = os.path.join(base_path, folder)
        if not os.path.isdir(full):
            continue
        for root, _, filenames in os.walk(full):
            for f in filenames:
                if f.lower().endswith(EXTENSIONS):
                    files.append(os.path.join(root, f))
    return files


def apply_whitelist(all_files, project_root):
    """Filter all_files op basis van WHITELIST. Support voor * wildcards."""
    if not WHITELIST:
        return all_files  # oude gedrag

    whitelist_norm = [w.replace("\\", "/").strip() for w in WHITELIST if w.strip()]
    filtered = []

    for path in all_files:
        rel = os.path.relpath(path, project_root).replace("\\", "/")
        if any(fnmatch.fnmatch(rel, pattern) for pattern in whitelist_norm):
            filtered.append(path)

    return filtered


def strip_noise_from_text(path: str, text: str) -> str:
    """
    Verwijder "onnodige" regels om tokens te besparen.

    Heuristieken (bewust simpel gehouden):
    - Lege regels → weg
    - In PHP/JS/TS/CSS/SCSS:
      - Volledige commentregels //...
      - Block comments tussen /* ... */ (volledige regels)
    - In YAML/YML: regels die met # beginnen
    - In Twig: regels die volledig {# ... #} zijn
    - In PHP: `use Foo\Bar;` regels worden verwijderd
    """

    if not STRIP_NOISE:
        return text

    ext = os.path.splitext(path)[1].lower()
    lines = text.splitlines()

    cleaned_lines = []
    in_block_comment = False

    for line in lines:
        stripped = line.strip()

        # 1. Lege regels weglaten
        if not stripped:
            continue

        # 2. Taal-specifieke filters
        # -------------------------- PHP / JS / TS / CSS / SCSS
        if ext in (".php", ".js", ".ts", ".css", ".scss"):
            # binnen een /* ... */ commentblok
            if in_block_comment:
                if "*/" in stripped:
                    in_block_comment = False
                continue

            # start van block comment
            if stripped.startswith("/*"):
                if "*/" not in stripped:
                    in_block_comment = True
                continue

            # // commentregel
            if stripped.startswith("//"):
                continue

        # YAML / YML → # commentregels
        if ext in (".yaml", ".yml"):
            if stripped.startswith("#"):
                continue

        # Twig → volledige {# ... #} regels
        if ext == ".twig":
            if stripped.startswith("{#") and stripped.endswith("#}"):
                continue

        # PHP-specifiek: use Foo\Bar; (imports) strippen
        if ext == ".php":
            if stripped.startswith("use ") and stripped.endswith(";"):
                # simpele heuristiek: alleen import-regels
                continue

        # Hier gekomen? Dan houden we de regel
        cleaned_lines.append(line)

    return "\n".join(cleaned_lines)


def main():
    # go one directory up
    project_root = os.path.realpath(os.path.join(os.path.dirname(__file__), ".."))

    targets = ["src", "templates","public/js"]
    all_files = collect_files(project_root, targets)
    all_files.sort()

    included_files = apply_whitelist(all_files, project_root)

    output_file = os.path.join(project_root, "project_samenvatting.txt")

    with open(output_file, "w", encoding="utf-8") as out:
        out.write("### Project Code Summary\n")
        out.write("### Auto-generated by PY/samenvatting.py\n\n")

        if WHITELIST:
            out.write("### WHITELIST actief — alleen geselecteerde bestanden\n")
            for w in WHITELIST:
                out.write(f"- {w}\n")
            out.write("\n")

        out.write(f"### STRIP_NOISE = {STRIP_NOISE}\n\n")

        if not included_files:
            out.write("[Geen bestanden gevonden die matchen met WHITELIST]\n")

        for path in included_files:
            relative = os.path.relpath(path, project_root).replace("\\", "/")

            out.write(f"\n\n---  FILE: {relative}  " + "-" * 60 + "\n\n")

            try:
                with open(path, "r", encoding="utf-8", errors="ignore") as f:
                    raw_content = f.read()
                content = strip_noise_from_text(path, raw_content)
                out.write(content)
            except Exception as e:
                out.write(f"[Error reading file: {e}]")

    print(f"✅ Samenvatting gemaakt: {output_file}")
    if WHITELIST:
        print(f"📌 Aantal bestanden in whitelist-output: {len(included_files)}")


if __name__ == "__main__":
    main()
