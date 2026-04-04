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
            'strings' => [
                'loading' => __('Searching free slots...', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'empty' => __('No free slots found in this period.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'reserve' => __('Reserve slot', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'selectDay' => __('Tag im Kalender auswaehlen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'pickTime' => __('Uhrzeit auswaehlen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'success' => __('Reservation received. Check your email for details.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'error' => __('Reservation failed. Please try another slot.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
            'label' => __('Find appointment', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'title' => __('Book a conversation', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
            __('Find appointment', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            __('Book a conversation', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
            'title' => __('Booking Popup (Restatify)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'permalink' => home_url('/') . Restatify_Booking_Assistant_Constants::BOOKING_TRIGGER_HASH,
            'info' => __('Opens the Restatify booking overlay on click.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Restatify Booking Assistant', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Configure core API connection first. Advanced sync and autoresponder options are grouped below.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('restatify_booking_assistant'); ?>

                <h2><?php esc_html_e('Core setup (required)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Booking API Base URL', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text code" type="url" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_base_url]" value="<?php echo esc_attr((string) $options['api_base_url']); ?>" placeholder="https://booking-api.example.com">
                            <p class="description"><?php esc_html_e('Public API endpoint. Example: https://booking-api.your-domain.tld', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API key', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text" type="password" required name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr((string) $options['api_key']); ?>">
                            <p class="description"><?php esc_html_e('Required for all API calls. Keep this secret.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default timezone', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[default_timezone]" value="<?php echo esc_attr((string) $options['default_timezone']); ?>">
                            <p class="description"><?php esc_html_e('Timezone used for slot search, booking timestamps and autoresponder placeholders. Example: Europe/Berlin.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default duration (minutes)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="small-text" type="number" min="15" max="180" step="15" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[default_duration_minutes]" value="<?php echo esc_attr((string) $options['default_duration_minutes']); ?>">
                            <p class="description"><?php esc_html_e('Defines booking length. Only slots long enough for this duration are offered and reservations are created with this value.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Search window (days)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="small-text" type="number" min="1" max="60" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[slot_window_days]" value="<?php echo esc_attr((string) $options['slot_window_days']); ?>">
                            <p class="description"><?php esc_html_e('How many days ahead the popup searches for free slots. Higher values show more options but can increase response time.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Calendars to sync', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea class="large-text code" rows="6" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_calendar_sources_raw]" required><?php echo esc_textarea((string) ($options['api_calendar_sources_raw'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Required. One calendar per line: calendar_id|Label|private or official|general or holiday', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google write target calendar ID', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <input class="regular-text code" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_google_write_calendar_id]" value="<?php echo esc_attr((string) $options['api_google_write_calendar_id']); ?>" placeholder="primary" required>
                            <p class="description"><?php esc_html_e('Required when "Create Google events" is enabled. No fallback calendar is used.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Weekly availability rules', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea class="large-text code" rows="7" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_availability_raw]" required><?php echo esc_textarea((string) ($options['api_availability_raw'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Required. One line per weekday. Format: day|HH:MM-HH:MM,HH:MM-HH:MM', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>

                <details style="margin-top:12px;">
                    <summary><strong><?php esc_html_e('Expert settings (optional)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong></summary>
                    <p class="description"><?php esc_html_e('Optional fine-tuning for sync behavior and autoresponder customization.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable API sync', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_sync_enabled]" value="1" <?php checked(!empty($options['api_sync_enabled'])); ?>>
                                    <?php esc_html_e('Allow API to run calendar free/busy synchronization.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Sync interval (minutes)', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="small-text" type="number" min="5" max="720" step="5" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_sync_interval_minutes]" value="<?php echo esc_attr((string) $options['api_sync_interval_minutes']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Create Google events', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[api_google_write_events_enabled]" value="1" <?php checked(!empty($options['api_google_write_events_enabled'])); ?>>
                                    <?php esc_html_e('After a successful reservation, also create an event in Google Calendar.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Contact channels', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_channels_raw]"><?php echo esc_textarea((string) ($options['contact_channels_raw'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('One channel per line: key|Label|input_kind(tel/email/url/text)|placeholder|value label|ICS template', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Prominent channels count', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="small-text" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_prominent_count]" value="<?php echo esc_attr((string) $options['contact_prominent_count']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('More channels label', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_more_label]" value="<?php echo esc_attr((string) $options['contact_more_label']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Less channels label', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[contact_less_label]" value="<?php echo esc_attr((string) $options['contact_less_label']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Autoresponder subject', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td><input class="regular-text" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_subject]" value="<?php echo esc_attr((string) $options['autoresponder_subject']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Autoresponder body', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[autoresponder_body]"><?php echo esc_textarea((string) $options['autoresponder_body']); ?></textarea>
                                <p class="description"><?php esc_html_e('Placeholders: {name}, {email}, {start}, {end}, {timezone}, {note}, {reference}, {contact_method}, {contact_value}, {contact_detail}', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>
                </details>
                <?php submit_button(); ?>
            </form>
            <p><strong><?php esc_html_e('Shortcode:', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></strong> [restatify_booking_popup]</p>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $options
     */
    private function render_popup_markup(string $label, string $title, bool $with_trigger, bool $is_global): string {
        $options = $this->options_service->get_options();
        $contact_channels = $this->options_service->get_contact_channels($options);
        $prominent_count = max(1, min(6, absint($options['contact_prominent_count'] ?? 3)));
        $default_channel = $contact_channels[0] ?? [
            'key' => 'phone',
            'label' => 'Telefon',
            'input_kind' => 'tel',
            'placeholder' => '+49...',
            'value_label' => 'Telefonnummer',
            'ics_template' => 'Telefon: {value}',
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
                    <button type="button" class="restatify-booking__close" data-booking-close aria-label="<?php esc_attr_e('Close', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">&times;</button>
                    <h3 class="restatify-booking__title"><?php echo esc_html($title); ?></h3>

                    <div class="restatify-booking__status" data-booking-status></div>
                    <div class="restatify-booking__slots" data-booking-slots></div>

                    <form class="restatify-booking__form" data-booking-form hidden>
                        <input type="hidden" name="slot_start" data-slot-start>
                        <div class="restatify-booking__wizard" data-booking-wizard>
                            <div class="restatify-booking__wizard-track" data-booking-steps>
                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <div class="restatify-booking__contact-block">
                                        <span class="restatify-booking__contact-heading"><?php esc_html_e('Preferred contact channel', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
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
                                                    data-value-label="<?php echo esc_attr((string) ($channel['value_label'] ?? 'Kontaktdaten')); ?>"
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
                                        <span><?php esc_html_e('Email', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <input type="email" name="email" required maxlength="190">
                                    </label>
                                </section>

                                <section class="restatify-booking__wizard-step" data-booking-step>
                                    <label>
                                        <span><?php esc_html_e('Notes', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                        <textarea name="note" rows="3" maxlength="1000"></textarea>
                                    </label>
                                </section>
                            </div>
                        </div>

                        <div class="restatify-booking__wizard-nav">
                            <button type="button" class="restatify-booking__wizard-btn" data-step-prev hidden><?php esc_html_e('Back', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                            <span class="restatify-booking__wizard-indicator" data-step-indicator>1/3</span>
                            <button type="button" class="restatify-booking__wizard-btn" data-step-next><?php esc_html_e('Next', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                        </div>

                        <button type="submit" class="restatify-booking__submit" hidden><?php esc_html_e('Reserve now', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                        <button type="button" class="restatify-booking__close" data-booking-cancel><?php esc_html_e('Cancel booking', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
