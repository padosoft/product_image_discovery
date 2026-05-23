# Product Image Discovery - Progress

## Session 2026-04-29

### Goal

Implementare il modulo Product Image Discovery & Verification come package Laravel 13 open source, partendo dai documenti:

- `C:\Users\lopad\Downloads\product_image_discovery\product_image_discovery_architecture.md`
- `C:\Users\lopad\Downloads\product_image_discovery\product_image_discovery_implementation_plan.md`

### Repository

- Target: `C:\Users\lopad\Documents\DocLore\Visual Basic\Ai\product_image_discovery`
- Package: `padosoft/product-image-discovery`
- Runtime verificato: Herd PHP `C:\Users\lopad\.config\herd\bin\php84\php.exe` (`PHP 8.4.20`)
- Test database: SQLite in-memory via Orchestra Testbench

### Completed

- Scaffold package Laravel 13: `composer.json`, `phpunit.xml`, config, service provider, routes, migrations, seeder, `.gitignore`.
- Data layer: migrations, enums, Eloquent models, default settings/providers seeder, database tests.
- API layer: Sanctum-oriented routes, ability middleware, controllers, form requests, resources, feature tests with fake models/jobs.
- Pipeline layer: ingest, search, extract, verify, download, quality jobs; idempotent request/candidate/source-page persistence.
- Search layer: provider interface, provider definitions, DB repository, manager, fake provider, Brave provider, tests.
- Matching/extraction/quality: product identity DTO, query generation, structured extractor, source resolver, scoring, quality analyzer, anti-false-positive tests.
- Logging/audit: secret redaction, database audit store, event logger, graceful behavior in pure unit contexts.
- Runtime store: `PipelineStoreInterface` bound to `EloquentPipelineStore`.
- Sidecar: Node HTTP service with `/health` and `/render`, Playwright renderer with static fallback, timeout and secret handling, offline Node tests.
- Documentation memory: `docs/LESSON.md`, `docs/PROGRESS.md`, `docs/RULES.md`, `AGENTS.md`, local skill seed.
- Community README rewritten with badges, pitch, architecture, installation, API quickstart, config, testing, roadmap, contributing.
- Added root `.env.example` and `sidecar/.env.example`.
- Expanded README with a junior-friendly live smoke test from a fresh Laravel app and real fashion product payload examples.
- Added opt-in live Brave provider/manager tests and an opt-in full live product-image pipeline test.
- Fixed enum persistence bug discovered by live pipeline: manual-review decision reasons must not be written into the request `rejection_reason` enum column.
- Added model-phrase matching so real catalog names such as `Air Force 1 07` can count as strong deterministic matches.
- Added request JSON examples under `examples/requests/` and removed source/product URLs from ERP request payload examples.
- Installed Laravel AI SDK and added optional AI verification for candidate images.
- Added `ProductImageCandidateVerifierAgent`, `ProductImageAiVerifier`, AI verification DTO, fake tests, job integration and live AI verifier test skipped unless an AI key is provided.
- Supported AI providers in package config/env: OpenAI, Anthropic and OpenRouter.
- Verified the opt-in live AI verifier against Anthropic with a real `ANTHROPIC_API_KEY`.
- Aligned `.env.example` and README AI documentation with every package/test/sidecar env variable currently used.
- Enabled and verified remote image attachments for the live Anthropic AI verifier.

### Fixes Made During Integration

- The initial implementation accidentally wrote some scaffold files in the parent workspace. Those files were removed; the actual repo files live under `product_image_discovery/...`.
- Route prefix aligned to `/api/product-image-discovery/...`.
- API tests aligned to the real public route prefix.
- Candidate migration/model now includes `fingerprint` and a unique `request_id + fingerprint` constraint for idempotent upserts.
- `EloquentPipelineStore` added to bridge queued jobs with Eloquent models.
- Service provider now binds the pipeline store, search config repository, audit store, event logger and search manager.
- Pure pipeline tests no longer fail when Laravel facades (`config`, `Log`, `Bus`, `Storage`) are unavailable.
- API ingest now persists only model fields and keeps the full inbound payload in `raw_payload`.
- `DownloadCandidateImageJob` handles inline image metadata and avoids hard failures outside a full app container.
- `IngestProductImageDiscoveryJob` now supports both raw payload ingest and persisted request-id resume, which makes the default API job configuration production-safe.

