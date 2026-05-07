<?php
/**
 * Plugin Name: Restatify Booking Assistant
 * Description: Manual slot search + reservation popup for WordPress, backed by Restatify Booking API.
 * Version: 2.0.1
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

if (class_exists('Restatify_Booking_Assistant_Plugin', false)) {
    return;
}

$restatify_booking_legacy_basenames = [
    'wp_restatify-booking-assistant/wp_restatify-booking-assistant.php',
    'wp-restatify-booking-assistant/wp-restatify-booking-assistant.php',
];

$restatify_booking_skip_bootstrap_for_request = false;

$active_plugins = get_option('active_plugins', []);
if (is_array($active_plugins)) {
    $filtered_plugins = array_values(array_filter(
        $active_plugins,
        static function ($plugin) use ($restatify_booking_legacy_basenames) {
            return !in_array((string) $plugin, $restatify_booking_legacy_basenames, true);
        }
    ));

    if (count($filtered_plugins) !== count($active_plugins)) {
        update_option('active_plugins', $filtered_plugins);
        set_transient('restatify_booking_assistant_admin_notice', [
            'type' => 'warning',
            'message' => __('Legacy booking plugin wurde automatisch deaktiviert, um Klassenkonflikte mit Restatify Booking zu vermeiden.', 'restatify-booking-assistant'),
        ], 300);
        $restatify_booking_skip_bootstrap_for_request = true;
    }
}

if (is_multisite()) {
    $sitewide_plugins = get_site_option('active_sitewide_plugins', []);
    if (is_array($sitewide_plugins)) {
        $sitewide_changed = false;
        foreach ($restatify_booking_legacy_basenames as $legacy_basename) {
            if (isset($sitewide_plugins[$legacy_basename])) {
                unset($sitewide_plugins[$legacy_basename]);
                $sitewide_changed = true;
            }
        }

        if ($sitewide_changed) {
            update_site_option('active_sitewide_plugins', $sitewide_plugins);
            $restatify_booking_skip_bootstrap_for_request = true;
        }
    }
}

if ($restatify_booking_skip_bootstrap_for_request) {
    return;
}

require_once __DIR__ . '/includes/class-restatify-booking-assistant-constants.php';
$migration_notice_manager_file = __DIR__ . '/includes/class-restatify-shared-migration-notice-manager.php';
if (file_exists($migration_notice_manager_file)) {
    require_once $migration_notice_manager_file;
}

if (!class_exists('Restatify_Shared_Migration_Notice_Manager', false)) {
    final class Restatify_Shared_Migration_Notice_Manager {
        public static function register(array $config): void {
            unset($config);
        }
    }
}

require_once __DIR__ . '/includes/class-restatify-booking-assistant-options.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-api-client.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-autoresponder.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-cancellation-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-ui.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-booking-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-plugin.php';

if (!class_exists('Restatify_Booking_Assistant_Plugin', false)) {
    return;
}

new Restatify_Booking_Assistant_Plugin(__FILE__);

/**
 * Optional helper for AI/chat handover flows that should open the booking overlay.
 */
if (!function_exists('restatify_booking_ai_handle_message')) {
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
}
