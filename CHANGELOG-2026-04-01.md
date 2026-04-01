# Changelog 18 maart t/m 1 april 2026

Hieronder staan de belangrijkste veranderingen van de afgelopen twee weken, in gewone taal.

## Nieuw en verbeterd

### MIDI-bestanden beter bekijken en beluisteren

- MIDI-bestanden kunnen nu duidelijker bekeken worden in de editor.
- Er is extra preview-functionaliteit toegevoegd.
- Loops kunnen nu apart worden afgespeeld, zodat sneller gecontroleerd kan worden of een stuk goed klinkt.

### MIDI-bestanden opschonen met "staggeren"

- Er is een nieuwe functie toegevoegd om gelijktijdige noten in een MIDI-bestand iets uit elkaar te zetten.
- Dit heet **staggeren**.
- Simpel gezegd: als meerdere noten precies op hetzelfde moment starten, worden ze heel klein beetje na elkaar geplaatst.
- Daardoor kan een MIDI-bestand natuurlijker klinken of beter werken in systemen die moeite hebben met exact gelijke starts.
- Vanuit de editor is nu zichtbaar wanneer zo'n bestand veel gelijktijdige noten bevat, en kan dit direct worden aangepast.

### Namen van MIDI-bestanden aanpassen

- Bestanden kunnen nu in de interface een eigen duidelijke naam krijgen.
- Daardoor hoef je niet meer alleen met technische bestandsnamen te werken.
- Ook de knoppen rond bestanden zijn overzichtelijker gemaakt.

### Betere meldingen in de editor

- Na het selecteren van een MIDI-bestand krijg je duidelijkere feedback.
- Dat maakt het makkelijker om te zien wat er is gebeurd en wat de volgende stap is.

### Koppeling met SemSym uitgebreid

- Er is nieuwe ondersteuning toegevoegd voor een koppeling met SemSym.
- Hiervoor zijn inlog- en beveiligingsonderdelen toegevoegd aan de API.

### Export en downloads verbeterd

- Het downloaden van documentbundels is intern opgeschoond en beter georganiseerd.
- Dit maakt de code onderhoudbaarder en verkleint de kans op fouten bij export.

## Technische verbeteringen

### Lokale ontwikkelomgeving vernieuwd

- De ontwikkelomgeving draait nu beter via Docker.
- Daarbij wordt gebruikgemaakt van PHP, MariaDB en Mailpit.
- Dit maakt lokaal werken consistenter en beter voorspelbaar.

### Tests uitgebreid

- Er zijn extra tests toegevoegd voor belangrijke onderdelen van de applicatie.
- Daardoor kunnen fouten sneller worden opgemerkt bij nieuwe wijzigingen.

### Documentatie flink uitgebreid

- Er is veel nieuwe documentatie toegevoegd over de opbouw van het systeem, de database en de MIDI-verwerking.
- Ook de deployment-instructies zijn verder uitgewerkt.

## Kort samengevat

De grootste zichtbare verbeteringen voor gebruikers zijn:

- betere MIDI-preview,
- loops los kunnen afspelen,
- gelijktijdige noten kunnen "staggeren",
- bestanden een eigen naam geven,
- en duidelijkere feedback in de editor.
