# Search Providers Roadmap

Tracker for the multi-PR effort that adds five new search providers (Tavily, Exa.ai, Firecrawl, WebSearchAPI, DuckDuckGo) to the package, plus the supporting foundation work (abstract HTTP provider, env trait, CI pipeline, README revamp).

## Status legend

- ⬜ Pending
- 🟡 In progress
- ✅ Done

## Workflow per PR

1. Branch `feat/search-provider-{name}` from `main`.
2. Implement provider class + driver registration + seed + tests + env vars + docs.
3. Local PHP gate: `vendor/bin/phpunit --testsuite Unit,Feature,E2E` must PASS (live test skipped if env key missing).
4. Local Copilot review on the diff.
5. Push, open PR, wait for green CI + Copilot PR review.
6. Run 1× live smoke with real key (from local `.env`) to confirm the provider returns at least one result.
7. Update `docs/PROGRESS.md` and append any new trap to `docs/LESSON.md`.
8. Merge to `main`.

## PR plan

| # | PR | Status | Provider | Driver | Notes |
|---|---|---|---|---|---|
| 1 | `feat/search-provider-tavily` | ✅ | Tavily | `tavily` | Includes foundation: `AbstractHttpSearchProvider`, `ReadsLocalEnv` trait, `.github/workflows/ci.yml`, README revamp (Quick Start + Supported Providers + TOC + Roadmap refresh). Merged in PR #3. |
| 2 | `feat/search-provider-exa` | 🟡 | Exa.ai | `exa` | `POST /search` + `contents.extras.imageLinks`. Auth via `x-api-key`. Flattens N image links per result into N candidates. |
| 3 | `feat/search-provider-firecrawl` | ⬜ | Firecrawl | `firecrawl` | `POST /v2/search` with `sources:["web","images"]`. Bearer auth. Site filter via `site:` operator in the query. |
| 4 | `feat/search-provider-websearchapi` | ⬜ | WebSearchAPI.ai | `websearchapi` | `GET /api/v1/search` with `engine=google_images`. Site filter via `site:` operator. |
| 5 | `feat/search-provider-duckduckgo` | ⬜ | DuckDuckGo | `duckduckgo` | HTML lite `html.duckduckgo.com/html`. Web-only (`supportsImageSearch()=false`). No API key required. Live test skipped in CI. |

## Per-PR gates

Every PR must satisfy ALL of:

| Gate | Required check |
|---|---|
| **Unit gate** | All `tests/Unit/Search/*Test.php` PASS, including `BraveSearchProviderTest` (unchanged). New provider has ≥4 unit cases (parse OK, empty payload, HTTP 4xx, site filter / peculiarity). |
| **Feature gate** | `tests/Feature` PASS (unchanged). No regression in API/pipeline tests. |
| **E2E gate** | New `LiveXxxSearchProviderTest` skipped cleanly when env key absent. Manual run with real key returns ≥1 result for the Nike smoke query. |
| **CI gate** | `.github/workflows/ci.yml` green on both `php-tests` (matrix 8.3, 8.4) and `sidecar-tests` jobs. |
| **Composer gate** | `composer validate --strict` PASS. |
| **Doc gate** | README "Supported Search Providers" lists the new provider; `.env.example` updated; `docs/PROGRESS.md` session entry added; `docs/LESSON.md` updated with any new trap discovered. |
| **Copilot review** | Local + PR-level Copilot review with no blocking comments. |

## Out of scope for this roadmap

- `serpapi` and `google_custom_search` drivers (seeded templates only).
- Cache layer for search results.
- Rate-limit enforcement (`rate_limit_per_minute` is stored in DB but not yet enforced at runtime — independent future work).
- Composer/PHP version bumps.

## Quick-resume protocol

If a session is interrupted mid-PR:

1. Open this file → find the row with status 🟡.
2. Open `docs/PROGRESS.md` → most recent session block lists the sub-task done last.
3. Continue from the next sub-task in the PR body in this roadmap.
