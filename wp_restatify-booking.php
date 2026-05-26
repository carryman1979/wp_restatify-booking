<?php
/**
 * Plugin Name: Restatify Booking Assistant
 * Description: Manual slot search + reservation popup for WordPress, backed by Restatify Booking API.
 * Version: 2.0.5
 * Author: Restatify
 * License: GPL-2.0-or-later
 * Text Domain: restatify-booking-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('RESTATIFY_BOOKING_SHARED_VERSION')) {
    define('RESTATIFY_BOOKING_SHARED_VERSION', '1.0.2');
}

$restatify_booking_require_first = static function (array $paths): bool {
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            require_once $path;
            return true;
        }
    }

    return false;
};

$restatify_booking_local_shared_root = dirname(__DIR__, 3) . '/wp_restatify-shared';
$restatify_booking_use_local_latest_shared = is_dir($restatify_booking_local_shared_root . '/src/php');
$restatify_booking_versioned_shared_roots = [];

if ($restatify_booking_use_local_latest_shared) {
    $restatify_booking_shared_base_path = $restatify_booking_local_shared_root;
    $restatify_booking_shared_base_url = home_url('/wp_restatify-shared');
} else {
    if (defined('WP_PLUGIN_DIR') && is_string(WP_PLUGIN_DIR) && WP_PLUGIN_DIR !== '') {
        $restatify_booking_versioned_shared_roots[] = [
            'path' => WP_PLUGIN_DIR . '/wp_restatify-shared',
            'url' => (defined('WP_PLUGIN_URL') && is_string(WP_PLUGIN_URL) && WP_PLUGIN_URL !== '')
                ? rtrim(WP_PLUGIN_URL, '/') . '/wp_restatify-shared'
                : '',
        ];
    }
    if (defined('WPMU_PLUGIN_DIR') && is_string(WPMU_PLUGIN_DIR) && WPMU_PLUGIN_DIR !== '') {
        $restatify_booking_versioned_shared_roots[] = [
            'path' => WPMU_PLUGIN_DIR . '/wp_restatify-shared',
            'url' => (defined('WPMU_PLUGIN_URL') && is_string(WPMU_PLUGIN_URL) && WPMU_PLUGIN_URL !== '')
                ? rtrim(WPMU_PLUGIN_URL, '/') . '/wp_restatify-shared'
                : '',
        ];
    }

    $restatify_booking_shared_base_path = '';
    $restatify_booking_shared_base_url = '';

    foreach ($restatify_booking_versioned_shared_roots as $restatify_booking_root) {
        $restatify_booking_versioned_path = rtrim((string) $restatify_booking_root['path'], '/') . '/versions/' . RESTATIFY_BOOKING_SHARED_VERSION;
        if (is_dir($restatify_booking_versioned_path . '/src/php')) {
            $restatify_booking_shared_base_path = $restatify_booking_versioned_path;
            $restatify_booking_root_url = (string) $restatify_booking_root['url'];
            $restatify_booking_shared_base_url = $restatify_booking_root_url !== ''
                ? rtrim($restatify_booking_root_url, '/') . '/versions/' . RESTATIFY_BOOKING_SHARED_VERSION
                : '';
            break;
        }
    }

    if ($restatify_booking_shared_base_path === '' && count($restatify_booking_versioned_shared_roots) > 0) {
        $restatify_booking_first_root = $restatify_booking_versioned_shared_roots[0];
        $restatify_booking_shared_base_path = rtrim((string) $restatify_booking_first_root['path'], '/') . '/versions/' . RESTATIFY_BOOKING_SHARED_VERSION;
        $restatify_booking_first_root_url = (string) ($restatify_booking_first_root['url'] ?? '');
        $restatify_booking_shared_base_url = $restatify_booking_first_root_url !== ''
            ? rtrim($restatify_booking_first_root_url, '/') . '/versions/' . RESTATIFY_BOOKING_SHARED_VERSION
            : '';
    }
}

if (!defined('RESTATIFY_BOOKING_SHARED_BASE_PATH')) {
    define('RESTATIFY_BOOKING_SHARED_BASE_PATH', $restatify_booking_shared_base_path);
}

if (!defined('RESTATIFY_BOOKING_SHARED_BASE_URL')) {
    define('RESTATIFY_BOOKING_SHARED_BASE_URL', $restatify_booking_shared_base_url);
}

$restatify_booking_shared_candidates = static function (string $relativePath) use ($restatify_booking_shared_base_path): array {
    if (!is_string($restatify_booking_shared_base_path) || $restatify_booking_shared_base_path === '') {
        return [];
    }

    return [rtrim($restatify_booking_shared_base_path, '/') . '/' . ltrim($relativePath, '/')];
};

$restatify_booking_symbol_exists = static function (string $symbol): bool {
    if ($symbol === '') {
        return false;
    }

    return class_exists($symbol, false)
        || interface_exists($symbol, false)
        || trait_exists($symbol, false);
};

$restatify_booking_require_shared = static function (string $relativePath, string $symbol = '') use (
    $restatify_booking_require_first,
    $restatify_booking_shared_candidates,
    $restatify_booking_symbol_exists
): bool {
    if ($symbol !== '' && $restatify_booking_symbol_exists($symbol)) {
        return true;
    }

    $required = $restatify_booking_require_first($restatify_booking_shared_candidates($relativePath));

    if ($symbol !== '') {
        return $restatify_booking_symbol_exists($symbol);
    }

    return $required;
};

$restatify_booking_require_shared('src/php/SharedRegistry.php', '\\Restatify\\Shared\\SharedRegistry');
$restatify_booking_require_shared('src/php/Contracts/BookingChatTokens.php', '\\Restatify\\Shared\\Contracts\\BookingChatTokens');
$restatify_booking_require_shared('src/php/Contracts/BookingPrefillSchema.php', '\\Restatify\\Shared\\Contracts\\BookingPrefillSchema');
$restatify_booking_require_shared('src/php/Contracts/BookingApiErrorCodes.php', '\\Restatify\\Shared\\Contracts\\BookingApiErrorCodes');
$restatify_booking_require_shared('src/php/Runtime/PluginState.php', '\\Restatify\\Shared\\Runtime\\PluginState');
$restatify_booking_require_shared('src/php/Runtime/BootstrapGuard.php', '\\Restatify\\Shared\\Runtime\\BootstrapGuard');
$restatify_booking_require_shared('src/php/Runtime/RateLimiter.php', '\\Restatify\\Shared\\Runtime\\RateLimiter');
$restatify_booking_require_shared('src/php/Util/BookingContactMethodsResolver.php', '\\Restatify\\Shared\\Util\\BookingContactMethodsResolver');
$restatify_booking_require_shared('src/php/Util/BookingContactChannelProfiles.php', '\\Restatify\\Shared\\Util\\BookingContactChannelProfiles');
$restatify_booking_require_shared('src/php/Util/BookingContactChannels.php', '\\Restatify\\Shared\\Util\\BookingContactChannels');
$restatify_booking_require_shared('src/php/Util/TokenReplacer.php', '\\Restatify\\Shared\\Util\\TokenReplacer');
$restatify_booking_require_shared('src/php/Mail/MailDispatcher.php', '\\Restatify\\Shared\\Mail\\MailDispatcher');
$restatify_booking_require_shared('src/php/Mail/PlaceholderCatalog.php', '\\Restatify\\Shared\\Mail\\PlaceholderCatalog');
$restatify_booking_require_shared('src/php/I18n/PolylangAdapter.php', '\\Restatify\\Shared\\I18n\\PolylangAdapter');

if (!$restatify_booking_require_shared('src/php/Util/PrivacyLegalNotice.php', '\\Restatify\\Shared\\Util\\PrivacyLegalNotice')) {
    throw new RuntimeException('Missing required shared dependency: wp_restatify-shared/src/php/Util/PrivacyLegalNotice.php');
}

if (class_exists('\\Restatify\\Shared\\Contracts\\BookingChatTokens', false)) {
    \Restatify\Shared\Contracts\BookingChatTokens::defineGlobalConstants();
}

if (class_exists('Restatify_Booking_Assistant_Plugin', false)) {
    return;
}

$restatify_booking_legacy_basenames = [
    'wp_restatify-booking-assistant/wp_restatify-booking-assistant.php',
    'wp-restatify-booking-assistant/wp-restatify-booking-assistant.php',
];

$restatify_booking_skip_bootstrap_for_request = false;
if (class_exists('\\Restatify\\Shared\\Runtime\\BootstrapGuard', false)) {
    $restatify_booking_skip_bootstrap_for_request = \Restatify\Shared\Runtime\BootstrapGuard::deactivateLegacyAndMaybeNotify(
        $restatify_booking_legacy_basenames,
        'restatify_booking_assistant_admin_notice',
        'Legacy booking plugin wurde automatisch deaktiviert, um Klassenkonflikte mit Restatify Booking zu vermeiden.',
        'restatify-booking-assistant'
    );
}

if ($restatify_booking_skip_bootstrap_for_request) {
    return;
}

require_once __DIR__ . '/includes/class-restatify-booking-assistant-constants.php';

$restatify_booking_shared_component = 'migration_notice_manager';
$restatify_booking_shared_manager_class = null;

if (class_exists('\\Restatify\\Shared\\SharedRegistry', false)) {
    $registered_payload = \Restatify\Shared\SharedRegistry::get(
        $restatify_booking_shared_component,
        RESTATIFY_BOOKING_SHARED_VERSION
    );

    if (is_array($registered_payload)) {
        $registered_class = (string) ($registered_payload['class'] ?? '');
        if ($registered_class !== '' && class_exists($registered_class, false)) {
            $restatify_booking_shared_manager_class = $registered_class;
        }
    }

    if ($restatify_booking_shared_manager_class === null) {
        $restatify_booking_require_shared('src/php/Migration/MigrationNoticeManager.php', '\\Restatify\\Shared\\Migration\\MigrationNoticeManager');

        if (class_exists('\\Restatify\\Shared\\Migration\\MigrationNoticeManager', false)) {
            $restatify_booking_shared_manager_class = '\\Restatify\\Shared\\Migration\\MigrationNoticeManager';
            \Restatify\Shared\SharedRegistry::register(
                $restatify_booking_shared_component,
                RESTATIFY_BOOKING_SHARED_VERSION,
                [ 'class' => $restatify_booking_shared_manager_class ]
            );
        }
    }
}

if (
    is_string($restatify_booking_shared_manager_class)
    && $restatify_booking_shared_manager_class !== ''
    && class_exists($restatify_booking_shared_manager_class, false)
    && !class_exists('Restatify_Shared_Migration_Notice_Manager', false)
) {
    class_alias($restatify_booking_shared_manager_class, 'Restatify_Shared_Migration_Notice_Manager');
}

if (!class_exists('Restatify_Shared_Migration_Notice_Manager', false)) {
    final class Restatify_Shared_Migration_Notice_Manager {
        public static function register(array $config): void {
            unset($config);
        }
    }
}

require_once __DIR__ . '/includes/class-restatify-booking-assistant-options.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-api-client.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-autoresponder.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-cancellation-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-ui.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-booking-controller.php';
require_once __DIR__ . '/includes/class-restatify-booking-assistant-plugin.php';

if (!class_exists('Restatify_Booking_Assistant_Plugin', false)) {
    return;
}

new Restatify_Booking_Assistant_Plugin(__FILE__);

/**
 * Optional helper for AI/chat handover flows that should open the booking overlay.
 */
if (!function_exists('restatify_booking_ai_handle_message')) {
function restatify_booking_ai_handle_message(string $message): string {
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    $booking_terms = '/termin|appointment|slot|verfuegbar|verfugbarkeit|frei|buchen|book/i';
    if (!preg_match($booking_terms, $message)) {
        return '';
    }

    $options = get_option(Restatify_Booking_Assistant_Constants::OPTION_KEY, []);
    if (!is_array($options) || empty($options['api_base_url'])) {
        return __('Booking service is not configured yet.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
    }

    return RESTATIFY_BOOKING_OPEN_TOKEN . ' ' . __('I am opening the booking tool now. Please choose a free slot, enter your details, and confirm. I will then submit it to the calendar API.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN);
}
}
