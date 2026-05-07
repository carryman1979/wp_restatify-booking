<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared constants for the Booking Assistant plugin.
 */
final class Restatify_Booking_Assistant_Constants {
    public const SETTINGS_GROUP = 'restatify_booking';
    public const OPTION_KEY = 'restatify_booking_options';
    public const LEGACY_OPTION_KEYS = [
        'restatify_booking_assistant_options',
    ];
    public const MIGRATION_STATE_OPTION = 'restatify_booking_migration_state';
    public const ADMIN_PAGE_SLUG = 'restatify-booking';
    public const NONCE_ACTION = 'restatify_booking_assistant_nonce';
    public const CANCEL_NONCE_ACTION = 'restatify_booking_assistant_cancel_nonce';
    public const FORCE_SYNC_ACTION = 'restatify_booking_force_sync';
    public const FORCE_SYNC_NONCE_ACTION = 'restatify_booking_force_sync_nonce';
    public const ADMIN_NOTICE_TRANSIENT = 'restatify_booking_assistant_admin_notice';
    public const CONNECTION_STATUS_OPTION = 'restatify_booking_assistant_connection_status';
    public const BOOKING_TRIGGER_HASH = '#restatify-booking';
    public const FRONTEND_ASSET_HANDLE = 'restatify-booking';
    public const CANCEL_QUERY_ARG = 'restatify_booking_cancel_token';
    public const POLYLANG_GROUP = 'Restatify Booking';
    public const TEXT_DOMAIN = 'restatify-booking-assistant';

    private function __construct() {
    }
}
