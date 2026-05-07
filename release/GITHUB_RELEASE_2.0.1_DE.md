# WP Restatify Booking 2.0.1

## Was ist neu

- Bootstrap-Haertung fuer gemischte Legacy/Neu-Deployments hinzugefuegt.
- Legacy-Booking-Assistant-Aktivierungseintraege werden bei Koexistenz beider Generationen automatisch entfernt.
- Geschuetztes Laden plus Fallback fuer den Shared-Migration-Notice-Manager eingebaut.
- Redeclare-Schutz fuer Plugin-Bootstrap-Klassen/Funktionen hinzugefuegt.

## Warum

Damit werden Aktivierungs-/Runtime-Fatals in Umgebungen verhindert, in denen Legacy- und refaktorierte Plugin-Generation parallel vorhanden sind oder Deployments unvollstaendig sind.

## Kompatibilitaet

- Plugin-Version: 2.0.1
- WordPress getestet bis 6.9
- Keine manuelle Einstellungsmigration erforderlich

## Artefakt

- wp_restatify-booking-2.0.1.zip
