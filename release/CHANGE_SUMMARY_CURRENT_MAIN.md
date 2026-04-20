Restatify Booking Assistant: current local changes on main

- Current released baseline on GitHub remains `1.3.0`.
- Admin settings page was reorganized into clearer cards for API connection, booking logic, calendars/availability, and contact channels.
- Contact channels were moved into the basic settings area and replaced with a structured row editor similar to calendar source management.
- Weekly availability rules were redesigned from raw text lines into Monday-Sunday cards with enable toggles and unlimited time slots per day.
- Mail template editing was streamlined into overlay popups with WYSIWYG editors instead of large inline editors on the settings page.
- Visible admin copy in touched areas was polished toward proper German umlauts and clearer wording.
- Mail template action buttons now include concise status summaries for the affected sections.
- Dynamic mail defaults were refined and backward-compatible migration logic was added so unchanged older defaults can be refreshed automatically.
- Existing legacy raw formats for contact channels and availability remain available as fallbacks for compatibility.