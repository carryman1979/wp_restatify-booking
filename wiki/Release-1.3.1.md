# Release 1.3.1

Version 1.3.1 konzentriert sich auf die Überarbeitung der WordPress-Admin-Oberfläche und die klarere Pflege zentraler Buchungsparameter.

## Highlights

- Deutlich aufgeräumtere Admin-Oberfläche mit klar getrennten Bereichen für API-Verbindung, Buchungslogik, Kalender und Kontaktkanäle
- Kontaktkanäle jetzt als strukturierte Zeilenpflege statt Rohtext und direkt in den Basis-Einstellungen platziert
- Wöchentliche Verfügbarkeiten als Montag-bis-Sonntag-Editor mit aktivierbaren Tagen und beliebig vielen Zeitfenstern pro Tag
- E-Mail-Vorlagen für Bestätigung, Inhaber-Mail und Storno jetzt in Overlay-Popups mit WYSIWYG-Editor statt als lange Inline-Blöcke
- Verbesserte Standardtexte und Betreffzeilen für Buchungs- und Stornomails mit rückwärtskompatibler Aktualisierungslogik

## Technische Einordnung

Diese Version verändert bewusst vor allem die Konfigurations- und Pflegeoberfläche im WordPress-Backend.

Wichtig dabei ist, dass bestehende gespeicherte Formate nicht hart gebrochen wurden. Legacy-Rohformate für Verfügbarkeiten und Kontaktkanäle bleiben erhalten und dienen weiterhin als Fallback.

## Kompatibilität

- Plugin-Version: 1.3.1
- Empfohlene API-Version: 1.2.0

## Validierung

- Geänderte PHP-Dateien der Admin- und Optionslogik wurden ohne Editorfehler geprüft
- Bestehende Legacy-Rohformate bleiben verfügbar
- Layout und Struktur wurden iterativ anhand der Admin-Ansicht nachgeschärft

## Fazit

Release 1.3.1 ist in erster Linie ein UX- und Wartbarkeitsrelease. Das Ziel ist eine deutlich sauberere Administration ohne Breaking Changes an den gespeicherten Optionsdaten.