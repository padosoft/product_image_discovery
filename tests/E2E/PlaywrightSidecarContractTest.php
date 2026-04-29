<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use PHPUnit\Framework\TestCase;

final class PlaywrightSidecarContractTest extends TestCase
{
    public function testSuccessPayloadShapeContract(): void
    {
        $payload = [
            'ok' => true,
            'final_url' => 'https://example.test/product/1',
            'html' => '<html><body>ok</body></html>',
            'images' => [
                [
                    'url' => 'https://example.test/a.jpg',
                    'width' => 1200,
                    'height' => 1600,
                    'alt' => 'hero',
                ],
            ],
            'error' => null,
        ];

        $this->assertRenderResponseShape($payload);
        self::assertTrue($payload['ok']);
        self::assertIsString($payload['final_url']);
        self::assertIsString($payload['html']);
        self::assertNull($payload['error']);
    }

    public function testErrorPayloadShapeContract(): void
    {
        $payload = [
            'ok' => false,
            'final_url' => null,
            'html' => null,
            'images' => [],
            'error' => [
                'code' => 'TIMEOUT',
                'message' => 'Render timeout after 1000 ms',
            ],
        ];

        $this->assertRenderResponseShape($payload);
        self::assertFalse($payload['ok']);
        self::assertNull($payload['final_url']);
        self::assertNull($payload['html']);
        self::assertIsArray($payload['error']);
        self::assertArrayHasKey('code', $payload['error']);
        self::assertArrayHasKey('message', $payload['error']);
    }

    public function testLiveSidecarRenderContractIsSkippable(): void
    {
        $sidecarBaseUrl = getenv('SIDECAR_E2E_URL');
        if (!is_string($sidecarBaseUrl) || trim($sidecarBaseUrl) === '') {
            self::markTestSkipped('Set SIDECAR_E2E_URL to run the live sidecar contract test.');
        }

        $fixtureUrl = getenv('SIDECAR_E2E_FIXTURE_URL');
        if (!is_string($fixtureUrl) || trim($fixtureUrl) === '') {
            self::markTestSkipped('Set SIDECAR_E2E_FIXTURE_URL to a reachable product-like URL for live contract test.');
        }

        $response = $this->httpPostJson(rtrim($sidecarBaseUrl, '/') . '/render', [
            'url' => $fixtureUrl,
            'timeout_ms' => 5000,
            'extract' => [
                'html' => true,
                'images' => true,
            ],
        ]);

        $this->assertRenderResponseShape($response);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertRenderResponseShape(array $payload): void
    {
        self::assertArrayHasKey('ok', $payload);
        self::assertArrayHasKey('final_url', $payload);
        self::assertArrayHasKey('html', $payload);
        self::assertArrayHasKey('images', $payload);
        self::assertArrayHasKey('error', $payload);

        self::assertIsBool($payload['ok']);
        self::assertTrue(is_string($payload['final_url']) || $payload['final_url'] === null);
        self::assertTrue(is_string($payload['html']) || $payload['html'] === null);
        self::assertIsArray($payload['images']);

        foreach ($payload['images'] as $image) {
            self::assertIsArray($image);
            self::assertArrayHasKey('url', $image);
            self::assertArrayHasKey('width', $image);
            self::assertArrayHasKey('height', $image);
            self::assertArrayHasKey('alt', $image);
            self::assertIsString($image['url']);
            self::assertTrue(is_int($image['width']) || $image['width'] === null);
            self::assertTrue(is_int($image['height']) || $image['height'] === null);
            self::assertTrue(is_string($image['alt']) || $image['alt'] === null);
        }

        if ($payload['error'] !== null) {
            self::assertIsArray($payload['error']);
            self::assertArrayHasKey('code', $payload['error']);
            self::assertArrayHasKey('message', $payload['error']);
            self::assertIsString($payload['error']['code']);
            self::assertIsString($payload['error']['message']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function httpPostJson(string $url, array $payload): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'timeout' => 8,
            ],
        ]);

        $raw = file_get_contents($url, false, $context);
        self::assertNotFalse($raw, 'Failed to call sidecar render endpoint.');

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded, 'Sidecar response is not valid JSON object.');

        return $decoded;
    }
}
