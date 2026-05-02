<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveProductImageAiVerifierTest extends TestCase
{
    public function testLiveAiProviderReturnsStructuredProductImageVerification(): void
    {
        $provider = $this->resolveProvider();

        if ($provider === null) {
            self::markTestSkipped('Set REGOLO_API_KEY, ANTHROPIC_API_KEY, OPENROUTER_API_KEY, or OPENAI_API_KEY in .env to run the live AI verifier test.');
        }

        [$providerName, $apiKey] = $provider;
        $model = $this->envValue('PRODUCT_IMAGE_DISCOVERY_AI_VISION_MODEL')
            ?: $this->envValue('PRODUCT_IMAGE_DISCOVERY_AI_DESCRIPTION_MODEL');
        $attachRemoteImage = $this->envBool('PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE');

        config()->set('product-image-discovery.ai.enabled', true);
        config()->set('product-image-discovery.ai.provider', $providerName);
        config()->set('product-image-discovery.ai.fail_silently', false);
        config()->set('product-image-discovery.ai.attach_remote_image', $attachRemoteImage);
        config()->set("product-image-discovery.ai.providers.{$providerName}.api_key", $apiKey);
        config()->set('product-image-discovery.ai.vision_model', $model);
        config()->set('ai.default', $providerName);
        config()->set("ai.providers.{$providerName}.key", $apiKey);

        if ($providerName === 'regolo') {
            config()->set('ai.providers.regolo.url', $this->envValue('REGOLO_URL') ?? $this->envValue('REGOLO_BASE_URL') ?? 'https://api.regolo.ai/v1');
        }

        if ($providerName === 'openrouter') {
            config()->set('ai.providers.openrouter.url', $this->envValue('OPENROUTER_URL') ?? 'https://openrouter.ai/api/v1');
        }

        if ($providerName === 'anthropic') {
            config()->set('ai.providers.anthropic.url', $this->envValue('ANTHROPIC_URL') ?? 'https://api.anthropic.com/v1');
        }

        if ($providerName === 'openai') {
            config()->set('ai.providers.openai.url', $this->envValue('OPENAI_URL') ?? 'https://api.openai.com/v1');
        }

        $result = (new ProductImageAiVerifier())->verify([
            'brand' => 'Nike',
            'model_code' => 'Air Force 1 07',
            'color_name' => 'White',
            'color_code' => 'CW2288-111',
            'category' => 'Sneakers',
            'material' => 'Leather',
        ], [
            'title' => "Nike Air Force 1 '07 Men's Shoes White",
            'source_page_url' => 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr',
            'image_url' => 'https://static.nike.com/a/images/t_web_pdp_535_v2/f_auto%2Cu_9ddf04c7-2a9a-4d76-add1-d15af8f0263d%2Cc_scale%2Cfl_relative%2Cw_1.0%2Ch_1.0%2Cfl_layer_apply/b7d9211c-26e7-431a-ac24-b0540fb3c00f/AIR%2BFORCE%2B1%2B%2707.png',
            'source_domain' => 'nike.com',
            'width' => 1200,
            'height' => 1200,
            'mime_type' => 'image/jpeg',
        ]);

        self::assertTrue($result->available);
        self::assertSame('completed', $result->status);
        self::assertSame($providerName, $result->provider);
        self::assertSame($attachRemoteImage, (bool) config('product-image-discovery.ai.attach_remote_image'));
        self::assertGreaterThanOrEqual(0, $result->confidence);
        self::assertLessThanOrEqual(100, $result->confidence);
        self::assertNotSame('', $result->notes);
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function resolveProvider(): ?array
    {
        $preferred = strtolower((string) ($this->envValue('PRODUCT_IMAGE_DISCOVERY_AI_PROVIDER') ?? ''));
        $candidates = $preferred !== '' ? [$preferred] : ['regolo', 'anthropic', 'openrouter', 'openai'];

        foreach (array_unique([...$candidates, 'regolo', 'anthropic', 'openrouter', 'openai']) as $candidate) {
            $keyName = match ($candidate) {
                'regolo' => 'REGOLO_API_KEY',
                'anthropic' => 'ANTHROPIC_API_KEY',
                'openrouter' => 'OPENROUTER_API_KEY',
                'openai' => 'OPENAI_API_KEY',
                default => null,
            };

            if ($keyName === null) {
                continue;
            }

            $apiKey = $this->envValue($keyName);

            if ($apiKey !== null && $apiKey !== '') {
                return [$candidate, $apiKey];
            }
        }

        return null;
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (! is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);

            if (trim($name) !== $key) {
                continue;
            }

            $rawValue = trim($rawValue);

            if (
                strlen($rawValue) >= 2
                && (($rawValue[0] === '"' && $rawValue[strlen($rawValue) - 1] === '"')
                    || ($rawValue[0] === "'" && $rawValue[strlen($rawValue) - 1] === "'"))
            ) {
                $rawValue = substr($rawValue, 1, -1);
            }

            return trim($rawValue);
        }

        return null;
    }

    private function envBool(string $key): bool
    {
        $value = $this->envValue($key);

        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
