# WP Restatify Booking Assistant

WordPress-Plugin fuer manuelle Terminsuche und Reservierungs-Popup.

Version: 1.2.1

## Features

- Shortcode-Popup fuer Termin-Suche und Reservierung
- Globaler Buchungs-Overlay-Trigger ueber Link-Hash `#restatify-booking`
- Verbindung zur Restatify Booking API
- Grundkonfiguration (erforderlich): API-Endpunkt und API-Key
- Experteneinstellungen (optional): Sync-Intervall, Kalenderliste, woechentliche Verfuegbarkeitsfenster, Autoresponder-Text
- Kalenderquellen-Modi mit `private`/`official` und `general`/`holiday`
- Autoresponder-E-Mail mit ICS-Anhang
- Optionale KI-Helper-Funktion fuer Integration mit dem Chat-Overlay-Plugin
- Optionale Uebergabe von Chat-Ereignissen (bestaetigt/abgebrochen), wenn Multi Chat Overlay installiert ist
- Polylang-Registrierung fuer konfigurierbare Autoresponder-Texte
- CI-freundliches Styling ueber CSS-Variablen und Theme-Farbpresets

## Kompatibilitaet und Unabhaengigkeit

- Funktioniert standalone nur mit der Booking API (Multi Chat Overlay ist optional).
- Funktioniert mit Multi Chat Overlay fuer chat-ausgeloestes Oeffnen der Buchung und Status-Rueckmeldungen im Chat.
- Wenn Multi Chat Overlay nicht installiert ist, laeuft der Buchungsablauf normal weiter und Chat-Events werden sicher ignoriert.

## Installation

1. Plugin-Ordner nach wp-content/plugins/ hochladen
2. Plugin im WordPress-Admin aktivieren
3. Einstellungen unter Einstellungen > Booking Assistant konfigurieren
4. Shortcode [restatify_booking_popup] auf einer Seite platzieren
5. Optional: beliebigen WP-Link mit `#restatify-booking` verwenden, um das Overlay zu oeffnen

## API-Abhaengigkeit

Erfordert eine laufende Restatify Booking API Instanz.

## Architecture

Das Plugin ist in kleine Klassen mit klaren Verantwortlichkeiten aufgeteilt:

- `wp_restatify-booking-assistant.php`
	Bootstrap-Datei: laedt Klassen, startet Plugin, behaelt optionale KI-Helper-Funktion.
- `includes/class-restatify-booking-assistant-plugin.php`
	Composition Root: verdrahtet Services und registriert alle WordPress-Hooks.
- `includes/class-restatify-booking-assistant-constants.php`
	Gemeinsame Konstanten (Option-Key, Nonce-Action, Text-Domain usw.).
- `includes/class-restatify-booking-assistant-options.php`
	Settings-Lifecycle: Defaults, Sanitisierung, Parser-Helfer, uebersetzte Optionen.
- `includes/class-restatify-booking-assistant-api-client.php`
	Backend-API-Kommunikation: authentifizierte Requests und Sync-Config-Push.
- `includes/class-restatify-booking-assistant-booking-controller.php`
	AJAX-Buchungsendpunkte: Terminsuche und Reservierung mit Kontaktkanal-Validierung.
- `includes/class-restatify-booking-assistant-autoresponder.php`
	Bestaetigungs-E-Mail und ICS-Generierung.
- `includes/class-restatify-booking-assistant-ui.php`
	Frontend-Rendering/Assets und Rendering der Admin-Einstellungsseite.

### Public interfaces

Die oeffentlichen Methoden in den oben genannten Klassen sind mit kurzen PHPDoc-Bloecken dokumentiert.
Diese Dokumentation beschreibt Zweck, erwartete Payloads und Verhalten, damit kuenftige Aenderungen sicher umgesetzt werden koennen.

### Request flow

1. Frontend-JS sendet an `admin-ajax`.
2. `Booking_Controller` validiert Payload und kanalspezifische Kontaktdaten.
3. `Api_Client` sendet Reservierung an die Booking API.
4. `Autoresponder` versendet E-Mail + ICS mit kanalbezogenen Kontaktdetails.

### Why this split

- Kleinere Dateien sind einfacher zu reviewen und zu testen.
- Das Naming zeigt die Verantwortung (`Options`, `Api_Client`, `Booking_Controller`, `UI`).
- Aenderungen in einem Bereich (z.B. API-Payloads) erfordern nicht mehr automatisch Aenderungen im Rendering.

## Polylang

Wenn Polylang aktiv ist, werden konfigurierbare Booking-Autoresponder-Texte in der Uebersetzungsgruppe registriert:

- `Restatify Booking Assistant`

Fields:

- Booking autoresponder subject
- Booking autoresponder body

Uebersetzung unter: Languages > Translations.

## CI styling hooks

Overlay-Styles sind theme-freundlich und koennen mit CSS-Variablen ueberschrieben werden:

- `--rs-booking-accent`
- `--rs-booking-accent-contrast`
- `--rs-booking-surface`
- `--rs-booking-text`
- `--rs-booking-border`
- `--rs-booking-slot-bg`

Defaults mappen, wo verfuegbar, auf gaengige WordPress-Preset-Variablen (`--wp--preset--color--*`).

## Sync configuration format

In den Plugin-Einstellungen werden Kalender zeilenweise definiert:

`calendar_id|Label|private|general`

or

`calendar_id|Label|official|holiday`

Woechentliche Verfuegbarkeitszeilen:

`mo|09:00-12:00,13:00-17:00`

## Changelog

### 1.2.2

- Monolithische Plugin-Datei in modulare Klassen unter `includes/` refaktoriert.
- Architektur-Dokumentation mit klaren Klassenverantwortlichkeiten und Ablauf ergaenzt.

### 1.2.1

- Klarere Beschreibungen fuer Admin-Felder (Zeitzone, Dauer, Suchzeitraum und Autoresponder-Felder) hinzugefuegt.

### 1.2.0

- Support-seitigen Trigger zum Oeffnen der Buchung ueber den Multi Chat Overlay Posteingang hinzugefuegt.
- Uebergabe von Buchungsbestaetigung/-abbruch als Chat-Ereignis hinzugefuegt.
- Expliziten Abbrechen-Button und verbessertes Abbruch-Handling hinzugefuegt.
- Polylang-Registrierung und Laufzeit-Uebersetzung fuer Autoresponder-Texte hinzugefuegt.
- Overlay-Styles auf Site-CI/Theme-Farben per CSS-Variablen ausgerichtet.
- Optionalen globalen Hash-basierten Oeffnungs-Trigger (`#restatify-booking`) hinzugefuegt.