### Verified Gates

- Composer install with Herd PHP 8.4: completed.
- Composer validate strict: PASS.
- PHPUnit aggregate:
  - Command: `& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite Unit,Feature,E2E`
  - Latest result with live `BRAVE_SEARCH_API_KEY`, live `ANTHROPIC_API_KEY` and remote image attachment enabled: PASS, `60 tests`, `276 assertions`, `1 skipped`.
  - Skip reason in the live-AI run: live sidecar contract requires `SIDECAR_E2E_URL`.
- Live Anthropic AI verifier:
  - Command: `& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite E2E --filter LiveProductImageAiVerifierTest`
  - Result with `ANTHROPIC_API_KEY` and `PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE=true`: PASS, `1 test`, `9 assertions`.
- Sidecar Node tests:
  - Command: `npm test` in `sidecar`
  - Result: PASS, `7/7`.

## Session 2026-04-30

### Completed

- Fixed the repo-local Codex skill by adding the required YAML frontmatter to `.agents/skills/product-image-discovery/SKILL.md`.
- Synchronized `.env.example` with package/test environment usage and aligned the local `.env` while preserving the real `BRAVE_SEARCH_API_KEY` and `ANTHROPIC_API_KEY`.
- Enabled local AI live testing with Anthropic remote image attachments:
  - `PRODUCT_IMAGE_DISCOVERY_AI_ENABLED=true`
  - `PRODUCT_IMAGE_DISCOVERY_AI_PROVIDER=anthropic`
  - `PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE=true`
  - `PRODUCT_IMAGE_DISCOVERY_AI_VISION_MODEL=claude-sonnet-4-5-20250929`
  - `PRODUCT_IMAGE_DISCOVERY_AI_DESCRIPTION_MODEL=claude-haiku-4-5-20251001`
- Added `product-image-discovery:debug-flow`, an Artisan command that accepts a request JSON file, runs ingest/search/extract/verify/download/quality, streams a detailed step-by-step console trace with ASCII art, and can write a full JSON debug report.
- Added `examples/requests/herno-cappa-nylon-ultralight-cammello.json` as a reusable live request for the Herno PI002223D cammello product.
- Fixed Brave image result mapping:
  - `properties.url` is the actual image URL.
  - `url` is the source/product page.
  - `page_fetched` is metadata, not a page URL.
  - `source`/`meta_url` can be used for source-domain fallback.
- Added prefix matching for concatenated fashion product codes such as `PI002223D12017Z2157` while keeping short code prefix matching disabled.
- Improved the debug command to rank unverified candidates deterministically before live AI verification, so `--max-candidates=1` can start from the strongest product identity match instead of the first provider result.
- Changed debug download behavior to download/quality-assess only the best verified candidate by default; `--download-all` keeps the exhaustive download mode available.
- Added debug early-stop controls so the command can stop verification after the first good verified candidate unless `--exhaustive` is passed:
  - `PRODUCT_IMAGE_DISCOVERY_DEBUG_STOP_ON_FIRST_GOOD=true`
  - `PRODUCT_IMAGE_DISCOVERY_DEBUG_GOOD_SCORE_THRESHOLD=65`
- Updated README with the debug command, Testbench usage, options and the Herno request example.
- Added console screenshots for the debug command under `resources/artisan-command-01.png` and `resources/artisan-command-02.png`.
- Composer validate strict: PASS.
- PHPUnit Unit/Feature/E2E: PASS, `60 tests`, `276 assertions`, `1 skipped`.
- Investigated Herno debug false positives:
  - White Nike shoe files found under Testbench storage were stale files from previous request id `1` runs, not images returned by the current Herno Brave query.
  - `product-image-discovery:debug-flow --fresh` now cleans old and new request storage directories; `--clean-storage` is also available for repeated debug runs.
  - Search query generation now prioritizes color-aware supplier/model queries such as `"Herno" "PI002223D" "CAMMELLO"` before bare `"Herno" "PI002223D"`.
  - The debug report now prints executed query attempts, not only the generated query list.
  - AI verification now uses a visual-first prompt for actual product type/color and treats numeric vendor color ids as metadata, not color names.
  - High-confidence AI visible mismatches are now scoring mismatches; low-confidence AI disagreements no longer override deterministic exact matches.
  - `cammello/camel/tan/beige/biscuit/light brown` are normalized as one color family.
