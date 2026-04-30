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
- Add CI workflows for PHP, Node sidecar, static analysis and coverage.
