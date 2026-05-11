<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles booking AJAX endpoints (slot search and reservation workflow).
 */
final class Restatify_Booking_Assistant_Booking_Controller {
    private Restatify_Booking_Assistant_Options $options_service;
    private Restatify_Booking_Assistant_Api_Client $api_client;
    private Restatify_Booking_Assistant_Autoresponder $autoresponder;

    public function __construct(
        Restatify_Booking_Assistant_Options $options_service,
        Restatify_Booking_Assistant_Api_Client $api_client,
        Restatify_Booking_Assistant_Autoresponder $autoresponder
    ) {
        $this->options_service = $options_service;
        $this->api_client = $api_client;
        $this->autoresponder = $autoresponder;
    }

    /**
     * AJAX endpoint for searching free slots.
     */
    public function ajax_find_slots(): void {
        $this->enforce_public_rate_limit('find_slots');
        $this->verify_nonce();

        $options = $this->options_service->get_options();
        $timezone = isset($_POST['timezone'])
            ? sanitize_text_field((string) wp_unslash($_POST['timezone']))
            : (string) $options['default_timezone'];
        $duration = max(15, min(180, absint(isset($_POST['duration_minutes']) ? wp_unslash($_POST['duration_minutes']) : $options['default_duration_minutes'])));
        $window_days = max(1, min(60, absint(isset($_POST['window_days']) ? wp_unslash($_POST['window_days']) : $options['slot_window_days'])));

        $start = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $end = $start->modify('+' . $window_days . ' days');

        $payload = [
            'start_iso' => $start->format(DATE_ATOM),
            'end_iso' => $end->format(DATE_ATOM),
            'duration_minutes' => $duration,
            'timezone' => $timezone,
        ];

        $response = $this->api_client->request('/v1/slots/search', $payload);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $slots = is_array($response['slots'] ?? null) ? $response['slots'] : [];
        $availability_rules = is_array($options['api_availability_rules'] ?? null) ? $options['api_availability_rules'] : [];
        $slots = $this->filter_slots_by_availability($slots, $availability_rules, $timezone, $duration);
        $slots = array_slice($slots, 0, 320);
        wp_send_json_success(['slots' => $slots]);
    }

