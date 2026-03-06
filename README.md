# ProgrammO (WordPress Plugin)

Herausgeber: Hubertoink  
Plugin-Name: ProgrammO

## Ziel

ProgrammO stellt einen dynamischen, mobilen Wochenplan für Jugendhaus-Angebote bereit.

- Offene Bereiche (hellblaue Kacheln)
- Angebote (rechteckige Kacheln)
- Zuordnung Angebot → Slot → Offener Bereich/Segment
- Zuordnung Offener Bereich → Personen
- Verlinkung auf Single-Seite aus `Events_OKJA` (wenn aktiv)
- Eigene lokale Angebote direkt in ProgrammO, ohne eigene Single-Seite

## Status (MVP Start)

Bereits umgesetzt:

1. Plugin-Grundgerüst und Aktivierungslogik
2. CPTs:
   - `programmo_area` (Offener Bereich)
   - `programmo_person` (Personen)
   - `programmo_offer` (eigene lokale Angebote)
   - `programmo_slot` (Wochenplan Slots)
3. Taxonomie:
   - `programmo_segment`
4. Metaboxen:
   - Bereich: Farbe + Personen-Zuordnung (Mehrfachauswahl)
   - Angebot: Beschreibung + Personenzuordnung aus ProgrammO und optional OKJA-Personenpool
   - Slot: Wochentag, Zeit, Bereich, Taxonomie-Filter (`jugendarbeit`, `paedagogik`), Angebot aus Events_OKJA
5. Events_OKJA-Bridge:
   - Lokale ProgrammO-Angebote werden gemeinsam mit OKJA-Angeboten im Dashboard-Pool geführt
   - Priorisierte Post-Types: `angebot`, dann `angebotsevent`
   - Taxonomie-Slugs: `jugendarbeit`, `paedagogik` (und kompatibel mit `tage`)
   - Angebotsauswahl im Slot (kombiniert: Angebot + A-Event)
   - Harte Kopplung: Wenn `angebotsevent` gewählt ist, verlinkt ProgrammO automatisch auf das zugeordnete `angebot` (`jhh_event_angebot_id`)
   - Lokale Angebote bleiben linklos, OKJA-Angebote behalten ihren Single-Link im Frontend
6. Shortcode:
   - `[programmo_weekplan]`
   - Responsive Darstellung
   - Details aufklappbar (`<details>`)
   - Personen- und Taxonomie-Badges auf Angebotskacheln

## Dashboard-Workflow

1. Eigene Angebote können direkt im ProgrammO-Dashboard in der Angebots-Sidebar angelegt werden.
2. Dabei sind Titel, Kurzbeschreibung und Personenzuordnung möglich.
3. Personen können aus `programmo_person` und, wenn vorhanden, zusätzlich aus dem OKJA-Personenpool kommen.
4. Neue lokale Angebote erscheinen sofort im Angebots-Pool und können per Drag & Drop Slots zugeordnet werden.

## Installation

1. Ordner als Plugin in WordPress einbinden (`wp-content/plugins/programmo`).
2. Plugin aktivieren.
3. Inhalte anlegen:
   - Offene Bereiche
   - Personen
   - Angebote (optional lokal in ProgrammO oder extern über Events_OKJA)
   - Wochenplan Slots
4. Seite erstellen und Shortcode `[programmo_weekplan]` einfügen.

## Geplante nächste Schritte

1. Exakte Contract-Integration mit `Events_OKJA` (finaler Post Type + Taxonomie-Mapping)
2. Sortierung nach Wochentag + Zeit in stabiler Reihenfolge
3. Admin-Verbesserung (Filter, Schnellzuordnung, Konfliktwarnungen)
4. Optionaler Gutenberg-Block statt nur Shortcode
5. Optionales JSON-API Endpoint für App/Display-Ausspielung