- Verified live Herno after the fixes:
  - Executed query: `"Herno" "PI002223D" "CAMMELLO"`.
  - Brave returned 10 results from `mariodannashop.it` and `carmenboutique.it`.
  - Candidate `1` from `mariodannashop.it` passed verification and quality.
  - Final score: `99`; quality score: `75`; status: `quality_passed`.
  - AI result: `match=true`, `color_match=true`, `product_type_match=true`, `confidence=90`.
  - Stored image: `product-image-discovery/1/1.JPG`, physically under Testbench storage.
  - Storage folder after the run contained only `1.JPG`, confirming stale files were removed.
- Composer validate strict: PASS.
- PHPUnit Unit/Feature/E2E: PASS, `66 tests`, `300 assertions`, `1 skipped`.
- Added `docs/ADMIN_UI_UX_GUIDELINES.md`, a host-admin UI/UX specification for integrating a vanilla JavaScript Laravel admin experience while keeping this package headless.
- Linked the admin UI guidance from README and adjusted the roadmap from a package UI starter kit toward host-admin integration examples.
- Added a README responsible-use disclaimer for lawful, authorized source access only.
- Strengthened EAN/barcode support:
  - API request/search validation now accepts `barcode`, `bar_code`, `gtin`, `gtin13` and `gtin14` aliases and normalizes them into `ean`.
  - Product identity normalization accepts the same aliases.
  - EAN search remains the highest-weight query.
  - Exact textual/structured EAN matches now count as strong product identity matches.
  - Structured GTIN/EAN mismatch is now treated as wrong-product risk.
- Added regression coverage for barcode aliases, EAN-first query generation, API search by barcode alias, EAN strong match and GTIN mismatch rejection.
- Composer validate strict: PASS.
- PHPUnit Unit/Feature/E2E: PASS, `72 tests`, `319 assertions`, `1 skipped`.
- Live debug command with real Brave/Anthropic credentials after EAN/barcode changes:
  - Report: `storage/debug/herno-after-ean-barcode-update.json`.
  - Query: `"Herno" "PI002223D" "CAMMELLO"`.
  - Candidate `1` from `mariodannashop.it` reached `quality_passed`.
  - Final score: `99`; quality score: `100`.
  - AI: `match=true`, `color_match=true`, `product_type_match=true`, `confidence=90`.

## Session 2026-05-02

### Completed

- Made Regolo explicit as the default optional AI provider:
  - added `padosoft/laravel-ai-regolo` as a package dependency
  - changed the package AI provider fallback and `.env.example` from `anthropic` to `regolo`
  - added `REGOLO_API_KEY`, `REGOLO_URL` and `REGOLO_BASE_URL` configuration
  - kept OpenAI, Anthropic and OpenRouter documented as alternate providers
  - updated AI verifier unit/feature/live-test defaults to include Regolo first

### Live Herno Debug Result

- Command wrote `storage/debug/herno-flow.json`.
- Brave returned 10 image results across `parisricci.com` and `us.herno.com`.
- The deterministic debug rank selected the official Herno result first even though Brave listed black ParisRicci variants earlier:
  - Page: `https://us.herno.com/en/women/outerwear`
  - Image: `https://us.herno.com/dw/image/v2/BGRP_PRD/on/demandware.static/-/Sites-33/default/dw04f1cf29/images/zoom/PI002223D12017Z_2157_0.jpg?sw=710&sh=900&sm=fit`
  - Candidate id: `4`
  - Candidate status: `quality_passed`
  - Final score: `69`
  - Quality score: `100`
  - Local path: `product-image-discovery/1/4.jpg`
  - Request status: `manual_review` because the source is not yet configured as auto-publishable.
- Anthropic analyzed the official remote image and returned a low-confidence conservative disagreement (`match=false`, `confidence=25`) because the request color code is textual (`CAMMELLO`) while the discovered image URL carries numeric color code `2157`. The report preserves this disagreement for manual review.

