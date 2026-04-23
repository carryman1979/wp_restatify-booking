# Release 1.3.2

Version 1.3.2 fokussiert die Integration des Booking-Assistants mit LightStart (WP Maintenance Mode), damit das Buchungs-Overlay im Wartungsfall kontrolliert und konfigurierbar unterdrueckt werden kann.

## Highlights

- Neue Option in den Plugin-Einstellungen: `Bei LightStart-Wartung ausblenden`
- Standardverhalten: Option ist aktiv (AN)
- Option wird nur angezeigt, wenn LightStart installiert und aktiv ist
- Unterdrueckung greift konsistent fuer:
  - Asset-Enqueue
  - Globales Overlay-Rendering
  - Shortcode-Ausgabe

## Technische Einordnung

Die Wartungsmodus-Pruefung erfolgt plugin-intern ueber die LightStart-Option `wpmm_settings.general.status`.

Zusatzlogik:

- LightStart-Statuspruefung nur, wenn Plugin `wp-maintenance-mode` wirklich verfuegbar und aktiv ist
- Beruecksichtigung von Single-Site und Multisite-Netzwerkaktivierung

## Dokumentation

- README um Abschnitt zur LightStart-Integration erweitert
- Changelog auf 1.3.2 angehoben
- Quellcode-Dokumentation in Options- und UI-Klasse ergaenzt

## Kompatibilitaet

- Plugin-Version: 1.3.2
- Empfohlene API-Version: unveraendert kompatibel zu 1.3.1-Stand
- Keine Breaking Changes in bestehenden Option-Formaten

## Fazit

Release 1.3.2 verbessert die Betriebssteuerung in Wartungsfenstern deutlich: Der Booking Assistant bleibt standardmaessig im LightStart-Wartungsmodus ausgeblendet, kann aber ueber die Plugin-Option bewusst angepasst werden.
