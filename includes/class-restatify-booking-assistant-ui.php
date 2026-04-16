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
            'permalink' => home_url('/') . Restatify_Booking_Assistant_Constants::BOOKING_TRIGGER_HASH,
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

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        ?>
        <div class="wrap">
            <style>
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
            </style>
            <h1><?php esc_html_e('Restatify Booking Assistant', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Konfiguriere zuerst die grundlegende API-Verbindung. Erweiterte Sync- und Autoresponder-Optionen sind weiter unten gruppiert.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('restatify_booking_assistant'); ?>

                <h2><?php esc_html_e('Grundkonfiguration (erforderlich)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Booking-API Basis-URL', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text code" type="url" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_base_url]" value="<?php echo esc_attr((string) $options['api_base_url']); ?>" placeholder="https://booking-api.example.com">
                            <p class="description"><?php esc_html_e('Oeffentlicher API-Endpunkt. Beispiel: https://booking-api.deine-domain.tld', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API key', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text" type="password" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr((string) $options['api_key']); ?>">
                            <p class="description"><?php esc_html_e('Erforderlich für alle API-Aufrufe. Bitte vertraulich behandeln.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Standard-Zeitzone', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[default_timezone]" value="<?php echo esc_attr((string) $options['default_timezone']); ?>">
                            <p class="description"><?php esc_html_e('Zeitzone für Termin-Suche, Buchungszeitstempel und Platzhalter im Autoresponder. Beispiel: Europe/Berlin.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
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
                            <p class="description"><?php esc_html_e('Wie viele Tage im Voraus das Popup nach freien Terminen sucht. Hoehere Werte zeigen mehr Optionen, können aber die Antwortzeit erhöhen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Oeffentliches Rate-Limit aktivieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_enabled]" value="1" <?php checked(!empty($options['public_rate_limit_enabled'])); ?>>
                                <?php esc_html_e('Begrenzt anonyme Booking-Anfragen pro Besucher-Fingerprint (IP + User-Agent).', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rate-Limit Fenster (Sekunden)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="small-text" type="number" min="10" max="3600" step="10" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_window_seconds]" value="<?php echo esc_attr((string) $options['public_rate_limit_window_seconds']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maximale Slot-Suchen pro Fenster', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="small-text" type="number" min="1" max="120" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_max_find_slots]" value="<?php echo esc_attr((string) $options['public_rate_limit_max_find_slots']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maximale Reservierungen pro Fenster', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="small-text" type="number" min="1" max="60" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[public_rate_limit_max_reserve_slot]" value="<?php echo esc_attr((string) $options['public_rate_limit_max_reserve_slot']); ?>">
                            <p class="description"><?php esc_html_e('Bei Ueberschreitung wird HTTP 429 zurueckgegeben und der Besucher kann spaeter erneut versuchen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Fallback-Kontakt-E-Mail (keine freien Termine)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text" type="email" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[no_slots_contact_email]" value="<?php echo esc_attr((string) ($options['no_slots_contact_email'] ?? '')); ?>" placeholder="contact@example.com">
                            <p class="description"><?php esc_html_e('Wird angezeigt, wenn im konfigurierten Suchzeitraum keine freien Termine verfügbar sind.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Zu synchronisierende Kalender', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <p><button type="button" class="button" data-rs-add-calendar-row="top"><?php esc_html_e('Kalender hinzufügen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button></p>

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
                        <th scope="row"><?php esc_html_e('Google Zielkalender-ID für Schreibzugriffe', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_google_write_calendar_id]" value="<?php echo esc_attr((string) $options['api_google_write_calendar_id']); ?>" placeholder="primary" required>
                            <p class="description"><?php esc_html_e('Erforderlich, wenn "Google-Events erstellen" aktiv ist. Es wird kein Fallback-Kalender verwendet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Wöchentliche Verfuegbarkeitsregeln', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea class="large-text code" rows="7" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_raw]" required><?php echo esc_textarea((string) ($options['api_availability_raw'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Erforderlich. Eine Zeile pro Wochentag. Format: day|HH:MM-HH:MM,HH:MM-HH:MM', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>

                <details style="margin-top:12px;">
                    <summary><strong><?php esc_html_e('Experteneinstellungen (optional)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong></summary>
                    <p class="description"><?php esc_html_e('Optionales Feintuning für Sync-Verhalten und Autoresponder-Anpassung.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('API-Sync aktivieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_sync_enabled]" value="1" <?php checked(!empty($options['api_sync_enabled'])); ?>>
                                    <?php esc_html_e('Erlaubt der API die Ausfuehrung der Kalender Frei/Belegt-Synchronisierung.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
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
                        <tr>
                            <th scope="row"><?php esc_html_e('Kontaktkanaele', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_raw]"><?php echo esc_textarea((string) ($options['contact_channels_raw'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Ein Kanal pro Zeile: key|Bezeichnung|input_kind(tel/email/url/text)|placeholder|Wertbezeichnung|ICS-Vorlage', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Anzahl prominent angezeigter Kanaele', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="small-text" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_prominent_count]" value="<?php echo esc_attr((string) $options['contact_prominent_count']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Beschriftung: Mehr Kanaele', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_more_label]" value="<?php echo esc_attr((string) $options['contact_more_label']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Beschriftung: Weniger Kanaele', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_less_label]" value="<?php echo esc_attr((string) $options['contact_less_label']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Mail-Platzhalter', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <p class="description"><?php esc_html_e('Per Klick wird der Platzhalter in das aktuell fokussierte Feld oder in den aktiven Editor eingefügt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                                <div class="rs-mail-placeholder-list">
                                    <?php foreach ($mail_placeholders as $placeholder) : ?>
                                        <button type="button" class="button button-secondary" data-rs-insert-placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_html($placeholder); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php esc_html_e('{cancellation_url} wird nur gesetzt, wenn die API einen Storno-Token zurückliefert.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestaetigungs-Mail an Interessent aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_enabled]" value="1" <?php checked(!empty($options['autoresponder_enabled'])); ?>>
                                    <?php esc_html_e('Sendet nach erfolgreicher Reservierung eine Bestätigung an die angegebene E-Mail-Adresse.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestaetigungs-Mail als HTML senden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_html_enabled]" value="1" <?php checked(!empty($options['autoresponder_html_enabled'])); ?>>
                                    <?php esc_html_e('Versendet die Interessenten-Mail als Multipart-Nachricht mit Text- und HTML-Teil.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestaetigungs-Mail Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_subject]" value="<?php echo esc_attr((string) $options['autoresponder_subject']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestaetigungs-Mail Text', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_body]"><?php echo esc_textarea((string) $options['autoresponder_body']); ?></textarea>
                                <p class="description"><?php esc_html_e('Wird immer als Text-Alternative verwendet und sollte auch bei aktiviertem HTML gepflegt sein.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestaetigungs-Mail HTML', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td class="rs-mail-editor-cell">
                                <?php
                                wp_editor(
                                    (string) ($options['autoresponder_html_body'] ?? ''),
                                    'restatify_booking_autoresponder_html_body',
                                    [
                                        'textarea_name' => Restatify_Booking_Assistant_Constants::OPTION_KEY . '[autoresponder_html_body]',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                    ]
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Benachrichtigung an Inhaber aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_notification_enabled]" value="1" <?php checked(!empty($options['owner_notification_enabled'])); ?>>
                                    <?php esc_html_e('Sendet nach erfolgreicher Reservierung zusätzlich eine interne Benachrichtigung.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Mail als HTML senden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_notification_html_enabled]" value="1" <?php checked(!empty($options['owner_notification_html_enabled'])); ?>>
                                    <?php esc_html_e('Versendet die interne Benachrichtigung ebenfalls als Multipart-Nachricht.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Empfaenger Inhaber-Mail', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="4" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_notification_recipients]"><?php echo esc_textarea((string) ($options['owner_notification_recipients'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Mehrere Empfänger per Komma oder jeweils in einer neuen Zeile angeben.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Mail Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_notification_subject]" value="<?php echo esc_attr((string) ($options['owner_notification_subject'] ?? '')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Mail Text', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_notification_body]"><?php echo esc_textarea((string) ($options['owner_notification_body'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Enthält die Text-Version für interne Benachrichtigungen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Mail HTML', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td class="rs-mail-editor-cell">
                                <?php
                                wp_editor(
                                    (string) ($options['owner_notification_html_body'] ?? ''),
                                    'restatify_booking_owner_notification_html_body',
                                    [
                                        'textarea_name' => Restatify_Booking_Assistant_Constants::OPTION_KEY . '[owner_notification_html_body]',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                    ]
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stornobestaetigung aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[cancellation_confirmation_enabled]" value="1" <?php checked(!empty($options['cancellation_confirmation_enabled'])); ?>>
                                    <?php esc_html_e('Sendet nach erfolgreicher Stornierung eine Bestätigung an den Interessenten.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stornobestaetigung als HTML senden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[cancellation_confirmation_html_enabled]" value="1" <?php checked(!empty($options['cancellation_confirmation_html_enabled'])); ?>>
                                    <?php esc_html_e('Versendet die Stornobestätigung als Multipart-Nachricht mit Text- und HTML-Teil.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stornobestaetigung Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[cancellation_confirmation_subject]" value="<?php echo esc_attr((string) ($options['cancellation_confirmation_subject'] ?? '')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stornobestaetigung Text', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[cancellation_confirmation_body]"><?php echo esc_textarea((string) ($options['cancellation_confirmation_body'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Verwendbare Zusatz-Platzhalter: {cancellation_reason}, {cancellation_message}, {site_name}.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stornobestaetigung HTML', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td class="rs-mail-editor-cell">
                                <?php
                                wp_editor(
                                    (string) ($options['cancellation_confirmation_html_body'] ?? ''),
                                    'restatify_booking_cancellation_confirmation_html_body',
                                    [
                                        'textarea_name' => Restatify_Booking_Assistant_Constants::OPTION_KEY . '[cancellation_confirmation_html_body]',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                    ]
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Stornobenachrichtigung aktiv', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_cancellation_enabled]" value="1" <?php checked(!empty($options['owner_cancellation_enabled'])); ?>>
                                    <?php esc_html_e('Sendet bei erfolgreicher Stornierung eine interne Benachrichtigung an die Inhaber-Empfänger.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Stornobenachrichtigung als HTML senden', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_cancellation_html_enabled]" value="1" <?php checked(!empty($options['owner_cancellation_html_enabled'])); ?>>
                                    <?php esc_html_e('Versendet die interne Stornobenachrichtigung als Multipart-Nachricht.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Stornobenachrichtigung Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_cancellation_subject]" value="<?php echo esc_attr((string) ($options['owner_cancellation_subject'] ?? '')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Stornobenachrichtigung Text', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[owner_cancellation_body]"><?php echo esc_textarea((string) ($options['owner_cancellation_body'] ?? '')); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Inhaber-Stornobenachrichtigung HTML', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td class="rs-mail-editor-cell">
                                <?php
                                wp_editor(
                                    (string) ($options['owner_cancellation_html_body'] ?? ''),
                                    'restatify_booking_owner_cancellation_html_body',
                                    [
                                        'textarea_name' => Restatify_Booking_Assistant_Constants::OPTION_KEY . '[owner_cancellation_html_body]',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                    ]
                                );
                                ?>
                            </td>
                        </tr>
                    </table>
                </details>
                <?php submit_button(); ?>
            </form>

            <script>
                (function () {
                    const table = document.querySelector('[data-rs-calendar-sources-table]');
                    if (!table) {
                        return;
                    }

                    const tbody = table.querySelector('tbody');
                    const addButtons = document.querySelectorAll('[data-rs-add-calendar-row]');

                    const rowTemplate = () => {
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
                    };

                    const reindexRows = () => {
                        const rows = tbody.querySelectorAll('[data-rs-calendar-source-row]');
                        rows.forEach((row, index) => {
                            const base = '<?php echo esc_js(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_rows][' + index + ']';
                            const fields = row.querySelectorAll('[data-rs-field], input[name*="[api_calendar_sources_rows]"], select[name*="[api_calendar_sources_rows]"]');
                            fields.forEach((field) => {
                                const key = field.getAttribute('data-rs-field')
                                    || (field.name.match(/\[(calendar_id|label|privacy_mode|calendar_type)\]$/) || [null, ''])[1];
                                if (!key) {
                                    return;
                                }
                                field.name = base + '[' + key + ']';
                            });
                        });
                    };

                    const ensureOneRow = () => {
                        if (tbody.querySelectorAll('[data-rs-calendar-source-row]').length > 0) {
                            return;
                        }
                        tbody.appendChild(rowTemplate());
                        reindexRows();
                    };

                    addButtons.forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const position = btn.getAttribute('data-rs-add-calendar-row');
                            const newRow = rowTemplate();
                            if (position === 'top' && tbody.firstChild) {
                                tbody.insertBefore(newRow, tbody.firstChild);
                            } else {
                                tbody.appendChild(newRow);
                            }
                            reindexRows();
                        });
                    });

                    tbody.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement) || !target.matches('[data-rs-remove-calendar-row]')) {
                            return;
                        }
                        const row = target.closest('[data-rs-calendar-source-row]');
                        if (row) {
                            row.remove();
                        }
                        ensureOneRow();
                        reindexRows();
                    });

                    reindexRows();

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

                    document.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement) || !target.matches('[data-rs-insert-placeholder]')) {
                            return;
                        }

                        event.preventDefault();
                        const value = target.getAttribute('data-rs-insert-placeholder') || '';
                        const activeElement = document.activeElement;

                        if (window.tinymce && window.tinymce.activeEditor && !window.tinymce.activeEditor.isHidden()) {
                            const editor = window.tinymce.activeEditor;
                            editor.focus();
                            editor.insertContent(value);
                            return;
                        }

                        if (activeElement instanceof HTMLInputElement || activeElement instanceof HTMLTextAreaElement) {
                            insertAtCursor(activeElement, value);
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

                        <button type="submit" class="restatify-booking__submit" hidden><?php esc_html_e('Jetzt reservieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}


