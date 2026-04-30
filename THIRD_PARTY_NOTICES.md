# THIRD_PARTY_NOTICES

Status: release-ready documentation baseline.
Last updated: 2026-04-30

## Scope
This plugin is mostly custom code. No bundled third-party JS/CSS library packages were identified in plugin assets at the time of writing.

## Third-Party Components

| Component | Usage | License | Action |
|---|---|---|---|
| WordPress Core APIs | Runtime platform dependency (not bundled by this plugin) | GPL-2.0-or-later (WordPress project) | Keep plugin license compatible |
| Optional external plugins (integration only) | Runtime integrations (for example chat overlay or maintenance mode plugins) | Not bundled in this repository | Document compatibility only |

## Verification Checklist Before Public Switch

- Re-check plugin assets if new external JS/CSS/fonts are added.
- If any third-party code is vendored later, add exact license and source URL here.
- Keep this file updated per release when dependencies change.

## Maintainer Note

This file is a legal/compliance helper, not legal advice.
