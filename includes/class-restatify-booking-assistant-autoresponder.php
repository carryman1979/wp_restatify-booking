<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends booking confirmation emails and builds ICS calendar attachments.
 */
final class Restatify_Booking_Assistant_Autoresponder {
    private Restatify_Booking_Assistant_Options $options;

    public function __construct(Restatify_Booking_Assistant_Options $options) {
        $this->options = $options;
    }

    /**
     * Sends confirmation email with placeholder expansion and optional ICS attachment.
     *
     * @param array<string,mixed> $reservation
     */
    public function send_confirmation(
        array $reservation,
        string $name,
        string $email,
        string $subject_line,
        string $note,
        string $contact_method,
        string $contact_value,
        string $contact_detail,
        string $cancellation_url
    ): void {
        $options = $this->options->get_options();
        $timezone = (string) $options['default_timezone'];

        $start_iso = $this->format_iso_for_mail((string) ($reservation['start_iso'] ?? ''), $timezone);
        $end_iso = $this->format_iso_for_mail((string) ($reservation['end_iso'] ?? ''), $timezone);

        $replacements = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject_line,
            'start' => $start_iso,
            'end' => $end_iso,
            'timezone' => $timezone,
            'note' => $note,
            'reference' => (string) ($reservation['reference'] ?? ''),
            'contact_method' => $contact_method,
            'contact_value' => $contact_value,
            'contact_detail' => $contact_detail,
            'cancellation_url' => $cancellation_url,
            'site_name' => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            'cancellation_reason' => '',
            'cancellation_message' => '',
        ];

        $attachment = $this->build_ics_attachment($reservation, $name, $email, $subject_line, $timezone, $note, $contact_method, $contact_detail);
        $attachments = [];
        if ($attachment !== '') {
            $attachments[] = $attachment;
        }

        if (!empty($options['autoresponder_enabled'])) {
            $this->send_configured_mail(
                [$email],
                $this->render_template((string) $options['autoresponder_subject'], $replacements),
                $this->render_template((string) $options['autoresponder_body'], $replacements),
                $this->render_template((string) ($options['autoresponder_html_body'] ?? ''), $replacements),
                !empty($options['autoresponder_html_enabled']),
                $attachments
            );
        }

        if (!empty($options['owner_notification_enabled'])) {
            $owner_recipients = $this->parse_recipients((string) ($options['owner_notification_recipients'] ?? ''));
            if (count($owner_recipients) > 0) {
                $this->send_configured_mail(
                    $owner_recipients,
                    $this->render_template((string) ($options['owner_notification_subject'] ?? ''), $replacements),
                    $this->render_template((string) ($options['owner_notification_body'] ?? ''), $replacements),
                    $this->render_template((string) ($options['owner_notification_html_body'] ?? ''), $replacements),
                    !empty($options['owner_notification_html_enabled']),
                    []
                );
            }
        }

