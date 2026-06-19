---
title: Decision Records
description: Architecture decision records for the package.
---

# Decision Records

::: collapsible ADR 001: Prefer conservative decisions
Status: accepted.

The package optimizes for low false positives. Ambiguous candidates should move to review or rejection instead of automatic acceptance because the cost of a wrong catalog image is high.
:::

::: collapsible ADR 002: Keep providers data-backed
Status: accepted.

Search providers are rows, not hard-coded service bindings. This allows operations teams to enable, disable, prioritize, and tune providers without code deployments.
:::

::: collapsible ADR 003: Keep Playwright outside PHP
Status: accepted.

Browser rendering runs in an optional Node sidecar. PHP workers remain focused on orchestration, persistence, and decisions.
:::

::: collapsible ADR 004: AI is optional evidence
Status: accepted.

AI and vision verification can improve candidate reasoning, but deterministic source, identity, and quality checks remain first-class controls.
:::

::: collapsible ADR 005: Keep audit data explicit
Status: accepted.

Requests, candidates, and events retain enough context to explain why a candidate was accepted, rejected, or sent to manual review.
:::
