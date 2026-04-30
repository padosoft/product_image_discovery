<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

final class ProductImageCandidateVerifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You verify whether a candidate image is safe to use for a product-color variant.
Be conservative: a wrong image is worse than no image.
Use only the provided product identity, candidate metadata, source data, and optional image attachment.
Do not infer missing barcodes or SKUs.
Return low confidence when the candidate may be a different model, brand, color, gender, category, bundle, placeholder, lifestyle-only image, or unrelated product.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'match' => $schema->boolean()->required(),
            'variant_safe' => $schema->boolean()->required(),
            'confidence' => $schema->integer()->min(0)->max(100)->required(),
            'brand_match' => $schema->boolean()->required(),
            'model_match' => $schema->boolean()->required(),
            'color_match' => $schema->boolean()->required(),
            'product_type_match' => $schema->boolean()->required(),
            'image_quality_ok' => $schema->boolean()->required(),
            'rejection_reason' => $schema->string()->required(),
            'notes' => $schema->string()->required(),
        ];
    }
}
