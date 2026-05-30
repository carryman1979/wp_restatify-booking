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

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string {
        return trim($text);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return is_string($key) ? $key : '';
    }
}

if (!function_exists('absint')) {
    function absint($value): int {
        return abs((int) $value);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value): string {
        $encoded = json_encode($value);
        return is_string($encoded) ? $encoded : '';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        /** @var mixed */
        private $data;

        /** @param mixed $data */
        public function __construct(string $code = '', string $message = '', $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        /** @return mixed */
        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []) {
        unset($url, $args);
        return $GLOBALS['restatify_booking_test_http_response'] ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int {
        if (is_array($response) && isset($response['response']['code'])) {
            return (int) $response['response']['code'];
        }

        return 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string {
        if (is_array($response) && isset($response['body']) && is_string($response['body'])) {
            return $response['body'];
        }

        return '';
    }
}

if (!class_exists('Restatify_Booking_Assistant_Options')) {
    final class Restatify_Booking_Assistant_Options {
        /** @return array<string,mixed> */
        public function get_options(): array {
            $options = $GLOBALS['restatify_booking_test_options'] ?? [];
            return is_array($options) ? $options : [];
        }
    }
}

require_once dirname(__DIR__, 4) . '/wp_restatify-shared/src/php/Contracts/BookingApiErrorCodes.php';
require_once dirname(__DIR__, 4) . '/wp_restatify-shared/src/php/Api/BookingApiErrorFormatter.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-constants.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-api-client.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-ui.php';
