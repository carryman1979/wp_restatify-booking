<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingAutoresponderIcsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['restatify_booking_test_sent_mails'] = [];
        $GLOBALS['restatify_booking_test_options'] = [
            'default_timezone' => 'Europe/Berlin',
            'autoresponder_enabled' => true,
            'autoresponder_subject' => 'Termin bestaetigt: {subject}',
            'autoresponder_body' => "Hallo {name}, dein Termin ist bestaetigt.",
            'autoresponder_html_enabled' => false,
            'autoresponder_html_body' => '',
            'owner_notification_enabled' => false,
        ];
    }

    public function testSendConfirmationAttachesIcsWithStableExtensionAndCalendarPayload(): void {
        $autoresponder = new Restatify_Booking_Assistant_Autoresponder(new Restatify_Booking_Assistant_Options());

        $reservation = [
            'reference' => 'ref-12345',
            'start_iso' => '2026-07-01T10:00:00+02:00',
            'end_iso' => '2026-07-01T10:30:00+02:00',
        ];

        $autoresponder->send_confirmation(
            $reservation,
            'Max Mustermann',
            'max@example.test',
            'Strategie Call',
            'Bitte 10 Minuten vorher erinnern',
            'Telefon',
            '+49 151 123456',
            'Telefon: +49 151 123456',
            'https://example.test/cancel?ref=ref-12345'
        );

        $sent = $GLOBALS['restatify_booking_test_sent_mails'] ?? [];
        self::assertCount(1, $sent);

        $mail = $sent[0];
        $attachments = $mail['attachments'] ?? [];
        self::assertCount(1, $attachments);

        $attachmentPath = (string) $attachments[0];
        self::assertStringEndsWith('.ics', $attachmentPath, 'Calendar attachment should have .ics extension, not .tmp.');

        $contentMap = is_array($mail['attachment_contents'] ?? null) ? $mail['attachment_contents'] : [];
        self::assertArrayHasKey($attachmentPath, $contentMap, 'Expected ICS content to be captured by wp_mail stub.');

        $ics = (string) $contentMap[$attachmentPath];
        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("VERSION:2.0\r\n", $ics);
        self::assertStringContainsString("METHOD:PUBLISH\r\n", $ics);
        self::assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        self::assertStringContainsString("UID:ref-12345\r\n", $ics);
        self::assertStringContainsString("DTSTART:20260701T080000Z\r\n", $ics);
        self::assertStringContainsString("DTEND:20260701T083000Z\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Restatify: Strategie Call\r\n", $ics);
        self::assertStringContainsString("DESCRIPTION:", $ics);
        self::assertStringContainsString("END:VEVENT\r\n", $ics);
        self::assertStringContainsString("END:VCALENDAR\r\n", $ics);
    }
}