### Remaining Optional Work

- Add real external providers beyond Brave (`serpapi`, `google_custom_search`) when API credentials and contracts are decided.
- Add a first-party Laravel app demo or example screenshots after the package API stabilizes.

## Session 2026-05-23

### Goal

Wire five new search providers (Tavily, Exa.ai, Firecrawl, WebSearchAPI, DuckDuckGo) on top of the existing Brave driver, introduce CI, and revamp the README with a junior-friendly Quick Start and a dedicated "Supported Search Providers" section. Tracked in `docs/ROADMAP_SEARCH_PROVIDERS.md`. One PR per provider.

### PR1 — feat/search-provider-tavily (foundation + Tavily + README revamp + CI)

In progress. Completed sub-tasks:

- Extracted `src/Services/Search/AbstractHttpSearchProvider.php` with the helpers previously inlined in `BraveSearchProvider` (`pickUrl`, `pick`, `dotGet`, `extractDomain`, `normalizeDomain`, `normalizeInt`, `normalizeFloat`, `applySiteFilter`, `assertHttpClientAvailable`). Brave now extends it with zero behavior change — existing `BraveSearchProviderTest` keeps passing.
- Extracted `tests/Concerns/ReadsLocalEnv.php` trait from the inline `envValue()` in `LiveBraveSearchProviderTest`. Brave live test now uses the trait.
- Added `.github/workflows/ci.yml` with two jobs:
  - `php-tests` matrix on PHP 8.3 and 8.4 (composer install, composer validate strict, phpunit Unit+Feature+E2E).
  - `sidecar-tests` running `npm ci && npm test` against `sidecar/`.
- Added `docs/ROADMAP_SEARCH_PROVIDERS.md` tracking 5 PRs with per-PR gates.
- Implemented `src/Services/Search/TavilySearchProvider.php` (`POST /search` with `Authorization: Bearer`, `include_images=true`, `include_image_descriptions=true`, `include_domains` for site filter). Normalizes both legacy string-array `images` and modern object-array `images` payloads. Joins images to results by domain to recover `page_url`/`title`.
- Registered `'tavily'` driver in `ProductImageDiscoveryServiceProvider`.
- Seeded `code=tavily` (priority=40, disabled) in `ProductImageDiscoveryDefaultsSeeder`.
- Added `tests/Unit/Search/TavilySearchProviderTest.php` with 6 cases (object images, legacy strings, empty payload, 401, site filter → include_domains, web search).
- Added `tests/E2E/LiveTavilySearchProviderTest.php` (opt-in via `TAVILY_API_KEY`).
- Updated `.env.example` with `TAVILY_API_KEY` + `TAVILY_URL`. Real key stored only in local `.env` (gitignored).
- README revamp:
  - New `## Quick Start (5 minutes, junior-friendly)` immediately after `Why This Package`. 9 copy-paste steps, ends with the fake provider returning a stored candidate. No Sanctum prerequisites, no paid keys, no sidecar.
  - Replaced `## Search Providers` (3 bullets) with `## Supported Search Providers` (full matrix + per-provider env vars + tinker activation snippets + doc links). Emphasizes "7 search providers ready to plug in".
  - TOC updated: added `Quick Start (5 minutes)` and renamed `Search Providers` → `Supported Search Providers`.
  - `## Roadmap` refreshed: split into `Recent additions (since v0.1.0)` and `Planned`. Removed the obsolete "GitHub Actions workflow" bullet (now done).

### Verified Gates (PR1, intermediate)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 79 tests / 331 assertions / 2 skipped.
- Baseline before this work was 72 tests / 319 / 1 skipped.
- Skipped tests: sidecar contract (needs `SIDECAR_E2E_URL`) + live AI verifier (Anthropic credits exhausted on the dev account — handled by a new `InsufficientCreditsException` skip in `LiveProductImageAiVerifierTest` so the gate stays green when external quota is depleted).
- Live Brave + Live Tavily tests both executed against real APIs (keys in `.env`, gitignored).
- Composer validate strict: PASS via `& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' validate --strict --no-check-publish`.

### PR2 — feat/search-provider-exa (Exa.ai)

In progress. Completed sub-tasks:

- Implemented `src/Services/Search/ExaSearchProvider.php` extending `AbstractHttpSearchProvider`. `POST /search` with `x-api-key` header. Flattens `results[].extras.imageLinks` into one candidate per image URL (deduped, primary `image` prepended). `searchWeb()` falls back to per-result mapping. Site filter via `includeDomains`. Configurable `search_type` (`auto`) and `image_links_per_result` (default 5).
- Registered `'exa'` driver in `ProductImageDiscoveryServiceProvider`.
- Seeded `code=exa` (priority=50, disabled).
- Added `tests/Unit/Search/ExaSearchProviderTest.php` with 5 cases (flatten image links, no image links → empty, 401, site filter → includeDomains, web search normalization).
- Added `tests/E2E/LiveExaSearchProviderTest.php` (opt-in via `EXA_API_KEY`).
- Updated `.env.example` with `EXA_API_KEY` + `EXA_URL`. Real key kept only in `.env` (gitignored).
- Updated `docs/ROADMAP_SEARCH_PROVIDERS.md` (PR1 ✅, PR2 🟡).
- README "Supported Search Providers" → Exa section: added activation snippet + `image_links_per_result` notes.

### Verified Gates (PR2, intermediate)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 85 tests / 347 assertions / 2 skipped.
- CI green on PR #4 (PHP 8.3 + 8.4 + sidecar Node 24).
- Live Exa exercised against real API locally.
- Merged in PR #4 (squash). ROADMAP updated PR2 → ✅.

### PR3 — feat/search-provider-firecrawl (Firecrawl)

In progress. Completed sub-tasks:

- Implemented `src/Services/Search/FirecrawlSearchProvider.php` extending `AbstractHttpSearchProvider`. `POST /v2/search` with `Authorization: Bearer` and body `{query, sources:[{type:"images"}], limit, includeDomains}`. Response parsed via `data.images[]` (imageUrl, imageWidth, imageHeight, source url) and `data.web[]` (title, description, url, metadata).
- Registered `'firecrawl'` driver in ServiceProvider.
- Seeded `code=firecrawl` (priority=60, disabled, timeout=60s for the slow synchronous v2/search endpoint, rate_limit=30/min for free tier safety).
- Added `tests/Unit/Search/FirecrawlSearchProviderTest.php` with 5 cases (parse images, empty `data.images`, 401, site filter → includeDomains + sources, web search mapping).
- Added `tests/E2E/LiveFirecrawlSearchProviderTest.php` (opt-in via `FIRECRAWL_API_KEY`).
- Updated `.env.example` with `FIRECRAWL_API_KEY` + `FIRECRAWL_URL`.
- Updated README + ROADMAP (PR2 ✅, PR3 🟡).

### Verified Gates (PR3, intermediate)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 91 tests / 364 assertions / 2 skipped.
- CI green on PR #5 (PHP 8.3 + 8.4 + sidecar Node 24).
- Merged in PR #5 (squash). ROADMAP updated PR3 → ✅.

### PR4 — feat/search-provider-websearchapi (WebSearchAPI.ai)

In progress. Completed sub-tasks:

- Discovered through `/docs/search-api` that WebSearchAPI.ai only exposes Google-backed organic web results via `POST /ai-search`; there is no dedicated image search endpoint. Decision: implement the driver as `supportsImageSearch()=false` (web-only) like DuckDuckGo. `SearchProviderManager` skips it for image queries; pipeline extraction still harvests images from the returned page URLs.
- Implemented `src/Services/Search/WebSearchApiSearchProvider.php`. `POST /ai-search` with `Authorization: Bearer`. Body: `{query, maxResults, includeContent, country, language, includeDomains}`. Response parsed from `organic[]` with title/url/description/content/position/score.
- Registered `'websearchapi'` driver in ServiceProvider.
- Seeded `code=websearchapi` (priority=70, disabled, supports_image_search=false explicit in config).
- Added `tests/Unit/Search/WebSearchApiSearchProviderTest.php` with 5 cases (supportsImageSearch=false + searchImages empty, web search parsing, web search empty when no organic, 401, site filter → includeDomains).
- Added `tests/E2E/LiveWebSearchApiSearchProviderTest.php` (opt-in via `WEBSEARCHAPI_API_KEY`).
- Updated `.env.example` with `WEBSEARCHAPI_API_KEY` + `WEBSEARCHAPI_URL`.
- README "Supported Search Providers" table updated: `websearchapi` image search marked ❌ (web-only), site filter via `includeDomains`. Documentation link corrected to `https://websearchapi.ai/docs/search-api`. Per-provider activation snippet added.

