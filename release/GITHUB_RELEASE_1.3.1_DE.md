Title: Restatify Booking Assistant 1.3.1

## Highlights

- Deutlich aufgeräumtere Admin-Oberfläche mit klar getrennten Bereichen für API-Verbindung, Buchungslogik, Kalender und Kontaktkanäle
- Kontaktkanäle jetzt als strukturierte Zeilenpflege statt Rohtext und direkt in den Basis-Einstellungen platziert
- Wöchentliche Verfügbarkeiten als Montag-bis-Sonntag-Editor mit aktivierbaren Tagen und beliebig vielen Zeitfenstern pro Tag
- E-Mail-Vorlagen für Bestätigung, Inhaber-Mail und Storno jetzt in Overlay-Popups mit WYSIWYG-Editor statt als lange Inline-Blöcke
- Verbesserte Standardtexte und Betreffzeilen für Buchungs- und Stornomails mit rückwärtskompatibler Aktualisierungslogik

## Kompatibilität

- Plugin-Version: `1.3.1`
- Empfohlene API-Version: `1.2.0`

## Validierung

- PHP-Dateien der geänderten Admin- und Optionslogik ohne Editorfehler geprüft
- Bestehende Legacy-Rohformate für Verfügbarkeiten und Kontaktkanäle bleiben als Fallback erhalten
- Layout und Struktur iterativ anhand der Admin-Ansicht nachgeschärft

## Release-Hinweis

- Diese Version fokussiert sich auf Admin-UX, Wartbarkeit und sauberere Konfiguration ohne Breaking Changes an den gespeicherten Optionsformaten