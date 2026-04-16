Restatify Booking Assistant 1.3.0 Deploy Checklist

1. Create a backup of the current plugin directory and active WordPress database.
2. Confirm the paired Booking API is at version `1.2.0` or newer.
3. Upload or replace the plugin with `wp-restatify-booking-assistant-1.3.0.zip`.
4. Open the plugin settings in WordPress admin and verify API URL, API key, calendar sources, and mail recipients.
5. If the Restatify base theme is active, verify custom logo and theme colors render correctly in HTML mails.
6. Send one test booking and confirm booking mail delivery in the target mail environment.
7. Open the generated cancellation link, cancel the booking, and verify cancellation confirmation plus optional owner notification.
8. If Polylang is active, review the newly registered mail strings and translations.
9. If any custom templates existed before upgrade, spot-check that saved templates were preserved.

Rollback

1. Restore the previous plugin build.
2. Clear any cached pages or object cache entries if used.
3. Re-test booking and cancellation once rollback is complete.