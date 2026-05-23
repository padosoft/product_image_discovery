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
  - Latest result with live `BRAVE_SEARCH_API_KEY`, live `ANTHROPIC_API_KEY` and remote image attachment enabled: PASS, `60 tests`, `276 assertions`, `1 skipped`.
- The skipped E2E/live tests are opt-in and require `SIDECAR_E2E_URL` or an AI provider key. When `ANTHROPIC_API_KEY` is present, only the sidecar contract remains skipped unless `SIDECAR_E2E_URL` is also configured in the process environment.
- Verified sidecar gate:
  - `npm test` inside `sidecar`
  - Latest result: PASS, `7/7`.
- Verified opt-in live Anthropic AI verifier:
  - `& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite E2E --filter LiveProductImageAiVerifierTest`
  - Latest result with `ANTHROPIC_API_KEY` and `PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE=true`: PASS, `1 test`, `9 assertions`.
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
- ERP request examples must not include image URLs or known product page URLs. The package's purpose is to discover those from product identity data.
- Live E2E caught an enum-cast bug that unit tests missed: request `rejection_reason` must contain only `ProductImageDiscoveryRejectionReason` values. Manual-review decision reasons such as `source_not_auto_publishable` belong in decision context/audit, not in the enum column.
- Real fashion product names often use multi-word model names rather than compact model codes. Matching must support phrase-based model matches while preserving anti-false-positive checks for similar code mismatches.
- Add regression tests at the layer where a bug appears. The enum issue needed an Eloquent feature test, not only a pure unit test.
- Brave live testing now covers both direct provider execution and DB-configured `SearchProviderManager` execution. Full live testing covers search, extraction, verification, download and quality assessment.
- Laravel AI SDK v0.6.5 supports `Lab::Anthropic`, `Lab::OpenAI` and `Lab::OpenRouter`. Use the SDK's env names (`ANTHROPIC_URL`, `OPENAI_URL`, `OPENROUTER_URL`) rather than package-specific `*_BASE_URL` names.
- Keep AI verification optional. It should enrich `ai_analysis` and visual confidence, but deterministic checks remain the safety gate.
- Remote image attachments are provider/model dependent. Keep `PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE=false` by default and make live attachment tests opt-in.
- For Anthropic live verification, stable model ids used locally: `claude-sonnet-4-5-20250929` for vision verification and `claude-haiku-4-5-20251001` for description-style support.
- Codex/local skills must include YAML frontmatter delimited by `---`; otherwise the agent runtime skips the skill before any repo work starts.
- Brave image search payloads are not shaped like web search payloads. Use `properties.url` for the actual image URL, `url` for the page URL, and keep `page_fetched` as provider metadata only.
- Fashion product codes are often concatenated with fabric/color suffixes (`PI002223D12017Z2157`). Prefix matching is useful for long normalized codes, but keep it disabled for short codes to avoid false positives.
- In Eloquent candidates, the default `final_score` can be `0` before verification. Debug tooling must not treat a `candidate` row with score `0` as already ranked; compute a deterministic pre-rank before spending live AI calls.
- Testbench resolves relative command arguments from its skeleton Laravel app. For package-local debug files, pass absolute request/report paths.
- A Laravel package root does not have an `artisan` file. `php artisan ...` is for host Laravel apps; from the package repo use `vendor/bin/testbench ...`.
- Live LLM vision can disagree conservatively on ERP color naming when the image URL uses a numeric vendor color code. Preserve the AI disagreement in the report and keep the result in manual review unless a trusted source/policy resolves the ambiguity.
- Live image downloads can be blocked by Cloudflare/third-party anti-bot pages after search and verification succeed. Live E2E tests should try alternate verified candidates and skip cleanly only when all external downloads are blocked.
- Testbench can reuse request ids across runs because the debug app often uses SQLite in-memory, while `vendor/orchestra/testbench-core/laravel/storage/...` persists. Debug runs must clean `product-image-discovery/{request_id}` storage when using `--fresh` or stale files from older live tests can be mistaken for newly downloaded candidates.
- For fashion colors, `cammello/camel/tan/beige/biscuit/light brown` should be treated as the same visual color family. A page title containing both `marrone` and `cammello` must not become a hard `WRONG_COLOR` when the requested ERP color is `cammello`.
- Vision AI should evaluate the attached image first for visible product type and visible color. Numeric vendor color codes in URLs/DOM are supporting metadata, not color names; high-confidence visible mismatches should become hard mismatch evidence, while low-confidence AI disagreement should not override deterministic brand/model/color matches.
- Real EAN/barcode/GTIN values are very strong product identity signals. Accept common aliases (`barcode`, `bar_code`, `gtin`, `gtin13`, `gtin14`) but normalize them into `ean`; exact matches should be strong matches, while structured GTIN mismatches should be wrong-product risk.
- Search providers grow fast — extract a common `AbstractHttpSearchProvider` once you have two HTTP-based drivers, otherwise the parsing helpers (`pickUrl`, `dotGet`, `extractDomain`, `normalize{Int,Float}`, `applySiteFilter`) end up copy-pasted in every new driver. The abstract is ~140 LOC and saves ~80 LOC per provider.
- Tavily's `images` payload is union-typed: it can be a `string[]` (legacy) or a `{url, description?}[]` (current with `include_image_descriptions=true`). Normalize both branches before mapping; assuming one shape breaks the other silently.
- Tavily returns top-level `images[]` decoupled from `results[]`. To recover a useful `page_url` and `title` for each image, index `results[]` by domain and join image-domain → result. When the join misses (image hosted on a CDN unrelated to any result), fall back to `image_url` as both `page_url` and source domain.
- New search providers must keep the request body free of `null`/empty values, because some providers (Tavily, Exa, WebSearchAPI) treat `include_domains: []` differently from a missing key. Use `array_filter` to drop empties before sending.
- Live provider keys must stay in the local `.env` only. `.env.example` documents the variable names with empty values. The `ReadsLocalEnv` trait reads keys from both `getenv()` and the on-disk `.env` so live E2E tests work without populating the Testbench environment.
- Opt-in live tests should skip cleanly on transient external-state failures (insufficient credits, quota throttling, anti-bot blocks). Catching `Laravel\Ai\Exceptions\InsufficientCreditsException` in `LiveProductImageAiVerifierTest` keeps the gate green when an AI provider account is temporarily out of credits. Same principle applies to live search providers: a 429 or quota-exhausted response should skip, not error.
- Exa.ai returns N images per result via `results[].extras.imageLinks` instead of a single top-level images array. The right pattern is 1:N flattening — emit one candidate per image URL while preserving the result's `url` as `page_url` and `title`. Dedupe URLs because the primary `image` field often duplicates the first entry in `extras.imageLinks`.
- Node `--test-isolation=none` is the stable flag form (Node 24+). Node 22 only exposes `--experimental-test-isolation`. CI Node version must be ≥24 when sidecar scripts use the stable flag, otherwise tests fail with `node: bad option`. The `engines.node` field in `sidecar/package.json` is advisory only — CI runners need an explicit `node-version: '24'` in `actions/setup-node`.
- `actions/setup-node` with `cache: 'npm'` and `cache-dependency-path` fails hard when the lockfile is missing. For repos without committed lockfiles, drop the cache config and run `npm install` instead of `npm ci`. Don't try to silently fall back inside one job step — the cache plugin errors out before the script runs.
- Firecrawl v2 `/search` accepts `sources` as an array of objects (`[{type:"web"}, {type:"images"}]`), not bare strings. Sending `["web","images"]` errors out with an unhelpful schema message. Firecrawl also has native `includeDomains`/`excludeDomains` — prefer that to the `site:` operator in the query string for cleaner intent. Default timeout in seeder is 60s because the synchronous /v2/search endpoint can take 20-40s on free tier.
- WebSearchAPI.ai's documented endpoint is `POST /ai-search` and only exposes Google-backed organic web results — there is no documented image search endpoint as of 2026-05-23. Implement the driver as `supportsImageSearch()=false` (web-only) and let `SearchProviderManager` skip it for image queries; the rest of the pipeline can still harvest images from the returned page URLs. Pre-implementation, search the actual API docs (`/docs/search-api`) rather than trusting third-party comparison pages that may infer endpoints from other providers.
- DuckDuckGo HTML lite (`html.duckduckgo.com/html/`) is unauthenticated but its `.result__a` `href` values are usually `//duckduckgo.com/l/?uddg=<URLENCODED_DEST>&rut=...` redirects — `urldecode($params['uddg'])` recovers the real destination. Always check for direct `http(s)://` hrefs first because some results bypass the redirect. Use POST with form-encoded `q` (not GET) to support long product queries.
- HTML scraper providers should be tested against a committed fixture file, not an inline string, so the fixture can be enriched with realistic edge cases (uddg redirects, direct hrefs, `result--no-result` placeholder containers that must be skipped) over time. Iterate via `.//\*[contains(concat(' ', normalize-space(@class), ' '), ' class-name ')]` XPath so multi-class containers like `result results_links results_links_deep web-result` match a query for `.result`.
- For HTML-scraper live tests against IP-rate-limited endpoints (DuckDuckGo, generic anti-bot services), skip in CI (via `getenv('CI')`) and also skip cleanly on 403/429/503 responses. Avoid hammering shared runner IPs that could get the host's range banned.
- When you plan to extract a layer into a separate composer package later, don't rush the rename. Two preparation moves are enough to make the future extraction a `git mv`: (1) extract a generic interface for the cross-cutting concern that ties the layer to the host (e.g. `SearchEventLoggerInterface` decouples `SearchProviderManager` from the domain audit logger), and (2) parameterize any "hard-wired" model/table references through a constructor override + config lookup with a sensible default. The actual rename of namespace and DTO classes belongs to the extraction PR itself, where it is mechanical. Pre-renaming inside the same namespace adds churn without reducing future friction.

## Future Session Rules

- Update `docs/PROGRESS.md` whenever a meaningful phase starts or finishes.
- Update this file whenever a new environment trap, architecture rule, testing rule or production risk is discovered.
- Re-run the full PHP and sidecar gates before declaring a task complete.
- Keep README claims aligned with implemented code. If a feature is only AI-ready or planned, label it that way.
