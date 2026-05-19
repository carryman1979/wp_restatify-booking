<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders frontend popup markup and admin settings UI.
 */
final class Restatify_Booking_Assistant_UI {
    private string $plugin_file;
    private Restatify_Booking_Assistant_Options $options_service;

    public function __construct(string $plugin_file, Restatify_Booking_Assistant_Options $options_service) {
        $this->plugin_file = $plugin_file;
        $this->options_service = $options_service;
    }

    /**
     * Enqueues frontend assets and localization payload.
     */
    public function enqueue_assets(): void {
        if (is_admin()) {
            return;
        }

        $base_url = plugin_dir_url($this->plugin_file) . 'assets/';
        $base_path = plugin_dir_path($this->plugin_file) . 'assets/';
        $options = $this->options_service->get_options();
        if ($this->should_disable_during_maintenance($options)) {
            return;
        }
        $no_slots_contact_email = sanitize_email((string) ($options['no_slots_contact_email'] ?? ''));
        if ($no_slots_contact_email === '') {
            $no_slots_contact_email = sanitize_email((string) get_option('admin_email', ''));
        }
        $multi_chat_available = $this->is_multi_chat_overlay_chat_enabled();

        wp_enqueue_style(
            Restatify_Booking_Assistant_Constants::FRONTEND_ASSET_HANDLE,
            $base_url . 'booking-assistant.css',
            [],
            file_exists($base_path . 'booking-assistant.css') ? (string) filemtime($base_path . 'booking-assistant.css') : '1.0.0'
        );

        wp_enqueue_script(
            Restatify_Booking_Assistant_Constants::FRONTEND_ASSET_HANDLE,
            $base_url . 'booking-assistant.js',
            [],
            file_exists($base_path . 'booking-assistant.js') ? (string) filemtime($base_path . 'booking-assistant.js') : '1.0.0',
            true
        );

        wp_localize_script(Restatify_Booking_Assistant_Constants::FRONTEND_ASSET_HANDLE, 'restatifyBookingAssistant', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Restatify_Booking_Assistant_Constants::NONCE_ACTION),
            'chatNonce' => wp_create_nonce('restatify_mco_chat_nonce'),
            'timezone' => (string) $options['default_timezone'],
            'durationMinutes' => (int) $options['default_duration_minutes'],
            'windowDays' => (int) $options['slot_window_days'],
            'triggerHash' => Restatify_Booking_Assistant_Constants::BOOKING_TRIGGER_HASH,
            'noSlots' => [
                'chatAvailable' => $multi_chat_available,
                'contactEmail' => $no_slots_contact_email,
            ],
            'strings' => [
                'loading' => __('Freie Termine werden gesucht...', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'empty' => __('Im ausgewählten Zeitraum wurden keine freien Termine gefunden.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'emptyRange' => __('Im konfigurierten Zeitraum sind aktuell keine freien Termine verfügbar.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'emptyChatHint' => __('Wenn es eilt, versuche bitte Kontakt über den Chat im Multi-Chat-Overlay aufzunehmen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'emptyEmailHint' => __('Alternativ schreibe uns bitte eine E-Mail an', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'contactValueLabelDefault' => __('Kontaktdaten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'contactMoreLabel' => __('Mehr...', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'contactLessLabel' => __('Weniger', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'reserve' => __('Termin reservieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'selectDay' => __('Tag im Kalender auswählen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'pickTime' => __('Uhrzeit auswählen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'success' => __('Reservierung eingegangen. Bitte prüfe deine E-Mails für die Details.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'error' => __('Reservierung fehlgeschlagen. Bitte versuche einen anderen Termin.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationGeneric' => __('Bitte prüfe deine Eingaben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationNameRequired' => __('Bitte deinen Namen eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationEmailRequired' => __('Bitte deine E-Mail-Adresse eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationEmailInvalid' => __('Bitte eine gültige E-Mail-Adresse eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationSubjectRequired' => __('Bitte einen Titel für den Termin eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationContactRequired' => __('Bitte Kontaktdaten eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationContactEmailInvalid' => __('Bitte eine gültige E-Mail-Adresse für den Kontaktkanal eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'validationContactUrlInvalid' => __('Bitte eine gültige URL für den Kontaktkanal eingeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            ],
        ]);
    }

    /**
     * Shortcode callback that renders a trigger + popup.
     *
     * @param array<string,mixed> $atts
     */
    public function render_shortcode(array $atts): string {
        $options = $this->options_service->get_options();
        if ($this->should_disable_during_maintenance($options)) {
            return '';
        }

        $atts = shortcode_atts([
            'label' => __('Termin finden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'title' => __('Gespräch buchen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ], $atts, 'restatify_booking_popup');

        return $this->render_popup_markup((string) $atts['label'], (string) $atts['title'], true, false);
    }

    /**
     * Renders the global popup in footer.
     */
    public function render_global_popup(): void {
        if (is_admin() || is_feed()) {
            return;
        }

        $options = $this->options_service->get_options();
        if ($this->should_disable_during_maintenance($options)) {
            return;
        }

        echo $this->render_popup_markup(
            __('Termin finden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            __('Gespräch buchen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            false,
            true
        );
    }

    /**
     * Adds booking overlay pseudo-link in WP link picker.
     *
     * @param array<int,array<string,mixed>> $results
     * @param array<string,mixed> $query
     * @return array<int,array<string,mixed>>
     */
    public function extend_wp_link_query(array $results, array $query): array {
        if (!current_user_can('edit_posts')) {
            return $results;
        }

        $search = strtolower(trim((string) ($query['s'] ?? '')));
        $matches_booking = $search === ''
            || str_contains($search, 'book')
            || str_contains($search, 'termin')
            || str_contains($search, 'slot')
            || str_contains($search, 'appoint');

        if (!$matches_booking) {
            return $results;
        }

        $results[] = [
            'ID' => 0,
            'title' => __('Buchungs-Popup (Restatify)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'permalink' => Restatify_Booking_Assistant_Constants::BOOKING_TRIGGER_HASH,
            'info' => __('Öffnet beim Klick das Restatify Buchungs-Overlay.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ];

        return $results;
    }

    /**
     * Renders plugin settings page.
     */
    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = $this->options_service->get_options();
        $default_options = $this->options_service->get_default_options();
        $shared_mail_editor_url = esc_url(home_url('/wp_restatify-shared/src/js/mail-template-editor.js'));
        $calendar_sources = is_array($options['api_calendar_sources'] ?? null) ? $options['api_calendar_sources'] : [];
        if (count($calendar_sources) === 0) {
            $calendar_sources = $this->options_service->parse_calendar_sources_raw((string) ($options['api_calendar_sources_raw'] ?? ''));
        }
        if (count($calendar_sources) === 0) {
            $calendar_sources[] = [
                'calendar_id' => '',
                'label' => '',
                'privacy_mode' => 'private',
                'calendar_type' => 'general',
            ];
        }
        $contact_channels = $this->options_service->get_contact_channels($options);
        if (count($contact_channels) === 0) {
            $contact_channels[] = [
                'key' => '',
                'label' => '',
                'input_kind' => 'tel',
                'placeholder' => '',
                'value_label' => '',
                'ics_template' => '{value}',
            ];
        }
        $availability_rules = is_array($options['api_availability_rules'] ?? null) ? $options['api_availability_rules'] : [];
        if (count($availability_rules) === 0) {
            $availability_rules = $this->options_service->parse_availability_raw((string) ($options['api_availability_raw'] ?? ''));
        }
        $weekday_labels = [
            0 => __('Montag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            1 => __('Dienstag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            2 => __('Mittwoch', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            3 => __('Donnerstag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            4 => __('Freitag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            5 => __('Samstag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            6 => __('Sonntag', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ];
        $availability_by_weekday = [];
        foreach ($availability_rules as $rule) {
            $weekday = (int) ($rule['weekday'] ?? -1);
            if ($weekday < 0 || $weekday > 6) {
                continue;
            }
            $availability_by_weekday[$weekday] = is_array($rule['windows'] ?? null) ? $rule['windows'] : [];
        }
        $mail_placeholders = class_exists('\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false)
            ? \Restatify\Shared\Mail\PlaceholderCatalog::bookingMail()
            : [
                '{name}',
                '{email}',
                '{site_name}',
                '{subject}',
                '{start}',
                '{end}',
                '{timezone}',
                '{note}',
                '{reference}',
                '{contact_method}',
                '{contact_value}',
                '{contact_detail}',
                '{cancellation_url}',
                '{cancellation_reason}',
                '{cancellation_message}',
            ];
        $mail_template_modals = [
            [
                'modal_id' => 'autoresponder',
                'button_label' => __('E-Mail-Template für Terminbestätigung bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'title' => __('Terminbestätigung bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'description' => __('Hier pflegst du Betreff sowie Text- und HTML-Version für die Bestätigung an Interessenten.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'sections' => [
                    [
                        'title' => __('Bestätigung an Interessenten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'toggles' => [
                            [
                                'key' => 'autoresponder_enabled',
                                'label' => __('Bestätigungsmail an Interessenten aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Sendet nach erfolgreicher Reservierung automatisch eine Bestätigung an die angegebene E-Mail-Adresse.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                            [
                                'key' => 'autoresponder_html_enabled',
                                'label' => __('HTML-Version mitsenden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Versendet die Mail als Multipart-Nachricht mit Text- und HTML-Teil.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                        ],
                        'subject_key' => 'autoresponder_subject',
                        'text_key' => 'autoresponder_body',
                        'text_help' => __('Die Textversion bleibt der Fallback für einfache Mail-Clients und sollte immer gepflegt sein.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'html_key' => 'autoresponder_html_body',
                        'html_editor_id' => 'restatify_booking_autoresponder_html_body',
                        'html_help' => __('Die HTML-Version wird im Visuell-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    ],
                ],
            ],
            [
                'modal_id' => 'owner',
                'button_label' => __('E-Mail-Template für Inhaber-Mail bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'title' => __('Inhaber-Mails bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'description' => __('Hier pflegst du interne Benachrichtigungen für neue Reservierungen und Stornierungen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'shared_fields' => [
                    [
                        'type' => 'textarea',
                        'key' => 'owner_notification_recipients',
                        'label' => __('Empfänger der Inhaber-Mails', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'rows' => 4,
                        'help' => __('Mehrere Empfänger per Komma oder jeweils in einer neuen Zeile angeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    ],
                ],
                'sections' => [
                    [
                        'title' => __('Neue Reservierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'toggles' => [
                            [
                                'key' => 'owner_notification_enabled',
                                'label' => __('Interne Benachrichtigung aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Sendet nach erfolgreicher Reservierung zusätzlich eine interne Benachrichtigung.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                            [
                                'key' => 'owner_notification_html_enabled',
                                'label' => __('HTML-Version mitsenden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Versendet die interne Buchungsbenachrichtigung ebenfalls als Multipart-Nachricht.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                        ],
                        'subject_key' => 'owner_notification_subject',
                        'text_key' => 'owner_notification_body',
                        'text_help' => __('Enthält die Text-Version für interne Buchungsbenachrichtigungen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'html_key' => 'owner_notification_html_body',
                        'html_editor_id' => 'restatify_booking_owner_notification_html_body',
                        'html_help' => __('Die HTML-Version wird im Visuell-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    ],
                    [
                        'title' => __('Stornierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'toggles' => [
                            [
                                'key' => 'owner_cancellation_enabled',
                                'label' => __('Interne Stornobenachrichtigung aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Sendet bei erfolgreicher Stornierung eine interne Benachrichtigung an dieselben Empfänger.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                            [
                                'key' => 'owner_cancellation_html_enabled',
                                'label' => __('HTML-Version mitsenden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Versendet die interne Stornobenachrichtigung als Multipart-Nachricht.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                        ],
                        'subject_key' => 'owner_cancellation_subject',
                        'text_key' => 'owner_cancellation_body',
                        'html_key' => 'owner_cancellation_html_body',
                        'html_editor_id' => 'restatify_booking_owner_cancellation_html_body',
                    ],
                ],
            ],
            [
                'modal_id' => 'cancellation',
                'button_label' => __('E-Mail-Template für Stornomail bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'title' => __('Stornomail bearbeiten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'description' => __('Hier pflegst du Betreff sowie Text- und HTML-Version für die Stornobestätigung an Interessenten.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'sections' => [
                    [
                        'title' => __('Stornobestätigung an Interessenten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'toggles' => [
                            [
                                'key' => 'cancellation_confirmation_enabled',
                                'label' => __('Stornobestätigung aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Sendet nach erfolgreicher Stornierung automatisch eine Bestätigung an den Interessenten.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                            [
                                'key' => 'cancellation_confirmation_html_enabled',
                                'label' => __('HTML-Version mitsenden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                                'description' => __('Versendet die Stornobestätigung als Multipart-Nachricht mit Text- und HTML-Teil.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                            ],
                        ],
                        'subject_key' => 'cancellation_confirmation_subject',
                        'text_key' => 'cancellation_confirmation_body',
                        'text_help' => __('Verwendbare Zusatz-Platzhalter: {cancellation_reason}, {cancellation_message}, {site_name}.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                        'html_key' => 'cancellation_confirmation_html_body',
                        'html_editor_id' => 'restatify_booking_cancellation_confirmation_html_body',
                        'html_help' => __('Die HTML-Version wird im Visuell-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    ],
                ],
            ],
        ];

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        $admin_settings_script_path = plugin_dir_path($this->plugin_file) . 'assets/admin-settings.js';
        $admin_settings_script_url = plugin_dir_url($this->plugin_file) . 'assets/admin-settings.js';
        $admin_settings_script_ver = file_exists($admin_settings_script_path)
            ? (string) filemtime($admin_settings_script_path)
            : '1.0.0';
        $admin_settings_script_url = esc_url(add_query_arg('ver', $admin_settings_script_ver, $admin_settings_script_url));
        $admin_settings_config_json = wp_json_encode([
            'optionKey' => Restatify_Booking_Assistant_Constants::OPTION_KEY,
            'calendarNamePlaceholder' => __('Kalendername', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'deleteLabel' => __('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'whatsappPlaceholder' => __('WhatsApp', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'phoneNumberPlaceholder' => __('Telefonnummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'untilLabel' => __('bis', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ]);
        if (!is_string($admin_settings_config_json) || $admin_settings_config_json === '') {
            $admin_settings_config_json = '{}';
        }

        require $this->get_template_path('admin-page.inc.php');
    }

    /**
     * @param array<string,mixed> $modal
     * @param array<string,mixed> $options
    * @param array<string,mixed> $default_options
    * @param array<int,string> $mail_placeholders
     */
    private function render_mail_template_modal(array $modal, array $options, array $default_options, array $mail_placeholders): void {
        require $this->get_template_path('mail-template-modal.inc.php');
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $options
     */
    private function render_mail_template_shared_field(array $field, array $options): void {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            return;
        }

        $label = (string) ($field['label'] ?? '');
        $help = (string) ($field['help'] ?? '');
        $type = (string) ($field['type'] ?? 'text');
        ?>
        <div class="rs-mail-modal__field">
            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
            <?php if ($type === 'textarea') : ?>
                <textarea
                    class="large-text code"
                    id="<?php echo esc_attr($key); ?>"
                    rows="<?php echo esc_attr((string) ((int) ($field['rows'] ?? 4))); ?>"
                    name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                ><?php echo esc_textarea((string) ($options[$key] ?? '')); ?></textarea>
            <?php else : ?>
                <input class="regular-text" id="<?php echo esc_attr($key); ?>" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) ($options[$key] ?? '')); ?>">
            <?php endif; ?>
            <?php if ($help !== '') : ?>
                <p class="description"><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $section
     * @param array<string,mixed> $options
     * @param array<string,mixed> $default_options
     */
    private function render_mail_template_section(array $section, array $options, array $default_options): void {
        $subject_key = (string) ($section['subject_key'] ?? '');
        $text_key = (string) ($section['text_key'] ?? '');
        $html_key = (string) ($section['html_key'] ?? '');
        $html_editor_id = (string) ($section['html_editor_id'] ?? '');
        $tab_group = $html_editor_id !== '' ? $html_editor_id : $text_key;

        require $this->get_template_path('mail-template-section.inc.php');
    }

    private function get_template_path(string $file): string {
        return plugin_dir_path($this->plugin_file) . 'templates/' . ltrim($file, '/');
    }

    /**
     * @param array<string,mixed> $toggle
     * @param array<string,mixed> $options
     */
    private function render_mail_template_toggle(array $toggle, array $options): void {
        $key = (string) ($toggle['key'] ?? '');
        if ($key === '') {
            return;
        }
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($options[$key])); ?>>
            <strong><?php echo esc_html((string) ($toggle['label'] ?? '')); ?></strong>
            <?php if (!empty($toggle['description'])) : ?>
                <span class="description"><?php echo esc_html((string) $toggle['description']); ?></span>
            <?php endif; ?>
        </label>
        <?php
    }

    /**
     * @param array<string,mixed> $modal
     * @param array<string,mixed> $options
     */
    private function get_mail_template_button_summary(array $modal, array $options): string {
        $summaries = [];

        foreach ((array) ($modal['sections'] ?? []) as $section) {
            $section_title = (string) ($section['title'] ?? '');
            $enabled = false;

            foreach ((array) ($section['toggles'] ?? []) as $toggle) {
                $key = (string) ($toggle['key'] ?? '');
                if ($key !== '' && !empty($options[$key])) {
                    $enabled = true;
                    break;
                }
            }

            if ($section_title !== '') {
                $summaries[] = sprintf(
                    '%s: %s',
                    $section_title,
                    $enabled
                        ? __('aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
                        : __('inaktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
                );
            }
        }

        if (count($summaries) === 0) {
            return __('Vorlage öffnen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
        }

        return implode(' · ', $summaries);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function is_multi_chat_overlay_chat_enabled(): bool {
        if (!class_exists('Restatify_Multi_Chat_Overlay')) {
            return false;
        }

        $multi_chat_options = get_option('restatify_multi_chat_overlay_options', []);
        if (!is_array($multi_chat_options)) {
            return false;
        }

        return !empty($multi_chat_options['enabled']) && !empty($multi_chat_options['own_chat_enabled']);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function should_disable_during_maintenance(array $options): bool {
        if (empty($options['disable_during_maintenance'])) {
            return false;
        }

        if (!$this->is_lightstart_available()) {
            return false;
        }

        $maintenance_options = get_option('wpmm_settings', []);
        if (!is_array($maintenance_options)) {
            return false;
        }

        return !empty($maintenance_options['general']['status']);
    }

    /**
     * Detect whether LightStart is installed and currently active.
     *
     * We check both standard and multisite network activation to keep the
     * maintenance toggle behavior consistent across installations.
     */
    private function is_lightstart_available(): bool {
        if (class_exists('\\Restatify\\Shared\\Runtime\\PluginState', false)) {
            return \Restatify\Shared\Runtime\PluginState::isLightstartAvailable();
        }

        if (!file_exists(WP_PLUGIN_DIR . '/wp-maintenance-mode/wp-maintenance-mode.php')) {
            return false;
        }

        $active_plugins = (array) get_option('active_plugins', []);
        $network_plugins = is_multisite() ? (array) get_site_option('active_sitewide_plugins', []) : [];

        return in_array('wp-maintenance-mode/wp-maintenance-mode.php', $active_plugins, true)
            || isset($network_plugins['wp-maintenance-mode/wp-maintenance-mode.php']);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function render_popup_markup(string $label, string $title, bool $with_trigger, bool $is_global): string {
        $options = $this->options_service->get_options();
        $contact_channels = $this->options_service->get_contact_channels($options);
        $prominent_count = max(1, min(6, absint($options['contact_prominent_count'] ?? 3)));
        $default_contact_value_label = __('Kontaktdaten', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
        $default_channel = $contact_channels[0] ?? [
            'key' => 'phone',
            'label' => __('Telefon', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'input_kind' => 'tel',
            'placeholder' => '+49...',
            'value_label' => __('Telefonnummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'ics_template' => __('Telefon: {value}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ];
        $default_input_kind = (string) ($default_channel['input_kind'] ?? 'tel');
        $default_input_type = in_array($default_input_kind, ['email', 'url', 'tel', 'text'], true) ? $default_input_kind : 'text';
        $default_input_mode = $default_input_type === 'email' ? 'email' : ($default_input_type === 'tel' ? 'tel' : 'text');
        $privacy_policy_url = trim((string) ($options['privacy_policy_url'] ?? ''));
        if ($privacy_policy_url === '' && function_exists('get_privacy_policy_url')) {
            $privacy_policy_url = (string) get_privacy_policy_url();
        }

        ob_start();
        ?>
        <div class="restatify-booking" data-restatify-booking<?php echo $is_global ? ' data-booking-global="1"' : ''; ?>>
            <?php if ($with_trigger) : ?>
                <button type="button" class="restatify-booking__trigger" data-booking-open><?php echo esc_html($label); ?></button>
            <?php endif; ?>
            <div class="restatify-booking__overlay" data-booking-overlay hidden>
                <div class="restatify-booking__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($title); ?>">
                    <button type="button" class="restatify-booking__close" data-booking-close aria-label="<?php esc_attr_e('Schließen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">&times;</button>
                    <h3 class="restatify-booking__title"><?php echo esc_html($title); ?></h3>

                    <div class="restatify-booking__status" data-booking-status></div>

                    <form class="restatify-booking__form" data-booking-form hidden>
                        <input type="hidden" name="slot_start" data-slot-start>
                        <div class="restatify-booking__wizard" data-booking-wizard>
                            <div class="restatify-booking__wizard-track" data-booking-steps>
                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <p class="restatify-booking__times-heading"><?php esc_html_e('Tag im Kalender auswählen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    <div class="restatify-booking__calendar" data-booking-calendar></div>
                                </section>

                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <p class="restatify-booking__times-heading"><?php esc_html_e('Uhrzeit auswählen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    <div class="restatify-booking__times" data-booking-times></div>
                                </section>

                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <div class="restatify-booking__contact-block">
                                        <span class="restatify-booking__contact-heading"><?php esc_html_e('Bevorzugter Kontaktkanal', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <input type="hidden" name="contact_method" data-contact-method value="<?php echo esc_attr((string) $default_channel['key']); ?>" required>
                                        <div class="restatify-booking__contact-channels<?php echo count($contact_channels) > $prominent_count ? ' is-collapsed' : ''; ?>" data-contact-channels>
                                            <?php foreach ($contact_channels as $index => $channel) :
                                                $channel_key = (string) ($channel['key'] ?? '');
                                                if ($channel_key === '') {
                                                    continue;
                                                }
                                                ?>
                                                <button
                                                    type="button"
                                                    class="restatify-booking__contact-channel<?php echo $index >= $prominent_count ? ' is-extra' : ''; ?><?php echo $index === 0 ? ' is-selected' : ''; ?>"
                                                    data-contact-channel
                                                    data-method-key="<?php echo esc_attr($channel_key); ?>"
                                                    data-input-kind="<?php echo esc_attr((string) ($channel['input_kind'] ?? 'text')); ?>"
                                                    data-value-label="<?php echo esc_attr((string) ($channel['value_label'] ?? $default_contact_value_label)); ?>"
                                                    data-placeholder="<?php echo esc_attr((string) ($channel['placeholder'] ?? '')); ?>"
                                                    data-ics-template="<?php echo esc_attr((string) ($channel['ics_template'] ?? '')); ?>"
                                                    aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                                >
                                                    <?php echo esc_html((string) ($channel['label'] ?? $channel_key)); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($contact_channels) > $prominent_count) : ?>
                                            <button
                                                type="button"
                                                class="restatify-booking__contact-toggle"
                                                data-contact-channels-toggle
                                                data-label-more="<?php echo esc_attr((string) $options['contact_more_label']); ?>"
                                                data-label-less="<?php echo esc_attr((string) $options['contact_less_label']); ?>"
                                                aria-expanded="false"
                                            >
                                                <?php echo esc_html((string) $options['contact_more_label']); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <label>
                                        <span data-contact-value-label><?php echo esc_html((string) $default_channel['value_label']); ?></span>
                                        <input
                                            type="<?php echo esc_attr($default_input_type); ?>"
                                            inputmode="<?php echo esc_attr($default_input_mode); ?>"
                                            name="contact_value"
                                            data-contact-value
                                            required
                                            maxlength="190"
                                            placeholder="<?php echo esc_attr((string) $default_channel['placeholder']); ?>"
                                        >
                                    </label>
                                </section>

                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <label>
                                        <span><?php esc_html_e('Name', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <input type="text" name="name" required maxlength="190">
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('E-Mail', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <input type="email" name="email" required maxlength="190">
                                    </label>
                                </section>

                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <label>
                                        <span><?php esc_html_e('Titel für den Termin', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <input type="text" name="subject" required maxlength="190" placeholder="<?php esc_attr_e('z.B. Erstberatung Immobilienkauf', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Freie Beschreibung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <textarea name="note" rows="4" maxlength="1000"></textarea>
                                    </label>
                                </section>
                            </div>
                        </div>

                        <div class="restatify-booking__wizard-nav">
                            <button type="button" class="restatify-booking__wizard-btn" data-step-prev hidden><?php esc_html_e('Zurück', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                            <span class="restatify-booking__wizard-indicator" data-step-indicator>1/5</span>
                            <button type="button" class="restatify-booking__wizard-btn" data-step-next><?php esc_html_e('Weiter', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                        </div>

                        <button type="button" class="restatify-booking__wizard-btn" data-step-pick-slot hidden><?php esc_html_e('Ersatztermin wählen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                        <button type="submit" class="restatify-booking__submit" hidden><?php esc_html_e('Jetzt reservieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                    </form>

                    <?php if ($privacy_policy_url !== '') : ?>
                        <p class="restatify-booking__legal-notice">
                            <?php esc_html_e('Mit der Nutzung dieses Buchungstools stimmst du unseren Datenschutzbestimmungen zu.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                            <a href="<?php echo esc_url($privacy_policy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Datenschutzerklärung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></a>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}


