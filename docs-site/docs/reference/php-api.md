---
title: PHP API
description: PHP classes and extension points.
---

# PHP API

## Actions

| Class | Responsibility |
| --- | --- |
| `GenerateSearchQueriesAction` | Builds provider queries from product identity. |
| `NormalizeProductIdentityAction` | Normalizes inbound identity fields. |
| `ScoreCandidateImageAction` | Scores candidate evidence. |
| `ResolveDecisionAction` | Converts evidence into candidate status. |
| `AssessImageQualityAction` | Evaluates image quality. |

## Services

| Service | Responsibility |
| --- | --- |
| `ProductImageAiVerifier` | Optional AI verification orchestration. |
| `GenericStructuredExtractor` | Extracts structured data from source pages. |
| `PatternSourceResolver` | Resolves URL patterns and source hints. |
| `ProductImageEventLogger` | Writes audit events. |
| `EloquentPipelineStore` | Persists pipeline state. |

## Extension Pattern

Prefer replacing behavior through configured providers, settings, and Laravel container bindings instead of editing package source in a host app.
