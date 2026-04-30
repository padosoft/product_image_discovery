<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Ai;

use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Image;
use Padosoft\ProductImageDiscovery\Ai\Agents\ProductImageCandidateVerifierAgent;
use Padosoft\ProductImageDiscovery\DTO\AiCandidateVerificationData;
use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Throwable;

final class ProductImageAiVerifier
{
    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', false);
    }

    public function hasConfiguredProvider(): bool
    {
        $provider = $this->providerName();
        $apiKey = $this->config("providers.{$provider}.api_key");

        return is_string($apiKey) && trim($apiKey) !== '';
    }

    /**
     * @param ProductIdentityData|array<string, mixed> $identity
     * @param CandidateImageData|array<string, mixed> $candidate
     */
    public function verify(ProductIdentityData|array $identity, CandidateImageData|array $candidate): AiCandidateVerificationData
    {
        if (! $this->isEnabled()) {
            return AiCandidateVerificationData::skipped('AI verification is disabled.');
        }

        $providerName = $this->providerName();
        $model = $this->modelName();

        if (! $this->hasConfiguredProvider()) {
            return AiCandidateVerificationData::failed("AI provider [{$providerName}] is missing an API key.", $providerName, $model);
        }

        $identity = is_array($identity) ? ProductIdentityData::fromArray($identity) : $identity;
        $candidate = is_array($candidate) ? CandidateImageData::fromArray($candidate) : $candidate;

        try {
            $response = (new ProductImageCandidateVerifierAgent())->prompt(
                prompt: $this->buildPrompt($identity, $candidate),
                attachments: $this->attachments($candidate),
                provider: $this->lab($providerName),
                model: $model,
                timeout: (int) $this->config('timeout', 45),
            );

            return AiCandidateVerificationData::fromPayload($response->toArray(), $providerName, $model);
        } catch (Throwable $exception) {
            if ((bool) $this->config('fail_silently', true)) {
                return AiCandidateVerificationData::failed($exception->getMessage(), $providerName, $model);
            }

            throw $exception;
        }
    }

    private function buildPrompt(ProductIdentityData $identity, CandidateImageData $candidate): string
    {
        $payload = [
            'product_identity' => $identity->toArray(),
            'candidate_image' => $candidate->toArray(),
            'decision_policy' => [
                'wrong_product_is_worse_than_no_image' => true,
                'variant_color_must_match' => true,
                'brand_and_model_should_match' => true,
                'do_not_invent_missing_identifiers' => true,
                'inspect_attached_image_before_metadata' => true,
                'attached_image_visual_color_overrides_url_or_dom_color_codes' => true,
                'numeric_vendor_color_codes_are_not_color_names' => true,
                'accept_visible_color_synonyms' => ['cammello', 'camel', 'tan', 'beige', 'biscuit', 'light brown'],
                'if_visible_color_matches_requested_color_ignore_numeric_url_code' => true,
                'if_metadata_and_image_disagree_image_wins' => true,
                'notes_must_include_observed_visible_color_and_product_type' => true,
            ],
        ];

        return "Verify this candidate product image. Return the structured schema only.\n\n"
            . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, object>
     */
    private function attachments(CandidateImageData $candidate): array
    {
        if (! (bool) $this->config('attach_remote_image', false)) {
            return [];
        }

        if ($candidate->imageUrl === null || ! str_starts_with($candidate->imageUrl, 'http')) {
            return [];
        }

        return [Image::fromUrl($candidate->imageUrl)];
    }

    private function providerName(): string
    {
        $provider = $this->config('provider', 'anthropic');

        return is_string($provider) && trim($provider) !== '' ? strtolower(trim($provider)) : 'anthropic';
    }

    private function modelName(): ?string
    {
        $model = $this->config('vision_model') ?: $this->config('description_model');

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    private function lab(string $provider): Lab|string
    {
        return match ($provider) {
            'anthropic' => Lab::Anthropic,
            'openai' => Lab::OpenAI,
            'openrouter' => Lab::OpenRouter,
            default => $provider,
        };
    }

    private function config(string $key, mixed $default = null): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            return config("product-image-discovery.ai.{$key}", $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
