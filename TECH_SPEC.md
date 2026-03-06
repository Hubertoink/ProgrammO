# Technische Spezifikation: ProgrammO ↔ Events_OKJA

## 1) Scope

ProgrammO ist die Wochenplan-Schicht. Events_OKJA bleibt für externe Angebotsinhalte und Single-Templates relevant, lokale Angebote können aber zusätzlich direkt in ProgrammO gepflegt werden.

## 2) Datenobjekte

### `programmo_area` (Offener Bereich)

- Titel (`post_title`)
- Beschreibung (`post_content`)
- `_programmo_area_color` (Hex)
- `_programmo_area_people` (Array von `programmo_person` IDs)

### `programmo_person` (Person)

- Titel (`post_title`)
- Beschreibung (`post_content`)

### `programmo_offer` (Lokales Angebot)

- Titel (`post_title`)
- Beschreibung (`post_content`)
- `_programmo_offer_programmo_people` (Array von `programmo_person` IDs)
- `_programmo_offer_okja_people` (Array von `person` IDs aus OKJA-Personenpool)
- `_programmo_event_color` (optionale Farbe / Verlauf für Angebotskachel)

### `programmo_slot` (Wochenplan Slot)

- Titel (`post_title`)
- `_programmo_weekday` (`monday`…`sunday`)
- `_programmo_start_time` (`HH:MM`)
- `_programmo_end_time` (`HH:MM`)
- `_programmo_area_id` (`programmo_area` ID)
- `_programmo_event_id` (Event-ID aus Events_OKJA)

### Taxonomie `programmo_segment`

- Hierarchisch für Bereich/Slot-Klassifikation

## 3) Integrationsvertrag mit Events_OKJA

### Bestätigte Slugs aus OKJA_Angebote

- Post Type Angebot: `angebot`
- Post Type A-Event: `angebotsevent`
- Taxonomie Jugendarbeit: `jugendarbeit`
- Taxonomie Pädagogik: `paedagogik`
- Legacy Taxonomie: `tage` (in OKJA als veraltet geführt)

Zusätzlicher relevanter Meta-Key in OKJA-Events:

- `jhh_event_angebot_id` (Verknüpfung Event → Angebot)

### Lesend (MVP)

- ProgrammO führt lokale `programmo_offer` gemeinsam mit externen `angebot`/`angebotsevent` im Dashboard.
- ProgrammO priorisiert für externe Quellen `angebot`, fallback `angebotsevent`.
- ProgrammO liest verfügbare Angebote für Slot-Zuordnung aus beiden Quellen.
- ProgrammO verlinkt im Frontend nur externe OKJA-Angebote; lokale Angebote bleiben ohne Single-Link.
- ProgrammO bietet in Slots Taxonomie-Filter für `jugendarbeit` und `paedagogik`.

### Erwartete Stabilisierung (empfohlen)

Events_OKJA stellt eine eindeutige Konstante/Filter bereit, z. B.:

- Konstante: `EVENTS_OKJA_POST_TYPE = okja_event`
- oder Filter: `events_okja/event_post_type`

Dann kann ProgrammO ohne Heuristik arbeiten.

## 4) WordPress Hooks (aktuell)

- `plugins_loaded` → Plugin-Boot
- `init` → CPT/Taxonomie-Registrierung
- `add_meta_boxes` → Admin-Metaboxen
- `save_post_programmo_area` → Bereich-Meta speichern
- `save_post_programmo_offer` → Angebots-Personen speichern
- `save_post_programmo_slot` → Slot-Meta speichern
- `admin_notices` → Hinweis, falls Events_OKJA fehlt
- `wp_enqueue_scripts` → Frontend-CSS
- `shortcode` `[programmo_weekplan]`

## 5) Geplante Erweiterungs-Hooks (nächster Schritt)

- `programmo/events/post_type` (Filter, Default: Auto-Erkennung)
- `programmo/events/query_args` (Filter für Angebotsliste)
- `programmo/events/link` (Filter für Event-Link)
- `programmo/slot/render_data` (Filter pro Kachel-Daten)

## 6) Frontend-Vertrag

Shortcode: `[programmo_weekplan]`

Ausgabe pro Slot:

- Tag + Uhrzeit
- Offener Bereich inkl. Personen
- Angebotstitel
- Link zur Event-Single (nur wenn vorhanden und extern)
- Personen-Badges aus lokalem Angebot oder OKJA-Taxonomie/Personenpool
- Aufklappbare Kurzinfos

## 7) Nächste technische Aufgaben

1. Eigene Sortierlogik nach Wochentagindex + Startzeit
2. Konfliktprüfung (Person doppelt in überlappenden Slots)
3. Sicheres Mapping zu finalen Events_OKJA Taxonomien
4. Optional REST-Endpoint (`/wp-json/programmo/v1/weekplan`)
