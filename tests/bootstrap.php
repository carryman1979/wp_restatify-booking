<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        if ($capability !== 'edit_posts') {
            return false;
        }

        return (bool) ($GLOBALS['restatify_booking_test_can_edit_posts'] ?? true);
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string {
        return $text;
    }
}

if (!class_exists('Restatify_Booking_Assistant_Options')) {
    final class Restatify_Booking_Assistant_Options {
        /** @return array<string,mixed> */
        public function get_options(): array {
            return [];
        }
    }
}

require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-constants.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-ui.php';
