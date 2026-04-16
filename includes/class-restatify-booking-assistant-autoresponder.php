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

        $search = ['{name}', '{email}', '{subject}', '{start}', '{end}', '{timezone}', '{note}', '{reference}', '{contact_method}', '{contact_value}', '{contact_detail}', '{cancellation_url}', '{site_name}', '{cancellation_reason}', '{cancellation_message}'];
        $replace = [
            $name,
            $email,
            $subject_line,
            $start_iso,
            $end_iso,
            $timezone,
            $note,
            (string) ($reservation['reference'] ?? ''),
            $contact_method,
            $contact_value,
            $contact_detail,
            $cancellation_url,
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            '',
            '',
        ];

        $attachment = $this->build_ics_attachment($reservation, $name, $email, $subject_line, $timezone, $note, $contact_method, $contact_detail);
        $attachments = [];
        if ($attachment !== '') {
            $attachments[] = $attachment;
        }

        if (!empty($options['autoresponder_enabled'])) {
            $this->send_configured_mail(
                [$email],
                str_replace($search, $replace, (string) $options['autoresponder_subject']),
                str_replace($search, $replace, (string) $options['autoresponder_body']),
                str_replace($search, $replace, (string) ($options['autoresponder_html_body'] ?? '')),
                !empty($options['autoresponder_html_enabled']),
                $attachments
            );
        }

        if (!empty($options['owner_notification_enabled'])) {
            $owner_recipients = $this->parse_recipients((string) ($options['owner_notification_recipients'] ?? ''));
            if (count($owner_recipients) > 0) {
                $this->send_configured_mail(
                    $owner_recipients,
                    str_replace($search, $replace, (string) ($options['owner_notification_subject'] ?? '')),
                    str_replace($search, $replace, (string) ($options['owner_notification_body'] ?? '')),
                    str_replace($search, $replace, (string) ($options['owner_notification_html_body'] ?? '')),
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
        $search = ['{name}', '{email}', '{subject}', '{start}', '{end}', '{timezone}', '{note}', '{reference}', '{contact_method}', '{contact_value}', '{contact_detail}', '{cancellation_url}', '{cancellation_reason}', '{cancellation_message}', '{site_name}'];
        $replace = [
            (string) ($reservation['name'] ?? ''),
            $email,
            '',
            $this->format_iso_for_mail((string) ($reservation['start_iso'] ?? ''), $timezone),
            $this->format_iso_for_mail((string) ($reservation['end_iso'] ?? ''), $timezone),
            $timezone,
            '',
            (string) ($reservation['reference'] ?? ''),
            '',
            '',
            '',
            '',
            $reason,
            $message,
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ];

        $this->send_configured_mail(
            [$email],
            str_replace($search, $replace, (string) ($options['cancellation_confirmation_subject'] ?? '')),
            str_replace($search, $replace, (string) ($options['cancellation_confirmation_body'] ?? '')),
            str_replace($search, $replace, (string) ($options['cancellation_confirmation_html_body'] ?? '')),
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
        $search = ['{name}', '{email}', '{subject}', '{start}', '{end}', '{timezone}', '{note}', '{reference}', '{contact_method}', '{contact_value}', '{contact_detail}', '{cancellation_url}', '{cancellation_reason}', '{cancellation_message}', '{site_name}'];
        $replace = [
            (string) ($reservation['name'] ?? ''),
            $email,
            '',
            $this->format_iso_for_mail((string) ($reservation['start_iso'] ?? ''), $timezone),
            $this->format_iso_for_mail((string) ($reservation['end_iso'] ?? ''), $timezone),
            $timezone,
            '',
            (string) ($reservation['reference'] ?? ''),
            '',
            '',
            '',
            '',
            $reason,
            $message,
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ];

        $this->send_configured_mail(
            $owner_recipients,
            str_replace($search, $replace, (string) ($options['owner_cancellation_subject'] ?? '')),
            str_replace($search, $replace, (string) ($options['owner_cancellation_body'] ?? '')),
            str_replace($search, $replace, (string) ($options['owner_cancellation_html_body'] ?? '')),
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
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }
}
