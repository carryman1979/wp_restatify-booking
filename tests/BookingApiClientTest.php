<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingApiClientTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['restatify_booking_test_options'] = [
            'api_base_url' => 'https://api.example.test',
            'api_key' => 'secret-key',
        ];
        $GLOBALS['restatify_booking_test_http_response'] = [
            'response' => ['code' => 200],
            'body' => '{}',
        ];
    }

    public function testRequestReturnsMappedErrorMessageForKnownCode(): void {
        $GLOBALS['restatify_booking_test_http_response'] = [
            'response' => ['code' => 422],
            'body' => json_encode([
                'detail' => [
                    'code' => 'SLOT_UNAVAILABLE',
                ],
            ]),
        ];

        $client = new Restatify_Booking_Assistant_Api_Client(new Restatify_Booking_Assistant_Options());
        $result = $client->request('/v1/slots', [], 'POST');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('restatify_booking_api_error', $result->get_error_code());
        self::assertSame('Slot is no longer available', $result->get_error_message());
        self::assertSame(['status' => 422], $result->get_error_data());
    }

    public function testRequestFlattensStructuredValidationDetail(): void {
        $GLOBALS['restatify_booking_test_http_response'] = [
            'response' => ['code' => 400],
            'body' => json_encode([
                'detail' => [
                    [
                        'loc' => ['body', 'start_iso'],
                        'msg' => 'must include timezone',
                    ],
                    [
                        'loc' => ['body', 'email'],
                        'message' => 'is invalid',
                    ],
                ],
            ]),
        ];

        $client = new Restatify_Booking_Assistant_Api_Client(new Restatify_Booking_Assistant_Options());
        $result = $client->request('/v1/slots', [], 'POST');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame(
            'body -> start_iso: must include timezone | body -> email: is invalid',
            $result->get_error_message()
        );
    }

    public function testRequestReturnsDecodedBodyOnSuccess(): void {
        $GLOBALS['restatify_booking_test_http_response'] = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'ok' => true,
                'data' => ['id' => 55],
            ]),
        ];

        $client = new Restatify_Booking_Assistant_Api_Client(new Restatify_Booking_Assistant_Options());
        $result = $client->request('/v1/slots', [], 'GET');

        self::assertIsArray($result);
        self::assertSame(true, $result['ok']);
        self::assertSame(55, $result['data']['id']);
    }
}
