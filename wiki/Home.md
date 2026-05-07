# Restatify Booking Assistant

Produktname (ab 2.0.0): Restatify-Booking  
WordPress-Slug (ab 2.0.0): wp_restatify-booking  
Website: https://www.restatify.tech

Der Restatify Booking Assistant verbindet WordPress mit der Restatify Booking API und stellt eine geführte Terminbuchung direkt auf der Website bereit.

Das Plugin ist dafür ausgelegt, freie Termine aus der API zu suchen, Reservierungen anzulegen, Bestätigungs- und Stornomails zu versenden und die Konfiguration in WordPress möglichst wartbar abzubilden.

## Kernfunktionen

- Terminbuchung direkt im WordPress-Frontend
- Anbindung an die Restatify Booking API
- Kalenderquellen und Zielkalender für Schreibzugriffe
- Öffentlicher Storno-Flow mit Token-basierter Absage
- Bestätigungs-, Inhaber- und Stornomails
- ICS-Erzeugung für Buchungsbestätigungen
- Strukturierte Kontaktkanäle im Buchungsdialog
- Wöchentliche Verfügbarkeiten mit mehreren Zeitfenstern pro Tag

## Admin-Konfiguration

Die aktuelle Admin-Oberfläche ist in klar getrennte Bereiche gegliedert:

- API-Verbindung
- Buchungslogik und Schutzmechanismen
- Kalender und Verfügbarkeiten
- Kontaktkanäle
- Experteneinstellungen für Synchronisierung und Mail-Templates

Dadurch ist die Konfiguration deutlich übersichtlicher als in früheren Versionen, in denen mehrere Bereiche stärker über Rohtextfelder und lange Formularblöcke abgebildet wurden.

## Kontaktkanäle

Kontaktkanäle werden als strukturierte Zeilen gepflegt. Für jeden Kanal können folgende Angaben definiert werden:

- Schlüssel
- Bezeichnung
- Eingabetyp
- Platzhalter
- Wertbezeichnung
- ICS-Vorlage

Die Kontaktkanäle liegen direkt in den Basis-Einstellungen und nicht mehr im Expertenbereich.

## Verfügbarkeiten

Die wöchentlichen Verfügbarkeiten werden nicht mehr nur als Rohtext gepflegt, sondern als strukturierter Editor von Montag bis Sonntag.

Für jeden Wochentag kann festgelegt werden:

- ob der Tag aktiv ist
- welche Zeitfenster verfügbar sind
- wie viele Zeitfenster an diesem Tag erlaubt sind

Bestehende Legacy-Rohformate bleiben als Fallback erhalten, damit ältere Installationen kompatibel bleiben.

## E-Mail-Templates

Die Mail-Vorlagen für folgende Fälle werden über Overlay-Popups mit WYSIWYG-Editor gepflegt:

- Terminbestätigung
- Inhaber-Benachrichtigung
- Stornobestätigung

Dadurch bleibt die Hauptseite schlank, ohne dass Text- und HTML-Versionen der Mails verloren gehen.

## Ziel der aktuellen Entwicklung

Die jüngsten Änderungen fokussieren sich auf:

- bessere Admin-UX
- klarere Konfigurationsstruktur
- geringere Fehleranfälligkeit bei der Pflege
- rückwärtskompatible Weiterentwicklung bestehender Optionsformate

## Releases

- [Release 1.3.3](Release-1.3.3)
- [Release 1.3.2](Release-1.3.2)
- [Release 1.3.1](Release-1.3.1)