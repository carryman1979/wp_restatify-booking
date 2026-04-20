Title: Restatify Booking Assistant 1.3.1

## Highlights

- Cleaner admin experience with dedicated sections for API connection, booking logic, calendars, and contact channels
- Contact channels moved into the basic settings area and upgraded from raw text to a structured row editor
- Weekly availability redesigned into a Monday-Sunday editor with per-day toggles and unlimited time windows
- Booking confirmation, owner notification, and cancellation templates now open in overlay popups with WYSIWYG editors instead of large inline editors
- Improved default booking and cancellation mail copy with backward-compatible migration logic for unchanged older defaults

## Compatibility

- Plugin version: `1.3.1`
- Recommended API version: `1.2.0`

## Validation

- Changed admin and options PHP files validated without editor errors
- Legacy raw formats for availability rules and contact channels remain available as fallbacks
- Admin layout refinements were iterated directly against the settings UI structure

## Release Note

- This release focuses on admin UX, maintainability, and clearer configuration without introducing breaking changes to stored option formats