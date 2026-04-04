<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages plugin options lifecycle (defaults, sanitizing, parsing and translated reads).
 */
final class Restatify_Booking_Assistant_Options {
    /**
     * Registers the plugin settings schema and sanitizer.
     */
    public function register_settings(): void {
        register_setting(
            'restatify_booking_assistant',
            Restatify_Booking_Assistant_Constants::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => $this->get_default_options(),
            ]
        );
    }

    /**
     * Registers translatable option strings for Polylang, if available.
     */
    public function register_polylang_strings(): void {
        if (!function_exists('pll_register_string')) {
            return;
        }

        $options = $this->get_options();

        pll_register_string(
            'Booking autoresponder subject',
            (string) ($options['autoresponder_subject'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            false
        );

        pll_register_string(
            'Booking autoresponder body',
            (string) ($options['autoresponder_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );
    }

    /**
     * Returns the merged and normalized plugin options.
     *
     * @return array<string,mixed>
     */
    public function get_options(): array {
        $saved = get_option(Restatify_Booking_Assistant_Constants::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $options = wp_parse_args($saved, $this->get_default_options());

        $options['autoresponder_subject'] = $this->translate_option_string((string) ($options['autoresponder_subject'] ?? ''));
        $options['autoresponder_body'] = $this->translate_option_string((string) ($options['autoresponder_body'] ?? ''));

        if (!is_array($options['contact_channels'] ?? null) || count((array) $options['contact_channels']) === 0) {
            $options['contact_channels'] = $this->parse_contact_channels_raw((string) ($options['contact_channels_raw'] ?? ''));
        }

        return $options;
    }

    /**
     * Sanitizes and normalizes settings payload from WordPress settings API.
     *
     * @param mixed $input
     * @return array<string,mixed>
     */
    public function sanitize_options($input): array {
        $defaults = $this->get_default_options();
        $input = is_array($input) ? $input : [];

        $calendar_sources_raw = sanitize_textarea_field((string) ($input['api_calendar_sources_raw'] ?? $defaults['api_calendar_sources_raw']));
        $calendar_sources = $this->parse_calendar_sources_raw($calendar_sources_raw);
        $availability_raw = sanitize_textarea_field((string) ($input['api_availability_raw'] ?? $defaults['api_availability_raw']));
        $availability_rules = $this->parse_availability_raw($availability_raw);
        $contact_channels_raw = sanitize_textarea_field((string) ($input['contact_channels_raw'] ?? $defaults['contact_channels_raw']));
        $contact_channels = $this->parse_contact_channels_raw($contact_channels_raw);

        return [
            'api_base_url' => esc_url_raw((string) ($input['api_base_url'] ?? $defaults['api_base_url'])),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? $defaults['api_key'])),
            'default_timezone' => sanitize_text_field((string) ($input['default_timezone'] ?? $defaults['default_timezone'])),
            'default_duration_minutes' => max(15, min(180, absint($input['default_duration_minutes'] ?? $defaults['default_duration_minutes']))),
            'slot_window_days' => max(1, min(60, absint($input['slot_window_days'] ?? $defaults['slot_window_days']))),
            'no_slots_contact_email' => sanitize_email((string) ($input['no_slots_contact_email'] ?? $defaults['no_slots_contact_email'])),
            'api_sync_enabled' => !empty($input['api_sync_enabled']),
            'api_sync_interval_minutes' => max(5, min(720, absint($input['api_sync_interval_minutes'] ?? $defaults['api_sync_interval_minutes']))),
            'api_google_write_events_enabled' => !empty($input['api_google_write_events_enabled']),
            'api_google_write_calendar_id' => sanitize_text_field((string) ($input['api_google_write_calendar_id'] ?? $defaults['api_google_write_calendar_id'])),
            'api_calendar_sources_raw' => $calendar_sources_raw,
            'api_calendar_sources' => $calendar_sources,
            'api_availability_raw' => $availability_raw,
            'api_availability_rules' => $availability_rules,
            'contact_channels_raw' => $contact_channels_raw,
            'contact_channels' => $contact_channels,
            'contact_prominent_count' => max(1, min(6, absint($input['contact_prominent_count'] ?? $defaults['contact_prominent_count']))),
            'contact_more_label' => sanitize_text_field((string) ($input['contact_more_label'] ?? $defaults['contact_more_label'])),
            'contact_less_label' => sanitize_text_field((string) ($input['contact_less_label'] ?? $defaults['contact_less_label'])),
            'autoresponder_subject' => sanitize_text_field((string) ($input['autoresponder_subject'] ?? $defaults['autoresponder_subject'])),
            'autoresponder_body' => sanitize_textarea_field((string) ($input['autoresponder_body'] ?? $defaults['autoresponder_body'])),
        ];
    }

    /**
     * Returns configured contact channels with guaranteed fallback defaults.
     *
     * @param array<string,mixed> $options
     * @return array<int,array<string,string>>
     */
    public function get_contact_channels(array $options): array {
        $channels = is_array($options['contact_channels'] ?? null) ? $options['contact_channels'] : [];
        if (count($channels) > 0) {
            return $channels;
        }

        $raw = (string) ($options['contact_channels_raw'] ?? '');
        $parsed = $this->parse_contact_channels_raw($raw);
        if (count($parsed) > 0) {
            return $parsed;
        }

        return $this->parse_contact_channels_raw($this->get_default_contact_channels_raw());
    }

    /**
     * Returns default options used on first install and as merge fallback.
     *
     * @return array<string,mixed>
     */
    public function get_default_options(): array {
        return [
            'api_base_url' => 'https://booking-api.example.com',
            'api_key' => '',
            'default_timezone' => wp_timezone_string() ?: 'Europe/Berlin',
            'default_duration_minutes' => 30,
            'slot_window_days' => 14,
            'no_slots_contact_email' => sanitize_email((string) get_option('admin_email', '')),
            'api_sync_enabled' => true,
            'api_sync_interval_minutes' => 15,
            'api_google_write_events_enabled' => true,
            'api_google_write_calendar_id' => '',
            'api_calendar_sources_raw' => '',
            'api_calendar_sources' => [],
            'api_availability_raw' => "mo|09:00-12:00,13:00-17:00\ndi|09:00-12:00,13:00-17:00\nmi|09:00-12:00,13:00-17:00\ndo|09:00-12:00,13:00-17:00\nfr|09:00-12:00,13:00-17:00",
            'api_availability_rules' => [],
            'contact_channels_raw' => $this->get_default_contact_channels_raw(),
            'contact_channels' => [],
            'contact_prominent_count' => 3,
            'contact_more_label' => __('Mehr...', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'contact_less_label' => __('Weniger', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'autoresponder_subject' => __('Deine Restatify Terminreservierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'autoresponder_body' => "Hallo {name},\n\nvielen Dank für deine Reservierung.\n\nThema: {subject}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nKontaktkanal: {contact_method}\nKontakt: {contact_detail}\nReferenz: {reference}\n\nViele Grüße\nRestatify",
        ];
    }

    /**
     * @return array<int,array{weekday:int,windows:array<int,array{start:string,end:string}>}>
     */
    public function parse_availability_raw(string $raw): array {
        $weekday_map = [
            'mo' => 0,
            'di' => 1,
            'mi' => 2,
            'do' => 3,
            'fr' => 4,
            'sa' => 5,
            'so' => 6,
            'mon' => 0,
            'tue' => 1,
            'wed' => 2,
            'thu' => 3,
            'fri' => 4,
            'sat' => 5,
            'sun' => 6,
        ];

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $by_day = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $day_key = strtolower((string) ($parts[0] ?? ''));
            if (!array_key_exists($day_key, $weekday_map)) {
                continue;
            }

            $weekday = (int) $weekday_map[$day_key];
            $ranges_raw = (string) ($parts[1] ?? '');
            if ($ranges_raw === '') {
                continue;
            }

            $ranges = array_map('trim', explode(',', $ranges_raw));
            foreach ($ranges as $range) {
                if ($range === '' || strpos($range, '-') === false) {
                    continue;
                }

                [$start, $end] = array_map('trim', explode('-', $range, 2));
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                    continue;
                }

                if ($start >= $end) {
                    continue;
                }

                if (!isset($by_day[$weekday])) {
                    $by_day[$weekday] = [];
                }

                $by_day[$weekday][] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        ksort($by_day);

        $rules = [];
        foreach ($by_day as $weekday => $windows) {
            if (count($windows) === 0) {
                continue;
            }

            usort($windows, static function ($a, $b) {
                return strcmp((string) $a['start'], (string) $b['start']);
            });

            $rules[] = [
                'weekday' => (int) $weekday,
                'windows' => $windows,
            ];
        }

        return $rules;
    }

    /**
     * @return array<int,array{calendar_id:string,label:string,privacy_mode:string,calendar_type:string}>
     */
    public function parse_calendar_sources_raw(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $sources = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $calendar_id = sanitize_text_field((string) ($parts[0] ?? ''));
            if ($calendar_id === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($parts[1] ?? $calendar_id));
            $privacy_mode = strtolower(sanitize_key((string) ($parts[2] ?? 'private')));
            if (!in_array($privacy_mode, ['private', 'official'], true)) {
                $privacy_mode = 'private';
            }
            $calendar_type = strtolower(sanitize_key((string) ($parts[3] ?? 'general')));
            if (!in_array($calendar_type, ['general', 'holiday'], true)) {
                $calendar_type = 'general';
            }

            $sources[] = [
                'calendar_id' => $calendar_id,
                'label' => $label,
                'privacy_mode' => $privacy_mode,
                'calendar_type' => $calendar_type,
            ];
        }

        return $sources;
    }

    /**
     * @return array<int,array{key:string,label:string,input_kind:string,placeholder:string,value_label:string,ics_template:string}>
     */
    public function parse_contact_channels_raw(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $channels = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $key = strtolower(sanitize_key((string) ($parts[0] ?? '')));
            if ($key === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($parts[1] ?? $key));
            $input_kind = strtolower(sanitize_key((string) ($parts[2] ?? 'tel')));
            if (!in_array($input_kind, ['tel', 'email', 'url', 'text'], true)) {
                $input_kind = 'text';
            }

            $placeholder = sanitize_text_field((string) ($parts[3] ?? ''));
            $value_label = sanitize_text_field((string) ($parts[4] ?? __('Kontaktdaten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)));
            $ics_template = sanitize_text_field((string) ($parts[5] ?? '{value}'));

            if ($ics_template === '') {
                $ics_template = '{value}';
            }

            $channels[] = [
                'key' => $key,
                'label' => $label,
                'input_kind' => $input_kind,
                'placeholder' => $placeholder,
                'value_label' => $value_label,
                'ics_template' => $ics_template,
            ];
        }

        return $channels;
    }

    private function get_default_contact_channels_raw(): string {
        return "phone|Telefon|tel|+49...|Telefonnummer|Telefon: {value}\n"
            . "whatsapp|WhatsApp|tel|+49...|Handynummer|WhatsApp: {value}\n"
            . "teams|Microsoft Teams|email|name@example.com|E-Mail-Adresse|Teams Kontakt: {value}\n"
            . "zoom|Zoom|email|name@example.com|E-Mail-Adresse|Zoom Kontakt: {value}\n"
            . "google_meet|Google Meet|email|name@example.com|E-Mail-Adresse|Google Meet Kontakt: {value}\n"
            . "signal|Signal|tel|+49...|Handynummer|Signal: {value}";
    }

    private function translate_option_string(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('pll__')) {
            $translated = pll__($value);
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $value;
    }
}


