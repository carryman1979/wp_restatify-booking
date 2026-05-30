<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('restatify_booking_shared_base_dir')) {
    function restatify_booking_shared_base_dir(): string {
        $candidates = [
            rtrim(dirname(RESTATIFY_BOOKING_PLUGIN_DIR, 3), '/\\') . '/wp_restatify-shared',
            rtrim((defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(RESTATIFY_BOOKING_PLUGIN_DIR, 2)), '/\\') . '/wp_restatify-shared',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}

if (!function_exists('restatify_booking_shared_versions_base_dir')) {
    function restatify_booking_shared_versions_base_dir(): string {
        return restatify_booking_shared_base_dir() . '/versions';
    }
}

if (!function_exists('restatify_booking_shared_legacy_base_dir')) {
    function restatify_booking_shared_legacy_base_dir(): string {
        return restatify_booking_shared_base_dir();
    }
}

if (!function_exists('restatify_booking_shared_target_dir')) {
    function restatify_booking_shared_target_dir(): string {
        return restatify_booking_shared_versions_base_dir() . '/' . RESTATIFY_BOOKING_SHARED_VERSION;
    }
}

if (!function_exists('restatify_booking_shared_packaged_version_dir')) {
    function restatify_booking_shared_packaged_version_dir(): string {
        return RESTATIFY_BOOKING_PLUGIN_DIR
            . 'shared-install/wp_restatify-shared/versions/'
            . RESTATIFY_BOOKING_SHARED_VERSION;
    }
}

if (!function_exists('restatify_booking_shared_required_file_list')) {
    function restatify_booking_shared_required_file_list(): array {
        return [
            'SharedRegistry.php',
            'Api/BookingApiErrorFormatter.php',
            'Contracts/BookingApiErrorCodes.php',
            'Contracts/BookingChatTokens.php',
            'Contracts/BookingPrefillSchema.php',
            'I18n/PolylangAdapter.php',
            'Mail/MailDispatcher.php',
            'Mail/PlaceholderCatalog.php',
            'Migration/MigrationNoticeManager.php',
            'Runtime/BootstrapGuard.php',
            'Runtime/PluginState.php',
            'Runtime/RateLimiter.php',
            'Util/BookingContactChannelProfiles.php',
            'Util/BookingContactChannels.php',
            'Util/BookingContactMethodsResolver.php',
            'Util/PrivacyLegalNotice.php',
            'Util/TokenReplacer.php',
        ];
    }
}

if (!function_exists('restatify_booking_shared_copy_tree')) {
    function restatify_booking_shared_copy_tree(string $source, string $target): bool {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($target) && !wp_mkdir_p($target)) {
            return false;
        }

        $items = scandir($source);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $source_path = $source . '/' . $item;
            $target_path = $target . '/' . $item;

            if (is_dir($source_path)) {
                if (!restatify_booking_shared_copy_tree($source_path, $target_path)) {
                    return false;
                }
                continue;
            }

            if (!@copy($source_path, $target_path)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('restatify_booking_shared_has_required_files')) {
    function restatify_booking_shared_has_required_files(string $base_dir): bool {
        foreach (restatify_booking_shared_required_file_list() as $relative_path) {
            if (!file_exists($base_dir . '/src/php/' . $relative_path)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('restatify_booking_shared_delete_tree')) {
    function restatify_booking_shared_delete_tree(string $path): bool {
        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path) || is_link($path)) {
            return @unlink($path);
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (!restatify_booking_shared_delete_tree($path . '/' . $item)) {
                return false;
            }
        }

        return @rmdir($path);
    }
}

if (!function_exists('restatify_booking_shared_extract_version_from_plugin')) {
    function restatify_booking_shared_extract_version_from_plugin(string $plugin_basename, string $constant_name): ?string {
        $plugin_file = trailingslashit(WP_PLUGIN_DIR) . ltrim($plugin_basename, '/');
        if (!file_exists($plugin_file)) {
            return null;
        }

        $contents = @file_get_contents($plugin_file);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $pattern = '/define\(\s*[\"\']' . preg_quote($constant_name, '/') . '[\"\']\s*,\s*[\"\']([^\"\']+)[\"\']\s*\)/';
        if (!preg_match($pattern, $contents, $matches)) {
            return null;
        }

        $version = trim((string) ($matches[1] ?? ''));
        return $version !== '' ? $version : null;
    }
}

if (!function_exists('restatify_booking_shared_required_versions')) {
    function restatify_booking_shared_required_versions(): array {
        $known_plugins = [
            'wp_restatify-booking/wp_restatify-booking.php' => 'RESTATIFY_BOOKING_SHARED_VERSION',
            'wp_restatify-ai-multichat/wp_restatify-ai-multichat.php' => 'RESTATIFY_AI_MULTICHAT_SHARED_VERSION',
            'wp-restatify-forms/wp-restatify-forms.php' => 'RESTATIFY_FORMS_SHARED_VERSION',
        ];

        $active_plugins = get_option('active_plugins', []);
        if (!is_array($active_plugins)) {
            $active_plugins = [];
        }

        if (is_multisite()) {
            $network_plugins = get_site_option('active_sitewide_plugins', []);
            if (is_array($network_plugins)) {
                $active_plugins = array_merge($active_plugins, array_keys($network_plugins));
            }
        }

        $active_lookup = array_fill_keys(array_map('strval', $active_plugins), true);
        $required_versions = [RESTATIFY_BOOKING_SHARED_VERSION];

        foreach ($known_plugins as $plugin_basename => $constant_name) {
            if (!isset($active_lookup[$plugin_basename])) {
                continue;
            }

            $version = restatify_booking_shared_extract_version_from_plugin($plugin_basename, $constant_name);
            if (is_string($version) && $version !== '') {
                $required_versions[] = $version;
            }
        }

        return array_values(array_unique($required_versions));
    }
}

if (!function_exists('restatify_booking_shared_cleanup_unused_versions')) {
    function restatify_booking_shared_cleanup_unused_versions(): void {
        $versions_base = restatify_booking_shared_versions_base_dir();
        if (!is_dir($versions_base)) {
            return;
        }

        $required_versions = restatify_booking_shared_required_versions();
        $required_lookup = array_fill_keys($required_versions, true);

        $entries = scandir($versions_base);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $versions_base . '/' . $entry;
            if (!is_dir($candidate)) {
                continue;
            }

            if (isset($required_lookup[$entry])) {
                continue;
            }

            restatify_booking_shared_delete_tree($candidate);
        }
    }
}

if (!function_exists('restatify_booking_shared_ensure_installed')) {
    function restatify_booking_shared_ensure_installed(): string {
        $target_dir = restatify_booking_shared_target_dir();
        if (restatify_booking_shared_has_required_files($target_dir)) {
            return $target_dir;
        }

        $packaged_dir = restatify_booking_shared_packaged_version_dir();
        if (restatify_booking_shared_has_required_files($packaged_dir) && restatify_booking_shared_copy_tree($packaged_dir, $target_dir)) {
            if (restatify_booking_shared_has_required_files($target_dir)) {
                return $target_dir;
            }
        }

        $legacy_dir = restatify_booking_shared_legacy_base_dir();
        if (restatify_booking_shared_has_required_files($legacy_dir)) {
            return $legacy_dir;
        }

        return $target_dir;
    }
}

if (!function_exists('restatify_booking_shared_bootstrap')) {
    function restatify_booking_shared_bootstrap(): string {
        $shared_root = restatify_booking_shared_ensure_installed();
        restatify_booking_shared_cleanup_unused_versions();
        return $shared_root;
    }
}

if (!function_exists('restatify_booking_shared_upgrade_hook')) {
    function restatify_booking_shared_upgrade_hook($upgrader_object, $options): void {
        unset($upgrader_object);

        if (!is_array($options)) {
            return;
        }

        $action = (string) ($options['action'] ?? '');
        $type = (string) ($options['type'] ?? '');
        if ($action !== 'update' || $type !== 'plugin') {
            return;
        }

        $plugins = $options['plugins'] ?? [];
        if (!is_array($plugins)) {
            return;
        }

        if (!in_array('wp_restatify-booking/wp_restatify-booking.php', $plugins, true)) {
            return;
        }

        restatify_booking_shared_bootstrap();
    }
}

register_activation_hook(RESTATIFY_BOOKING_PLUGIN_FILE, 'restatify_booking_shared_bootstrap');
add_action('upgrader_process_complete', 'restatify_booking_shared_upgrade_hook', 10, 2);
