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

        pll_register_string(
            'Booking autoresponder HTML body',
            (string) ($options['autoresponder_html_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking owner notification subject',
            (string) ($options['owner_notification_subject'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            false
        );

        pll_register_string(
            'Booking owner notification text body',
            (string) ($options['owner_notification_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking owner notification HTML body',
            (string) ($options['owner_notification_html_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking cancellation confirmation subject',
            (string) ($options['cancellation_confirmation_subject'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            false
        );

        pll_register_string(
            'Booking cancellation confirmation text body',
            (string) ($options['cancellation_confirmation_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking cancellation confirmation HTML body',
            (string) ($options['cancellation_confirmation_html_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking owner cancellation subject',
            (string) ($options['owner_cancellation_subject'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            false
        );

        pll_register_string(
            'Booking owner cancellation text body',
            (string) ($options['owner_cancellation_body'] ?? ''),
            Restatify_Booking_Assistant_Constants::POLYLANG_GROUP,
            true
        );

        pll_register_string(
            'Booking owner cancellation HTML body',
            (string) ($options['owner_cancellation_html_body'] ?? ''),
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
        $options = $this->apply_dynamic_mail_defaults($saved, $options);

        $options['autoresponder_subject'] = $this->translate_option_string((string) ($options['autoresponder_subject'] ?? ''));
        $options['autoresponder_body'] = $this->translate_option_string((string) ($options['autoresponder_body'] ?? ''));
        $options['autoresponder_html_body'] = $this->translate_option_string((string) ($options['autoresponder_html_body'] ?? ''));
        $options['owner_notification_subject'] = $this->translate_option_string((string) ($options['owner_notification_subject'] ?? ''));
        $options['owner_notification_body'] = $this->translate_option_string((string) ($options['owner_notification_body'] ?? ''));
        $options['owner_notification_html_body'] = $this->translate_option_string((string) ($options['owner_notification_html_body'] ?? ''));
        $options['cancellation_confirmation_subject'] = $this->translate_option_string((string) ($options['cancellation_confirmation_subject'] ?? ''));
        $options['cancellation_confirmation_body'] = $this->translate_option_string((string) ($options['cancellation_confirmation_body'] ?? ''));
        $options['cancellation_confirmation_html_body'] = $this->translate_option_string((string) ($options['cancellation_confirmation_html_body'] ?? ''));
        $options['owner_cancellation_subject'] = $this->translate_option_string((string) ($options['owner_cancellation_subject'] ?? ''));
        $options['owner_cancellation_body'] = $this->translate_option_string((string) ($options['owner_cancellation_body'] ?? ''));
        $options['owner_cancellation_html_body'] = $this->translate_option_string((string) ($options['owner_cancellation_html_body'] ?? ''));

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

        $calendar_source_rows = $this->normalize_calendar_source_rows($input['api_calendar_sources_rows'] ?? []);
        if (count($calendar_source_rows) > 0) {
            $calendar_sources = $calendar_source_rows;
            $calendar_sources_raw = $this->calendar_sources_to_raw($calendar_sources);
        } else {
            $calendar_sources_raw = sanitize_textarea_field((string) ($input['api_calendar_sources_raw'] ?? $defaults['api_calendar_sources_raw']));
            $calendar_sources = $this->parse_calendar_sources_raw($calendar_sources_raw);
        }

        if (!empty($input['api_availability_editor_present'])) {
            $availability_rules = $this->normalize_availability_rows($input['api_availability_rows'] ?? []);
            $availability_raw = $this->availability_rules_to_raw($availability_rules);
        } else {
            $availability_raw = sanitize_textarea_field((string) ($input['api_availability_raw'] ?? $defaults['api_availability_raw']));
            $availability_rules = $this->parse_availability_raw($availability_raw);
        }
        $contact_channel_rows = $this->normalize_contact_channel_rows($input['contact_channels_rows'] ?? []);
        if (count($contact_channel_rows) > 0) {
            $contact_channels = $contact_channel_rows;
            $contact_channels_raw = $this->contact_channels_to_raw($contact_channels);
        } else {
            $contact_channels_raw = sanitize_textarea_field((string) ($input['contact_channels_raw'] ?? $defaults['contact_channels_raw']));
            $contact_channels = $this->parse_contact_channels_raw($contact_channels_raw);
        }

        return [
            'api_base_url' => esc_url_raw((string) ($input['api_base_url'] ?? $defaults['api_base_url'])),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? $defaults['api_key'])),
            'default_timezone' => sanitize_text_field((string) ($input['default_timezone'] ?? $defaults['default_timezone'])),
            'default_duration_minutes' => max(15, min(180, absint($input['default_duration_minutes'] ?? $defaults['default_duration_minutes']))),
            'slot_window_days' => max(1, min(60, absint($input['slot_window_days'] ?? $defaults['slot_window_days']))),
            'public_rate_limit_enabled' => !empty($input['public_rate_limit_enabled']),
            'public_rate_limit_window_seconds' => max(10, min(3600, absint($input['public_rate_limit_window_seconds'] ?? $defaults['public_rate_limit_window_seconds']))),
            'public_rate_limit_max_find_slots' => max(1, min(120, absint($input['public_rate_limit_max_find_slots'] ?? $defaults['public_rate_limit_max_find_slots']))),
            'public_rate_limit_max_reserve_slot' => max(1, min(60, absint($input['public_rate_limit_max_reserve_slot'] ?? $defaults['public_rate_limit_max_reserve_slot']))),
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
            'autoresponder_enabled' => !empty($input['autoresponder_enabled']),
            'autoresponder_html_enabled' => !empty($input['autoresponder_html_enabled']),
            'autoresponder_subject' => sanitize_text_field((string) ($input['autoresponder_subject'] ?? $defaults['autoresponder_subject'])),
            'autoresponder_body' => sanitize_textarea_field((string) ($input['autoresponder_body'] ?? $defaults['autoresponder_body'])),
            'autoresponder_html_body' => wp_kses_post((string) ($input['autoresponder_html_body'] ?? $defaults['autoresponder_html_body'])),
            'owner_notification_enabled' => !empty($input['owner_notification_enabled']),
            'owner_notification_html_enabled' => !empty($input['owner_notification_html_enabled']),
            'owner_notification_recipients' => sanitize_textarea_field((string) ($input['owner_notification_recipients'] ?? $defaults['owner_notification_recipients'])),
            'owner_notification_subject' => sanitize_text_field((string) ($input['owner_notification_subject'] ?? $defaults['owner_notification_subject'])),
            'owner_notification_body' => sanitize_textarea_field((string) ($input['owner_notification_body'] ?? $defaults['owner_notification_body'])),
            'owner_notification_html_body' => wp_kses_post((string) ($input['owner_notification_html_body'] ?? $defaults['owner_notification_html_body'])),
            'cancellation_confirmation_enabled' => !empty($input['cancellation_confirmation_enabled']),
            'cancellation_confirmation_html_enabled' => !empty($input['cancellation_confirmation_html_enabled']),
            'cancellation_confirmation_subject' => sanitize_text_field((string) ($input['cancellation_confirmation_subject'] ?? $defaults['cancellation_confirmation_subject'])),
            'cancellation_confirmation_body' => sanitize_textarea_field((string) ($input['cancellation_confirmation_body'] ?? $defaults['cancellation_confirmation_body'])),
            'cancellation_confirmation_html_body' => wp_kses_post((string) ($input['cancellation_confirmation_html_body'] ?? $defaults['cancellation_confirmation_html_body'])),
            'owner_cancellation_enabled' => !empty($input['owner_cancellation_enabled']),
            'owner_cancellation_html_enabled' => !empty($input['owner_cancellation_html_enabled']),
            'owner_cancellation_subject' => sanitize_text_field((string) ($input['owner_cancellation_subject'] ?? $defaults['owner_cancellation_subject'])),
            'owner_cancellation_body' => sanitize_textarea_field((string) ($input['owner_cancellation_body'] ?? $defaults['owner_cancellation_body'])),
            'owner_cancellation_html_body' => wp_kses_post((string) ($input['owner_cancellation_html_body'] ?? $defaults['owner_cancellation_html_body'])),
        ];
    }

    /**
     * @param mixed $rows
     * @return array<int,array{calendar_id:string,label:string,privacy_mode:string,calendar_type:string}>
     */
    private function normalize_calendar_source_rows($rows): array {
        if (!is_array($rows)) {
            return [];
        }

        $sources = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $calendar_id = sanitize_text_field((string) ($row['calendar_id'] ?? ''));
            if ($calendar_id === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($row['label'] ?? $calendar_id));
            $privacy_mode = strtolower(sanitize_key((string) ($row['privacy_mode'] ?? 'private')));
            if (!in_array($privacy_mode, ['private', 'official'], true)) {
                $privacy_mode = 'private';
            }

            $calendar_type = strtolower(sanitize_key((string) ($row['calendar_type'] ?? 'general')));
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
     * @param mixed $rows
     * @return array<int,array{key:string,label:string,input_kind:string,placeholder:string,value_label:string,ics_template:string}>
     */
    private function normalize_contact_channel_rows($rows): array {
        if (!is_array($rows)) {
            return [];
        }

        $channels = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = strtolower(sanitize_key((string) ($row['key'] ?? '')));
            if ($key === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($row['label'] ?? $key));
            $input_kind = strtolower(sanitize_key((string) ($row['input_kind'] ?? 'tel')));
            if (!in_array($input_kind, ['tel', 'email', 'url', 'text'], true)) {
                $input_kind = 'text';
            }

            $placeholder = sanitize_text_field((string) ($row['placeholder'] ?? ''));
            $value_label = sanitize_text_field((string) ($row['value_label'] ?? __('Kontaktdaten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)));
            $ics_template = sanitize_text_field((string) ($row['ics_template'] ?? '{value}'));
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

    /**
     * @param array<int,array{calendar_id:string,label:string,privacy_mode:string,calendar_type:string}> $sources
     */
    private function calendar_sources_to_raw(array $sources): string {
        $lines = [];
        foreach ($sources as $source) {
            $lines[] = implode('|', [
                (string) ($source['calendar_id'] ?? ''),
                (string) ($source['label'] ?? ''),
                (string) ($source['privacy_mode'] ?? 'private'),
                (string) ($source['calendar_type'] ?? 'general'),
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int,array{key:string,label:string,input_kind:string,placeholder:string,value_label:string,ics_template:string}> $channels
     */
    private function contact_channels_to_raw(array $channels): string {
        $lines = [];
        foreach ($channels as $channel) {
            $lines[] = implode('|', [
                (string) ($channel['key'] ?? ''),
                (string) ($channel['label'] ?? ''),
                (string) ($channel['input_kind'] ?? 'text'),
                (string) ($channel['placeholder'] ?? ''),
                (string) ($channel['value_label'] ?? ''),
                (string) ($channel['ics_template'] ?? '{value}'),
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param mixed $rows
     * @return array<int,array{weekday:int,windows:array<int,array{start:string,end:string}>}>
     */
    private function normalize_availability_rows($rows): array {
        if (!is_array($rows)) {
            return [];
        }

        $rules = [];
        foreach ($rows as $weekday_key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $weekday = (int) $weekday_key;
            if ($weekday < 0 || $weekday > 6 || empty($row['enabled'])) {
                continue;
            }

            $windows = [];
            $window_rows = is_array($row['windows'] ?? null) ? $row['windows'] : [];
            foreach ($window_rows as $window_row) {
                if (!is_array($window_row)) {
                    continue;
                }

                $start = trim(sanitize_text_field((string) ($window_row['start'] ?? '')));
                $end = trim(sanitize_text_field((string) ($window_row['end'] ?? '')));
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                    continue;
                }
                if ($start >= $end) {
                    continue;
                }

                $windows[] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }

            if (count($windows) === 0) {
                continue;
            }

            usort($windows, static function ($left, $right) {
                return strcmp((string) $left['start'], (string) $right['start']);
            });

            $rules[] = [
                'weekday' => $weekday,
                'windows' => $windows,
            ];
        }

        usort($rules, static function ($left, $right) {
            return ((int) $left['weekday']) <=> ((int) $right['weekday']);
        });

        return $rules;
    }

    /**
     * @param array<int,array{weekday:int,windows:array<int,array{start:string,end:string}>}> $rules
     */
    private function availability_rules_to_raw(array $rules): string {
        $weekday_map = [
            0 => 'mo',
            1 => 'di',
            2 => 'mi',
            3 => 'do',
            4 => 'fr',
            5 => 'sa',
            6 => 'so',
        ];

        $lines = [];
        foreach ($rules as $rule) {
            $weekday = (int) ($rule['weekday'] ?? -1);
            if (!isset($weekday_map[$weekday])) {
                continue;
            }

            $windows = [];
            foreach ((array) ($rule['windows'] ?? []) as $window) {
                $start = (string) ($window['start'] ?? '');
                $end = (string) ($window['end'] ?? '');
                if ($start === '' || $end === '') {
                    continue;
                }
                $windows[] = $start . '-' . $end;
            }

            if (count($windows) === 0) {
                continue;
            }

            $lines[] = $weekday_map[$weekday] . '|' . implode(',', $windows);
        }

        return implode("\n", $lines);
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
        $mail_branding = $this->get_mail_branding_context();

        return [
            'api_base_url' => 'https://booking-api.example.com',
            'api_key' => '',
            'default_timezone' => wp_timezone_string() ?: 'Europe/Berlin',
            'default_duration_minutes' => 30,
            'slot_window_days' => 14,
            'public_rate_limit_enabled' => true,
            'public_rate_limit_window_seconds' => 60,
            'public_rate_limit_max_find_slots' => 30,
            'public_rate_limit_max_reserve_slot' => 10,
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
            'autoresponder_enabled' => true,
            'autoresponder_html_enabled' => true,
            'autoresponder_subject' => __('Deine Terminbestätigung von {site_name}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'autoresponder_body' => $this->get_default_autoresponder_text_body(),
                        'autoresponder_html_body' => $this->get_default_autoresponder_html_body($mail_branding),
            'owner_notification_enabled' => false,
            'owner_notification_html_enabled' => true,
            'owner_notification_recipients' => sanitize_email((string) get_option('admin_email', '')),
            'owner_notification_subject' => __('Neue Terminreservierung {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_notification_body' => $this->get_default_owner_notification_text_body(),
                        'owner_notification_html_body' => $this->get_default_owner_notification_html_body($mail_branding),
                        'cancellation_confirmation_enabled' => true,
                        'cancellation_confirmation_html_enabled' => true,
                        'cancellation_confirmation_subject' => __('Deine Stornobestätigung von {site_name}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'cancellation_confirmation_body' => $this->get_default_cancellation_confirmation_text_body(),
                        'cancellation_confirmation_html_body' => $this->get_default_cancellation_confirmation_html_body($mail_branding),
                        'owner_cancellation_enabled' => false,
                        'owner_cancellation_html_enabled' => true,
                        'owner_cancellation_subject' => __('Termin storniert {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_cancellation_body' => $this->get_default_owner_cancellation_text_body(),
                        'owner_cancellation_html_body' => $this->get_default_owner_cancellation_html_body($mail_branding),
        ];
    }

        /**
         * @param array<string,mixed> $saved
         * @param array<string,mixed> $options
         * @return array<string,mixed>
         */
        private function apply_dynamic_mail_defaults(array $saved, array $options): array {
                $dynamic_defaults = $this->get_default_options();
                $legacy_defaults = $this->get_legacy_mail_defaults();
                $previous_dynamic_defaults = $this->get_previous_dynamic_mail_defaults();

                foreach (['autoresponder_enabled', 'autoresponder_html_enabled', 'owner_notification_enabled', 'owner_notification_html_enabled', 'cancellation_confirmation_enabled', 'cancellation_confirmation_html_enabled', 'owner_cancellation_enabled', 'owner_cancellation_html_enabled'] as $bool_key) {
                        if (!array_key_exists($bool_key, $saved)) {
                                $options[$bool_key] = $dynamic_defaults[$bool_key];
                        }
                }

                foreach (['autoresponder_subject', 'autoresponder_body', 'autoresponder_html_body', 'owner_notification_subject', 'owner_notification_body', 'owner_notification_html_body', 'cancellation_confirmation_subject', 'cancellation_confirmation_body', 'cancellation_confirmation_html_body', 'owner_cancellation_subject', 'owner_cancellation_body', 'owner_cancellation_html_body'] as $key) {
                        if (!array_key_exists($key, $saved)) {
                                $options[$key] = $dynamic_defaults[$key];
                                continue;
                        }

                        $current_value = (string) ($saved[$key] ?? '');
                        $known_legacy_values = array_values(array_filter([
                            (string) ($legacy_defaults[$key] ?? ''),
                            (string) ($previous_dynamic_defaults[$key] ?? ''),
                        ], static function ($value): bool {
                            return $value !== '';
                        }));
                        if (trim($current_value) === '' || in_array($current_value, $known_legacy_values, true)) {
                                $options[$key] = $dynamic_defaults[$key];
                        }
                }

                return $options;
        }

        /**
         * @return array<string,string>
         */
        private function get_legacy_mail_defaults(): array {
                return [
                        'autoresponder_subject' => __('Deine Restatify Terminreservierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'autoresponder_body' => "Hallo {name},\n\nvielen Dank für deine Reservierung.\n\nThema: {subject}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nKontaktkanal: {contact_method}\nKontakt: {contact_detail}\nReferenz: {reference}\nTermin stornieren: {cancellation_url}\n\nViele Grüße\nRestatify",
                        'autoresponder_html_body' => '<p>Hallo {name},</p><p>vielen Dank für deine Reservierung.</p><ul><li><strong>Thema:</strong> {subject}</li><li><strong>Start:</strong> {start}</li><li><strong>Ende:</strong> {end}</li><li><strong>Zeitzone:</strong> {timezone}</li><li><strong>Kontaktkanal:</strong> {contact_method}</li><li><strong>Kontakt:</strong> {contact_detail}</li><li><strong>Referenz:</strong> {reference}</li></ul><p><a href="{cancellation_url}">Termin stornieren</a></p><p>Viele Grüße<br>Restatify</p>',
                        'owner_notification_subject' => __('Neuer Restatify Termin {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_notification_body' => "Ein neuer Termin wurde gebucht.\n\nName: {name}\nE-Mail: {email}\nThema: {subject}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nKontaktkanal: {contact_method}\nKontakt: {contact_detail}\nNachricht: {note}\nReferenz: {reference}\nTermin stornieren: {cancellation_url}",
                        'owner_notification_html_body' => '<p>Ein neuer Termin wurde gebucht.</p><ul><li><strong>Name:</strong> {name}</li><li><strong>E-Mail:</strong> {email}</li><li><strong>Thema:</strong> {subject}</li><li><strong>Start:</strong> {start}</li><li><strong>Ende:</strong> {end}</li><li><strong>Zeitzone:</strong> {timezone}</li><li><strong>Kontaktkanal:</strong> {contact_method}</li><li><strong>Kontakt:</strong> {contact_detail}</li><li><strong>Referenz:</strong> {reference}</li></ul><p><strong>Nachricht:</strong><br>{note}</p><p><a href="{cancellation_url}">Termin stornieren</a></p>',
                        'owner_cancellation_subject' => __('Termin storniert: {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_cancellation_body' => "Ein Termin wurde storniert.\n\nName: {name}\nE-Mail: {email}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\nStornogrund: {cancellation_reason}\nNachricht: {cancellation_message}",
                        'owner_cancellation_html_body' => '<p>Ein Termin wurde storniert.</p><ul><li><strong>Name:</strong> {name}</li><li><strong>E-Mail:</strong> {email}</li><li><strong>Start:</strong> {start}</li><li><strong>Ende:</strong> {end}</li><li><strong>Zeitzone:</strong> {timezone}</li><li><strong>Referenz:</strong> {reference}</li><li><strong>Stornogrund:</strong> {cancellation_reason}</li></ul><p><strong>Nachricht:</strong><br>{cancellation_message}</p>',
                ];
        }

                /**
                 * @return array<string,string>
                 */
                private function get_previous_dynamic_mail_defaults(): array {
                    return [
                        'autoresponder_subject' => __('Deine Restatify Terminreservierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'autoresponder_body' => "Hallo {name},\n\ndeine Reservierung wurde erfolgreich eingetragen.\n\nThema: {subject}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nKontaktkanal: {contact_method}\nKontakt: {contact_detail}\nReferenz: {reference}\nTermin stornieren: {cancellation_url}\n\nBitte prüfe die Angaben und bewahre diese Nachricht bis zum Termin auf.\n\nViele Grüße\n{site_name}\n\nDisclaimer: Diese Nachricht enthält organisatorische Informationen zu deiner Terminreservierung. Bitte sende keine sensiblen Daten per E-Mail.\nDiese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.",
                        'autoresponder_html_body' => $this->build_previous_autoresponder_html_body(),
                        'owner_notification_subject' => __('Neuer Restatify Termin {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_notification_body' => "Ein neuer Termin wurde gebucht.\n\nName: {name}\nE-Mail: {email}\nThema: {subject}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nKontaktkanal: {contact_method}\nKontakt: {contact_detail}\nNachricht: {note}\nReferenz: {reference}\nTermin stornieren: {cancellation_url}\n\nHinweis: Diese Benachrichtigung wurde automatisch durch Restatify erzeugt.\nDisclaimer: Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.\nDiese E-Mail wurde maschinell erstellt.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.",
                        'owner_notification_html_body' => $this->build_previous_owner_notification_html_body(),
                        'cancellation_confirmation_subject' => __('Deine Restatify Termin-Stornierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'cancellation_confirmation_body' => "Hallo {name},\n\ndein Termin wurde erfolgreich storniert.\n\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\nStornogrund: {cancellation_reason}\nNachricht: {cancellation_message}\n\nFalls du einen neuen Termin vereinbaren möchtest, nutze bitte erneut das Buchungstool auf unserer Website.\n\nViele Grüße\n{site_name}\n\nDisclaimer: Diese Nachricht bestätigt ausschließlich die Stornierung deines Termins. Bitte sende keine sensiblen Daten per E-Mail.\nDiese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.",
                        'cancellation_confirmation_html_body' => $this->build_previous_cancellation_confirmation_html_body(),
                        'owner_cancellation_subject' => __('Termin storniert: {reference}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'owner_cancellation_body' => "Ein Termin wurde storniert.\n\nName: {name}\nE-Mail: {email}\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\nStornogrund: {cancellation_reason}\nNachricht: {cancellation_message}\n\nHinweis: Diese Benachrichtigung wurde automatisch durch Restatify erzeugt.\nDisclaimer: Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.\nDiese E-Mail wurde maschinell erstellt.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.",
                        'owner_cancellation_html_body' => $this->build_previous_owner_cancellation_html_body(),
                    ];
                }

        private function get_default_autoresponder_text_body(): string {
                    return "Hallo {name},\n\nvielen Dank für deine Terminreservierung. Dein Wunschtermin wurde erfolgreich eingetragen.\n\nThema: {subject}\nBeginn: {start}\nEnde: {end}\nZeitzone: {timezone}\nBevorzugter Kontaktkanal: {contact_method}\nKontaktdaten: {contact_detail}\nReferenz: {reference}\nStornolink: {cancellation_url}\n\nBitte prüfe die Angaben und bewahre diese Nachricht bis zum Termin auf. Wenn sich etwas ändert, kannst du den Termin über den Stornolink absagen und anschließend neu buchen.\n\nViele Grüße\n{site_name}\n\nHinweis: Diese Nachricht enthält organisatorische Informationen zu deiner Terminreservierung. Bitte sende keine sensiblen Daten per E-Mail.\nDiese E-Mail wurde automatisch erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
        }

        private function get_default_owner_notification_text_body(): string {
                    return "Es wurde eine neue Terminreservierung erfasst.\n\nName: {name}\nE-Mail: {email}\nThema: {subject}\nBeginn: {start}\nEnde: {end}\nZeitzone: {timezone}\nBevorzugter Kontaktkanal: {contact_method}\nKontaktdaten: {contact_detail}\nNachricht: {note}\nReferenz: {reference}\nStornolink: {cancellation_url}\n\nHinweis: Diese Benachrichtigung wurde automatisch durch Restatify erzeugt. Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.\nDiese E-Mail wurde automatisch erstellt.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
        }

        private function get_default_cancellation_confirmation_text_body(): string {
                    return "Hallo {name},\n\ndein Termin wurde erfolgreich storniert.\n\nBeginn: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\nStornogrund: {cancellation_reason}\nNachricht: {cancellation_message}\n\nWenn du einen neuen Termin vereinbaren möchtest, nutze bitte erneut das Buchungstool auf unserer Website.\n\nViele Grüße\n{site_name}\n\nHinweis: Diese Nachricht bestätigt ausschließlich die Stornierung deines Termins. Bitte sende keine sensiblen Daten per E-Mail.\nDiese E-Mail wurde automatisch erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
        }

        private function get_default_owner_cancellation_text_body(): string {
                    return "Ein Termin wurde storniert.\n\nName: {name}\nE-Mail: {email}\nBeginn: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\nStornogrund: {cancellation_reason}\nNachricht: {cancellation_message}\n\nHinweis: Diese Benachrichtigung wurde automatisch durch Restatify erzeugt. Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.\nDiese E-Mail wurde automatisch erstellt.\nSchütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
        }

        /**
         * @param array<string,string> $branding
         */
        private function get_default_autoresponder_html_body(array $branding): string {
                $content = '<p style="margin:0 0 16px;">Hallo {name},</p><p style="margin:0 0 16px;">vielen Dank für deine Terminreservierung. Nachfolgend findest du alle wichtigen Details zu deinem Termin.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Thema:</strong> {subject}</td></tr><tr><td style="padding:0 0 10px;"><strong>Beginn:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Bevorzugter Kontaktkanal:</strong> {contact_method}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontaktdaten:</strong> {contact_detail}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr></table><p style="margin:24px 0 0;">Wenn du den Termin nicht wahrnehmen kannst, nutze bitte den folgenden Link:</p><p style="margin:16px 0 0;"><a href="{cancellation_url}" style="display:inline-block;background:' . $branding['primary_color'] . ';color:' . $branding['contrast_color'] . ';text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:700;">Termin stornieren</a></p>';
                return $this->build_default_html_mail($branding, 'Termin bestätigt', 'Deine Terminbestätigung', $content);
        }

        /**
         * @param array<string,string> $branding
         */
        private function get_default_owner_notification_html_body(array $branding): string {
                $content = '<p style="margin:0 0 16px;">Es wurde eine neue Terminreservierung erfasst.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Name:</strong> {name}</td></tr><tr><td style="padding:0 0 10px;"><strong>E-Mail:</strong> {email}</td></tr><tr><td style="padding:0 0 10px;"><strong>Thema:</strong> {subject}</td></tr><tr><td style="padding:0 0 10px;"><strong>Beginn:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Bevorzugter Kontaktkanal:</strong> {contact_method}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontaktdaten:</strong> {contact_detail}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:12px 0 0;"><strong>Nachricht:</strong><br>{note}</td></tr></table><p style="margin:24px 0 0;"><a href="{cancellation_url}" style="display:inline-block;background:' . $branding['secondary_color'] . ';color:' . $branding['contrast_color'] . ';text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:700;">Stornolink öffnen</a></p>';
                return $this->build_default_html_mail($branding, 'Neue Reservierung', 'Interne Terminbenachrichtigung', $content);
        }

        /**
         * @param array<string,string> $branding
         */
        private function get_default_cancellation_confirmation_html_body(array $branding): string {
                $content = '<p style="margin:0 0 16px;">Hallo {name},</p><p style="margin:0 0 16px;">dein Termin wurde erfolgreich storniert.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Beginn:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:0 0 10px;"><strong>Stornogrund:</strong> {cancellation_reason}</td></tr><tr><td style="padding:0;"><strong>Nachricht:</strong><br>{cancellation_message}</td></tr></table><p style="margin:20px 0 0;">Wenn du einen neuen Termin vereinbaren möchtest, nutze bitte erneut das Buchungstool auf unserer Website.</p>';
                return $this->build_default_html_mail($branding, 'Termin abgesagt', 'Deine Stornobestätigung', $content);
        }

        /**
         * @param array<string,string> $branding
         */
        private function get_default_owner_cancellation_html_body(array $branding): string {
                $content = '<p style="margin:0 0 16px;">Ein Termin wurde storniert.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Name:</strong> {name}</td></tr><tr><td style="padding:0 0 10px;"><strong>E-Mail:</strong> {email}</td></tr><tr><td style="padding:0 0 10px;"><strong>Beginn:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:0 0 10px;"><strong>Stornogrund:</strong> {cancellation_reason}</td></tr><tr><td style="padding:0;"><strong>Nachricht:</strong><br>{cancellation_message}</td></tr></table>';
                return $this->build_default_html_mail($branding, 'Termin abgesagt', 'Interne Stornobenachrichtigung', $content);
        }

            private function build_previous_autoresponder_html_body(): string {
                $branding = $this->get_mail_branding_context();
                $content = '<p style="margin:0 0 16px;">Hallo {name},</p><p style="margin:0 0 16px;">deine Reservierung wurde erfolgreich eingetragen. Nachfolgend findest du die wichtigsten Informationen.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Thema:</strong> {subject}</td></tr><tr><td style="padding:0 0 10px;"><strong>Start:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontaktkanal:</strong> {contact_method}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontakt:</strong> {contact_detail}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr></table><p style="margin:24px 0 0;"><a href="{cancellation_url}" style="display:inline-block;background:' . $branding['primary_color'] . ';color:' . $branding['contrast_color'] . ';text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:700;">Termin stornieren</a></p>';
                return $this->build_default_html_mail($branding, 'Termin erfolgreich reserviert', 'Deine Reservierungsbestätigung', $content);
            }

            private function build_previous_owner_notification_html_body(): string {
                $branding = $this->get_mail_branding_context();
                $content = '<p style="margin:0 0 16px;">Ein neuer Termin wurde gebucht.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Name:</strong> {name}</td></tr><tr><td style="padding:0 0 10px;"><strong>E-Mail:</strong> {email}</td></tr><tr><td style="padding:0 0 10px;"><strong>Thema:</strong> {subject}</td></tr><tr><td style="padding:0 0 10px;"><strong>Start:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontaktkanal:</strong> {contact_method}</td></tr><tr><td style="padding:0 0 10px;"><strong>Kontakt:</strong> {contact_detail}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:12px 0 0;"><strong>Nachricht:</strong><br>{note}</td></tr></table><p style="margin:24px 0 0;"><a href="{cancellation_url}" style="display:inline-block;background:' . $branding['secondary_color'] . ';color:' . $branding['contrast_color'] . ';text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:700;">Stornolink öffnen</a></p>';
                return $this->build_default_html_mail($branding, 'Neuer Termin', 'Interne Terminbenachrichtigung', $content);
            }

            private function build_previous_cancellation_confirmation_html_body(): string {
                $branding = $this->get_mail_branding_context();
                $content = '<p style="margin:0 0 16px;">Hallo {name},</p><p style="margin:0 0 16px;">dein Termin wurde erfolgreich storniert.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Start:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:0 0 10px;"><strong>Stornogrund:</strong> {cancellation_reason}</td></tr><tr><td style="padding:0;"><strong>Nachricht:</strong><br>{cancellation_message}</td></tr></table><p style="margin:20px 0 0;">Wenn du einen neuen Termin vereinbaren möchtest, nutze bitte erneut das Buchungstool auf unserer Website.</p>';
                return $this->build_default_html_mail($branding, 'Termin storniert', 'Deine Stornobestätigung', $content);
            }

            private function build_previous_owner_cancellation_html_body(): string {
                $branding = $this->get_mail_branding_context();
                $content = '<p style="margin:0 0 16px;">Ein Termin wurde storniert.</p><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:0 0 10px;"><strong>Name:</strong> {name}</td></tr><tr><td style="padding:0 0 10px;"><strong>E-Mail:</strong> {email}</td></tr><tr><td style="padding:0 0 10px;"><strong>Start:</strong> {start}</td></tr><tr><td style="padding:0 0 10px;"><strong>Ende:</strong> {end}</td></tr><tr><td style="padding:0 0 10px;"><strong>Zeitzone:</strong> {timezone}</td></tr><tr><td style="padding:0 0 10px;"><strong>Referenz:</strong> {reference}</td></tr><tr><td style="padding:0 0 10px;"><strong>Stornogrund:</strong> {cancellation_reason}</td></tr><tr><td style="padding:0;"><strong>Nachricht:</strong><br>{cancellation_message}</td></tr></table>';
                return $this->build_default_html_mail($branding, 'Termin storniert', 'Interne Stornobenachrichtigung', $content);
            }

        /**
         * @param array<string,string> $branding
         */
        private function build_default_html_mail(array $branding, string $eyebrow, string $headline, string $content): string {
                $logo_url = esc_url($branding['logo_url']);
                $site_name = esc_html($branding['site_name']);
                $home_url = esc_url($branding['home_url']);
                $primary_color = esc_attr($branding['primary_color']);
                $secondary_color = esc_attr($branding['secondary_color']);
                $background_color = esc_attr($branding['background_color']);
                $surface_color = esc_attr($branding['surface_color']);
                $text_color = esc_attr($branding['text_color']);
                $muted_color = esc_attr($branding['muted_color']);
                $contrast_color = esc_attr($branding['contrast_color']);

                return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background:{$background_color};font-family:Arial,sans-serif;color:{$text_color};">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:680px;border-collapse:collapse;background:{$surface_color};border:1px solid rgba(11,18,33,0.08);border-radius:24px;overflow:hidden;box-shadow:0 18px 48px rgba(11,18,33,0.08);">
                <tr>
                    <td style="padding:28px 32px;background:linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%);color:{$contrast_color};">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                            <tr>
                                <td align="left" style="vertical-align:middle;">
                                    <img src="{$logo_url}" alt="{$site_name}" style="display:block;max-width:220px;width:auto;max-height:56px;height:auto;border:0;">
                                </td>
                                <td align="right" style="vertical-align:middle;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;font-weight:700;opacity:0.95;">
                                    {$eyebrow}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;color:{$text_color};">{$headline}</h1>
                        <div style="font-size:16px;line-height:1.65;color:{$text_color};">{$content}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 32px;">
                        <div style="height:1px;background:linear-gradient(90deg, {$primary_color} 0%, {$secondary_color} 100%);"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 32px;font-size:13px;line-height:1.6;color:{$muted_color};">
                        <p style="margin:0 0 10px;"><strong>Disclaimer:</strong> Diese Nachricht enthält organisatorische Informationen zu deinem Termin. Bitte sende keine sensiblen Daten per E-Mail.</p>
                        <p style="margin:0 0 10px;">Diese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.</p>
                        <p style="margin:0 0 10px;">Schütze die Umwelt, indem du diese E-Mail nicht ausdruckst.</p>
                        <p style="margin:0;">{$site_name} · <a href="{$home_url}" style="color:{$primary_color};text-decoration:none;">{$home_url}</a></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML;
        }

        /**
         * @return array<string,string>
         */
        private function get_mail_branding_context(): array {
                $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
                $branding = [
                        'site_name' => $site_name !== '' ? $site_name : 'Restatify',
                        'home_url' => home_url('/'),
                        'logo_url' => $this->get_placeholder_logo_url(),
                        'primary_color' => '#2563eb',
                        'secondary_color' => '#0f766e',
                        'background_color' => '#eef4ff',
                        'surface_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'muted_color' => '#52607a',
                        'contrast_color' => '#ffffff',
                ];

                if (!$this->is_restatify_theme_active()) {
                        return $branding;
                }

                $branding['logo_url'] = $this->get_restatify_theme_logo_url();
                $palette = $this->get_restatify_theme_palette();
                $branding['primary_color'] = $palette['primary'] ?? '#ff6b00';
                $branding['secondary_color'] = $palette['secondary'] ?? '#00c2ff';
                $branding['background_color'] = $palette['background'] ?? '#f8fafc';
                $branding['text_color'] = $palette['text'] ?? '#0b1221';
                $branding['muted_color'] = '#5b6577';

                return $branding;
        }

        private function is_restatify_theme_active(): bool {
                $theme = wp_get_theme();
                if (!$theme->exists()) {
                        return false;
                }

                return in_array('wp_restatify-base-theme', [$theme->get_stylesheet(), $theme->get_template()], true);
        }

        private function get_restatify_theme_logo_url(): string {
                $custom_logo_id = (int) get_theme_mod('custom_logo');
                if ($custom_logo_id > 0) {
                        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                        if (is_string($logo_url) && $logo_url !== '') {
                                return $logo_url;
                        }
                }

                return $this->get_placeholder_logo_url();
        }

        /**
         * @return array<string,string>
         */
        private function get_restatify_theme_palette(): array {
                $palette = [];
                $theme_json_path = get_template_directory() . '/theme.json';
                if (!file_exists($theme_json_path)) {
                        return $palette;
                }

                $content = file_get_contents($theme_json_path);
                if (!is_string($content) || $content === '') {
                        return $palette;
                }

                $decoded = json_decode($content, true);
                $items = is_array($decoded['settings']['color']['palette'] ?? null) ? $decoded['settings']['color']['palette'] : [];
                foreach ($items as $item) {
                        if (!is_array($item)) {
                                continue;
                        }

                        $slug = sanitize_key((string) ($item['slug'] ?? ''));
                        $color = sanitize_hex_color((string) ($item['color'] ?? ''));
                        if ($slug === '' || $color === null) {
                                continue;
                        }

                        $palette[$slug] = $color;
                }

                return $palette;
        }

        private function get_placeholder_logo_url(): string {
            return plugins_url('assets/mail-logo-placeholder.svg', dirname(__DIR__) . '/wp_restatify-booking-assistant.php');
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
        return 'phone|'
            . __('Telefon', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|tel|+49...|'
            . __('Telefonnummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|'
            . __('Telefon: {value}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . "\nwhatsapp|WhatsApp|tel|+49...|"
            . __('Handynummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|WhatsApp: {value}'
            . "\nteams|Microsoft Teams|email|name@example.com|"
            . __('E-Mail-Adresse', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|'
            . __('Teams Kontakt: {value}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . "\nzoom|Zoom|email|name@example.com|"
            . __('E-Mail-Adresse', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|'
            . __('Zoom Kontakt: {value}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . "\ngoogle_meet|Google Meet|email|name@example.com|"
            . __('E-Mail-Adresse', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|'
            . __('Google Meet Kontakt: {value}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . "\nsignal|Signal|tel|+49...|"
            . __('Handynummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            . '|Signal: {value}';
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


