# Package Extraction Readiness — `padosoft/laravel-search-providers`

This document captures the contract between `product-image-discovery` and the future standalone search-providers package. It is the source of truth used when extracting the search layer to its own composer package.

## Why this document exists

The same set of search providers (Brave, Tavily, Exa.ai, Firecrawl, WebSearchAPI.ai, DuckDuckGo) will be reused by `product-pricing-comparison` and future Padosoft projects. Maintaining 7 driver implementations + tests + live runs in every consumer is unsustainable.

The medium-term plan is a single `padosoft/laravel-search-providers` package that exposes:

- A `SearchProviderInterface` contract.
- Provider implementations (Brave, Tavily, Exa.ai, Firecrawl, WebSearchAPI.ai, DuckDuckGo, Fake).
- A `SearchProviderManager` that resolves providers from a configurable repository.
- Generic search DTOs (`SearchQuery`, `SearchResult`, `SearchResultCollection`).
- An `EloquentSearchProviderConfigRepository` against a `search_providers` table.

`product-image-discovery` already isolates the search layer behind interfaces, so the extraction is mostly a `git mv` plus namespace rewrite. This document lists what is package-ready vs domain-specific today, and the abstraction tweaks that have been applied in PR #8 (`refactor/abstract-search-providers`) to make the extraction trivial when the second consumer (`product-pricing-comparison`) lands.

## Layered map

### 100% package-ready (move as-is, just rename namespace)

| File | Notes |
|---|---|
| `src/Services/Search/AbstractHttpSearchProvider.php` | Generic HTTP + parsing helpers. No domain references. |
| `src/Services/Search/BraveSearchProvider.php` | Generic. |
| `src/Services/Search/TavilySearchProvider.php` | Generic. |
| `src/Services/Search/ExaSearchProvider.php` | Generic. |
| `src/Services/Search/FirecrawlSearchProvider.php` | Generic. |
| `src/Services/Search/WebSearchApiSearchProvider.php` | Generic. |
| `src/Services/Search/DuckDuckGoSearchProvider.php` | Generic. |
| `src/Services/Search/FakeSearchProvider.php` | Generic test helper, ship it with the package. |
| `src/Services/Search/CallableSearchProviderFactory.php` | Generic. |
| `src/Services/Search/SearchProviderFactoryInterface.php` | Generic. |
| `src/Services/Search/SearchProviderConfigRepositoryInterface.php` | Generic. |
| `src/Services/Search/SearchProviderManager.php` | Generic after PR #8 (decoupled from `ProductImageEventLogger`, depends on `SearchEventLoggerInterface`). |
| `src/Services/Search/ProductImageSearchProviderInterface.php` | Rename to `SearchProviderInterface` at extraction time. |
| `src/Services/Search/Contracts/SearchEventLoggerInterface.php` | New in PR #8. Ship as-is. |
| `src/Services/Search/Data/SearchProviderDefinition.php` | Generic. |
| `src/Services/Search/Data/SearchProviderExecutionResult.php` | Generic. |
| `src/Services/Search/Data/ProductImageSearchQueryData.php` | Rename to `SearchQueryData` at extraction time (or keep both as alias). |
| `src/Services/Search/Data/ProductImageSearchResult.php` | Rename to `SearchResult` at extraction time. |
| `src/Services/Search/Data/ProductImageSearchResultCollection.php` | Rename to `SearchResultCollection` at extraction time. |

### Mostly package-ready (move with configurable indirection)

| File | Adjustment |
|---|---|
| `src/Services/Search/DatabaseSearchProviderConfigRepository.php` | PR #8 introduces a constructor `?string $providerModel` parameter and a `config('product-image-discovery.models.search_provider')` lookup. In the package, this becomes `config('search-providers.model')`. The model resolution falls back to the package default, so swapping in a host-specific model is a one-line config change. |
| `database/migrations/2026_04_29_000006_create_product_image_search_providers_table.php` | The future package ships a generic `create_search_providers_table` migration. `product-image-discovery` keeps a rename migration that ALTERS the legacy `product_image_search_providers` to `search_providers` (or vice-versa via config). |
| `src/Models/ProductImageSearchProvider.php` | Future package ships `SearchProviderConfig` with the same `active`/`ordered` scopes. The host can either replace the model via config or keep a thin subclass that extends the package model. |

