# Release 1.3.3

Version 1.3.3 erweitert den operativen Betrieb des Booking-Assistants um direkte Statussichtbarkeit und einen manuellen Re-Sync-Trigger.

## Highlights

- Neuer Button `Force Sync jetzt ausfuehren` auf der Einstellungsseite
- Dashboard-Widget `Booking API Status` in wp-admin
- Live-Pruefung von:
  - API-Erreichbarkeit ueber `/health`
  - API-Key-Autorisierung ueber `/v1/config/sync`
  - Anzahl konfigurierter `calendar_sources`

## Technische Einordnung

Der Force-Sync nutzt eine dedizierte `admin_post`-Action mit:

- Nonce-Pruefung
- Capability-Pruefung (`manage_options`)
- Rueckmeldung ueber Admin-Notice

Das Dashboard-Widget zeigt Verbindungszustand als Ampelstatus:

- `Connected` (gruen)
- `Needs Attention` (gelb)
- `Disconnected` (rot)

## Dokumentation

- README auf 1.3.3 angehoben
- Changelog fuer 1.3.3 ergaenzt

## Kompatibilitaet

- Plugin-Version: 1.3.3
- Empfohlene API-Version: 1.2.2
- Keine Breaking Changes in bestehenden Option-Formaten
