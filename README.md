# WP Restatify Booking Assistant

WordPress-Plugin fuer manuelle Terminsuche und Reservierungs-Popup.

Version: 1.3.2

## Features

- Shortcode-Popup fuer Termin-Suche und Reservierung
- Globaler Buchungs-Overlay-Trigger ueber Link-Hash `#restatify-booking`
- Verbindung zur Restatify Booking API
- Grundkonfiguration (erforderlich): API-Endpunkt und API-Key
- Experteneinstellungen (optional): Sync-Intervall, Kalenderliste, woechentliche Verfuegbarkeitsfenster, Autoresponder-Text
- Kalenderquellen-Modi mit `private`/`official` und `general`/`holiday`
- Branded HTML-/Text-Mails fuer Reservierung, Stornobestaetigung und interne Benachrichtigungen
- Theme-aware Mail-Branding: nutzt bei aktivem Restatify-Theme Logo und CI-Farben, sonst Platzhalter-Logo und Standardfarben
- Oeffentliche Storno-Seite mit Nonce, Rechenaufgabe und API-gestuetzter Termin-Stornierung
- Autoresponder-E-Mail mit ICS-Anhang
- Optionale KI-Helper-Funktion fuer Integration mit dem Chat-Overlay-Plugin
- Optionale Uebergabe von Chat-Ereignissen (bestaetigt/abgebrochen), wenn Multi Chat Overlay installiert ist
- Polylang-Registrierung fuer konfigurierbare Autoresponder-Texte
- CI-freundliches Styling ueber CSS-Variablen und Theme-Farbpresets

## Kompatibilitaet und Unabhaengigkeit

- Funktioniert standalone nur mit der Booking API (Multi Chat Overlay ist optional).
- Funktioniert mit Multi Chat Overlay fuer chat-ausgeloestes Oeffnen der Buchung und Status-Rueckmeldungen im Chat.
- Wenn Multi Chat Overlay nicht installiert ist, laeuft der Buchungsablauf normal weiter und Chat-Events werden sicher ignoriert.

## LightStart-Wartungsmodus (Integration)

- In den Plugin-Einstellungen gibt es die Option `Bei LightStart-Wartung ausblenden`.
- Standardwert ist `AN`.
- Die Option wird nur angezeigt, wenn LightStart (`wp-maintenance-mode`) installiert und aktiviert ist.
- Ist LightStart nicht installiert oder nicht aktiv, wird diese Option nicht angezeigt und das Booking-Overlay bleibt sichtbar.
- Ist LightStart aktiv und Wartungsmodus eingeschaltet, wird das Booking-Overlay bei aktivierter Option automatisch unterdrueckt.

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
	Bestaetigungs-, Storno- und interne E-Mails mit HTML/Text-Fallback und ICS-Generierung.
- `includes/class-restatify-booking-assistant-cancellation-controller.php`
	Oeffentliche Storno-Seite mit Formular, Captcha, API-Call und Versand der Stornobestaetigung.
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
5. `Cancellation_Controller` verarbeitet den Storno-Link und versendet Stornobestaetigungen nach erfolgreichem API-Call.

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
- Booking autoresponder HTML body
- Booking owner notification subject/body
- Booking cancellation confirmation subject/body
- Booking owner cancellation subject/body

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

### 1.3.2

- LightStart-Integration fuer Wartungsmodus hinzugefuegt.
- Neue Option `Bei LightStart-Wartung ausblenden` in den Plugin-Einstellungen (Standard: AN).
- Option nur sichtbar, wenn LightStart (`wp-maintenance-mode`) installiert und aktiv ist.
- Booking-Overlay (Assets, Shortcode, Global-Overlay) wird bei aktivem LightStart-Wartungsstatus optional unterdrueckt.
- Dokumentation fuer Betrieb mit LightStart erweitert.

### 1.3.1

- Admin-Oberflaeche fuer Kalender-, Verfuegbarkeits- und Kontaktkanalpflege weiter aufgeraeumt und strukturiert.
- Fehlerbehandlung im Buchungs-Popup verbessert, inklusive Rueckweg zur Terminauswahl bei fehlgeschlagener Reservierung.
- Hash-Link-Verhalten fuer das Buchungs-Overlay auf derselben Seite und ueber Navigationslinks stabilisiert.
- Empfohlene API-Kombination auf Booking API 1.2.1 angehoben, damit Feiertags- und Live-Kalenderpruefung konsistent zusammenarbeiten.

### 1.3.0

- Lesbare lokale Zeitdarstellung in den Buchungs- und Stornomails hinzugefuegt.
- Stornobestaetigung fuer Interessenten und optionale interne Stornobenachrichtigung hinzugefuegt.
- Branded HTML-Default-Templates mit Footer, Disclaimer, Maschinenhinweis und Umwelthinweis hinzugefuegt.
- Restatify-Theme Branding fuer Mails (Custom Logo + CI-Farben) mit Fallback auf Platzhalter-Logo und Standardfarben implementiert.

### 1.2.2

- Monolithische Plugin-Datei in modulare Klassen unter `includes/` refaktoriert.
- Architektur-Dokumentation mit klaren Klassenverantwortlichkeiten und Ablauf ergaenzt.
- Konfigurierbares oeffentliches Rate-Limiting fuer anonyme Slot-Suche und Reservierung hinzugefuegt.

### 1.2.1

- Klarere Beschreibungen fuer Admin-Felder (Zeitzone, Dauer, Suchzeitraum und Autoresponder-Felder) hinzugefuegt.

### 1.2.0

- Support-seitigen Trigger zum Oeffnen der Buchung ueber den Multi Chat Overlay Posteingang hinzugefuegt.
- Uebergabe von Buchungsbestaetigung/-abbruch als Chat-Ereignis hinzugefuegt.
- Expliziten Abbrechen-Button und verbessertes Abbruch-Handling hinzugefuegt.
- Polylang-Registrierung und Laufzeit-Uebersetzung fuer Autoresponder-Texte hinzugefuegt.
- Overlay-Styles auf Site-CI/Theme-Farben per CSS-Variablen ausgerichtet.
- Optionalen globalen Hash-basierten Oeffnungs-Trigger (`#restatify-booking`) hinzugefuegt.

