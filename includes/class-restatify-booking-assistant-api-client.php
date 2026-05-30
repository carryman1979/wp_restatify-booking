<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encapsulates HTTP communication with the Booking API backend.
 */
final class Restatify_Booking_Assistant_Api_Client {
    private Restatify_Booking_Assistant_Options $options;

    public function __construct(Restatify_Booking_Assistant_Options $options) {
        $this->options = $options;
    }

    /**
     * Sends sync configuration payload to the API.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>|WP_Error
     */
    public function push_sync_config(array $options) {
        $calendar_sources = is_array($options['api_calendar_sources'] ?? null) ? $options['api_calendar_sources'] : [];
        $availability_rules = is_array($options['api_availability_rules'] ?? null) ? $options['api_availability_rules'] : [];

        $payload = [
            'sync_enabled' => !empty($options['api_sync_enabled']),
            'sync_interval_minutes' => max(5, min(720, absint($options['api_sync_interval_minutes'] ?? 15))),
            'calendar_sources' => $calendar_sources,
            'availability_rules' => $availability_rules,
            'write_events_enabled' => !empty($options['api_google_write_events_enabled']),
            'write_calendar_id' => sanitize_text_field((string) ($options['api_google_write_calendar_id'] ?? '')),
        ];

        return $this->request('/v1/config/sync', $payload, 'PUT');
    }

    /**
     * Executes an authenticated API request.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|WP_Error
     */
    public function request(string $path, array $payload = [], string $method = 'POST') {
        $options = $this->options->get_options();
        $base_url = rtrim((string) $options['api_base_url'], '/');
        if ($base_url === '') {
            return new WP_Error('restatify_booking_missing_api_url', __('Booking API Base URL is required.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN));
        }

        if (trim((string) ($options['api_key'] ?? '')) === '') {
            return new WP_Error('restatify_booking_missing_api_key', __('Booking API key is required.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN));
        }

        $url = $base_url . $path;
        $headers = [
            'Content-Type' => 'application/json',
            'X-API-Key' => (string) $options['api_key'],
        ];

        $args = [
            'timeout' => 15,
            'headers' => $headers,
            'method' => strtoupper($method),
        ];

        if (count($payload) > 0) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $message = \Restatify\Shared\Api\BookingApiErrorFormatter::extractMessage($body);

            if ($message === '') {
                $message = __('Booking backend is currently unavailable. Please try again later.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
            }

            return new WP_Error('restatify_booking_api_error', $message, ['status' => $status]);
        }

        if (!is_array($body)) {
            return new WP_Error(
                'restatify_booking_api_error',
                __('Booking backend is currently unavailable. Please try again later.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            );
        }

        return $body;
    }
}
