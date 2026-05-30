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

$restatify_booking_test_require_first = static function (array $paths): bool {
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            require_once $path;
            return true;
        }
    }

    return false;
};

$restatify_booking_test_shared_version = '1.0.2';
$restatify_booking_test_root_shared = dirname(__DIR__, 4) . '/wp_restatify-shared';
$restatify_booking_test_packaged_shared = dirname(__DIR__)
    . '/shared-install/wp_restatify-shared/versions/'
    . $restatify_booking_test_shared_version;

$restatify_booking_test_require_first([
    $restatify_booking_test_root_shared . '/versions/' . $restatify_booking_test_shared_version . '/src/php/Contracts/BookingApiErrorCodes.php',
    $restatify_booking_test_root_shared . '/src/php/Contracts/BookingApiErrorCodes.php',
    $restatify_booking_test_packaged_shared . '/src/php/Contracts/BookingApiErrorCodes.php',
]);

$restatify_booking_test_require_first([
    $restatify_booking_test_root_shared . '/versions/' . $restatify_booking_test_shared_version . '/src/php/Api/BookingApiErrorFormatter.php',
    $restatify_booking_test_root_shared . '/src/php/Api/BookingApiErrorFormatter.php',
    $restatify_booking_test_packaged_shared . '/src/php/Api/BookingApiErrorFormatter.php',
]);

if (!class_exists('\Restatify\\Shared\\Contracts\\BookingApiErrorCodes', false)) {
    final class Restatify_Booking_Test_BookingApiErrorCodes {
        public static function defaultMessageForCode(string $code): string {
            return match ($code) {
                'SLOT_UNAVAILABLE' => 'Slot is no longer available',
                default => '',
            };
        }
    }

    class_alias('Restatify_Booking_Test_BookingApiErrorCodes', '\\Restatify\\Shared\\Contracts\\BookingApiErrorCodes');
}

if (!class_exists('\Restatify\\Shared\\Api\\BookingApiErrorFormatter', false)) {
    final class Restatify_Booking_Test_BookingApiErrorFormatter {
        public static function extractMessage($body): string {
            if (!is_array($body)) {
                return '';
            }

            if (isset($body['message']) && is_string($body['message'])) {
                return trim($body['message']);
            }

            if (isset($body['detail']) && is_array($body['detail'])) {
                $detail = $body['detail'];
                $code = isset($detail['code']) && is_string($detail['code']) ? trim($detail['code']) : '';
                if ($code !== '') {
                    $mapped = \Restatify\Shared\Contracts\BookingApiErrorCodes::defaultMessageForCode($code);
                    if ($mapped !== '') {
                        return $mapped;
                    }
                }
            }

            return self::flattenDetail($body['detail'] ?? null);
        }

        public static function flattenDetail($detail): string {
            if (!is_array($detail)) {
                return is_string($detail) ? trim($detail) : '';
            }

            $messages = [];
            foreach ($detail as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $msg = '';
                if (isset($item['msg']) && is_string($item['msg'])) {
                    $msg = trim($item['msg']);
                } elseif (isset($item['message']) && is_string($item['message'])) {
                    $msg = trim($item['message']);
                }

                if ($msg === '') {
                    continue;
                }

                $prefix = '';
                if (isset($item['loc']) && is_array($item['loc'])) {
                    $parts = array_filter(array_map('strval', $item['loc']), static fn (string $p): bool => $p !== '');
                    if (count($parts) > 0) {
                        $prefix = implode(' -> ', $parts) . ': ';
                    }
                }

                $messages[] = $prefix . $msg;
            }

            return implode(' | ', array_values(array_unique($messages)));
        }
    }

    class_alias('Restatify_Booking_Test_BookingApiErrorFormatter', '\\Restatify\\Shared\\Api\\BookingApiErrorFormatter');
}

require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-constants.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-api-client.php';
require_once dirname(__DIR__) . '/includes/class-restatify-booking-assistant-ui.php';
