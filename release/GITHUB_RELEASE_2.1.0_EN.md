# Restatify Booking Assistant 2.1.0

## What's new

- Booking autoresponder now generates calendar attachments with a stable `.ics` extension (instead of exposing `.tmp` filenames in mail clients).
- Hardened ICS output (escaping + line folding) for better calendar client compatibility.

## Test status

- PHPUnit suite passed with PHP `8.4.2`.
- New regression coverage: `BookingAutoresponderIcsTest`.

## Compatibility

- Plugin version: `2.1.0`
- No migration required.

## Artifact

- `wp_restatify-booking-2.1.0.zip`
