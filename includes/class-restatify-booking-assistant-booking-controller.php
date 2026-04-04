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
        $this->verify_nonce();

        $options = $this->options_service->get_options();
        $timezone = sanitize_text_field((string) ($_POST['timezone'] ?? $options['default_timezone']));
        $duration = max(15, min(180, absint($_POST['duration_minutes'] ?? $options['default_duration_minutes'])));
        $window_days = max(1, min(60, absint($_POST['window_days'] ?? $options['slot_window_days'])));

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

        $slots = is_array($response['slots'] ?? null) ? array_slice($response['slots'], 0, 320) : [];
        wp_send_json_success(['slots' => $slots]);
    }

    /**
     * AJAX endpoint for creating a reservation and sending confirmation.
     */
    public function ajax_reserve_slot(): void {
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

        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $note = sanitize_textarea_field((string) ($_POST['note'] ?? ''));
        $slot_start = sanitize_text_field((string) ($_POST['slot_start'] ?? ''));
        $default_method = (string) ($contact_channels[0]['key'] ?? 'phone');
        $contact_method = sanitize_key((string) ($_POST['contact_method'] ?? $default_method));
        $contact_value_raw = sanitize_text_field((string) ($_POST['contact_value'] ?? ''));

        if ($name === '' || $email === '' || $slot_start === '' || $subject === '') {
            wp_send_json_error(['message' => __('Please complete all required fields.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 400);
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

        $this->autoresponder->send_confirmation($response, $name, $email, $subject, $note_with_contact, $contact_label, $contact_value, $contact_detail);

        wp_send_json_success([
            'reference' => sanitize_text_field((string) ($response['reference'] ?? '')),
            'start_iso' => sanitize_text_field((string) ($response['start_iso'] ?? $slot_start)),
            'end_iso' => sanitize_text_field((string) ($response['end_iso'] ?? '')),
        ]);
    }

    private function verify_nonce(): void {
        $nonce = sanitize_text_field((string) ($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, Restatify_Booking_Assistant_Constants::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Invalid request token.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)], 403);
        }
    }
}
