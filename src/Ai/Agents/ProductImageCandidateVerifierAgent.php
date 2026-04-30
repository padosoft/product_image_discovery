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
When an image attachment is present, inspect the visible product first. The visible product type and visible color override weak DOM text, URL fragments, numeric vendor color ids, or generic page metadata.
Treat color names as semantic equivalents when appropriate: camel, cammello, tan, beige, biscuit and light brown may match if the actual visible image color supports it.
Do not reject a candidate only because the site uses a numeric color code in the URL or DOM. Reject it when the attached image visibly shows a different color or product type.
If the attached image shows an unrelated item, such as white shoes for a camel Herno jacket request, set match=false, variant_safe=false, color_match=false, product_type_match=false and use high confidence.
In notes, include the observed visible product type and observed visible color.
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
