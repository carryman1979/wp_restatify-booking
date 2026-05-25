# Copilot Instructions for wp_restatify_booking

Shared baseline:
- https://github.com/carryman1979/wp_restatify-shared/blob/main/docs/ai/copilot-instructions.shared.md

Repo-specific requirements:
- Do not break global booking trigger hash #restatify-booking.
- Keep WordPress link picker compatibility for booking overlay entries.
- Do not remove nonce/security checks from booking AJAX flows.
- Keep shared loader order stable: local root shared first for dev, otherwise exact versioned shared under plugins/mu-plugins, never mixed in one request.

Required checks:
- composer run test:unit:php
- If trigger or link picker logic changed, verify booking trigger selection in editor flow.
