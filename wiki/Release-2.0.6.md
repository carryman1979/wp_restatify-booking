# Release 2.0.6

Version 2.0.5 vereinheitlicht die Shared-Aufloesung zwischen lokaler Entwicklung und Live-Deployments.

## Highlights

- Shared-Resolver nutzt lokal bevorzugt Root-Shared (`wp_restatify-shared/src/*`).
- Ohne Root-Shared wird nur die benoetigte Version aus `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (oder MU-Plugins) geladen.
- Verbleibende UI-Referenzen auf shared Mail-Editor verwenden jetzt die aufgeloeste Shared-Base-URL.
- Copilot-Repo-Beschreibung auf die Shared-Loader-Policy aktualisiert.

## Release-prep refresh (2026-05-30)

- Kein Versionssprung: Release-Prep verbleibt auf `2.0.6`.
- UI-/Theme-Polish (inkl. Dark-Theme-Aspekten) und Admin-Flow-Aufraeumarbeiten nachgezogen.
- API-Client-/Options-/UI-Refactorings und Test-Baseline fuer den koordinierten Rollout abgeglichen.

## Kompatibilitaet

- Plugin-Version: `2.0.6`
- Keine beabsichtigten Breaking Changes

## Artefakt

- `wp_restatify-booking-2.0.5.zip`

