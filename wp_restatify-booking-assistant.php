<?php
/**
 * Plugin Name: Restatify Booking Assistant
 * Description: Manual slot search + reservation popup for WordPress, backed by Restatify Booking API.
 * Version: 1.0.0
 * Author: Restatify
 * License: GPL-2.0-or-later
 * Text Domain: restatify-booking-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Restatify_Booking_Assistant {
    private const OPTION_KEY = 'restatify_booking_assistant_options';
    private const NONCE_ACTION = 'restatify_booking_assistant_nonce';
    private const ADMIN_NOTICE_TRANSIENT = 'restatify_booking_assistant_admin_notice';

    public function __construct() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('update_option_' . self::OPTION_KEY, [$this, 'handle_options_updated'], 10, 2);

        add_action('wp_ajax_restatify_booking_find_slots', [$this, 'ajax_find_slots']);
        add_action('wp_ajax_nopriv_restatify_booking_find_slots', [$this, 'ajax_find_slots']);
        add_action('wp_ajax_restatify_booking_reserve_slot', [$this, 'ajax_reserve_slot']);
        add_action('wp_ajax_nopriv_restatify_booking_reserve_slot', [$this, 'ajax_reserve_slot']);
    }

    public function register_shortcode(): void {
        add_shortcode('restatify_booking_popup', [$this, 'render_shortcode']);
    }

    public function register_settings(): void {
        register_setting(
            'restatify_booking_assistant',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => $this->get_default_options(),
            ]
        );
    }

    public function register_admin_page(): void {
        add_options_page(
            __('Restatify Booking Assistant', 'restatify-booking-assistant'),
            __('Booking Assistant', 'restatify-booking-assistant'),
            'manage_options',
            'restatify-booking-assistant',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_assets(): void {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'restatify_booking_popup')) {
            return;
        }

        $base_url = plugin_dir_url(__FILE__) . 'assets/';
        $base_path = plugin_dir_path(__FILE__) . 'assets/';
        $options = $this->get_options();

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
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'timezone' => (string) $options['default_timezone'],
            'durationMinutes' => (int) $options['default_duration_minutes'],
            'windowDays' => (int) $options['slot_window_days'],
            'strings' => [
                'loading' => __('Searching free slots...', 'restatify-booking-assistant'),
                'empty' => __('No free slots found in this period.', 'restatify-booking-assistant'),
                'reserve' => __('Reserve slot', 'restatify-booking-assistant'),
                'success' => __('Reservation received. Check your email for details.', 'restatify-booking-assistant'),
                'error' => __('Reservation failed. Please try another slot.', 'restatify-booking-assistant'),
            ],
        ]);
    }

    public function render_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'label' => __('Find appointment', 'restatify-booking-assistant'),
            'title' => __('Book a conversation', 'restatify-booking-assistant'),
        ], $atts, 'restatify_booking_popup');

        ob_start();
        ?>
        <div class="restatify-booking" data-restatify-booking>
            <button type="button" class="restatify-booking__trigger" data-booking-open><?php echo esc_html($atts['label']); ?></button>
            <div class="restatify-booking__overlay" data-booking-overlay hidden>
                <div class="restatify-booking__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($atts['title']); ?>">
                    <button type="button" class="restatify-booking__close" data-booking-close aria-label="<?php esc_attr_e('Close', 'restatify-booking-assistant'); ?>">&times;</button>
                    <h3 class="restatify-booking__title"><?php echo esc_html($atts['title']); ?></h3>

                    <div class="restatify-booking__status" data-booking-status></div>
                    <div class="restatify-booking__slots" data-booking-slots></div>

                    <form class="restatify-booking__form" data-booking-form hidden>
                        <input type="hidden" name="slot_start" data-slot-start>
                        <input type="hidden" name="slot_end" data-slot-end>
                        <label>
                            <span><?php esc_html_e('Name', 'restatify-booking-assistant'); ?></span>
                            <input type="text" name="name" required maxlength="190">
                        </label>
                        <label>
                            <span><?php esc_html_e('Email', 'restatify-booking-assistant'); ?></span>
                            <input type="email" name="email" required maxlength="190">
                        </label>
                        <label>
                            <span><?php esc_html_e('Notes', 'restatify-booking-assistant'); ?></span>
                            <textarea name="note" rows="3" maxlength="1000"></textarea>
                        </label>
                        <button type="submit" class="restatify-booking__submit"><?php esc_html_e('Reserve now', 'restatify-booking-assistant'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function ajax_find_slots(): void {
        $this->verify_nonce();

        $options = $this->get_options();
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

        $response = $this->request_api('/v1/slots/search', $payload);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $slots = is_array($response['slots'] ?? null) ? array_slice($response['slots'], 0, 24) : [];
        wp_send_json_success(['slots' => $slots]);
    }

    public function ajax_reserve_slot(): void {
        $this->verify_nonce();

        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $note = sanitize_textarea_field((string) ($_POST['note'] ?? ''));
        $slot_start = sanitize_text_field((string) ($_POST['slot_start'] ?? ''));

        if ($name === '' || $email === '' || $slot_start === '') {
            wp_send_json_error(['message' => __('Please complete all required fields.', 'restatify-booking-assistant')], 400);
        }

        $options = $this->get_options();
        $payload = [
            'start_iso' => $slot_start,
            'duration_minutes' => (int) $options['default_duration_minutes'],
            'timezone' => (string) $options['default_timezone'],
            'name' => $name,
            'email' => $email,
            'note' => $note,
        ];

        $response = $this->request_api('/v1/reservations', $payload);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $this->send_autoresponder($response, $name, $email, $note);

        wp_send_json_success([
            'reference' => sanitize_text_field((string) ($response['reference'] ?? '')),
            'start_iso' => sanitize_text_field((string) ($response['start_iso'] ?? $slot_start)),
            'end_iso' => sanitize_text_field((string) ($response['end_iso'] ?? '')),
        ]);
    }

    private function verify_nonce(): void {
        $nonce = sanitize_text_field((string) ($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Invalid request token.', 'restatify-booking-assistant')], 403);
        }
    }

    public function handle_options_updated($old_value, $new_value): void {
        if (!is_array($new_value)) {
            return;
        }

        $result = $this->push_sync_config_to_api($new_value);
        if (is_wp_error($result)) {
            set_transient(self::ADMIN_NOTICE_TRANSIENT, [
                'type' => 'error',
                'message' => sprintf(
                    __('Sync configuration could not be sent to API: %s', 'restatify-booking-assistant'),
                    $result->get_error_message()
                ),
            ], 60);

            return;
        }

        set_transient(self::ADMIN_NOTICE_TRANSIENT, [
            'type' => 'success',
            'message' => __('Sync configuration was updated in Booking API.', 'restatify-booking-assistant'),
        ], 60);
    }

    public function render_admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || sanitize_key((string) $_GET['page']) !== 'restatify-booking-assistant') {
            return;
        }

        $notice = get_transient(self::ADMIN_NOTICE_TRANSIENT);
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient(self::ADMIN_NOTICE_TRANSIENT);

        $type = (string) ($notice['type'] ?? 'info');
        if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            $type = 'info';
        }

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html((string) $notice['message']) . '</p></div>';
    }

    private function push_sync_config_to_api(array $options) {
        $calendar_sources = is_array($options['api_calendar_sources'] ?? null) ? $options['api_calendar_sources'] : [];
        $availability_rules = is_array($options['api_availability_rules'] ?? null) ? $options['api_availability_rules'] : [];

        $payload = [
            'sync_enabled' => !empty($options['api_sync_enabled']),
            'sync_interval_minutes' => max(5, min(720, absint($options['api_sync_interval_minutes'] ?? 15))),
            'calendar_sources' => $calendar_sources,
            'availability_rules' => $availability_rules,
        ];

        return $this->request_api('/v1/config/sync', $payload, 'PUT');
    }

    private function request_api(string $path, array $payload = [], string $method = 'POST') {
        $options = $this->get_options();
        $base_url = rtrim((string) $options['api_base_url'], '/');
        $url = $base_url . $path;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($options['api_key'])) {
            $headers['X-API-Key'] = (string) $options['api_key'];
        }

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

        if ($status < 200 || $status >= 300 || !is_array($body)) {
            return new WP_Error('restatify_booking_api_error', __('Booking API request failed.', 'restatify-booking-assistant'));
        }

        return $body;
    }

    private function send_autoresponder(array $reservation, string $name, string $email, string $note): void {
        $options = $this->get_options();
        $subject = (string) $options['autoresponder_subject'];
        $template = (string) $options['autoresponder_body'];
        $timezone = (string) $options['default_timezone'];

        $start_iso = (string) ($reservation['start_iso'] ?? '');
        $end_iso = (string) ($reservation['end_iso'] ?? '');

        $search = ['{name}', '{email}', '{start}', '{end}', '{timezone}', '{note}', '{reference}'];
        $replace = [
            $name,
            $email,
            $start_iso,
            $end_iso,
            $timezone,
            $note,
            (string) ($reservation['reference'] ?? ''),
        ];

        $body = str_replace($search, $replace, $template);

        $attachment = $this->build_ics_attachment($reservation, $name, $email, $timezone, $note);
        $attachments = [];
        if ($attachment !== '') {
            $attachments[] = $attachment;
        }

        wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8'], $attachments);

        if ($attachment !== '' && file_exists($attachment)) {
            wp_delete_file($attachment);
        }
    }

    private function build_ics_attachment(array $reservation, string $name, string $email, string $timezone, string $note): string {
        $start_iso = (string) ($reservation['start_iso'] ?? '');
        $end_iso = (string) ($reservation['end_iso'] ?? '');
        if ($start_iso === '' || $end_iso === '') {
            return '';
        }

        try {
            $start = new DateTimeImmutable($start_iso, new DateTimeZone($timezone));
            $end = new DateTimeImmutable($end_iso, new DateTimeZone($timezone));
        } catch (Exception $e) {
            return '';
        }

        $uid = (string) ($reservation['reference'] ?? wp_generate_uuid4());
        $summary = 'Restatify Gespräch';
        $description = "Name: {$name}\\nEmail: {$email}\\nNotiz: {$note}";

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Restatify//Booking Assistant//DE\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= 'UID:' . sanitize_text_field($uid) . "\r\n";
        $ics .= 'DTSTAMP:' . gmdate('Ymd\\THis\\Z') . "\r\n";
        $ics .= 'DTSTART:' . gmdate('Ymd\\THis\\Z', $start->getTimestamp()) . "\r\n";
        $ics .= 'DTEND:' . gmdate('Ymd\\THis\\Z', $end->getTimestamp()) . "\r\n";
        $ics .= 'SUMMARY:' . $this->escape_ics_text($summary) . "\r\n";
        $ics .= 'DESCRIPTION:' . $this->escape_ics_text($description) . "\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        $tmp = wp_tempnam('restatify-booking.ics');
        if (!$tmp) {
            return '';
        }

        $written = file_put_contents($tmp, $ics);
        if ($written === false) {
            return '';
        }

        return $tmp;
    }

    private function escape_ics_text(string $value): string {
        $value = str_replace("\\", "\\\\", $value);
        $value = str_replace(";", "\\;", $value);
        $value = str_replace(",", "\\,", $value);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }

    private function sanitize_options($input): array {
        $defaults = $this->get_default_options();
        $input = is_array($input) ? $input : [];

        $calendar_sources_raw = sanitize_textarea_field((string) ($input['api_calendar_sources_raw'] ?? $defaults['api_calendar_sources_raw']));
        $calendar_sources = $this->parse_calendar_sources_raw($calendar_sources_raw);
        $availability_raw = sanitize_textarea_field((string) ($input['api_availability_raw'] ?? $defaults['api_availability_raw']));
        $availability_rules = $this->parse_availability_raw($availability_raw);

        return [
            'api_base_url' => esc_url_raw((string) ($input['api_base_url'] ?? $defaults['api_base_url'])),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? $defaults['api_key'])),
            'default_timezone' => sanitize_text_field((string) ($input['default_timezone'] ?? $defaults['default_timezone'])),
            'default_duration_minutes' => max(15, min(180, absint($input['default_duration_minutes'] ?? $defaults['default_duration_minutes']))),
            'slot_window_days' => max(1, min(60, absint($input['slot_window_days'] ?? $defaults['slot_window_days']))),
            'api_sync_enabled' => !empty($input['api_sync_enabled']),
            'api_sync_interval_minutes' => max(5, min(720, absint($input['api_sync_interval_minutes'] ?? $defaults['api_sync_interval_minutes']))),
            'api_calendar_sources_raw' => $calendar_sources_raw,
            'api_calendar_sources' => $calendar_sources,
            'api_availability_raw' => $availability_raw,
            'api_availability_rules' => $availability_rules,
            'autoresponder_subject' => sanitize_text_field((string) ($input['autoresponder_subject'] ?? $defaults['autoresponder_subject'])),
            'autoresponder_body' => sanitize_textarea_field((string) ($input['autoresponder_body'] ?? $defaults['autoresponder_body'])),
        ];
    }

    private function parse_availability_raw(string $raw): array {
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

    private function parse_calendar_sources_raw(string $raw): array {
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

            $sources[] = [
                'calendar_id' => $calendar_id,
                'label' => $label,
                'privacy_mode' => $privacy_mode,
            ];
        }

        return $sources;
    }

    private function get_options(): array {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $this->get_default_options());
    }

    private function get_default_options(): array {
        return [
            'api_base_url' => 'https://booking-api.example.com',
            'api_key' => '',
            'default_timezone' => wp_timezone_string() ?: 'Europe/Berlin',
            'default_duration_minutes' => 30,
            'slot_window_days' => 14,
            'api_sync_enabled' => true,
            'api_sync_interval_minutes' => 15,
            'api_calendar_sources_raw' => '',
            'api_calendar_sources' => [],
            'api_availability_raw' => "mo|09:00-12:00,13:00-17:00\ndi|09:00-12:00,13:00-17:00\nmi|09:00-12:00,13:00-17:00\ndo|09:00-12:00,13:00-17:00\nfr|09:00-12:00,13:00-17:00",
            'api_availability_rules' => [],
            'autoresponder_subject' => __('Your Restatify appointment reservation', 'restatify-booking-assistant'),
            'autoresponder_body' => "Hallo {name},\n\nvielen Dank fuer deine Reservierung.\n\nStart: {start}\nEnde: {end}\nZeitzone: {timezone}\nReferenz: {reference}\n\nViele Gruesse\nRestatify",
        ];
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Restatify Booking Assistant', 'restatify-booking-assistant'); ?></h1>
            <p><?php esc_html_e('Connect the plugin to your Restatify Booking API and define autoresponder text.', 'restatify-booking-assistant'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('restatify_booking_assistant'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Booking API Base URL', 'restatify-booking-assistant'); ?></th>
                        <td><input class="regular-text code" type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_base_url]" value="<?php echo esc_attr((string) $options['api_base_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API key', 'restatify-booking-assistant'); ?></th>
                        <td><input class="regular-text" type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr((string) $options['api_key']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default timezone', 'restatify-booking-assistant'); ?></th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_timezone]" value="<?php echo esc_attr((string) $options['default_timezone']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default duration (minutes)', 'restatify-booking-assistant'); ?></th>
                        <td><input class="small-text" type="number" min="15" max="180" step="15" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_duration_minutes]" value="<?php echo esc_attr((string) $options['default_duration_minutes']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Search window (days)', 'restatify-booking-assistant'); ?></th>
                        <td><input class="small-text" type="number" min="1" max="60" step="1" name="<?php echo esc_attr(self::OPTION_KEY); ?>[slot_window_days]" value="<?php echo esc_attr((string) $options['slot_window_days']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable API sync', 'restatify-booking-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_sync_enabled]" value="1" <?php checked(!empty($options['api_sync_enabled'])); ?>>
                                <?php esc_html_e('Allow API to run calendar free/busy synchronization.', 'restatify-booking-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sync interval (minutes)', 'restatify-booking-assistant'); ?></th>
                        <td><input class="small-text" type="number" min="5" max="720" step="5" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_sync_interval_minutes]" value="<?php echo esc_attr((string) $options['api_sync_interval_minutes']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Calendars to sync', 'restatify-booking-assistant'); ?></th>
                        <td>
                            <textarea class="large-text code" rows="6" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_calendar_sources_raw]"><?php echo esc_textarea((string) ($options['api_calendar_sources_raw'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One calendar per line: calendar_id|Label|private or official', 'restatify-booking-assistant'); ?></p>
                            <p class="description"><?php esc_html_e('Example: company-calendar@group.calendar.google.com|Company Main|official', 'restatify-booking-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Weekly availability rules', 'restatify-booking-assistant'); ?></th>
                        <td>
                            <textarea class="large-text code" rows="7" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_availability_raw]"><?php echo esc_textarea((string) ($options['api_availability_raw'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One line per weekday. Format: day|HH:MM-HH:MM,HH:MM-HH:MM', 'restatify-booking-assistant'); ?></p>
                            <p class="description"><?php esc_html_e('Allowed days: mo, di, mi, do, fr, sa, so (or mon..sun). Example: mi|09:00-12:00,13:00-16:30', 'restatify-booking-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Autoresponder subject', 'restatify-booking-assistant'); ?></th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[autoresponder_subject]" value="<?php echo esc_attr((string) $options['autoresponder_subject']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Autoresponder body', 'restatify-booking-assistant'); ?></th>
                        <td>
                            <textarea class="large-text code" rows="8" name="<?php echo esc_attr(self::OPTION_KEY); ?>[autoresponder_body]"><?php echo esc_textarea((string) $options['autoresponder_body']); ?></textarea>
                            <p class="description"><?php esc_html_e('Placeholders: {name}, {email}, {start}, {end}, {timezone}, {note}, {reference}', 'restatify-booking-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p><strong><?php esc_html_e('Shortcode:', 'restatify-booking-assistant'); ?></strong> [restatify_booking_popup]</p>
        </div>
        <?php
    }
}

new Restatify_Booking_Assistant();

function restatify_booking_ai_handle_message(string $message): string {
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    $booking_terms = '/termin|appointment|slot|verfuegbar|verfugbarkeit|frei|buchen|book/i';
    if (!preg_match($booking_terms, $message)) {
        return '';
    }

    $options = get_option('restatify_booking_assistant_options', []);
    if (!is_array($options) || empty($options['api_base_url'])) {
        return __('Booking service is not configured yet.', 'restatify-booking-assistant');
    }

    $timezone = sanitize_text_field((string) ($options['default_timezone'] ?? 'Europe/Berlin'));
    $duration = max(15, min(180, absint($options['default_duration_minutes'] ?? 30)));

    $email = '';
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $message, $email_match) === 1) {
        $email = sanitize_email((string) ($email_match[0] ?? ''));
    }

    $date = '';
    $time = '';
    if (preg_match('/(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})/', $message, $datetime_match) === 1) {
        $date = (string) ($datetime_match[1] ?? '');
        $time = (string) ($datetime_match[2] ?? '');
    }

    if ($email !== '' && $date !== '' && $time !== '' && preg_match('/buchen|book|reservieren|reserve/i', $message)) {
        $name = 'Chat User';
        if (preg_match('/name\s*:\s*([^,\n]+)/i', $message, $name_match) === 1) {
            $name = sanitize_text_field((string) ($name_match[1] ?? 'Chat User'));
        }

        try {
            $start = new DateTimeImmutable($date . ' ' . $time, new DateTimeZone($timezone));
        } catch (Exception $e) {
            return __('I could not parse the requested booking time. Please use YYYY-MM-DD HH:MM.', 'restatify-booking-assistant');
        }

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($options['api_key'])) {
            $headers['X-API-Key'] = (string) $options['api_key'];
        }

        $create_payload = [
            'start_iso' => $start->format(DATE_ATOM),
            'duration_minutes' => $duration,
            'timezone' => $timezone,
            'name' => $name,
            'email' => $email,
            'note' => 'Reserved via chat overlay assistant',
        ];

        $create = wp_remote_post(rtrim((string) $options['api_base_url'], '/') . '/v1/reservations', [
            'timeout' => 12,
            'headers' => $headers,
            'body' => wp_json_encode($create_payload),
        ]);

        if (is_wp_error($create)) {
            return __('I could not complete the reservation right now. Please try again in a moment.', 'restatify-booking-assistant');
        }

        $create_status = (int) wp_remote_retrieve_response_code($create);
        $create_body = json_decode((string) wp_remote_retrieve_body($create), true);
        if ($create_status < 200 || $create_status >= 300 || !is_array($create_body)) {
            return __('This slot could not be reserved. Please ask me for free slots and choose another time.', 'restatify-booking-assistant');
        }

        $reference = sanitize_text_field((string) ($create_body['reference'] ?? ''));
        $start_iso = sanitize_text_field((string) ($create_body['start_iso'] ?? ''));

        return sprintf(
            __('Reservation confirmed for %1$s. Reference: %2$s. A confirmation email should arrive shortly at %3$s.', 'restatify-booking-assistant'),
            $start_iso,
            $reference !== '' ? $reference : '-',
            $email
        );
    }

    $start = new DateTimeImmutable('now', new DateTimeZone($timezone));
    $end = $start->modify('+7 days');

    $payload = [
        'start_iso' => $start->format(DATE_ATOM),
        'end_iso' => $end->format(DATE_ATOM),
        'duration_minutes' => $duration,
        'timezone' => $timezone,
    ];

    $headers = ['Content-Type' => 'application/json'];
    if (!empty($options['api_key'])) {
        $headers['X-API-Key'] = (string) $options['api_key'];
    }

    $response = wp_remote_post(rtrim((string) $options['api_base_url'], '/') . '/v1/slots/search', [
        'timeout' => 12,
        'headers' => $headers,
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return __('I could not reach the booking service right now.', 'restatify-booking-assistant');
    }

    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $slots = is_array($body['slots'] ?? null) ? $body['slots'] : [];
    if (count($slots) === 0) {
        return __('Currently I do not see free slots in the next 7 days. Please try again later.', 'restatify-booking-assistant');
    }

    $lines = [__('I found free slots:', 'restatify-booking-assistant')];
    foreach (array_slice($slots, 0, 5) as $slot) {
        $lines[] = '- ' . sanitize_text_field((string) ($slot['start_iso'] ?? ''));
    }

    $lines[] = __('If you want, I can help you reserve one via the booking popup.', 'restatify-booking-assistant');

    return implode("\n", $lines);
}
