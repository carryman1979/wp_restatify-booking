# Restatify Booking Assistant 2.0.5

## What's new

- Shared resolver now prioritizes local root shared (`wp_restatify-shared/src/*`) in development environments.
- If root shared is unavailable, it loads only the exact required shared version from `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (or MU-plugins).
- The UI reference to the shared mail-template editor now uses the resolved shared base URL.
- Copilot repo guidance was aligned with the shared loader order policy.

## Compatibility

- Plugin version: `2.0.5`
- No migration required.

## Artifact

- `wp_restatify-booking-2.0.5.zip`
