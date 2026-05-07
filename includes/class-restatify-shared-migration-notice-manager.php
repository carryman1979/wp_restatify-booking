<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Restatify_Shared_Migration_Notice_Manager')) {
    /**
     * Reusable post-migration admin notice flow for legacy settings cleanup.
     */
    final class Restatify_Shared_Migration_Notice_Manager {
        /**
         * @param array<string,mixed> $config
         */
        public static function register(array $config): void {
            add_action('admin_notices', static function () use ($config): void {
                self::render_notice($config);
            });

            add_action('admin_init', static function () use ($config): void {
                self::handle_action($config);
            });
        }

        /**
         * @param array<string,mixed> $config
         */
        private static function render_notice(array $config): void {
            if (!current_user_can('manage_options')) {
                return;
            }

            $state_key = (string) ($config['state_option_key'] ?? 'restatify_migration_state');
            $state_show_key = (string) ($config['state_show_key'] ?? 'show_notice');
            $state = get_option($state_key, []);
            if (!is_array($state) || empty($state[$state_show_key])) {
                return;
            }

            $page_slug = (string) ($config['page_slug'] ?? '');
            if ($page_slug !== '') {
                $current_page = sanitize_key((string) ($_GET['page'] ?? ''));
                if ($current_page !== $page_slug) {
                    return;
                }
            }

            $legacy_option_keys = is_array($config['legacy_option_keys'] ?? null)
                ? array_values($config['legacy_option_keys'])
                : [];
            if (count($legacy_option_keys) === 0) {
                return;
            }

            $lang = self::current_lang();
            $action_query_arg = (string) ($config['action_query_arg'] ?? 'restatify_migration_notice_action');
            $nonce_query_arg = (string) ($config['nonce_query_arg'] ?? 'restatify_migration_notice_nonce');
            $nonce_action = (string) ($config['nonce_action'] ?? 'restatify_migration_notice_action');
            $nonce = wp_create_nonce($nonce_action);

            $action_url = admin_url('options-general.php');
            if ($page_slug !== '') {
                $action_url = add_query_arg(['page' => $page_slug], $action_url);
            }

            $title = $lang === 'de'
                ? (string) ($config['title_de'] ?? 'Migration abgeschlossen')
                : (string) ($config['title_en'] ?? 'Migration completed');
            $body = $lang === 'de'
                ? (string) ($config['body_de'] ?? 'Die Einstellungen wurden erfolgreich migriert.')
                : (string) ($config['body_en'] ?? 'Settings were migrated successfully.');
            $warning = $lang === 'de'
                ? (string) ($config['warning_de'] ?? 'Hinweis: Logs und Historie werden nicht migriert.')
                : (string) ($config['warning_en'] ?? 'Note: logs and history are not migrated.');
            $keep_label = $lang === 'de'
                ? (string) ($config['keep_label_de'] ?? 'Legacy-Einstellungen behalten')
                : (string) ($config['keep_label_en'] ?? 'Keep legacy settings');
            $remove_label = $lang === 'de'
                ? (string) ($config['remove_label_de'] ?? 'Legacy-Einstellungen entfernen')
                : (string) ($config['remove_label_en'] ?? 'Remove legacy settings');

            $keep_url = add_query_arg([
                $action_query_arg => 'keep',
                $nonce_query_arg => $nonce,
            ], $action_url);

            $remove_url = add_query_arg([
                $action_query_arg => 'remove',
                $nonce_query_arg => $nonce,
            ], $action_url);

            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html($title) . '</strong></p>';
            echo '<p>' . esc_html($body) . '</p>';
            echo '<p><em>' . esc_html($warning) . '</em></p>';
            echo '<p>';
            echo '<a class="button button-primary" href="' . esc_url($keep_url) . '">' . esc_html($keep_label) . '</a> ';
            echo '<a class="button" href="' . esc_url($remove_url) . '">' . esc_html($remove_label) . '</a>';
            echo '</p>';
            echo '</div>';
        }

        /**
         * @param array<string,mixed> $config
         */
        private static function handle_action(array $config): void {
            if (!current_user_can('manage_options')) {
                return;
            }

            $action_query_arg = (string) ($config['action_query_arg'] ?? 'restatify_migration_notice_action');
            $nonce_query_arg = (string) ($config['nonce_query_arg'] ?? 'restatify_migration_notice_nonce');
            $nonce_action = (string) ($config['nonce_action'] ?? 'restatify_migration_notice_action');

            $action = sanitize_key((string) ($_GET[$action_query_arg] ?? ''));
            if ($action === '') {
                return;
            }

            $nonce = sanitize_text_field((string) ($_GET[$nonce_query_arg] ?? ''));
            if (!wp_verify_nonce($nonce, $nonce_action)) {
                return;
            }

            $state_key = (string) ($config['state_option_key'] ?? 'restatify_migration_state');
            $state_show_key = (string) ($config['state_show_key'] ?? 'show_notice');
            $state = get_option($state_key, []);
            if (!is_array($state)) {
                $state = [];
            }

            if (!in_array($action, ['keep', 'remove'], true)) {
                return;
            }

            if ($action === 'remove') {
                $legacy_option_keys = is_array($config['legacy_option_keys'] ?? null)
                    ? array_values($config['legacy_option_keys'])
                    : [];
                $source_option_key = isset($state['source_option_key']) ? (string) $state['source_option_key'] : '';

                if ($source_option_key !== '' && in_array($source_option_key, $legacy_option_keys, true)) {
                    delete_option($source_option_key);
                }
            }

            $state[$state_show_key] = false;
            $state['decision'] = $action;
            $state['decided_at'] = time();
            update_option($state_key, $state, false);

            $notice_key = (string) ($config['notice_transient_key'] ?? '');
            if ($notice_key !== '') {
                $lang = self::current_lang();
                if ($action === 'remove') {
                    $message = $lang === 'de'
                        ? (string) ($config['success_remove_de'] ?? 'Legacy-Einstellungen wurden entfernt.')
                        : (string) ($config['success_remove_en'] ?? 'Legacy settings were removed.');
                } else {
                    $message = $lang === 'de'
                        ? (string) ($config['success_keep_de'] ?? 'Legacy-Einstellungen wurden beibehalten.')
                        : (string) ($config['success_keep_en'] ?? 'Legacy settings were kept.');
                }

                set_transient($notice_key, [
                    'type' => 'success',
                    'message' => $message,
                ], 60);
            }

            $page_slug = (string) ($config['page_slug'] ?? '');
            $redirect_url = admin_url('options-general.php');
            if ($page_slug !== '') {
                $redirect_url = add_query_arg(['page' => $page_slug], $redirect_url);
            }
            wp_safe_redirect($redirect_url);
            exit;
        }

        private static function current_lang(): string {
            if (function_exists('determine_locale')) {
                $locale = (string) determine_locale();
                if (strpos(strtolower($locale), 'de') === 0) {
                    return 'de';
                }
            }

            return 'en';
        }
    }
}
