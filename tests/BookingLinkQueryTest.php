<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingLinkQueryTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['restatify_booking_test_can_edit_posts'] = true;
    }

    public function testExtendWpLinkQueryAddsBookingEntry(): void {
        $ui = new Restatify_Booking_Assistant_UI(
            dirname(__DIR__) . '/wp_restatify-booking.php',
            new Restatify_Booking_Assistant_Options()
        );

        $results = $ui->extend_wp_link_query([], ['s' => 'booking']);

        self::assertCount(1, $results);
        self::assertSame('#restatify-booking', $results[0]['permalink']);
        self::assertSame(0, $results[0]['ID']);
    }

    public function testExtendWpLinkQuerySkipsWhenNoMatch(): void {
        $ui = new Restatify_Booking_Assistant_UI(
            dirname(__DIR__) . '/wp_restatify-booking.php',
            new Restatify_Booking_Assistant_Options()
        );

        $seed = [[
            'ID' => 123,
            'title' => 'Example',
            'permalink' => 'https://example.org',
            'info' => 'Seed',
        ]];

        $results = $ui->extend_wp_link_query($seed, ['s' => 'newsletter']);

        self::assertSame($seed, $results);
    }
}
