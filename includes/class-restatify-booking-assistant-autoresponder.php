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
        string $contact_detail
    ): void {
        $options = $this->options->get_options();
        $subject = (string) $options['autoresponder_subject'];
        $template = (string) $options['autoresponder_body'];
        $timezone = (string) $options['default_timezone'];

        $start_iso = (string) ($reservation['start_iso'] ?? '');
        $end_iso = (string) ($reservation['end_iso'] ?? '');

        $search = ['{name}', '{email}', '{subject}', '{start}', '{end}', '{timezone}', '{note}', '{reference}', '{contact_method}', '{contact_value}', '{contact_detail}'];
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
        ];

        $body = str_replace($search, $replace, $template);

        $attachment = $this->build_ics_attachment($reservation, $name, $email, $subject_line, $timezone, $note, $contact_method, $contact_detail);
        $attachments = [];
        if ($attachment !== '') {
            $attachments[] = $attachment;
        }

        wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8'], $attachments);

        if ($attachment !== '' && file_exists($attachment)) {
            wp_delete_file($attachment);
        }
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