### Stays in `product-image-discovery`

| File | Reason |
|---|---|
| `src/Jobs/SearchProductImageJob.php`, `ExtractCandidateSourcesJob.php`, `VerifyCandidateImageJob.php`, `DownloadCandidateImageJob.php`, `AssessImageQualityJob.php` | Pipeline workflow specific to "find a correct product image". `SearchProductImageJob` calls the (external) `SearchProviderManager` but the orchestration is domain. |
| `src/Actions/GenerateSearchQueriesAction.php`, `ResolveDecisionAction.php` | "How do we generate queries from `brand+model+color+ean+sku`?" is product-image-specific. |
| `src/Services/Extraction/*`, `src/Services/Quality/*`, `src/Actions/AssessImageQualityAction.php` | Image-specific extraction and quality scoring. |
| `src/Services/Logging/ProductImageEventLogger.php` | Domain audit store. Implements `SearchEventLoggerInterface` (PR #8) so it satisfies the package contract. |
| `src/Services/Ai/*` | AI verification of candidate images. |
| `src/Http/*`, `src/Models/ProductImageDiscovery*` | API + persistence for the discovery pipeline. |

## What PR #8 (`refactor/abstract-search-providers`) does

This branch makes the extraction a `git mv` instead of a full rewrite:

1. **`SearchEventLoggerInterface`** added at `src/Services/Search/Contracts/SearchEventLoggerInterface.php`. Single method `record(string $eventType, array $context = [], string $level = 'info'): mixed`. `ProductImageEventLogger` implements it. `SearchProviderManager` depends on the interface, not the concrete class.
2. **`DatabaseSearchProviderConfigRepository`** accepts a `?string $providerModel` constructor argument and reads `config('product-image-discovery.models.search_provider')` when the override is omitted. The host can swap the model without subclassing the repository. The `class_exists` guard still keeps it pure-unit-test friendly.
3. **No behavior changes**. PHPUnit gate identical: 103 tests / 396 assertions / 2 skipped.

## Future extraction recipe (when `product-pricing-comparison` starts)

1. Scaffold `padosoft/laravel-search-providers` from a fresh Laravel package skeleton, copy `.github/workflows/ci.yml` from this repo.
2. `git mv` the package-ready files listed above, rewriting namespace `Padosoft\ProductImageDiscovery\Services\Search\` → `Padosoft\LaravelSearchProviders\`. Rename `ProductImageSearchProviderInterface` → `SearchProviderInterface`. Rename `ProductImageSearchQueryData` → `SearchQueryData`, `ProductImageSearchResult` → `SearchResult`, `ProductImageSearchResultCollection` → `SearchResultCollection`.
3. Copy `tests/Unit/Search/*` and `tests/E2E/Live*SearchProviderTest.php` over, rewrite namespaces.
4. Add a generic `create_search_providers_table` migration in the package; add a default Eloquent model `SearchProviderConfig` with `scopeActive`/`scopeOrdered`.
5. Publish a `config/search-providers.php` with `model`, `table`, `factories` keys.
6. Publish a `ServiceProvider` that binds `SearchProviderManager` as singleton with the default factories.
7. In `product-image-discovery`:
   - `composer require padosoft/laravel-search-providers`
   - Remove the moved files.
   - Replace internal imports with the new namespaces (a single `sed` covers most).
   - Update `config/product-image-discovery.php` to point `models.search_provider` at the local subclass (or null = use package default).
   - Add a rename migration if the table needs to switch from `product_image_search_providers` to `search_providers`.
8. Re-run `vendor/bin/phpunit --testsuite Unit,Feature,E2E`. Expected delta: -~30 tests moved to the new package, same assertion count for the rest. Live tests run from both sides.

## Stability commitment

- Once the package is published, the contracts in this file are the API surface. Breaking changes require a major version bump.
- New providers added to the package are minor releases.
- Bug fixes inside an existing provider are patch releases.
- `product-image-discovery` and `product-pricing-comparison` track `^1.0` of the package and pull patches automatically.

## Last update

2026-05-23 — PR #8 (refactor/abstract-search-providers) introduces `SearchEventLoggerInterface` and parameterizes the database repository. Tag `v0.3.0` cuts the refactor.
