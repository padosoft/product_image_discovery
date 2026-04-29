# AGENTS.md

Instructions for future Codex/agent sessions working on this repository.

## Project Identity

- Repository root: `C:\Users\lopad\Documents\DocLore\Visual Basic\Ai\product_image_discovery`
- Package name: `padosoft/product-image-discovery`
- Stack: Laravel 13 package, Sanctum-oriented API, Eloquent persistence, queue jobs, optional Node/Playwright sidecar.
- Core goal: discover, verify, score and prepare product images while minimizing false positives.

## Required Context Files

Read these before changing behavior:

- `docs/LESSON.md`
- `docs/PROGRESS.md`
- `docs/RULES.md`
- `README.md`
- `config/product-image-discovery.php`
- `src/ProductImageDiscoveryServiceProvider.php`

## Workspace Rules

- The parent workspace is `C:\Users\lopad\Documents\DocLore\Visual Basic\Ai`.
- When using `apply_patch`, prefix repo paths with `product_image_discovery/...`.
- Do not create package files in the parent workspace root.
- Do not revert user changes unless explicitly asked.
- Keep `vendor/`, `.phpunit.cache/`, `node_modules/` and generated coverage out of git.

## Runtime Commands

Use Herd PHP explicitly:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' -v
```

Use Herd Composer explicitly:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' install
```

Validate Composer:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' validate --strict
```

Run the package suite:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite Unit,Feature,E2E
```

Run the sidecar suite:

```powershell
npm test
```

## Architecture Rules

- Primary product identity is `client_id + erp_model_color_id`.
- Be conservative: wrong product images are more damaging than no image.
- Jobs must be idempotent and safe to retry.
- The API dispatches the configured ingest job with a persisted request id; raw payload ingestion must still be supported.
- Search providers must be configured through `ProductImageSearchProvider` records and resolved through `SearchProviderManager`.
- AI and Playwright are optional support layers. Do not make them required for default tests or package boot.
- Store explainability: raw payload, context, candidate scores and audit events must tell why a decision happened.

## Testing Rules

- Use SQLite in-memory for Eloquent tests.
- Keep fake providers and in-memory stores for deterministic unit/feature tests.
- Live browser/search/LLM tests must be opt-in and skipped cleanly without credentials or URLs.
- Before declaring completion, run:
  - Composer validate strict.
  - PHPUnit Unit, Feature and E2E.
  - Sidecar `npm test` when sidecar code is touched.

## Documentation Rules

- Update `docs/PROGRESS.md` during long tasks.
- Update `docs/LESSON.md` when a new trap or project rule is discovered.
- Keep `README.md` strong enough for open-source community adoption: badges, value proposition, architecture, install, quickstart, config, tests, roadmap and contributing.
