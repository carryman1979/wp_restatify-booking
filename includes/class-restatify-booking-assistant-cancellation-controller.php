<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders and processes the public cancellation page.
 */
final class Restatify_Booking_Assistant_Cancellation_Controller {
    private Restatify_Booking_Assistant_Api_Client $api_client;
    private Restatify_Booking_Assistant_Autoresponder $autoresponder;

    public function __construct(Restatify_Booking_Assistant_Api_Client $api_client, Restatify_Booking_Assistant_Autoresponder $autoresponder) {
        $this->api_client = $api_client;
        $this->autoresponder = $autoresponder;
    }

    public function maybe_render_page(): void {
        $token = isset($_GET[Restatify_Booking_Assistant_Constants::CANCEL_QUERY_ARG])
            ? sanitize_text_field((string) wp_unslash($_GET[Restatify_Booking_Assistant_Constants::CANCEL_QUERY_ARG]))
            : '';

        if ($token === '') {
            return;
        }

        $state = [
            'error' => '',
            'success' => '',
            'reason' => '',
            'message' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $state = $this->handle_submission($token);
        }

        $this->render_page($token, $state);
        exit;
    }

    /**
     * @return array{error:string,success:string,reason:string,message:string}
     */
    private function handle_submission(string $token): array {
        $nonce = isset($_POST['restatify_booking_cancel_nonce'])
            ? sanitize_text_field((string) wp_unslash($_POST['restatify_booking_cancel_nonce']))
            : '';
        if (!wp_verify_nonce($nonce, Restatify_Booking_Assistant_Constants::CANCEL_NONCE_ACTION)) {
            return [
                'error' => __('Ungültige Anfrage. Bitte versuche es erneut.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'success' => '',
                'reason' => '',
                'message' => '',
            ];
        }

        $reason = isset($_POST['cancel_reason']) ? sanitize_text_field((string) wp_unslash($_POST['cancel_reason'])) : '';
        $message = isset($_POST['cancel_message']) ? sanitize_textarea_field((string) wp_unslash($_POST['cancel_message'])) : '';
        $captcha_answer = isset($_POST['cancel_captcha_answer']) ? sanitize_text_field((string) wp_unslash($_POST['cancel_captcha_answer'])) : '';

        [$a, $b] = $this->get_captcha_numbers($token);
        if ((string) ($a + $b) !== trim($captcha_answer)) {
            return [
                'error' => __('Bitte löse die Rechenaufgabe korrekt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
                'success' => '',
                'reason' => $reason,
                'message' => $message,
            ];
        }

        $response = $this->api_client->request('/v1/reservations/cancel', [
            'cancel_token' => $token,
            'reason' => $reason,
            'message' => $message,
        ], 'POST');

        if (is_wp_error($response)) {
            return [
                'error' => $response->get_error_message(),
                'success' => '',
                'reason' => $reason,
                'message' => $message,
            ];
        }

        if (empty($response['already_cancelled'])) {
            $this->autoresponder->send_cancellation_confirmation($response, $reason, $message);
        }

        return [
            'error' => '',
            'success' => __('Der Termin wurde erfolgreich storniert.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN),
            'reason' => $reason,
            'message' => $message,
        ];
    }

    /**
     * @param array{error:string,success:string,reason:string,message:string} $state
     */
    private function render_page(string $token, array $state): void {
        nocache_headers();
        status_header(200);

        [$a, $b] = $this->get_captcha_numbers($token);
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Termin stornieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></title>
            <?php wp_head(); ?>
            <style>
                body.restatify-booking-cancel-body { background:#f6f7fb; color:#142033; font-family:system-ui,sans-serif; }
                .restatify-booking-cancel-wrap { max-width:760px; margin:48px auto; padding:24px; }
                .restatify-booking-cancel-card { background:#fff; border:1px solid #d9e0ea; border-radius:18px; box-shadow:0 18px 50px rgba(20,32,51,.08); padding:28px; }
                .restatify-booking-cancel-form { display:grid; gap:16px; }
                .restatify-booking-cancel-form label { display:grid; gap:6px; }
                .restatify-booking-cancel-form input, .restatify-booking-cancel-form textarea { width:100%; box-sizing:border-box; padding:12px 14px; border:1px solid #c8d2df; border-radius:12px; }
                .restatify-booking-cancel-form textarea { min-height:150px; resize:vertical; }
                .restatify-booking-cancel-submit { border:0; border-radius:999px; background:#ff6b00; color:#fff; padding:12px 18px; width:fit-content; cursor:pointer; }
                .restatify-booking-cancel-error { color:#b42318; font-weight:600; }
                .restatify-booking-cancel-success { color:#166534; font-weight:600; }
            </style>
        </head>
        <body <?php body_class('restatify-booking-cancel-body'); ?>>
            <?php wp_body_open(); ?>
            <main class="restatify-booking-cancel-wrap">
                <div class="restatify-booking-cancel-card">
                    <h1><?php esc_html_e('Termin stornieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></h1>
                    <p><?php esc_html_e('Hier kannst du deinen Termin stornieren und optional eine kurze Nachricht hinterlassen.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>

                    <?php if ($state['error'] !== '') : ?>
                        <p class="restatify-booking-cancel-error"><?php echo esc_html($state['error']); ?></p>
                    <?php endif; ?>

                    <?php if ($state['success'] !== '') : ?>
                        <p class="restatify-booking-cancel-success"><?php echo esc_html($state['success']); ?></p>
                    <?php else : ?>
                        <form class="restatify-booking-cancel-form" method="post">
                            <?php wp_nonce_field(Restatify_Booking_Assistant_Constants::CANCEL_NONCE_ACTION, 'restatify_booking_cancel_nonce'); ?>
                            <label>
                                <span><?php esc_html_e('Grund der Stornierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                <input type="text" name="cancel_reason" maxlength="120" value="<?php echo esc_attr($state['reason']); ?>" placeholder="<?php esc_attr_e('Optional', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Nachricht', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                                <textarea name="cancel_message" maxlength="1000" placeholder="<?php esc_attr_e('Optionaler Hinweis zur Stornierung', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>"><?php echo esc_textarea($state['message']); ?></textarea>
                            </label>
                            <label>
                                <span><?php echo esc_html(sprintf(__('Rechenaufgabe: %1$d + %2$d = ?', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN), $a, $b)); ?></span>
                                <input type="text" name="cancel_captcha_answer" inputmode="numeric" required>
                            </label>
                            <button class="restatify-booking-cancel-submit" type="submit"><?php esc_html_e('Termin verbindlich stornieren', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * @return array{0:int,1:int}
     */
    private function get_captcha_numbers(string $token): array {
        $hash = md5($token);
        $a = (hexdec(substr($hash, 0, 2)) % 9) + 1;
        $b = (hexdec(substr($hash, 2, 2)) % 9) + 1;
        return [$a, $b];
    }
}