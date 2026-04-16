Restatify Booking Assistant: current local changes on main

- Version bump from 1.2.x to 1.3.0 in the plugin bootstrap and README.
- Added a public cancellation controller with token-based cancellation page rendering, nonce protection, captcha validation, and cancellation confirmation handling.
- Extended the booking flow to pass API cancel tokens into a generated cancellation URL for outgoing mails.
- Expanded autoresponder support to include branded HTML/text booking mails, subscriber cancellation mails, and optional owner booking/cancellation notifications.
- Added dynamic mail branding defaults that adopt Restatify theme logo and palette when the base theme is active, with plugin fallback branding otherwise.
- Extended plugin settings UI with richer mail editors, placeholder insertion, owner-notification settings, and structured calendar-source rows.
- Improved frontend validation and wizard UX in booking JavaScript and CSS, including disabled/ready states and inline validation feedback.
- Fixed API error handling so structured FastAPI validation payloads are flattened into readable messages instead of surfacing as "Array".
- Added release documentation and cleaned release packaging for the 1.3.0 archive.