### Verified Gates (PR4, intermediate)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 97 tests / 380 assertions / 2 skipped.
- CI green on PR #6.
- Merged in PR #6 (squash). ROADMAP updated PR4 → ✅.

### PR5 — feat/search-provider-duckduckgo (DuckDuckGo HTML lite)

In progress. Completed sub-tasks:

- Implemented `src/Services/Search/DuckDuckGoSearchProvider.php`. POSTs `{q: ...}` form-encoded to `https://html.duckduckgo.com/html/` with a realistic User-Agent. Parses the response with `\DOMDocument::loadHTML` (libxml errors suppressed) + `\DOMXPath`. Iterates `.result` containers, picks `.result__a` (link + title), `.result__snippet` (description), `.result__url` (source domain). `result__a` `href` values are typically `//duckduckgo.com/l/?uddg=<URLENCODED>&rut=...`; the provider extracts and `urldecode`s the `uddg` (or `u`/`url`) param to recover the real destination URL. Falls through to direct `http(s)://` hrefs when present.
- `supportsImageSearch()` returns `false`; `searchImages()` returns an empty collection. SearchProviderManager skips the driver for image queries automatically (`SearchProviderManager.php:72-75`).
- Site filter implemented by appending `site:<host>` to the user query (DDG HTML lite has no native domain filter input).
- Driver `'duckduckgo'` registered in ServiceProvider.
- Seeded `code=duckduckgo` (priority=80, disabled, rate_limit=20/min for anti-bot safety).
- Added `tests/Unit/Search/DuckDuckGoSearchProviderTest.php` with 5 cases (image disabled, parse fixture + decode uddg redirect, empty results HTML, site:operator appended to query, HTTP 429 propagates).
- Added `tests/Unit/Search/fixtures/duckduckgo-html-lite.html` (minimal but realistic DDG HTML containing both `uddg` redirect and direct href cases).
- Added `tests/E2E/LiveDuckDuckGoSearchProviderTest.php`: opt-out via `CI` env var (skipped on CI), and skips cleanly on 403/429/503 anti-bot responses.
- Updated `.env.example` with `DUCKDUCKGO_URL` (only override variable; no key needed).
- README "Supported Search Providers" → DuckDuckGo: activation snippet, anti-bot note. ROADMAP PR4 → ✅, PR5 → 🟡.

### Verified Gates (PR5, final)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 103 tests / 396 assertions / 2 skipped.
- CI green on PR #7.
- Merged in PR #7 (squash). ROADMAP PR5 → ✅. Tag `v0.2.0` cut with full release notes covering all 5 PRs.

### PR #8 — refactor/abstract-search-providers (package-extraction prep)

Goal: make the future extraction to `padosoft/laravel-search-providers` a `git mv` instead of a rewrite, so when `product-pricing-comparison` starts we lift the providers out in one afternoon.

Completed sub-tasks:

- Added `src/Services/Search/Contracts/SearchEventLoggerInterface.php` with a single method `record(string $eventType, array $context = [], string $level = 'info'): mixed`. This is the generic event-logging contract the future package will ship.
- `ProductImageEventLogger` now implements `SearchEventLoggerInterface` so the existing host-app audit logger satisfies the contract.
- `SearchProviderManager` constructor depends on `SearchEventLoggerInterface` instead of the concrete `ProductImageEventLogger`. Behavior is unchanged because the existing binding still resolves to the same concrete logger.
- `DatabaseSearchProviderConfigRepository` now accepts an optional `?string $providerModel` constructor argument and resolves the backing Eloquent model from constructor override → `config('product-image-discovery.models.search_provider')` → package default. Host-app consumers (and the future package) can swap the model with a one-line config change instead of subclassing the repository.
- Added `docs/PACKAGE_EXTRACTION_READINESS.md`: source of truth for the package boundary. Lists which files are 100% package-ready (move-as-is), which are mostly ready (with configurable indirection), which stay in `product-image-discovery`, and the step-by-step extraction recipe.

