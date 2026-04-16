<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared constants for the Booking Assistant plugin.
 */
final class Restatify_Booking_Assistant_Constants {
    public const OPTION_KEY = 'restatify_booking_assistant_options';
    public const NONCE_ACTION = 'restatify_booking_assistant_nonce';
    public const CANCEL_NONCE_ACTION = 'restatify_booking_assistant_cancel_nonce';
    public const ADMIN_NOTICE_TRANSIENT = 'restatify_booking_assistant_admin_notice';
    public const BOOKING_TRIGGER_HASH = '#restatify-booking';
    public const CANCEL_QUERY_ARG = 'restatify_booking_cancel_token';
    public const POLYLANG_GROUP = 'Restatify Booking Assistant';
    public const TEXT_DOMAIN = 'restatify-booking-assistant';

    private function __construct() {
    }
}
