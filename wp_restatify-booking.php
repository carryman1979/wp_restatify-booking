<?php
/**
 * Plugin Name: Restatify Booking Assistant
 * Description: Manual slot search + reservation popup for WordPress, backed by Restatify Booking API.
 * Version: 2.0.4
 * Author: Restatify
 * License: GPL-2.0-or-later
 * Text Domain: restatify-booking-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('RESTATIFY_BOOKING_PLUGIN_DIR')) {
    define('RESTATIFY_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('RESTATIFY_BOOKING_PLUGIN_FILE')) {
    define('RESTATIFY_BOOKING_PLUGIN_FILE', __FILE__);
}

if (!defined('RESTATIFY_BOOKING_SHARED_VERSION')) {
    define('RESTATIFY_BOOKING_SHARED_VERSION', '1.0.2');
}

require_once RESTATIFY_BOOKING_PLUGIN_DIR . 'includes/class-restatify-booking-shared-library.php';

$restatify_booking_shared_root = restatify_booking_shared_bootstrap();

$restatify_booking_require_all = static function (string $shared_root, array $relative_paths): bool {
    foreach ($relative_paths as $relative_path) {
        $full_path = $shared_root . '/src/php/' . ltrim((string) $relative_path, '/');
        if (!file_exists($full_path)) {
            return false;
        }

        require_once $full_path;
    }

    return true;
};

if (!$restatify_booking_require_all($restatify_booking_shared_root, [
    'SharedRegistry.php',
    'Contracts/BookingChatTokens.php',
    'Contracts/BookingPrefillSchema.php',
    'Contracts/BookingApiErrorCodes.php',
    'Runtime/PluginState.php',
    'Runtime/BootstrapGuard.php',
    'Runtime/RateLimiter.php',
    'Util/BookingContactMethodsResolver.php',
    'Util/BookingContactChannelProfiles.php',
    'Util/BookingContactChannels.php',
    'Util/TokenReplacer.php',
    'Mail/MailDispatcher.php',
    'Mail/PlaceholderCatalog.php',
    'I18n/PolylangAdapter.php',
    'Util/PrivacyLegalNotice.php',
])) {
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
        $restatify_booking_require_all($restatify_booking_shared_root, [
            'Migration/MigrationNoticeManager.php',
        ]);

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