Verified Gates (PR #8):

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 103 tests / 396 assertions / 2 skipped (no test count change — pure refactor).
- Composer validate strict PASS.

## Session 2026-05-23 (afternoon)

Extraction completed. The search layer now lives in its own composer package, `padosoft/laravel-ai-search-providers v1.0.0` (https://github.com/padosoft/laravel-ai-search-providers, https://packagist.org/packages/padosoft/laravel-ai-search-providers). The package was developed across 6 PRs (A1–A6) and tagged `v1.0.0`.

### PR B1 — refactor: depend on padosoft/laravel-ai-search-providers, drop in-tree search layer

Branch `refactor/depend-on-ai-search-providers`.

- `composer require padosoft/laravel-ai-search-providers:^1.0` (v1.0.0 installed from Packagist).
- Removed `src/Services/Search/` (17 files) and `tests/Unit/Search/` (10 files including fixtures + support helper) and the 5 moved live tests under `tests/E2E/` and `tests/Concerns/ReadsLocalEnv.php`.
- `src/Models/ProductImageSearchProvider.php` rewritten as a 3-line subclass of `Padosoft\LaravelAiSearchProviders\Models\SearchProviderConfig` that hard-codes `$table = 'product_image_search_providers'`. Scopes/casts inherited from the package model. `padosoft/product_image_discovery_admin` keeps working unchanged.
- `src/ProductImageDiscoveryServiceProvider.php`:
  - Removed all factory registrations, the local `SearchProviderManager` singleton, and the `SearchEventLoggerInterface` contract that lived inside the package.
  - `register()` sets `ai-search-providers.table = 'product_image_search_providers'` and `ai-search-providers.model = ProductImageSearchProvider::class` so the package's `EloquentSearchProviderConfigRepository` reads from the legacy table.
  - Binds the package's `SearchEventLoggerInterface` to the local `ProductImageEventLogger`, so every provider attempt continues to land in the `product_image_discovery_events` audit trail.
- `src/Services/Logging/ProductImageEventLogger.php`: single import change — implements `Padosoft\LaravelAiSearchProviders\Contracts\SearchEventLoggerInterface` (no API change, just the namespace).
- `src/Jobs/SearchProductImageJob.php`, `src/Console/Commands/ProductImageDiscoveryDebugFlowCommand.php`, `tests/Feature/Pipeline/PipelineJobsTest.php`, `tests/E2E/LiveBraveSearchProviderTest.php`, `tests/E2E/LiveProductImagePipelineTest.php`: switched imports to the package namespaces (`Padosoft\LaravelAiSearchProviders\…`). The local DTO alias `ProductImageSearchQueryData` was replaced with the package's `SearchQueryData` (aliased as `ProviderSearchQueryData` in the job to disambiguate from the domain-specific `Padosoft\ProductImageDiscovery\DTO\SearchQueryData` used by the query-builder layer).
- New `tests/Support/InMemorySearchProviderConfigRepository.php` — local 20-line helper that implements the package's `SearchProviderConfigRepositoryInterface`. (The package's own in-memory helper is in its `autoload-dev` namespace and not visible to consumers.)
- `tests/TestCase.php` registers both `LaravelAiSearchProvidersServiceProvider` and `ProductImageDiscoveryServiceProvider` so Testbench sees the search-providers package bindings (`EloquentSearchProviderConfigRepository`, default factories) in addition to the local providers.
- `LiveBraveSearchProviderTest` rewritten as a focused integration test that exercises the wiring between consumer and package: insert a row via the local `ProductImageSearchProvider` subclass, resolve the package's `SearchProviderManager`, verify the attempt succeeds against the live Brave API. The standalone live test (without DB) moved to the package's own E2E suite.

### Verified Gates (PR B1)

- `vendor/bin/phpunit --testsuite Unit,Feature,E2E` PASS: 69 tests, 294 assertions, 2 skipped. Baseline before extraction was 103 / 396 / 2; the 34 dropped tests + 102 assertions moved to the package's own suite.
- `composer validate --strict --no-check-publish`: PASS.
- Live AI verifier test still gracefully skips on insufficient credits.
