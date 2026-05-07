<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main composition root wiring plugin services and WordPress hooks.
 */
final class Restatify_Booking_Assistant_Plugin {
    private string $plugin_file;
    private Restatify_Booking_Assistant_Options $options_service;
    private Restatify_Booking_Assistant_Api_Client $api_client;
    private Restatify_Booking_Assistant_Autoresponder $autoresponder;
    private Restatify_Booking_Assistant_Cancellation_Controller $cancellation_controller;
    private Restatify_Booking_Assistant_UI $ui;
    private Restatify_Booking_Assistant_Booking_Controller $booking_controller;

    public function __construct(string $plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->options_service = new Restatify_Booking_Assistant_Options();
        $this->api_client = new Restatify_Booking_Assistant_Api_Client($this->options_service);
        $this->autoresponder = new Restatify_Booking_Assistant_Autoresponder($this->options_service);
        $this->cancellation_controller = new Restatify_Booking_Assistant_Cancellation_Controller($this->api_client, $this->autoresponder);
        $this->ui = new Restatify_Booking_Assistant_UI($plugin_file, $this->options_service);
        $this->booking_controller = new Restatify_Booking_Assistant_Booking_Controller(
            $this->options_service,
            $this->api_client,
            $this->autoresponder
        );

        $this->register_hooks();
    }

    /**
     * Registers all plugin hooks.
     */
    public function register_hooks(): void {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_shortcode']);
        add_action('admin_init', [$this->options_service, 'register_settings']);
        add_action('admin_init', [$this->options_service, 'register_polylang_strings']);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
        add_action('admin_post_' . Restatify_Booking_Assistant_Constants::FORCE_SYNC_ACTION, [$this, 'handle_force_sync']);
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        add_action('wp_enqueue_scripts', [$this->ui, 'enqueue_assets']);
        add_action('wp_footer', [$this->ui, 'render_global_popup'], 30);
        add_action('template_redirect', [$this->cancellation_controller, 'maybe_render_page']);

        Restatify_Shared_Migration_Notice_Manager::register([
            'state_option_key' => Restatify_Booking_Assistant_Constants::MIGRATION_STATE_OPTION,
            'state_show_key' => 'show_notice',
            'page_slug' => Restatify_Booking_Assistant_Constants::ADMIN_PAGE_SLUG,
            'legacy_option_keys' => Restatify_Booking_Assistant_Constants::LEGACY_OPTION_KEYS,
            'notice_transient_key' => Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT,
            'action_query_arg' => 'restatify_booking_migration_notice_action',
            'nonce_query_arg' => 'restatify_booking_migration_notice_nonce',
            'nonce_action' => 'restatify_booking_migration_notice',
            'title_de' => 'Restatify Booking 2.0: Migration abgeschlossen',
            'title_en' => 'Restatify Booking 2.0: Migration completed',
            'body_de' => 'Ihre Einstellungen wurden aus der Legacy-Konfiguration uebernommen. Standard ist: Legacy-Einstellungen vorerst behalten.',
            'body_en' => 'Your settings were migrated from the legacy configuration. Default is to keep legacy settings for now.',
            'warning_de' => 'Hinweis: Protokolle und Verlauf wurden bewusst nicht migriert.',
            'warning_en' => 'Note: logs and history were intentionally not migrated.',
            'keep_label_de' => 'Legacy-Einstellungen behalten (Standard)',
            'keep_label_en' => 'Keep legacy settings (default)',
            'remove_label_de' => 'Legacy-Einstellungen entfernen',
            'remove_label_en' => 'Remove legacy settings',
            'success_keep_de' => 'Legacy-Einstellungen wurden zur Sicherheit beibehalten. Sie koennen diese spaeter entfernen.',
            'success_keep_en' => 'Legacy settings were kept for safety. You can remove them later.',
            'success_remove_de' => 'Legacy-Einstellungen wurden entfernt. Die aktuellen Restatify Booking Einstellungen bleiben aktiv.',
            'success_remove_en' => 'Legacy settings were removed. Current Restatify Booking settings stay active.',
        ]);

        add_action('update_option_' . Restatify_Booking_Assistant_Constants::OPTION_KEY, [$this, 'handle_options_updated'], 10, 2);
        add_filter('wp_link_query', [$this->ui, 'extend_wp_link_query'], 10, 2);

        add_action('wp_ajax_restatify_booking_find_slots', [$this->booking_controller, 'ajax_find_slots']);
        add_action('wp_ajax_nopriv_restatify_booking_find_slots', [$this->booking_controller, 'ajax_find_slots']);
        add_action('wp_ajax_restatify_booking_reserve_slot', [$this->booking_controller, 'ajax_reserve_slot']);
        add_action('wp_ajax_nopriv_restatify_booking_reserve_slot', [$this->booking_controller, 'ajax_reserve_slot']);
    }

