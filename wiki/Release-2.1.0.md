# Release 2.1.0

Version 2.1.0 behebt den fehlerhaften Kalender-Mailanhang in Buchungsbestaetigungen.

## Highlights

- Booking-Autoresponder erzeugt ICS-Anhaenge mit stabiler `.ics`-Dateiendung statt temporaerer `.tmp`-Datei.
- ICS-Inhalt nutzt robustes Escaping/Folding fuer bessere Kompatibilitaet in Kalender-Clients.

## Tests

- PHPUnit-Suite erfolgreich mit PHP 8.4.2 (Laragon) ausgefuehrt.
- Neuer Regressionstest `BookingAutoresponderIcsTest` deckt Dateiendung und VEVENT-Basiskonsistenz ab.

## Kompatibilitaet

- Plugin-Version: `2.1.0`
- Keine beabsichtigten Breaking Changes

## Artefakt

- `wp_restatify-booking-2.1.0.zip`
