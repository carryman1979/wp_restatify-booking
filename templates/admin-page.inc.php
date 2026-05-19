<?php
$vars = get_defined_vars();
$shared_mail_editor_url = isset($vars['shared_mail_editor_url']) ? (string) $vars['shared_mail_editor_url'] : '';
$options = isset($vars['options']) && is_array($vars['options']) ? $vars['options'] : [];
$default_options = isset($vars['default_options']) && is_array($vars['default_options']) ? $vars['default_options'] : [];
$calendar_sources = isset($vars['calendar_sources']) && is_array($vars['calendar_sources']) ? $vars['calendar_sources'] : [];
$weekday_labels = isset($vars['weekday_labels']) && is_array($vars['weekday_labels']) ? $vars['weekday_labels'] : [];
$availability_by_weekday = isset($vars['availability_by_weekday']) && is_array($vars['availability_by_weekday']) ? $vars['availability_by_weekday'] : [];
$contact_channels = isset($vars['contact_channels']) && is_array($vars['contact_channels']) ? $vars['contact_channels'] : [];
$mail_template_modals = isset($vars['mail_template_modals']) && is_array($vars['mail_template_modals']) ? $vars['mail_template_modals'] : [];
$mail_placeholders = isset($vars['mail_placeholders']) && is_array($vars['mail_placeholders']) ? $vars['mail_placeholders'] : [];
$admin_settings_config_json = isset($vars['admin_settings_config_json']) ? (string) $vars['admin_settings_config_json'] : '{}';
$admin_settings_script_url = isset($vars['admin_settings_script_url']) ? (string) $vars['admin_settings_script_url'] : '';
?>
<div class="wrap">
            <script src="<?php echo $shared_mail_editor_url; ?>"></script>
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

                .wrap .rs-mail-template-tabs {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-bottom: 12px;
                }

                .wrap .rs-mail-template-tabs .button.is-active {
                    background: #2271b1;
                    border-color: #2271b1;
                    color: #fff;
                }

                .wrap .rs-mail-template-panel[hidden] {
                    display: none !important;
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
                <?php settings_fields(Restatify_Booking_Assistant_Constants::SETTINGS_GROUP); ?>

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
                            <tr>
                                <th scope="row"><?php esc_html_e('URL zur Datenschutzerklärung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                                <td>
                                    <input class="regular-text code" type="url" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[privacy_policy_url]" value="<?php echo esc_attr((string) ($options['privacy_policy_url'] ?? '')); ?>" placeholder="https://example.com/datenschutz">
                                    <p class="description"><?php esc_html_e('Wird im Booking-Overlay als Legal-Hinweis verlinkt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
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
                    <?php $this->render_mail_template_modal($modal, $options, $default_options, $mail_placeholders); ?>
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

            <script>window.restatifyBookingAdminConfig = <?php echo $admin_settings_config_json; ?>;</script>
            <script src="<?php echo $admin_settings_script_url; ?>"></script>

            <p><strong><?php esc_html_e('Shortcode:', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong> [restatify_booking_popup]</p>
        </div>