    /**
     * Loads translation files.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            Restatify_Booking_Assistant_Constants::TEXT_DOMAIN,
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    /**
     * Registers booking popup shortcode.
     */
    public function register_shortcode(): void {
        add_shortcode('restatify_booking_popup', [$this->ui, 'render_shortcode']);
    }

    /**
     * Registers settings page entry in WP admin.
     */
    public function register_admin_page(): void {
        add_options_page(
            __('Restatify Booking Assistant', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            __('Booking Assistant', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'manage_options',
            Restatify_Booking_Assistant_Constants::ADMIN_PAGE_SLUG,
            [$this->ui, 'render_admin_page']
        );
    }

    /**
     * Pushes changed sync options to backend API and stores transient admin notice.
     *
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function handle_options_updated($old_value, $new_value): void {
        if (!is_array($new_value)) {
            return;
        }

        $this->push_sync_config($new_value, false);
    }

    /**
     * Handles explicit manual force-sync action from settings page.
     */
    public function handle_force_sync(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN));
        }

        check_admin_referer(Restatify_Booking_Assistant_Constants::FORCE_SYNC_NONCE_ACTION);

        $options = $this->options_service->get_options();
        $this->push_sync_config($options, true);

        wp_safe_redirect(add_query_arg(['page' => Restatify_Booking_Assistant_Constants::ADMIN_PAGE_SLUG], admin_url('options-general.php')));
        exit;
    }

    /**
     * Registers Booking API status widget in wp-admin dashboard.
     */
    public function register_dashboard_widget(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'restatify_booking_assistant_status_widget',
            __('Booking API Status', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Renders dashboard widget with current API connectivity status.
     */
    public function render_dashboard_widget(): void {
        $status = $this->probe_connection_status();
        $state = (string) ($status['state'] ?? 'error');
        $message = (string) ($status['message'] ?? __('Status unknown.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN));
        $checked_at = (int) ($status['checked_at'] ?? time());
        $calendar_sources = max(0, absint($status['calendar_sources'] ?? 0));

        $badge_styles = [
            'success' => 'background:#e8f8ef;color:#116b37;border:1px solid #b8ebcb;',
            'warning' => 'background:#fff8e7;color:#915d00;border:1px solid #f3d28f;',
            'error' => 'background:#fff1f1;color:#9f1f1f;border:1px solid #f4b5b5;',
        ];
        $badge_labels = [
            'success' => __('Connected', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'warning' => __('Needs Attention', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'error' => __('Disconnected', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ];

        $badge_style = $badge_styles[$state] ?? $badge_styles['error'];
        $badge_label = $badge_labels[$state] ?? $badge_labels['error'];
        $force_sync_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . Restatify_Booking_Assistant_Constants::FORCE_SYNC_ACTION),
            Restatify_Booking_Assistant_Constants::FORCE_SYNC_NONCE_ACTION
        );

        echo '<p><span style="display:inline-block;padding:4px 10px;border-radius:999px;font-weight:600;' . esc_attr($badge_style) . '">' . esc_html($badge_label) . '</span></p>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<ul style="margin:0 0 12px 1.2em;list-style:disc;">';
        echo '<li>' . sprintf(esc_html__('Calendar sources configured: %d', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN), $calendar_sources) . '</li>';
        echo '<li>' . sprintf(esc_html__('Last checked: %s', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN), esc_html(wp_date('Y-m-d H:i:s', $checked_at))) . '</li>';
        echo '</ul>';
        echo '<p><a class="button button-secondary" href="' . esc_url($force_sync_url) . '">' . esc_html__('Force Sync Now', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN) . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('options-general.php?page=' . Restatify_Booking_Assistant_Constants::ADMIN_PAGE_SLUG)) . '">' . esc_html__('Open Booking Settings', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN) . '</a></p>';
    }

    /**
     * Pushes current sync config to API and stores admin notice/status.
     *
     * @param array<string,mixed> $new_value
     */
    private function push_sync_config(array $new_value, bool $is_manual): void {
        $result = $this->api_client->push_sync_config($new_value);
        if (is_wp_error($result)) {
            $message = sprintf(
                __('Sync configuration could not be sent to API: %s', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                $result->get_error_message()
            );
            set_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT, [
                'type' => 'error',
                'message' => $message,
            ], 60);

            $this->store_connection_status([
                'state' => 'error',
                'message' => $result->get_error_message(),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ]);
            return;
        }

        $success_message = $is_manual
            ? __('Force sync completed successfully.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
            : __('Sync configuration was updated in Booking API.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
        set_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT, [
            'type' => 'success',
            'message' => $success_message,
        ], 60);

        $calendar_sources = is_array($result['calendar_sources'] ?? null) ? count($result['calendar_sources']) : 0;
        $this->store_connection_status([
            'state' => $calendar_sources > 0 ? 'success' : 'warning',
            'message' => $calendar_sources > 0
                ? __('Connection and authentication with Booking API are healthy.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
                : __('Booking API is reachable, but no calendar sources are configured.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'calendar_sources' => $calendar_sources,
            'checked_at' => time(),
        ]);
    }

    /**
     * Performs live connectivity/authentication checks against configured API.
     *
     * @return array<string,mixed>
     */
    private function probe_connection_status(): array {
        $options = $this->options_service->get_options();
        $base_url = rtrim((string) ($options['api_base_url'] ?? ''), '/');
        $api_key = trim((string) ($options['api_key'] ?? ''));

        if ($base_url === '' || $api_key === '') {
            $status = [
                'state' => 'error',
                'message' => __('Booking API base URL or API key is missing.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        $health_response = wp_remote_get($base_url . '/health', [
            'timeout' => 8,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($health_response)) {
            $status = [
                'state' => 'error',
                'message' => sprintf(
                    __('Booking API is unreachable: %s', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    $health_response->get_error_message()
                ),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        $health_code = (int) wp_remote_retrieve_response_code($health_response);
        if ($health_code < 200 || $health_code >= 300) {
            $status = [
                'state' => 'error',
                'message' => sprintf(
                    __('Booking API health check failed with HTTP %d.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    $health_code
                ),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        $config_response = wp_remote_get($base_url . '/v1/config/sync', [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $api_key,
            ],
        ]);

        if (is_wp_error($config_response)) {
            $status = [
                'state' => 'error',
                'message' => sprintf(
                    __('Booking API authentication check failed: %s', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    $config_response->get_error_message()
                ),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        $config_code = (int) wp_remote_retrieve_response_code($config_response);
        if ($config_code === 401 || $config_code === 403) {
            $status = [
                'state' => 'error',
                'message' => __('Booking API rejected the configured API key.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        if ($config_code < 200 || $config_code >= 300) {
            $status = [
                'state' => 'error',
                'message' => sprintf(
                    __('Booking API config check failed with HTTP %d.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    $config_code
                ),
                'calendar_sources' => 0,
                'checked_at' => time(),
            ];
            $this->store_connection_status($status);
            return $status;
        }

        $config_body = json_decode((string) wp_remote_retrieve_body($config_response), true);
        $calendar_sources = is_array($config_body['calendar_sources'] ?? null)
            ? count($config_body['calendar_sources'])
            : 0;

        $status = [
            'state' => $calendar_sources > 0 ? 'success' : 'warning',
            'message' => $calendar_sources > 0
                ? __('Booking API reachable, authenticated, and calendar sources are configured.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN)
                : __('Booking API reachable and authenticated, but no calendar sources are configured.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'calendar_sources' => $calendar_sources,
            'checked_at' => time(),
        ];
        $this->store_connection_status($status);
        return $status;
    }

    /**
     * @param array<string,mixed> $status
     */
    private function store_connection_status(array $status): void {
        update_option(Restatify_Booking_Assistant_Constants::CONNECTION_STATUS_OPTION, $status, false);
    }

    /**
     * Renders transient admin notice on plugin settings page.
     */
    public function render_admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || sanitize_key((string) $_GET['page']) !== Restatify_Booking_Assistant_Constants::ADMIN_PAGE_SLUG) {
            return;
        }

        $notice = get_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT);
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT);

        $type = (string) ($notice['type'] ?? 'info');
        if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            $type = 'info';
        }

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html((string) $notice['message']) . '</p></div>';
    }
}
