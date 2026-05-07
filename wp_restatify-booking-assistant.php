<?php
/**
 * Plugin Name: Restatify Booking Assistant
 * Description: Manual slot search + reservation popup for WordPress, backed by Restatify Booking API.
 * Version: 1.3.3
 * Author: Restatify
 * License: GPL-2.0-or-later
 * Text Domain: restatify-booking-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('RESTATIFY_BOOKING_OPEN_TOKEN')) {
    define('RESTATIFY_BOOKING_OPEN_TOKEN', '[[RESTATIFY_BOOKING_OPEN]]');
}

require_once __DIR__ . '/includes/class-restatify-booking-assistant-constants.php';
require_once __DIR__ . '/includes/class-restatify-shared-migration-notice-manager.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-options.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-api-client.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-autoresponder.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-cancellation-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-ui.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-booking-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-plugin.php';

new Restatify_Booking_Assistant_Plugin(__FILE__);

/**
 * Optional helper for AI/chat handover flows that should open the booking overlay.
 */
function restatify_booking_ai_handle_message(string $message): string {
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    $booking_terms = '/termin|appointment|slot|verfuegbar|verfugbarkeit|frei|buchen|book/i';
    if (!preg_match($booking_terms, $message)) {
        return '';
    }

    $options = get_option(Restatify_Booking_Assistant_Constants::OPTION_KEY, []);
    if (!is_array($options) || empty($options['api_base_url'])) {
        return __('Booking service is not configured yet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
    }

    return RESTATIFY_BOOKING_OPEN_TOKEN . ' ' . __('I am opening the booking tool now. Please choose a free slot, enter your details, and confirm. I will then submit it to the calendar API.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
}