    /**
     * AJAX endpoint for creating a reservation and sending confirmation.
     */
    public function ajax_reserve_slot(): void {
        $this->enforce_public_rate_limit('reserve_slot');
        $this->verify_nonce();

        $options = $this->options_service->get_options();
        $contact_channels = $this->options_service->get_contact_channels($options);
        $contact_map = [];
        foreach ($contact_channels as $channel) {
            $key = (string) ($channel['key'] ?? '');
            if ($key !== '') {
                $contact_map[$key] = $channel;
            }
        }

        $name = isset($_POST['name']) ? sanitize_text_field((string) wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email((string) wp_unslash($_POST['email'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field((string) wp_unslash($_POST['subject'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field((string) wp_unslash($_POST['note'])) : '';
        $slot_start = isset($_POST['slot_start']) ? sanitize_text_field((string) wp_unslash($_POST['slot_start'])) : '';
        $default_method = (string) ($contact_channels[0]['key'] ?? 'phone');
        $contact_method = isset($_POST['contact_method'])
            ? sanitize_key((string) wp_unslash($_POST['contact_method']))
            : $default_method;
        $contact_value_raw = isset($_POST['contact_value']) ? sanitize_text_field((string) wp_unslash($_POST['contact_value'])) : '';

        if ($name === '' || $email === '' || $slot_start === '' || $subject === '') {
            wp_send_json_error(['message' => __('Please complete all required fields.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Please provide a valid email address.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
        }

        if (!array_key_exists($contact_method, $contact_map)) {
            wp_send_json_error(['message' => __('Please select a valid contact channel.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
        }

        $channel = $contact_map[$contact_method];
        $input_kind = (string) ($channel['input_kind'] ?? 'text');
        $contact_label = (string) ($channel['label'] ?? $contact_method);
        $ics_template = (string) ($channel['ics_template'] ?? '{value}');

        $contact_value = '';
        if ($input_kind === 'email') {
            $contact_value = sanitize_email($contact_value_raw);
            if ($contact_value === '' || !is_email($contact_value)) {
                wp_send_json_error(['message' => __('Please provide a valid email address for the selected channel.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
            }
        } elseif ($input_kind === 'tel') {
            $compact = preg_replace('/\s+/', '', $contact_value_raw);
            if (!is_string($compact)) {
                $compact = '';
            }

            if ($compact === '' || !preg_match('/^\+?[0-9()\/\-]{6,30}$/', $compact)) {
                wp_send_json_error(['message' => __('Please provide a valid phone/mobile number for the selected channel.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
            }

            $contact_value = $contact_value_raw;
        } elseif ($input_kind === 'url') {
            $contact_value = esc_url_raw($contact_value_raw);
            if ($contact_value === '' || !wp_http_validate_url($contact_value)) {
                wp_send_json_error(['message' => __('Please provide a valid URL for the selected channel.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
            }
        } else {
            $contact_value = sanitize_text_field($contact_value_raw);
            if ($contact_value === '') {
                wp_send_json_error(['message' => __('Please provide contact details for the selected channel.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
            }
        }

        $contact_detail = str_replace('{value}', $contact_value, $ics_template);
        if (trim($contact_detail) === '' || $contact_detail === $ics_template) {
            $contact_detail = $contact_label . ': ' . $contact_value;
        }

        $note_with_contact = trim($note);
        if ($note_with_contact !== '') {
            $note_with_contact .= "\n\n";
        }
        $note_with_contact = 'Subject: ' . $subject . "\n" . $note_with_contact;
        $note_with_contact .= sprintf('Contact channel: %s', $contact_label) . "\n";
        $note_with_contact .= sprintf('Contact details: %s', $contact_detail);

        $payload = [
            'start_iso' => $slot_start,
            'duration_minutes' => (int) $options['default_duration_minutes'],
            'timezone' => (string) $options['default_timezone'],
            'name' => $name,
            'email' => $email,
            'note' => $note_with_contact,
        ];

        $response = $this->api_client->request('/v1/reservations', $payload);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $cancel_token = sanitize_text_field((string) ($response['cancel_token'] ?? ''));
        $cancellation_url = $cancel_token !== ''
            ? add_query_arg([Restatify_Booking_Assistant_Constants::CANCEL_QUERY_ARG => $cancel_token], home_url('/'))
            : '';

        $this->autoresponder->send_confirmation($response, $name, $email, $subject, $note_with_contact, $contact_label, $contact_value, $contact_detail, $cancellation_url);

        wp_send_json_success([
            'reference' => sanitize_text_field((string) ($response['reference'] ?? '')),
            'start_iso' => sanitize_text_field((string) ($response['start_iso'] ?? $slot_start)),
            'end_iso' => sanitize_text_field((string) ($response['end_iso'] ?? '')),
        ]);
    }

    private function verify_nonce(): void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, Restatify_Booking_Assistant_Constants::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Invalid request token.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 403);
        }
    }

    private function enforce_public_rate_limit(string $action): void {
        $options = $this->options_service->get_options();
        if (empty($options['public_rate_limit_enabled'])) {
            return;
        }

        $window = max(10, min(3600, absint($options['public_rate_limit_window_seconds'] ?? 60)));
        $max_find = max(1, min(120, absint($options['public_rate_limit_max_find_slots'] ?? 30)));
        $max_reserve = max(1, min(60, absint($options['public_rate_limit_max_reserve_slot'] ?? 10)));

        $max_requests = $action === 'reserve_slot' ? $max_reserve : $max_find;

        if (class_exists('\\Restatify\\Shared\\Runtime\\RateLimiter', false)) {
            $allowed = \Restatify\Shared\Runtime\RateLimiter::hit(
                'restatify_booking_rl_',
                $action,
                $window,
                $max_requests
            );

            if (!$allowed) {
                wp_send_json_error(
                    ['message' => __('Too many requests. Please wait a moment and try again.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)],
                    429
                );
            }

            return;
        }

        $ip = $this->get_client_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $fingerprint = md5($ip . '|' . $ua . '|' . $action);
        $key = 'restatify_booking_rl_' . $fingerprint;

        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = [
                'count' => 0,
                'start' => time(),
            ];
        }

        $now = time();
        $start = (int) ($bucket['start'] ?? $now);
        if (($now - $start) >= $window) {
            $bucket = [
                'count' => 0,
                'start' => $now,
            ];
        }

        $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;

        if ($bucket['count'] > $max_requests) {
            set_transient($key, $bucket, $window);
            wp_send_json_error(
                ['message' => __('Too many requests. Please wait a moment and try again.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)],
                429
            );
        }

        set_transient($key, $bucket, $window);
    }

    private function get_client_ip(): string {
        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? (string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']) : '';
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return 'unknown';
    }

    /**
     * Ensures returned slots stay inside configured weekday windows in the selected timezone.
     *
     * @param array<int,mixed> $slots
     * @param array<int,mixed> $availability_rules
     * @return array<int,array<string,mixed>>
     */
    private function filter_slots_by_availability(array $slots, array $availability_rules, string $timezone, int $duration_minutes): array {
        if (count($availability_rules) === 0) {
            return array_values(array_filter($slots, static fn ($slot) => is_array($slot)));
        }

        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $exception) {
            $tz = wp_timezone();
        }

        $windows_by_weekday = [];
        foreach ($availability_rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $weekday = isset($rule['weekday']) ? (int) $rule['weekday'] : -1;
            if ($weekday < 0 || $weekday > 6) {
                continue;
            }

            $windows = is_array($rule['windows'] ?? null) ? $rule['windows'] : [];
            foreach ($windows as $window) {
                if (!is_array($window)) {
                    continue;
                }

                $start = trim((string) ($window['start'] ?? ''));
                $end = trim((string) ($window['end'] ?? ''));
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                    continue;
                }

                if ($start >= $end) {
                    continue;
                }

                $windows_by_weekday[$weekday][] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        if (count($windows_by_weekday) === 0) {
            return array_values(array_filter($slots, static fn ($slot) => is_array($slot)));
        }

        $filtered = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $start_iso = trim((string) ($slot['start_iso'] ?? ''));
            if ($start_iso === '') {
                continue;
            }

            try {
                $slot_start = (new DateTimeImmutable($start_iso))->setTimezone($tz);
            } catch (Exception $exception) {
                continue;
            }

            $slot_end = $slot_start->modify('+' . $duration_minutes . ' minutes');
            $weekday = ((int) $slot_start->format('N')) - 1;
            $start_hm = $slot_start->format('H:i');
            $end_hm = $slot_end->format('H:i');

            $windows = $windows_by_weekday[$weekday] ?? [];
            if (count($windows) === 0) {
                continue;
            }

            if ($slot_end->format('Y-m-d') !== $slot_start->format('Y-m-d')) {
                continue;
            }

            $is_allowed = false;
            foreach ($windows as $window) {
                $window_start = (string) ($window['start'] ?? '');
                $window_end = (string) ($window['end'] ?? '');

                if ($start_hm >= $window_start && $end_hm <= $window_end) {
                    $is_allowed = true;
                    break;
                }
            }

            if ($is_allowed) {
                $filtered[] = $slot;
            }
        }

        return $filtered;
    }
}
