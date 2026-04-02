# WP Restatify Booking Assistant

WordPress plugin for manual appointment slot search and reservation popup.

## Features

- Shortcode popup for slot lookup and reservation
- Connects to Restatify Booking API
- Core settings (required): API endpoint and API key
- Expert settings (optional): sync interval, calendar list, weekly availability windows, autoresponder text
- Calendar source modes include `private`/`official` and `general`/`holiday`
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

`calendar_id|Label|private|general`

or

`calendar_id|Label|official|holiday`

Weekly availability lines:

`mo|09:00-12:00,13:00-17:00`
