---
title: Provider Hygiene
description: Keep search providers safe, useful, and auditable.
---

# Provider Hygiene

Provider hygiene keeps discovery lawful, reliable, and debuggable.

| Practice | Reason |
| --- | --- |
| Store API keys outside user-facing responses | Prevent secret leaks. |
| Set provider timeouts | Avoid stuck pipeline jobs. |
| Use priority intentionally | Keep high-trust sources first. |
| Disable failing providers quickly | Protect worker capacity. |
| Log redacted provider context | Preserve audit value without exposing secrets. |

::: callout info
The search layer is consumed via `padosoft/laravel-ai-search-providers`; this package keeps a compatibility model for the legacy table name.
:::
