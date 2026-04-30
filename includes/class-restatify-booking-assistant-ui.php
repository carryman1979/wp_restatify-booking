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
            'restatify-booking-assistant',
            $base_url . 'booking-assistant.css',
            [],
            file_exists($base_path . 'booking-assistant.css') ? (string) filemtime($base_path . 'booking-assistant.css') : '1.0.0'
        );

        wp_enqueue_script(
            'restatify-booking-assistant',
            $base_url . 'booking-assistant.js',
            [],
            file_exists($base_path . 'booking-assistant.js') ? (string) filemtime($base_path . 'booking-assistant.js') : '1.0.0',
            true
        );

        wp_localize_script('restatify-booking-assistant', 'restatifyBookingAssistant', [
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
        $mail_placeholders = [
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
                        'html_help' => __('Die HTML-Version wird im WYSIWYG-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
                        'html_help' => __('Die HTML-Version wird im WYSIWYG-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
                        'html_help' => __('Die HTML-Version wird im WYSIWYG-Editor bearbeitet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    ],
                ],
            ],
        ];

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        ?>
        <div class="wrap">
            <style>
                .wrap .rs-admin-grid {
                    display: grid;
                    gap: 18px;
                    margin-top: 16px;
                }

                .wrap .rs-admin-card {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
                    padding: 20px;
                }

                .wrap .rs-admin-card h3 {
                    margin-top: 0;
                    margin-bottom: 8px;
                }

                .wrap .rs-admin-card p:last-child {
                    margin-bottom: 0;
                }

                .wrap .rs-admin-section-lead {
                    color: #50575e;
                    margin: 0 0 14px;
                }

                .wrap .form-table td,
                .wrap .widefat td,
                .wrap details {
                    overflow: visible;
                }

                .wrap .form-table input[type="text"],
                .wrap .form-table input[type="url"],
                .wrap .form-table input[type="email"],
                .wrap .form-table input[type="password"],
                .wrap .form-table input[type="number"],
                .wrap .form-table textarea,
                .wrap .form-table select {
                    position: relative;
                    z-index: 0;
                }

                .wrap .form-table input[type="text"]:focus,
                .wrap .form-table input[type="url"]:focus,
                .wrap .form-table input[type="email"]:focus,
                .wrap .form-table input[type="password"]:focus,
                .wrap .form-table input[type="number"]:focus,
                .wrap .form-table textarea:focus,
                .wrap .form-table select:focus {
                    border-color: #d63638;
                    box-shadow: inset 0 0 0 1px #d63638;
                    outline: 0;
                    z-index: 1;
                }

                .wrap .form-table textarea {
                    min-height: 120px;
                    resize: vertical;
                }

                .wrap .rs-inline-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin: 0 0 12px;
                }

                .wrap .rs-mail-placeholder-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin: 8px 0 10px;
                }

                .wrap .rs-mail-placeholder-list .button {
                    margin: 0;
                }

                .wrap .rs-mail-editor-cell .wp-editor-wrap {
                    max-width: 980px;
                }

                .wrap .rs-mail-template-actions {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                    margin-top: 16px;
                }

                .wrap .rs-availability-days {
                    align-items: flex-start;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                    justify-content: flex-start;
                }

                .wrap .rs-availability-day {
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    box-sizing: border-box;
                    flex: 1 1 300px;
                    max-width: 380px;
                    min-width: 300px;
                    overflow: hidden;
                    padding: 14px;
                }

                .wrap .rs-availability-day.is-disabled {
                    background: #f6f7f7;
                }

                .wrap .rs-availability-day__toggle {
                    align-items: center;
                    display: flex;
                    font-weight: 600;
                    gap: 8px;
                    margin-bottom: 10px;
                }

                .wrap .rs-availability-day__slots[hidden] {
                    display: none;
                }

                .wrap .rs-availability-slots {
                    display: grid;
                    gap: 8px;
                    margin-bottom: 10px;
                }

                .wrap .rs-availability-slot {
                    align-items: center;
                    display: grid;
                    gap: 8px;
                    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                    justify-content: stretch;
                }

                .wrap .rs-availability-slot span {
                    color: #50575e;
                }

                .wrap .rs-availability-slot input[type="time"] {
                    min-width: 0;
                    width: 100%;
                }

                .wrap .rs-availability-slot [data-rs-remove-availability-slot] {
                    grid-column: 1 / -1;
                    justify-self: start;
                }

                @media (max-width: 782px) {
                    .wrap .rs-availability-day {
                        max-width: none;
                        min-width: 100%;
                    }
                }

                .wrap .rs-mail-template-button {
                    align-items: flex-start;
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    cursor: pointer;
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    min-height: 108px;
                    padding: 16px;
                    text-align: left;
                    width: 100%;
                }

                .wrap .rs-mail-template-button strong {
                    font-size: 14px;
                }

                .wrap .rs-mail-template-button span {
                    color: #50575e;
                }

                .wrap .rs-mail-template-button em {
                    color: #1d2327;
                    font-style: normal;
                    font-weight: 600;
                }

                .wrap .rs-mail-modal {
                    align-items: center;
                    background: rgba(15, 23, 42, 0.6);
                    display: flex;
                    inset: 0;
                    justify-content: center;
                    padding: 24px;
                    position: fixed;
                    z-index: 100000;
                }

                .wrap .rs-mail-modal[hidden] {
                    display: none;
                }

                .wrap .rs-mail-modal__panel {
                    background: #fff;
                    border-radius: 18px;
                    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.24);
                    max-height: min(92vh, 1100px);
                    max-width: 1120px;
                    overflow: auto;
                    padding: 24px;
                    position: relative;
                    width: min(100%, 1120px);
                }

                .wrap .rs-mail-modal__close {
                    font-size: 24px;
                    line-height: 1;
                    min-width: 40px;
                    padding: 4px 10px;
                    position: absolute;
                    right: 18px;
                    top: 18px;
                }

                .wrap .rs-mail-modal__intro {
                    margin: 0 48px 18px 0;
                }

                .wrap .rs-mail-modal__section {
                    border-top: 1px solid #dcdcde;
                    margin-top: 18px;
                    padding-top: 18px;
                }

                .wrap .rs-mail-modal__section:first-of-type {
                    border-top: 0;
                    margin-top: 0;
                    padding-top: 0;
                }

                .wrap .rs-mail-modal__field {
                    margin-bottom: 18px;
                }

                .wrap .rs-mail-modal__field label,
                .wrap .rs-mail-modal__field > span {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 6px;
                }

                .wrap .rs-mail-modal__field .description {
                    margin-top: 6px;
                }

                .wrap .rs-mail-modal__checks {
                    display: grid;
                    gap: 12px;
                    margin-bottom: 18px;
                }

                .wrap .rs-mail-modal__checks label {
                    display: block;
                    font-weight: 400;
                }

                .wrap .rs-mail-modal__checks strong {
                    display: block;
                    margin-bottom: 4px;
                }

                .wrap .rs-mail-modal__footer {
                    border-top: 1px solid #dcdcde;
                    margin-top: 24px;
                    padding-top: 18px;
                }

                body.rs-mail-modal-open {
                    overflow: hidden;
                }
            </style>
            <h1><?php esc_html_e('Restatify Booking Assistant', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Konfiguriere zuerst die grundlegende API-Verbindung. Kontaktkanäle sind direkt in der Basis-Konfiguration verfügbar, erweiterte Einstellungen für Synchronisierung und E-Mail-Templates folgen darunter.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('restatify_booking_assistant'); ?>

                <h2><?php esc_html_e('Grundkonfiguration (erforderlich)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h2>
                <div class="rs-admin-grid">
                    <section class="rs-admin-card">
                        <h3><?php esc_html_e('API-Verbindung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                        <p class="rs-admin-section-lead"><?php esc_html_e('Diese Angaben sind zwingend erforderlich, damit das Buchungs-Plugin mit der Booking-API sprechen kann.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Booking-API Basis-URL', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text code" type="url" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_base_url]" value="<?php echo esc_attr((string) $options['api_base_url']); ?>" placeholder="https://booking-api.example.com">
                                    <p class="description"><?php esc_html_e('Öffentlicher API-Endpunkt. Beispiel: https://booking-api.deine-domain.tld', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('API-Schlüssel', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text" type="password" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr((string) $options['api_key']); ?>">
                                    <p class="description"><?php esc_html_e('Erforderlich für alle API-Aufrufe. Bitte vertraulich behandeln.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Standard-Zeitzone', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[default_timezone]" value="<?php echo esc_attr((string) $options['default_timezone']); ?>">
                                    <p class="description"><?php esc_html_e('Zeitzone für Terminsuche, Buchungszeitstempel und Platzhalter in den Mails. Beispiel: Europe/Berlin.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="rs-admin-card">
                        <h3><?php esc_html_e('Buchungslogik & Schutz', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                        <p class="rs-admin-section-lead"><?php esc_html_e('Hier steuerst du Dauer, Suchfenster und den Schutz gegen übermäßige öffentliche Anfragen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Standarddauer (Minuten)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="small-text" type="number" min="15" max="180" step="15" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[default_duration_minutes]" value="<?php echo esc_attr((string) $options['default_duration_minutes']); ?>">
                                    <p class="description"><?php esc_html_e('Definiert die Buchungsdauer. Es werden nur Termine angeboten, die lang genug sind, und Reservierungen werden mit diesem Wert angelegt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Suchzeitraum (Tage)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="small-text" type="number" min="1" max="60" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[slot_window_days]" value="<?php echo esc_attr((string) $options['slot_window_days']); ?>">
                                    <p class="description"><?php esc_html_e('Wie viele Tage im Voraus das Popup nach freien Terminen sucht. Höhere Werte zeigen mehr Optionen, können aber die Antwortzeit erhöhen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Öffentliches Rate-Limit aktivieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_enabled]" value="1" <?php checked(!empty($options['public_rate_limit_enabled'])); ?>>
                                        <?php esc_html_e('Begrenzt anonyme Booking-Anfragen pro Besucher-Fingerprint (IP + User-Agent).', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Rate-Limit-Fenster (Sekunden)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td><input class="small-text" type="number" min="10" max="3600" step="10" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_window_seconds]" value="<?php echo esc_attr((string) $options['public_rate_limit_window_seconds']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Maximale Slot-Suchen pro Fenster', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td><input class="small-text" type="number" min="1" max="120" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_max_find_slots]" value="<?php echo esc_attr((string) $options['public_rate_limit_max_find_slots']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Maximale Reservierungen pro Fenster', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="small-text" type="number" min="1" max="60" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_max_reserve_slot]" value="<?php echo esc_attr((string) $options['public_rate_limit_max_reserve_slot']); ?>">
                                    <p class="description"><?php esc_html_e('Bei Überschreitung wird HTTP 429 zurückgegeben und der Besucher kann es später erneut versuchen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Fallback-Kontakt-E-Mail', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text" type="email" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[no_slots_contact_email]" value="<?php echo esc_attr((string) ($options['no_slots_contact_email'] ?? '')); ?>" placeholder="contact@example.com">
                                    <p class="description"><?php esc_html_e('Wird angezeigt, wenn im konfigurierten Suchzeitraum keine freien Termine verfügbar sind.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <?php if ($this->is_lightstart_available()) : ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Bei LightStart-Wartung ausblenden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[disable_during_maintenance]" value="1" <?php checked(!empty($options['disable_during_maintenance'])); ?>>
                                            <?php esc_html_e('Booking-Overlay nicht anzeigen, solange der Wartungsmodus (LightStart) aktiv ist.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </section>

                    <section class="rs-admin-card">
                        <h3><?php esc_html_e('Kalender & Verfügbarkeit', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                        <p class="rs-admin-section-lead"><?php esc_html_e('Definiere hier, welche Kalender gelesen werden, wohin geschrieben wird und zu welchen Zeiten überhaupt Termine angeboten werden dürfen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Zu synchronisierende Kalender', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <div class="rs-inline-actions"><button type="button" class="button" data-rs-add-calendar-row="top"><?php esc_html_e('Kalender hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></div>

                                    <table class="widefat striped" data-rs-calendar-sources-table style="max-width:1100px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Kalender-ID', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Bezeichnung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Sichtbarkeit', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Typ', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Aktion', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calendar_sources as $idx => $source) : ?>
                                                <?php
                                                $calendar_id = (string) ($source['calendar_id'] ?? '');
                                                $label = (string) ($source['label'] ?? '');
                                                $privacy_mode = (string) ($source['privacy_mode'] ?? 'private');
                                                $calendar_type = (string) ($source['calendar_type'] ?? 'general');
                                                ?>
                                                <tr data-rs-calendar-source-row>
                                                    <td>
                                                        <input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_rows][<?php echo esc_attr((string) $idx); ?>][calendar_id]" value="<?php echo esc_attr($calendar_id); ?>" placeholder="calendar-id@group.calendar.google.com">
                                                    </td>
                                                    <td>
                                                        <input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_rows][<?php echo esc_attr((string) $idx); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Kalendername', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">
                                                    </td>
                                                    <td>
                                                        <select name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_rows][<?php echo esc_attr((string) $idx); ?>][privacy_mode]">
                                                            <option value="private" <?php selected($privacy_mode, 'private'); ?>>private</option>
                                                            <option value="official" <?php selected($privacy_mode, 'official'); ?>>official</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_rows][<?php echo esc_attr((string) $idx); ?>][calendar_type]">
                                                            <option value="general" <?php selected($calendar_type, 'general'); ?>>general</option>
                                                            <option value="holiday" <?php selected($calendar_type, 'holiday'); ?>>holiday</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="button-link-delete" data-rs-remove-calendar-row><?php esc_html_e('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <p style="margin-top:8px;"><button type="button" class="button" data-rs-add-calendar-row="bottom"><?php esc_html_e('Kalender hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></p>
                                    <p class="description"><?php esc_html_e('Empfohlen: Pflege Kalender als einzelne Zeilen mit Dropdowns, um Formatfehler zu vermeiden.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>

                                    <details style="margin-top:8px;">
                                        <summary><?php esc_html_e('Rohformat (Legacy)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></summary>
                                        <textarea class="large-text code" rows="5" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_raw]"><?php echo esc_textarea((string) ($options['api_calendar_sources_raw'] ?? '')); ?></textarea>
                                        <p class="description"><?php esc_html_e('Format je Zeile: calendar_id|Bezeichnung|private oder official|general oder holiday. Wird nur verwendet, wenn oben keine Zeilen gepflegt sind.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    </details>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Google-Zielkalender-ID für Schreibzugriffe', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_google_write_calendar_id]" value="<?php echo esc_attr((string) $options['api_google_write_calendar_id']); ?>" placeholder="primary" required>
                                    <p class="description"><?php esc_html_e('Erforderlich, wenn „Google-Events erstellen“ aktiv ist. Es wird kein Fallback-Kalender verwendet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Wöchentliche Verfügbarkeitsregeln', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input type="hidden" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_editor_present]" value="1">
                                    <div class="rs-availability-days" data-rs-availability-days>
                                        <?php foreach ($weekday_labels as $weekday => $weekday_label) : ?>
                                            <?php
                                            $day_windows = is_array($availability_by_weekday[$weekday] ?? null) ? $availability_by_weekday[$weekday] : [];
                                            $is_enabled = count($day_windows) > 0;
                                            ?>
                                            <div class="rs-availability-day<?php echo $is_enabled ? '' : ' is-disabled'; ?>" data-rs-availability-day data-weekday="<?php echo esc_attr((string) $weekday); ?>">
                                                <label class="rs-availability-day__toggle">
                                                    <input type="checkbox" data-rs-availability-enabled name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_rows][<?php echo esc_attr((string) $weekday); ?>][enabled]" value="1" <?php checked($is_enabled); ?>>
                                                    <span><?php echo esc_html($weekday_label); ?></span>
                                                </label>
                                                <div class="rs-availability-day__slots" data-rs-availability-slots-wrapper<?php echo $is_enabled ? '' : ' hidden'; ?>>
                                                    <div class="rs-availability-slots" data-rs-availability-slots>
                                                        <?php foreach ($day_windows as $slot_index => $window) : ?>
                                                            <div class="rs-availability-slot" data-rs-availability-slot>
                                                                <input type="time" data-rs-field="start" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_rows][<?php echo esc_attr((string) $weekday); ?>][windows][<?php echo esc_attr((string) $slot_index); ?>][start]" value="<?php echo esc_attr((string) ($window['start'] ?? '')); ?>">
                                                                <span><?php esc_html_e('bis', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                                                <input type="time" data-rs-field="end" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_rows][<?php echo esc_attr((string) $weekday); ?>][windows][<?php echo esc_attr((string) $slot_index); ?>][end]" value="<?php echo esc_attr((string) ($window['end'] ?? '')); ?>">
                                                                <button type="button" class="button-link-delete" data-rs-remove-availability-slot><?php esc_html_e('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="button" class="button" data-rs-add-availability-slot><?php esc_html_e('Zeitfenster hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php esc_html_e('Aktiviere die gewünschten Wochentage und hinterlege pro Tag beliebig viele Zeitfenster.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    <details style="margin-top:8px;">
                                        <summary><?php esc_html_e('Rohformat (Legacy)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></summary>
                                        <textarea class="large-text code" rows="7" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_raw]"><?php echo esc_textarea((string) ($options['api_availability_raw'] ?? '')); ?></textarea>
                                        <p class="description"><?php esc_html_e('Format je Zeile: day|HH:MM-HH:MM,HH:MM-HH:MM. Wird nur verwendet, wenn der neue Editor nicht aktiv ist.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    </details>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="rs-admin-card">
                        <h3><?php esc_html_e('Kontaktkanäle', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                        <p class="rs-admin-section-lead"><?php esc_html_e('Lege hier fest, welche Kontaktwege im Buchungsdialog angeboten werden und wie sie beschriftet sind.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Kontaktkanäle', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <div class="rs-inline-actions"><button type="button" class="button" data-rs-add-contact-row="top"><?php esc_html_e('Kontaktkanal hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></div>
                                    <table class="widefat striped" data-rs-contact-channels-table style="max-width:1200px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Schlüssel', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Bezeichnung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Eingabetyp', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Platzhalter', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Wertbezeichnung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('ICS-Vorlage', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                                <th><?php esc_html_e('Aktion', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contact_channels as $idx => $channel) : ?>
                                                <?php
                                                $channel_key = (string) ($channel['key'] ?? '');
                                                $label = (string) ($channel['label'] ?? '');
                                                $input_kind = (string) ($channel['input_kind'] ?? 'tel');
                                                $placeholder = (string) ($channel['placeholder'] ?? '');
                                                $value_label = (string) ($channel['value_label'] ?? '');
                                                $ics_template = (string) ($channel['ics_template'] ?? '{value}');
                                                ?>
                                                <tr data-rs-contact-channel-row>
                                                    <td><input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][key]" value="<?php echo esc_attr($channel_key); ?>" placeholder="whatsapp"></td>
                                                    <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('WhatsApp', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>"></td>
                                                    <td>
                                                        <select name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][input_kind]">
                                                            <option value="tel" <?php selected($input_kind, 'tel'); ?>>tel</option>
                                                            <option value="email" <?php selected($input_kind, 'email'); ?>>email</option>
                                                            <option value="url" <?php selected($input_kind, 'url'); ?>>url</option>
                                                            <option value="text" <?php selected($input_kind, 'text'); ?>>text</option>
                                                        </select>
                                                    </td>
                                                    <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>" placeholder="+49..."></td>
                                                    <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][value_label]" value="<?php echo esc_attr($value_label); ?>" placeholder="<?php esc_attr_e('Telefonnummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>"></td>
                                                    <td><input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_rows][<?php echo esc_attr((string) $idx); ?>][ics_template]" value="<?php echo esc_attr($ics_template); ?>" placeholder="Telefon: {value}"></td>
                                                    <td><button type="button" class="button-link-delete" data-rs-remove-contact-row><?php esc_html_e('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p style="margin-top:8px;"><button type="button" class="button" data-rs-add-contact-row="bottom"><?php esc_html_e('Kontaktkanal hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></p>
                                    <p class="description"><?php esc_html_e('Pflege Kontaktkanäle als einzelne Zeilen mit festen Spalten, ähnlich wie bei den Kalender-IDs.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    <details style="margin-top:8px;">
                                        <summary><?php esc_html_e('Rohformat (Legacy)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></summary>
                                        <textarea class="large-text code" rows="6" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_raw]"><?php echo esc_textarea((string) ($options['contact_channels_raw'] ?? '')); ?></textarea>
                                        <p class="description"><?php esc_html_e('Format je Zeile: key|Bezeichnung|input_kind(tel/email/url/text)|placeholder|Wertbezeichnung|ICS-Vorlage. Wird nur verwendet, wenn oben keine Zeilen gepflegt sind.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                    </details>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Anzahl prominent angezeigter Kanäle', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td><input class="small-text" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_prominent_count]" value="<?php echo esc_attr((string) $options['contact_prominent_count']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Beschriftung: Mehr Kanäle', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_more_label]" value="<?php echo esc_attr((string) $options['contact_more_label']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Beschriftung: Weniger Kanäle', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_less_label]" value="<?php echo esc_attr((string) $options['contact_less_label']); ?>"></td>
                            </tr>
                        </table>
                    </section>
                </div>

                <details style="margin-top:12px;">
                    <summary><strong><?php esc_html_e('Experteneinstellungen (optional)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong></summary>
                    <p class="description"><?php esc_html_e('Optionales Feintuning für Synchronisierung und Mail-Vorlagen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>

                    <div class="rs-admin-grid">
                        <section class="rs-admin-card">
                            <h3><?php esc_html_e('Synchronisierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('API-Sync aktivieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_sync_enabled]" value="1" <?php checked(!empty($options['api_sync_enabled'])); ?>>
                                            <?php esc_html_e('Erlaubt der API die Ausführung der Kalender-Frei/Belegt-Synchronisierung.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Sync-Intervall (Minuten)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                    <td><input class="small-text" type="number" min="5" max="720" step="5" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_sync_interval_minutes]" value="<?php echo esc_attr((string) $options['api_sync_interval_minutes']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Google-Events erstellen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_google_write_events_enabled]" value="1" <?php checked(!empty($options['api_google_write_events_enabled'])); ?>>
                                            <?php esc_html_e('Nach einer erfolgreichen Reservierung wird zusätzlich ein Event im Google Kalender erstellt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </section>

                        <section class="rs-admin-card">
                            <h3><?php esc_html_e('E-Mail-Templates', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h3>
                            <p><?php esc_html_e('Die eigentlichen Vorlagen werden in einem Overlay geöffnet, damit die Einstellungsseite schlank bleibt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            <div class="rs-mail-template-actions">
                                <?php foreach ($mail_template_modals as $modal) : ?>
                                    <button type="button" class="rs-mail-template-button" data-rs-open-mail-modal="<?php echo esc_attr((string) $modal['modal_id']); ?>">
                                        <strong><?php echo esc_html((string) $modal['button_label']); ?></strong>
                                        <span><?php echo esc_html((string) $modal['description']); ?></span>
                                        <em><?php echo esc_html($this->get_mail_template_button_summary($modal, $options)); ?></em>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top:16px;"><?php esc_html_e('{cancellation_url} wird nur gesetzt, wenn die API einen Storno-Token zurückliefert.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </section>
                    </div>
                </details>

                <?php foreach ($mail_template_modals as $modal) : ?>
                    <?php $this->render_mail_template_modal($modal, $options, $mail_placeholders); ?>
                <?php endforeach; ?>
                <?php
                $force_sync_url = wp_nonce_url(
                    admin_url('admin-post.php?action=' . Restatify_Booking_Assistant_Constants::FORCE_SYNC_ACTION),
                    Restatify_Booking_Assistant_Constants::FORCE_SYNC_NONCE_ACTION
                );
                ?>
                <p>
                    <a class="button button-secondary" href="<?php echo esc_url($force_sync_url); ?>"><?php esc_html_e('Force Sync jetzt ausführen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></a>
                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Sendet die aktuellen Sync-Einstellungen sofort erneut an die Booking API.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                </p>
                <?php submit_button(); ?>
            </form>

            <script>
                (function () {
                    const optionKey = '<?php echo esc_js(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>';

                    const setupRepeater = (config) => {
                        const table = document.querySelector(config.tableSelector);
                        if (!table) {
                            return;
                        }

                        const tbody = table.querySelector('tbody');
                        if (!tbody) {
                            return;
                        }

                        const reindexRows = () => {
                            const rows = tbody.querySelectorAll(config.rowSelector);
                            rows.forEach((row, index) => {
                                const base = optionKey + '[' + config.optionKey + '][' + index + ']';
                                const fields = row.querySelectorAll('[data-rs-field], input, select, textarea');
                                fields.forEach((field) => {
                                    const key = field.getAttribute('data-rs-field') || '';
                                    if (!key) {
                                        return;
                                    }
                                    field.name = base + '[' + key + ']';
                                });
                            });
                        };

                        const ensureOneRow = () => {
                            if (tbody.querySelectorAll(config.rowSelector).length > 0) {
                                return;
                            }
                            tbody.appendChild(config.rowTemplate());
                            reindexRows();
                        };

                        document.querySelectorAll(config.addSelector).forEach((button) => {
                            button.addEventListener('click', () => {
                                const position = button.getAttribute(config.positionAttribute);
                                const row = config.rowTemplate();
                                if (position === 'top' && tbody.firstChild) {
                                    tbody.insertBefore(row, tbody.firstChild);
                                } else {
                                    tbody.appendChild(row);
                                }
                                reindexRows();
                            });
                        });

                        tbody.addEventListener('click', (event) => {
                            const target = event.target;
                            if (!(target instanceof HTMLElement) || !target.matches(config.removeSelector)) {
                                return;
                            }
                            const row = target.closest(config.rowSelector);
                            if (row) {
                                row.remove();
                            }
                            ensureOneRow();
                            reindexRows();
                        });

                        reindexRows();
                    };

                    setupRepeater({
                        tableSelector: '[data-rs-calendar-sources-table]',
                        rowSelector: '[data-rs-calendar-source-row]',
                        addSelector: '[data-rs-add-calendar-row]',
                        removeSelector: '[data-rs-remove-calendar-row]',
                        positionAttribute: 'data-rs-add-calendar-row',
                        optionKey: 'api_calendar_sources_rows',
                        rowTemplate: () => {
                            const row = document.createElement('tr');
                            row.setAttribute('data-rs-calendar-source-row', '');
                            row.innerHTML = [
                                '<td><input class="regular-text code" type="text" data-rs-field="calendar_id" placeholder="calendar-id@group.calendar.google.com"></td>',
                                '<td><input class="regular-text" type="text" data-rs-field="label" placeholder="<?php echo esc_attr(__('Kalendername', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?>"></td>',
                                '<td><select data-rs-field="privacy_mode"><option value="private">private</option><option value="official">official</option></select></td>',
                                '<td><select data-rs-field="calendar_type"><option value="general">general</option><option value="holiday">holiday</option></select></td>',
                                '<td><button type="button" class="button-link-delete" data-rs-remove-calendar-row><?php echo esc_js(__('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?></button></td>'
                            ].join('');
                            return row;
                        }
                    });

                    setupRepeater({
                        tableSelector: '[data-rs-contact-channels-table]',
                        rowSelector: '[data-rs-contact-channel-row]',
                        addSelector: '[data-rs-add-contact-row]',
                        removeSelector: '[data-rs-remove-contact-row]',
                        positionAttribute: 'data-rs-add-contact-row',
                        optionKey: 'contact_channels_rows',
                        rowTemplate: () => {
                            const row = document.createElement('tr');
                            row.setAttribute('data-rs-contact-channel-row', '');
                            row.innerHTML = [
                                '<td><input class="regular-text code" type="text" data-rs-field="key" placeholder="whatsapp"></td>',
                                '<td><input class="regular-text" type="text" data-rs-field="label" placeholder="<?php echo esc_attr(__('WhatsApp', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?>"></td>',
                                '<td><select data-rs-field="input_kind"><option value="tel">tel</option><option value="email">email</option><option value="url">url</option><option value="text">text</option></select></td>',
                                '<td><input class="regular-text" type="text" data-rs-field="placeholder" placeholder="+49..."></td>',
                                '<td><input class="regular-text" type="text" data-rs-field="value_label" placeholder="<?php echo esc_attr(__('Telefonnummer', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?>"></td>',
                                '<td><input class="regular-text code" type="text" data-rs-field="ics_template" placeholder="Telefon: {value}"></td>',
                                '<td><button type="button" class="button-link-delete" data-rs-remove-contact-row><?php echo esc_js(__('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?></button></td>'
                            ].join('');
                            return row;
                        }
                    });

                    const createAvailabilitySlot = () => {
                        const row = document.createElement('div');
                        row.className = 'rs-availability-slot';
                        row.setAttribute('data-rs-availability-slot', '');
                        row.innerHTML = [
                            '<input type="time" data-rs-field="start">',
                            '<span><?php echo esc_js(__('bis', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?></span>',
                            '<input type="time" data-rs-field="end">',
                            '<button type="button" class="button-link-delete" data-rs-remove-availability-slot><?php echo esc_js(__('Löschen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)); ?></button>'
                        ].join('');
                        return row;
                    };

                    const reindexAvailabilityDay = (day) => {
                        if (!(day instanceof HTMLElement)) {
                            return;
                        }
                        const weekday = day.getAttribute('data-weekday') || '';
                        const slots = day.querySelectorAll('[data-rs-availability-slot]');
                        slots.forEach((slot, index) => {
                            slot.querySelectorAll('[data-rs-field]').forEach((field) => {
                                const key = field.getAttribute('data-rs-field') || '';
                                if (!key) {
                                    return;
                                }
                                field.name = optionKey + '[api_availability_rows][' + weekday + '][windows][' + index + '][' + key + ']';
                            });
                        });
                    };

                    document.querySelectorAll('[data-rs-availability-day]').forEach((day) => {
                        const enabled = day.querySelector('[data-rs-availability-enabled]');
                        const wrapper = day.querySelector('[data-rs-availability-slots-wrapper]');
                        const slots = day.querySelector('[data-rs-availability-slots]');
                        const addButton = day.querySelector('[data-rs-add-availability-slot]');

                        if (!(enabled instanceof HTMLInputElement) || !(wrapper instanceof HTMLElement) || !(slots instanceof HTMLElement) || !(addButton instanceof HTMLElement)) {
                            return;
                        }

                        const syncDayState = () => {
                            const isEnabled = enabled.checked;
                            day.classList.toggle('is-disabled', !isEnabled);
                            wrapper.hidden = !isEnabled;
                            if (isEnabled && slots.querySelectorAll('[data-rs-availability-slot]').length === 0) {
                                slots.appendChild(createAvailabilitySlot());
                            }
                            reindexAvailabilityDay(day);
                        };

                        enabled.addEventListener('change', syncDayState);
                        addButton.addEventListener('click', () => {
                            slots.appendChild(createAvailabilitySlot());
                            reindexAvailabilityDay(day);
                        });
                        slots.addEventListener('click', (event) => {
                            const target = event.target;
                            if (!(target instanceof HTMLElement) || !target.matches('[data-rs-remove-availability-slot]')) {
                                return;
                            }
                            const slot = target.closest('[data-rs-availability-slot]');
                            if (slot) {
                                slot.remove();
                            }
                            reindexAvailabilityDay(day);
                        });

                        reindexAvailabilityDay(day);
                        syncDayState();
                    });

                    const insertAtCursor = (field, value) => {
                        const start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
                        const end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;
                        field.value = field.value.slice(0, start) + value + field.value.slice(end);
                        const nextPos = start + value.length;
                        field.focus();
                        if (typeof field.setSelectionRange === 'function') {
                            field.setSelectionRange(nextPos, nextPos);
                        }
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    };

                    let lastFocusedField = null;

                    document.addEventListener('focusin', (event) => {
                        const target = event.target;
                        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                            lastFocusedField = target;
                        }
                    });

                    const ensureEditor = (textarea) => {
                        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.rsEditorInitialized === '1') {
                            return;
                        }
                        if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
                            return;
                        }

                        window.wp.editor.initialize(textarea.id, {
                            tinymce: {
                                wpautop: true,
                                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo,removeformat',
                                toolbar2: '',
                            },
                            quicktags: true,
                            mediaButtons: false,
                        });

                        textarea.dataset.rsEditorInitialized = '1';

                        const bindEditor = () => {
                            if (!window.tinymce || typeof window.tinymce.get !== 'function') {
                                return;
                            }
                            const editor = window.tinymce.get(textarea.id);
                            if (!editor) {
                                window.requestAnimationFrame(bindEditor);
                                return;
                            }
                            editor.on('focus', () => {
                                const modal = textarea.closest('[data-rs-mail-modal]');
                                if (modal instanceof HTMLElement) {
                                    modal.dataset.rsActiveEditorId = textarea.id;
                                }
                            });
                        };

                        window.requestAnimationFrame(bindEditor);
                    };

                    const closeModal = (modal) => {
                        if (!(modal instanceof HTMLElement)) {
                            return;
                        }
                        modal.hidden = true;
                        delete modal.dataset.rsActiveEditorId;
                        if (!document.querySelector('[data-rs-mail-modal]:not([hidden])')) {
                            document.body.classList.remove('rs-mail-modal-open');
                        }
                    };

                    const openModal = (modalId) => {
                        const modal = document.querySelector('[data-rs-mail-modal="' + modalId + '"]');
                        if (!(modal instanceof HTMLElement)) {
                            return;
                        }
                        modal.hidden = false;
                        document.body.classList.add('rs-mail-modal-open');
                        modal.querySelectorAll('[data-rs-mail-html-editor]').forEach((field) => ensureEditor(field));
                        const firstFocusable = modal.querySelector('input, textarea, button, select');
                        if (firstFocusable instanceof HTMLElement) {
                            firstFocusable.focus();
                        }
                    };

                    document.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }

                        const openTrigger = target.closest('[data-rs-open-mail-modal]');
                        if (openTrigger instanceof HTMLElement) {
                            event.preventDefault();
                            openModal(openTrigger.getAttribute('data-rs-open-mail-modal') || '');
                            return;
                        }

                        const closeTrigger = target.closest('[data-rs-close-mail-modal]');
                        if (closeTrigger instanceof HTMLElement) {
                            event.preventDefault();
                            closeModal(closeTrigger.closest('[data-rs-mail-modal]'));
                            return;
                        }

                        if (target.matches('[data-rs-mail-modal]')) {
                            closeModal(target);
                        }
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key !== 'Escape') {
                            return;
                        }
                        const modal = document.querySelector('[data-rs-mail-modal]:not([hidden])');
                        if (modal instanceof HTMLElement) {
                            closeModal(modal);
                        }
                    });

                    document.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement) || !target.matches('[data-rs-insert-placeholder]')) {
                            return;
                        }

                        event.preventDefault();
                        const value = target.getAttribute('data-rs-insert-placeholder') || '';
                        const modal = target.closest('[data-rs-mail-modal]');
                        const activeEditorId = modal instanceof HTMLElement ? (modal.dataset.rsActiveEditorId || '') : '';

                        if (activeEditorId && window.tinymce && typeof window.tinymce.get === 'function') {
                            const editor = window.tinymce.get(activeEditorId);
                            if (editor && !editor.isHidden()) {
                                editor.focus();
                                editor.insertContent(value);
                                return;
                            }
                        }

                        if (window.tinymce && window.tinymce.activeEditor && !window.tinymce.activeEditor.isHidden()) {
                            const editor = window.tinymce.activeEditor;
                            editor.focus();
                            editor.insertContent(value);
                            return;
                        }

                        if (lastFocusedField instanceof HTMLInputElement || lastFocusedField instanceof HTMLTextAreaElement) {
                            insertAtCursor(lastFocusedField, value);
                            return;
                        }

                        const quicktagsField = document.querySelector('.wp-editor-area:focus');
                        if (quicktagsField instanceof HTMLTextAreaElement) {
                            insertAtCursor(quicktagsField, value);
                        }
                    });
                })();
            </script>

            <p><strong><?php esc_html_e('Shortcode:', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong> [restatify_booking_popup]</p>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $modal
     * @param array<string,mixed> $options
     * @param array<int,string> $mail_placeholders
     */
    private function render_mail_template_modal(array $modal, array $options, array $mail_placeholders): void {
        ?>
        <div class="rs-mail-modal" data-rs-mail-modal="<?php echo esc_attr((string) ($modal['modal_id'] ?? '')); ?>" hidden>
            <div class="rs-mail-modal__panel">
                <button type="button" class="button rs-mail-modal__close" data-rs-close-mail-modal aria-label="<?php esc_attr_e('Schließen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">&times;</button>
                <div class="rs-mail-modal__intro">
                    <h2><?php echo esc_html((string) ($modal['title'] ?? '')); ?></h2>
                    <p><?php echo esc_html((string) ($modal['description'] ?? '')); ?></p>
                </div>

                <?php foreach ((array) ($modal['shared_fields'] ?? []) as $field) : ?>
                    <?php $this->render_mail_template_shared_field($field, $options); ?>
                <?php endforeach; ?>

                <?php foreach ((array) ($modal['sections'] ?? []) as $section) : ?>
                    <?php $this->render_mail_template_section($section, $options); ?>
                <?php endforeach; ?>

                <div class="rs-mail-modal__footer">
                    <p class="description"><?php esc_html_e('Per Klick wird der Platzhalter in das zuletzt fokussierte Feld oder in den aktiven Editor eingefügt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                    <div class="rs-mail-placeholder-list">
                        <?php foreach ($mail_placeholders as $placeholder) : ?>
                            <button type="button" class="button button-secondary" data-rs-insert-placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_html($placeholder); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
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
     */
    private function render_mail_template_section(array $section, array $options): void {
        $subject_key = (string) ($section['subject_key'] ?? '');
        $text_key = (string) ($section['text_key'] ?? '');
        $html_key = (string) ($section['html_key'] ?? '');
        $html_editor_id = (string) ($section['html_editor_id'] ?? '');
        ?>
        <section class="rs-mail-modal__section">
            <h3><?php echo esc_html((string) ($section['title'] ?? '')); ?></h3>

            <div class="rs-mail-modal__checks">
                <?php foreach ((array) ($section['toggles'] ?? []) as $toggle) : ?>
                    <?php $this->render_mail_template_toggle($toggle, $options); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($subject_key !== '') : ?>
                <div class="rs-mail-modal__field">
                    <label for="<?php echo esc_attr($subject_key); ?>"><?php esc_html_e('Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></label>
                    <input class="regular-text" id="<?php echo esc_attr($subject_key); ?>" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($subject_key); ?>]" value="<?php echo esc_attr((string) ($options[$subject_key] ?? '')); ?>">
                </div>
            <?php endif; ?>

            <?php if ($text_key !== '') : ?>
                <div class="rs-mail-modal__field">
                    <label for="<?php echo esc_attr($text_key); ?>"><?php esc_html_e('Text-Version', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></label>
                    <textarea class="large-text code" id="<?php echo esc_attr($text_key); ?>" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($text_key); ?>]"><?php echo esc_textarea((string) ($options[$text_key] ?? '')); ?></textarea>
                    <?php if (!empty($section['text_help'])) : ?>
                        <p class="description"><?php echo esc_html((string) $section['text_help']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($html_key !== '' && $html_editor_id !== '') : ?>
                <div class="rs-mail-modal__field rs-mail-editor-cell">
                    <span><?php esc_html_e('HTML-Version', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                    <textarea class="large-text code rs-mail-html-textarea" id="<?php echo esc_attr($html_editor_id); ?>" rows="12" data-rs-mail-html-editor name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($html_key); ?>]"><?php echo esc_textarea((string) ($options[$html_key] ?? '')); ?></textarea>
                    <?php if (!empty($section['html_help'])) : ?>
                        <p class="description"><?php echo esc_html((string) $section['html_help']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
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
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}


