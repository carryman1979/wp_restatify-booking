# WP Restatify Booking Assistant

WordPress plugin for manual appointment slot search and reservation popup.

Version: 1.2.0

## Features

- Shortcode popup for slot lookup and reservation
- Global booking overlay trigger via link hash `#restatify-booking`
- Connects to Restatify Booking API
- Core settings (required): API endpoint and API key
- Expert settings (optional): sync interval, calendar list, weekly availability windows, autoresponder text
- Calendar source modes include `private`/`official` and `general`/`holiday`
- Autoresponder email with ICS attachment
- Optional AI helper function for chat-overlay plugin integration
- Optional chat event handover (confirmed/cancelled) when Multi Chat Overlay is installed
- Polylang registration for configurable autoresponder texts
- CI-friendly styling via CSS variables and theme color presets

## Compatibility and independence

- Works standalone with only Booking API (Multi Chat Overlay is optional).
- Works with Multi Chat Overlay for chat-triggered booking open and booking status feedback in chat.
- If Multi Chat Overlay is not installed, booking flow continues normally and chat event calls are ignored safely.

## Installation

1. Upload plugin folder into wp-content/plugins/
2. Activate plugin in WordPress admin
3. Configure settings under Settings > Booking Assistant
4. Place shortcode [restatify_booking_popup] on a page
5. Optional: use any WP link with `#restatify-booking` to open the overlay

## API dependency

Requires a running Restatify Booking API instance.

## Polylang

When Polylang is active, configurable booking autoresponder texts are registered in translation group:

- `Restatify Booking Assistant`

Fields:

- Booking autoresponder subject
- Booking autoresponder body

Translate via: Languages > Translations.

## CI styling hooks

Overlay styles are theme-friendly and can be overridden with CSS variables:

- `--rs-booking-accent`
- `--rs-booking-accent-contrast`
- `--rs-booking-surface`
- `--rs-booking-text`
- `--rs-booking-border`
- `--rs-booking-slot-bg`

Defaults map to common WordPress preset variables where available (`--wp--preset--color--*`).

## Sync configuration format

In plugin settings, define calendars one per line:

`calendar_id|Label|private|general`

or

`calendar_id|Label|official|holiday`

Weekly availability lines:

`mo|09:00-12:00,13:00-17:00`

## Changelog

### 1.2.0

- Added support-side booking open trigger flow through Multi Chat Overlay inbox.
- Added booking confirmation/cancellation chat event handover.
- Added explicit cancel button and improved cancellation handling.
- Added Polylang registration and runtime translation for autoresponder texts.
- Updated overlay styles to align with site CI/theme colors via CSS variables.
- Added optional global hash-based open trigger (`#restatify-booking`).