        if ($attachment !== '' && file_exists($attachment)) {
            wp_delete_file($attachment);
        }
    }

    /**
     * Sends cancellation confirmation email to the subscriber.
     *
     * @param array<string,mixed> $reservation
     */
    public function send_cancellation_confirmation(array $reservation, string $reason, string $message): void {
        $options = $this->options->get_options();
        if (empty($options['cancellation_confirmation_enabled'])) {
            return;
        }

        $email = sanitize_email((string) ($reservation['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return;
        }

        $timezone = (string) ($reservation['timezone'] ?? ($options['default_timezone'] ?? 'Europe/Berlin'));
        $replacements = [
            'name' => (string) ($reservation['name'] ?? ''),
            'email' => $email,
            'subject' => '',
            'start' => $this->format_iso_for_mail((string) ($reservation['start_iso'] ?? ''), $timezone),
            'end' => $this->format_iso_for_mail((string) ($reservation['end_iso'] ?? ''), $timezone),
            'timezone' => $timezone,
            'note' => '',
            'reference' => (string) ($reservation['reference'] ?? ''),
            'contact_method' => '',
            'contact_value' => '',
            'contact_detail' => '',
            'cancellation_url' => '',
            'cancellation_reason' => $reason,
            'cancellation_message' => $message,
            'site_name' => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ];

        $this->send_configured_mail(
            [$email],
            $this->render_template((string) ($options['cancellation_confirmation_subject'] ?? ''), $replacements),
            $this->render_template((string) ($options['cancellation_confirmation_body'] ?? ''), $replacements),
            $this->render_template((string) ($options['cancellation_confirmation_html_body'] ?? ''), $replacements),
            !empty($options['cancellation_confirmation_html_enabled']),
            []
        );

        $this->send_owner_cancellation_notification($reservation, $reason, $message);
    }

    /**
     * @param array<string,mixed> $reservation
     */
    public function send_owner_cancellation_notification(array $reservation, string $reason, string $message): void {
        $options = $this->options->get_options();
        if (empty($options['owner_cancellation_enabled'])) {
            return;
        }

        $owner_recipients = $this->parse_recipients((string) ($options['owner_notification_recipients'] ?? ''));
        if (count($owner_recipients) === 0) {
            return;
        }

        $timezone = (string) ($reservation['timezone'] ?? ($options['default_timezone'] ?? 'Europe/Berlin'));
        $email = sanitize_email((string) ($reservation['email'] ?? ''));
        $replacements = [
            'name' => (string) ($reservation['name'] ?? ''),
            'email' => $email,
            'subject' => '',
            'start' => $this->format_iso_for_mail((string) ($reservation['start_iso'] ?? ''), $timezone),
            'end' => $this->format_iso_for_mail((string) ($reservation['end_iso'] ?? ''), $timezone),
            'timezone' => $timezone,
            'note' => '',
            'reference' => (string) ($reservation['reference'] ?? ''),
            'contact_method' => '',
            'contact_value' => '',
            'contact_detail' => '',
            'cancellation_url' => '',
            'cancellation_reason' => $reason,
            'cancellation_message' => $message,
            'site_name' => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ];

        $this->send_configured_mail(
            $owner_recipients,
            $this->render_template((string) ($options['owner_cancellation_subject'] ?? ''), $replacements),
            $this->render_template((string) ($options['owner_cancellation_body'] ?? ''), $replacements),
            $this->render_template((string) ($options['owner_cancellation_html_body'] ?? ''), $replacements),
            !empty($options['owner_cancellation_html_enabled']),
            []
        );
    }

    /**
     * @param array<int,string> $recipients
     * @param array<int,string> $attachments
     */
    private function send_configured_mail(array $recipients, string $subject, string $text_body, string $html_body, bool $html_enabled, array $attachments = []): void {
        if (count($recipients) === 0) {
            return;
        }

        $subject = trim($subject);
        $text_body = trim($text_body);
        $html_body = trim($html_body);

        if ($subject === '' || ($text_body === '' && $html_body === '')) {
            return;
        }

        if (class_exists('\\Restatify\\Shared\\Mail\\MailDispatcher', false)) {
            \Restatify\Shared\Mail\MailDispatcher::send(
                $recipients,
                $subject,
                $html_body,
                $text_body,
                $html_enabled,
                [],
                $attachments,
                $this->get_mail_from_address(),
                wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
            );

            return;
        }

        $mail_from = $this->get_mail_from_address();
        $mail_from_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $from_callback = static function () use ($mail_from): string {
            return $mail_from;
        };
        $from_name_callback = static function () use ($mail_from_name): string {
            return $mail_from_name;
        };

        add_filter('wp_mail_from', $from_callback);
        add_filter('wp_mail_from_name', $from_name_callback);

        if (!$html_enabled || $html_body === '') {
            wp_mail($recipients, $subject, $text_body, ['Content-Type: text/plain; charset=UTF-8'], $attachments);
            remove_filter('wp_mail_from', $from_callback);
            remove_filter('wp_mail_from_name', $from_name_callback);
            return;
        }

        $callback = static function ($phpmailer) use ($html_body, $text_body): void {
            $phpmailer->isHTML(true);
            $phpmailer->Body = $html_body;
            $phpmailer->AltBody = $text_body;
        };

        add_action('phpmailer_init', $callback);
        wp_mail($recipients, $subject, $html_body, [], $attachments);
        remove_action('phpmailer_init', $callback);
        remove_filter('wp_mail_from', $from_callback);
        remove_filter('wp_mail_from_name', $from_name_callback);
    }

    /**
     * @param array<string,string> $replacements
     */
    private function render_template(string $template, array $replacements): string {
        if (class_exists('\\Restatify\\Shared\\Util\\TokenReplacer', false)) {
            return \Restatify\Shared\Util\TokenReplacer::replace($template, $replacements);
        }

        $search = [];
        $replace = [];
        foreach ($replacements as $token => $value) {
            $search[] = '{' . $token . '}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }

    private function get_mail_from_address(): string {
        $admin_email = sanitize_email((string) get_option('admin_email', ''));
        if ($admin_email !== '' && is_email($admin_email)) {
            return $admin_email;
        }

        return 'wordpress@example.test';
    }

    private function format_iso_for_mail(string $iso_value, string $timezone_name): string {
        if ($iso_value === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($iso_value);
            $timezone = new DateTimeZone($timezone_name !== '' ? $timezone_name : 'Europe/Berlin');
            $localized = $date->setTimezone($timezone);
            return wp_date('d.m.Y H:i', $localized->getTimestamp(), $timezone);
        } catch (Exception $exception) {
            return $iso_value;
        }
    }

    /**
     * @return array<int,string>
     */
    private function parse_recipients(string $raw): array {
        $lines = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $recipients = [];
        foreach ($lines as $line) {
            $email = sanitize_email(trim((string) $line));
            if ($email === '' || !is_email($email)) {
                continue;
            }
            $recipients[] = $email;
        }

        return array_values(array_unique($recipients));
    }

    /**
     * @param array<string,mixed> $reservation
     */
    private function build_ics_attachment(
        array $reservation,
        string $name,
        string $email,
        string $subject_line,
        string $timezone,
        string $note,
        string $contact_method,
        string $contact_detail
    ): string {
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
        $summary = trim($subject_line) !== '' ? ('Restatify: ' . $subject_line) : 'Restatify Gespraech';
        $description = "Name: {$name}\\nEmail: {$email}\\nKontaktkanal: {$contact_method}\\nKontakt: {$contact_detail}\\nNotiz: {$note}";

        $ics = '';
        $ics .= "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Restatify//Booking Assistant//DE\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= $this->fold_ics_line('UID:' . sanitize_text_field($uid));
        $ics .= $this->fold_ics_line('DTSTAMP:' . gmdate('Ymd\\THis\\Z'));
        $ics .= $this->fold_ics_line('DTSTART:' . gmdate('Ymd\\THis\\Z', $start->getTimestamp()));
        $ics .= $this->fold_ics_line('DTEND:' . gmdate('Ymd\\THis\\Z', $end->getTimestamp()));
        $ics .= $this->fold_ics_line('SUMMARY:' . $this->escape_ics_text($summary));
        $ics .= $this->fold_ics_line('DESCRIPTION:' . $this->escape_ics_text($description));
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        $tmp = wp_tempnam('restatify-booking');
        if (!$tmp) {
            return '';
        }

        $target = $tmp . '.ics';
        if (file_exists($target)) {
            wp_delete_file($target);
        }

        if (@rename($tmp, $target)) {
            $tmp = $target;
        }

        $written = file_put_contents($tmp, $ics);
        if ($written === false) {
            if (file_exists($tmp)) {
                wp_delete_file($tmp);
            }
            return '';
        }

        return $tmp;
    }

    private function fold_ics_line(string $line): string {
        $max_bytes = 73;
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return "\r\n";
        }

        $parts = [];
        $remaining = $line;
        while ($remaining !== '') {
            if (strlen($remaining) <= $max_bytes) {
                $parts[] = $remaining;
                break;
            }

            $chunk = substr($remaining, 0, $max_bytes);
            while (function_exists('mb_check_encoding') && !mb_check_encoding($chunk, 'UTF-8') && strlen($chunk) > 1) {
                $chunk = substr($chunk, 0, -1);
            }

            if ($chunk === '') {
                $chunk = substr($remaining, 0, 1);
            }

            $parts[] = $chunk;
            $remaining = substr($remaining, strlen($chunk));
        }

        return implode("\r\n ", $parts) . "\r\n";
    }

    private function escape_ics_text(string $value): string {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }
}
