# WP Restatify Booking Assistant

WordPress plugin for manual appointment slot search and reservation popup.

## Features

- Shortcode popup for slot lookup and reservation
- Connects to Restatify Booking API
- Admin settings for API endpoint/key, default duration/timezone
- Admin settings for API sync behavior (interval, calendar list, private/official mode)
- Autoresponder email with ICS attachment
- Optional AI helper function for chat-overlay plugin integration

## Installation

1. Upload plugin folder into wp-content/plugins/
2. Activate plugin in WordPress admin
3. Configure settings under Settings > Booking Assistant
4. Place shortcode [restatify_booking_popup] on a page

## API dependency

Requires a running Restatify Booking API instance.

## Sync configuration format

In plugin settings, define calendars one per line:

`calendar_id|Label|private`

or

`calendar_id|Label|official`
