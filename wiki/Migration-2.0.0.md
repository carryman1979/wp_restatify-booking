# Migration 2.0.0 (Rename + Shared)

## Zielbild

- Produktname: Restatify-Booking
- Neuer Plugin-Slug: wp_restatify-booking
- Alt-Slug: wp_restatify-booking-assistant
- Website: https://www.restatify.tech
- Shared-Package: wp_restatify-shared (public, GPL-2.0-or-later)

## Release-Entscheidungen

- Major-Release: 2.0.0
- Migration nur fuer Einstellungen
- Logs/Historie werden nicht migriert
- Migrationsdialog direkt nach erfolgreicher Migration
- Default-Auswahl im Dialog: Alte Plugins behalten
- Dialog-Sprachen: DE/EN (Default DE)

## Technische Migrationsregeln

1. Beim ersten Start von wp_restatify-booking pruefen, ob Altplugin-Daten vorhanden sind.
2. Einstellungen aus restatify_booking_assistant_options nach neuem Ziel-Key uebernehmen.
3. Backup der Quelldaten vor dem Schreiben anlegen.
4. Migration idempotent gestalten (mehrfaches Ausfuehren ohne Seiteneffekte).
5. Migrationsstatus in eigener Option speichern.

## Nach erfolgreicher Migration

Admin-Hinweis mit Aktionen:

- Alte Plugins behalten (Default)
- Alte Plugins deaktivieren und entfernen

Warnhinweis:

- Logs/Historie werden nicht migriert und koennen beim Entfernen verfallen.

## Shared-Package-Regel

- Plugins teilen Shared-Code nur bei exakt gleicher Shared-Version.
- Bei Versionsabweichung laedt jedes Plugin seine eigene kompatible Shared-Version.
- Keine globale unversionierte Singleton-Ladung.

## Rollback

1. Neues Plugin deaktivieren.
2. Altes Plugin aktiv lassen oder wieder aktivieren.
3. Backup-Optionen zurueckspielen (wenn erforderlich).

## Operator-Checkliste

1. Vor Update Datenbank-Backup erstellen.
2. Update auf 2.0.0 einspielen.
3. Migrationsergebnis im Admin pruefen.
4. Testbuchung plus Mailversand pruefen.
5. Entscheidung im Migrationsdialog treffen.
