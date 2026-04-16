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
        add_action('wp_enqueue_scripts', [$this->ui, 'enqueue_assets']);
        add_action('wp_footer', [$this->ui, 'render_global_popup'], 30);
        add_action('template_redirect', [$this->cancellation_controller, 'maybe_render_page']);

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
            'restatify-booking-assistant',
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

        $result = $this->api_client->push_sync_config($new_value);
        if (is_wp_error($result)) {
            set_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT, [
                'type' => 'error',
                'message' => sprintf(
                    __('Sync configuration could not be sent to API: %s', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                    $result->get_error_message()
                ),
            ], 60);
            return;
        }

        set_transient(Restatify_Booking_Assistant_Constants::ADMIN_NOTICE_TRANSIENT, [
            'type' => 'success',
            'message' => __('Sync configuration was updated in Booking API.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
        ], 60);
    }

    /**
     * Renders transient admin notice on plugin settings page.
     */
    public function render_admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || sanitize_key((string) $_GET['page']) !== 'restatify-booking-assistant') {
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
