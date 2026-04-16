Title: Restatify Booking Assistant 1.3.0

## Highlights

- Public cancellation flow with secure cancel tokens, nonce protection, and captcha confirmation
- Subscriber cancellation confirmations and optional owner cancellation notifications
- Branded HTML mail defaults with footer, disclaimer, machine-generated note, and environmental note
- Automatic Restatify theme branding for mail logo and CI colors, with plugin fallback branding when the theme is inactive
- Localized booking and cancellation times in outgoing mails
- Improved backend error mapping so FastAPI validation failures no longer surface as the generic string "Array"

## Compatibility

- Plugin version: `1.3.0`
- Recommended API version: `1.2.0`

## Validation

- Local smoke-tested against WordPress, Booking API, and Mailpit
- Booking confirmation, cancellation confirmation, and owner cancellation notification verified locally
- Release archive rebuilt and checked to exclude Git metadata and nested release artifacts

## Included Artifact

- `wp-restatify-booking-assistant-1.3.0.zip`