<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Actions;

use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\DTO\CandidateScoreData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final class ScoreCandidateImageAction
{
    /**
     * @param CandidateImageData|array<string, mixed> $candidate
     * @param array<string, mixed> $trustedSource
     * @param array<string, mixed> $settings
     */
    public function handle(ProductIdentityData $identity, CandidateImageData|array $candidate, array $trustedSource = [], array $settings = []): CandidateScoreData
    {
        $candidate = is_array($candidate) ? CandidateImageData::fromArray($candidate) : $candidate;

        $source = $this->resolveSource($candidate, $trustedSource);
        $corpus = $candidate->textCorpus();
        $structured = $candidate->structuredData;
        $evidence = ['matches' => [], 'mismatches' => [], 'source' => $source];
        $issues = [];
        $riskPenalty = 0;

        [$textualScore, $textEvidence, $strongMatches, $flags] = $this->scoreTextual($identity, $candidate, $corpus);
        [$structuredScore, $structuredEvidence, $structuredStrongMatches, $structuredFlags] = $this->scoreStructured($identity, $structured);

        $strongMatches = array_merge($strongMatches, $structuredStrongMatches);
        $flags = array_merge($flags, array_filter($structuredFlags));
        $evidence['matches'] = array_merge($textEvidence['matches'], $structuredEvidence['matches']);
        $evidence['mismatches'] = array_merge($textEvidence['mismatches'], $structuredEvidence['mismatches']);

        $brandMatched = (bool) ($flags['brand_matched'] ?? false);
        $brandMismatch = (bool) ($flags['brand_mismatch'] ?? false);
        $colorMatched = (bool) ($flags['color_matched'] ?? false);
        $colorMismatch = (bool) ($flags['color_mismatch'] ?? false);
        $modelMatched = (bool) ($flags['model_matched'] ?? false);
        $modelMismatch = (bool) ($flags['model_mismatch'] ?? false);

        if ($brandMismatch) {
            $riskPenalty += (int) ($settings['brand_mismatch_penalty'] ?? 40);
            $issues[] = 'WRONG_BRAND';
        }

        if ($colorMismatch) {
            $riskPenalty += (int) ($settings['color_mismatch_penalty'] ?? 45);
            $issues[] = 'WRONG_COLOR';
        }

        if ($modelMismatch) {
            $riskPenalty += (int) ($settings['model_mismatch_penalty'] ?? 50);
            $issues[] = 'WRONG_PRODUCT';
        }

        $sourceScore = $source['trusted'] ? (int) round(((int) $source['trust_score']) * 0.20) : 0;

        if (! $source['trusted']) {
            $riskPenalty += (int) ($settings['non_trusted_source_penalty'] ?? 15);
            $issues[] = 'SOURCE_NOT_ALLOWED';
        }

        if (! $source['allow_download']) {
            $riskPenalty += 20;
            $issues[] = 'DOWNLOAD_NOT_ALLOWED';
        }

        if ($candidate->robotsAllowed === false) {
            $riskPenalty += 30;
            $issues[] = 'ROBOTS_OR_PERMISSION_BLOCKED';
        }

        $visualScore = min(15, (int) round($this->visualRawScore($candidate) * 0.15));
        $qualityScore = max(0, min(100, $candidate->qualityScore));
        $qualityContribution = (int) round($qualityScore * 0.10);
        $qualityPassed = $qualityScore >= (int) ($settings['min_quality_score'] ?? 70);

        if ($qualityScore > 0 && ! $qualityPassed) {
            $issues[] = 'LOW_QUALITY';
        }

        $finalScore = max(0, min(100, $sourceScore + $textualScore + $structuredScore + $visualScore + $qualityContribution - $riskPenalty));
        $hasStrongMatch = count($strongMatches) > 0;
        $status = $finalScore >= (int) ($settings['min_candidate_score'] ?? 45) && ! $modelMismatch && ! $colorMismatch && ! $brandMismatch
            ? 'candidate'
            : 'low_score_rejected';
        $rejectionReason = $status === 'low_score_rejected'
            ? ($issues[0] ?? 'LOW_CONFIDENCE')
            : null;

        $evidence['strong_matches'] = array_values(array_unique($strongMatches));
        $evidence['component_scores'] = [
            'source' => $sourceScore,
            'textual' => $textualScore,
            'structured' => $structuredScore,
            'visual' => $visualScore,
            'quality' => $qualityContribution,
            'risk_penalty' => $riskPenalty,
        ];

        return new CandidateScoreData(
            sourceTrustScore: $sourceScore,
            textualMatchScore: $textualScore,
            structuredMatchScore: $structuredScore,
            visualMatchScore: $visualScore,
            qualityScore: $qualityScore,
            riskPenalty: $riskPenalty,
            finalScore: $finalScore,
            evidence: $evidence,
            issues: array_values(array_unique($issues)),
            hasStrongMatch: $hasStrongMatch,
            sourceTrusted: $source['trusted'],
            allowAutoPublish: $source['allow_auto_publish'],
            allowDownload: $source['allow_download'],
            brandMatched: $brandMatched,
            brandMismatch: $brandMismatch,
            colorMatched: $colorMatched,
            colorMismatch: $colorMismatch,
            modelMatched: $modelMatched,
            modelMismatch: $modelMismatch,
            qualityPassed: $qualityPassed,
            robotsAllowed: $candidate->robotsAllowed,
            rejectionReason: $rejectionReason,
            status: $status,
        );
    }

    /**
     * @return array{0:int,1:array{matches:list<string>,mismatches:list<string>},2:list<string>,3:array<string, bool>}
     */
    private function scoreTextual(ProductIdentityData $identity, CandidateImageData $candidate, string $corpus): array
    {
        $score = 0;
        $matches = [];
        $mismatches = [];
        $strongMatches = [];
        $flags = [];

        if ($identity->brand !== null) {
            if (TextNormalizer::containsWord($corpus, $identity->brand)) {
                $score += 10;
                $matches[] = 'brand';
                $flags['brand_matched'] = true;
            }
        }

        if ($identity->ean !== null && str_contains(preg_replace('/\D+/', '', $corpus) ?? '', $identity->ean)) {
            $score += 40;
            $matches[] = 'ean';
            $strongMatches[] = 'ean';
        }

        if (TextNormalizer::containsExactCode($corpus, $identity->supplierSku)
            || TextNormalizer::containsCodePrefix($corpus, $identity->supplierSku)) {
            $score += 35;
            $matches[] = 'supplier_sku';
            $strongMatches[] = 'supplier_sku';
        }

        if (TextNormalizer::containsExactCode($corpus, $identity->sku)) {
            $score += 25;
            $matches[] = 'sku';
            $strongMatches[] = 'sku';
        }

        if (TextNormalizer::containsExactCode($corpus, $identity->modelCode)
            || TextNormalizer::containsCodePrefix($corpus, $identity->modelCode)) {
            $score += 35;
            $matches[] = 'model_code';
            $strongMatches[] = 'model_code';
            $flags['model_matched'] = true;
        } elseif (TextNormalizer::containsPhrase($corpus, $identity->modelCode)) {
            $score += 25;
            $matches[] = 'model_phrase';
            $strongMatches[] = 'model_phrase';
            $flags['model_matched'] = true;
        } elseif (TextNormalizer::hasSimilarCodeMismatch($corpus, $identity->modelCode)) {
            $mismatches[] = 'model_code_similar_mismatch';
            $flags['model_mismatch'] = true;
        }

        if ($identity->colorCode !== null && TextNormalizer::containsExactCode($corpus, $identity->colorCode)) {
            $score += 12;
            $matches[] = 'color_code';
            $flags['color_matched'] = true;
        }

        $expectedColor = $identity->normalizedColorName();

        if ($expectedColor !== null) {
            $mentionedColors = TextNormalizer::mentionedColors($corpus);

            if (in_array($expectedColor, $mentionedColors, true)) {
                $score += 15;
                $matches[] = 'color_name';
                $flags['color_matched'] = true;
            } elseif ($mentionedColors !== []) {
                $mismatches[] = 'color_name_mismatch';
                $flags['color_mismatch'] = true;
            }
        }

        foreach (['season' => $identity->season, 'material' => $identity->material, 'category' => $identity->category] as $field => $value) {
            if ($value !== null && TextNormalizer::containsWord($corpus, $value)) {
                $score += 3;
                $matches[] = $field;
            }
        }

        if ($candidate->role === 'main_product_image') {
            $score += 5;
            $matches[] = 'main_product_image_role';
        }

        return [min(100, $score), ['matches' => $matches, 'mismatches' => $mismatches], $strongMatches, $flags];
    }

    /**
     * @param array<string, mixed> $structured
     * @return array{0:int,1:array{matches:list<string>,mismatches:list<string>},2:list<string>,3:array<string, bool>}
     */
    private function scoreStructured(ProductIdentityData $identity, array $structured): array
    {
        $score = 0;
        $matches = [];
        $mismatches = [];
        $strongMatches = [];
        $flags = [];

        $brand = $this->structuredValue($structured, ['brand', 'manufacturer']);

        if ($identity->brand !== null && $brand !== null) {
            if (TextNormalizer::normalizeText($brand) === $identity->normalizedBrand()) {
                $score += 12;
                $matches[] = 'structured_brand';
                $flags['brand_matched'] = true;
            } else {
                $mismatches[] = 'structured_brand_mismatch';
                $flags['brand_mismatch'] = true;
            }
        }

        $gtin = TextNormalizer::normalizeEan($this->structuredValue($structured, ['gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'ean']));

        if ($identity->ean !== null && $gtin !== null) {
            if ($identity->ean === $gtin) {
                $score += 45;
                $matches[] = 'structured_gtin';
                $strongMatches[] = 'gtin';
            } else {
                $mismatches[] = 'structured_gtin_mismatch';
            }
        }

        foreach (['supplier_sku' => $identity->supplierSku, 'sku' => $identity->sku, 'mpn' => $identity->modelCode] as $field => $expected) {
            $actual = $this->structuredValue($structured, [$field, str_replace('_', '', $field)]);

            if ($expected === null || $actual === null) {
                continue;
            }

            if (TextNormalizer::normalizeCode($actual) === TextNormalizer::normalizeCode($expected)) {
                $score += $field === 'mpn' ? 35 : 30;
                $matches[] = 'structured_' . $field;
                $strongMatches[] = $field === 'mpn' ? 'model_code' : $field;

                if ($field === 'mpn') {
                    $flags['model_matched'] = true;
                }
            } elseif ($field === 'mpn') {
                $mismatches[] = 'structured_model_code_mismatch';
                $flags['model_mismatch'] = true;
            }
        }

        $expectedColor = $identity->normalizedColorName();
        $actualColor = TextNormalizer::canonicalColor($this->structuredValue($structured, ['color', 'colour']));

        if ($expectedColor !== null && $actualColor !== null) {
            if ($expectedColor === $actualColor) {
                $score += 15;
                $matches[] = 'structured_color';
                $flags['color_matched'] = true;
            } else {
                $mismatches[] = 'structured_color_mismatch';
                $flags['color_mismatch'] = true;
            }
        }

        return [min(100, $score), ['matches' => $matches, 'mismatches' => $mismatches], $strongMatches, $flags];
    }

    /**
     * @param list<string> $keys
     */
    private function structuredValue(array $structured, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $structured)) {
                continue;
            }

            $value = $structured[$key];

            if (is_array($value) && isset($value['name'])) {
                return TextNormalizer::nullableString($value['name']);
            }

            if (is_scalar($value)) {
                return TextNormalizer::nullableString($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $trustedSource
     * @return array{trusted:bool,trust_score:int,allow_auto_publish:bool,allow_download:bool}
     */
    private function resolveSource(CandidateImageData $candidate, array $trustedSource): array
    {
        $candidateDomain = DomainNormalizer::normalizeDomain($candidate->sourceDomain ?? $candidate->sourcePageUrl);
        $trustedDomain = DomainNormalizer::normalizeDomain($trustedSource['domain'] ?? null);
        $sourceActive = ($trustedSource['is_active'] ?? true) !== false;
        $domainMatches = $trustedDomain !== null && $candidateDomain !== null && ($candidateDomain === $trustedDomain || str_ends_with($candidateDomain, '.' . $trustedDomain));
        $trusted = $candidate->sourceTrusted || ($sourceActive && $domainMatches);
        $trustScore = $trusted ? (int) ($trustedSource['trust_score'] ?? $candidate->sourceTrustScore) : $candidate->sourceTrustScore;

        return [
            'trusted' => $trusted,
            'trust_score' => max(0, min(100, $trustScore)),
            'allow_auto_publish' => $trusted && (bool) ($trustedSource['allow_auto_publish'] ?? $candidate->allowAutoPublish),
            'allow_download' => $trusted ? (bool) ($trustedSource['allow_download'] ?? $candidate->allowDownload) : $candidate->allowDownload,
        ];
    }

    private function visualRawScore(CandidateImageData $candidate): int
    {
        if ($candidate->visualMatchScore > 0) {
            return $candidate->visualMatchScore;
        }

        foreach (['match_score', 'score', 'confidence', 'vision_score'] as $key) {
            if (! isset($candidate->aiAnalysis[$key]) || ! is_numeric($candidate->aiAnalysis[$key])) {
                continue;
            }

            $value = (float) $candidate->aiAnalysis[$key];

            return (int) round($value <= 1 ? $value * 100 : $value);
        }

        return 0;
    }
}
