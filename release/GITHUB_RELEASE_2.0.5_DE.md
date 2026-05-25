# Restatify Booking Assistant 2.0.5

## Neu in diesem Release

- Shared-Resolver priorisiert lokales Root-Shared (`wp_restatify-shared/src/*`) fuer Entwicklungsumgebungen.
- Wenn Root-Shared fehlt, wird exakt die benoetigte Version aus `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (oder MU-Plugins) geladen.
- UI-Referenz auf den Shared Mail-Template-Editor nutzt die aufgeloeste Shared-Base-URL.
- Copilot-Repo-Richtlinie auf die Shared-Loader-Reihenfolge abgestimmt.

## Kompatibilitaet

- Plugin-Version: `2.0.5`
- Keine Migration erforderlich.

## Artefakt

- `wp_restatify-booking-2.0.5.zip`
