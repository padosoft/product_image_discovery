# Product Image Discovery - Project Rules

## Product Rules

- Prefer precision over coverage. A missing image is acceptable; a wrong image is not.
- Never auto-publish a candidate without a strong score, a trustworthy source signal and no hard rejection reason.
- Keep the matching pipeline explainable. Every decision should be reproducible from stored candidate data, scores and audit events.
- Treat `client_id + erp_model_color_id` as the primary product-color identity.
- Keep client-specific configuration first-class: settings, trusted sources and provider configuration can vary by client.

## Engineering Rules

- This is a Laravel package, not a full application.
- Keep runtime dependencies minimal and package-friendly. Do not assume Horizon, Redis, MySQL, Playwright or an LLM are installed in every consumer app.
- Bind runtime behavior through contracts when it touches persistence, search providers, audit logging or side effects.
- Keep jobs idempotent. Retrying a job must not duplicate requests, candidates or source pages.
- Make external systems opt-in: search APIs, browser rendering, file downloads and LLM calls must be mockable or replaceable.
- Do not put secrets in audit logs. Use `SecretRedactor` for provider configs and event metadata.

## Testing Rules

- Default test database: SQLite in-memory.
- Default PHP command:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite Unit,Feature,E2E
```

- Default sidecar command:

```powershell
npm test
```

- Live sidecar/browser tests are opt-in and require `SIDECAR_E2E_URL`.
- Live LLM/provider tests are opt-in and must be skipped when credentials are absent.
- Do not weaken tests to make a suite green. Fix the contract or make the external dependency explicitly optional.

## Documentation Rules

- Keep `docs/PROGRESS.md` current during long work.
- Keep `docs/LESSON.md` current when discovering environment traps, design rules or fixed integration bugs.
- Keep `README.md` useful for an external open-source user, not only for this local workspace.
- Keep claims precise: implemented, optional, AI-ready and roadmap features must be distinguishable.
