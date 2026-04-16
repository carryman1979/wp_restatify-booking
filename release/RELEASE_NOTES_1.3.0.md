Restatify Booking Assistant 1.3.0

- Adds public cancellation flow with secure cancel tokens, nonce protection, and captcha confirmation.
- Sends subscriber cancellation confirmations and optional owner cancellation notifications.
- Introduces branded HTML mail defaults with footer, disclaimer, machine-generated note, and environmental note.
- Uses Restatify theme logo and palette automatically when the Restatify base theme is active, with a plugin fallback logo otherwise.
- Formats booking and cancellation times in outgoing mails using the configured local timezone.
- Improves API error handling so structured backend validation errors no longer surface as the generic string "Array".

Packaging notes

- Plugin version: 1.3.0
- Requires the Booking API release 1.2.0 for cancellation response enrichment.
- Local smoke-tested against WordPress at http://localhost/restatify.tech/, API at http://127.0.0.1:8088, and Mailpit at http://localhost:8025.