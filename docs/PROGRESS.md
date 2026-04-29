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
- Prepared env/config keys for future OpenAI, Anthropic and OpenRouter AI integration without making AI a runtime dependency yet.

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
  - Result: PASS, `48 tests`, `213 assertions`, `1 skipped`.
  - Skip reason: live sidecar contract test is opt-in and requires `SIDECAR_E2E_URL`.
- Sidecar Node tests:
  - Command: `npm test` in `sidecar`
  - Result: PASS, `7/7`.

### Remaining Optional Work

- Add real external providers beyond Brave (`serpapi`, `google_custom_search`) when API credentials and contracts are decided.
- Add an opt-in live LLM/vision test profile if real provider keys are supplied in `.env.testing`.
- Add a first-party Laravel app demo or example screenshots after the package API stabilizes.
- Add CI workflows for PHP, Node sidecar, static analysis and coverage.
