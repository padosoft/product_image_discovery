# Product Image Discovery - Lessons Learned

## Workspace And Paths

- The file initially referenced as `product_image_discovery_implementation.md` was not present. The correct analysis document is `product_image_discovery_architecture.md`.
- The repository started almost empty (`.git`, `README.md`, `LICENSE`). Treat it as a standalone Laravel package, not as a patch inside an existing Laravel application.
- `apply_patch` runs from `C:\Users\lopad\Documents\DocLore\Visual Basic\Ai`. Always edit this repo with paths prefixed by `product_image_discovery/...`.
- Do not write package files into the parent workspace root. If that happens, remove only the accidental files and leave unrelated user files untouched.

## Runtime

- Laravel 13 requires PHP `^8.3`.
- XAMPP PHP at `C:\xampp\php\php.exe` is PHP 8.2.12. It can help for lightweight token checks only, not for the real Laravel 13 suite.
- Herd PHP 8.4 is the verified runtime:
  - `C:\Users\lopad\.config\herd\bin\php84\php.exe`
  - Verified version: `PHP 8.4.20`
- Composer is available through Herd:
  - `C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar`
- Prefer explicit Herd commands instead of relying on `php`, `composer`, or `herd.bat` from PATH.

## Testing

- Use SQLite in-memory for Eloquent/Testbench tests.
- Verified PHP gate:
  - `& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite Unit,Feature,E2E`
  - Latest result: PASS, `48 tests`, `213 assertions`, `1 skipped`.
- The skipped E2E test is the opt-in live sidecar contract and requires `SIDECAR_E2E_URL`.
- Verified sidecar gate:
  - `npm test` inside `sidecar`
  - Latest result: PASS, `7/7`.
- Keep external network, search APIs, browser downloads and LLM calls out of the default suite. Add them only as explicit opt-in tests.

## Architecture Decisions

- Primary identity key is `client_id + erp_model_color_id`.
- The package must optimize for low false positives. No automatic publication unless the candidate is strongly matched.
- Deterministic checks are the core: trusted source, product text, structured data, source extraction, image quality and decision thresholds.
- Playwright/browser rendering is optional and isolated in the Node sidecar.
- AI/vision/description features should stay optional and config gated. Do not make LLM calls mandatory for basic package usage or tests.
- Search providers are database-configured and resolved through `SearchProviderManager`.
- Runtime jobs should depend on contracts (`PipelineStoreInterface`, provider repositories, audit stores) so unit tests can use in-memory fakes.

## Integration Traps Already Fixed

- API routes must be under `/api/product-image-discovery/...`.
- API controller stores only persistable request attributes and keeps the full payload in `raw_payload`.
- The default ingest job must accept both raw payload arrays and persisted request ids. The API dispatches the configured ingest job with a request id.
- Candidate dedupe requires a stable `fingerprint` column and unique `request_id + fingerprint` index.
- Pure PHPUnit tests may run without a Laravel facade root. Job helpers and loggers must degrade gracefully when facades are unavailable.
- `EloquentPipelineStore::mergeRequestContext()` stores pipeline context under `raw_payload.context`; request arrays expose it as `context`.
- Sidecar tests are offline by default. The live contract test should remain opt-in.
- Keep `.env.example` focused on host-app smoke testing and optional provider credentials. Keep `sidecar/.env.example` focused only on variables the Node sidecar actually reads.
- README install commands should use single PHP namespace separators in copied shell commands, for example `Padosoft\ProductImageDiscovery\...`, not doubled separators.

## Future Session Rules

- Update `docs/PROGRESS.md` whenever a meaningful phase starts or finishes.
- Update this file whenever a new environment trap, architecture rule, testing rule or production risk is discovered.
- Re-run the full PHP and sidecar gates before declaring a task complete.
- Keep README claims aligned with implemented code. If a feature is only AI-ready or planned, label it that way.
