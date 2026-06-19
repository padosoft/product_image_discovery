---
title: Runbooks
description: Operational runbooks for common production issues.
---

# Runbooks

## Provider Failure Spike

::: steps
1. Check audit events for provider code, timeout, and error patterns.
2. Disable the failing provider row if it is harming throughput.
3. Confirm queue workers are not saturated.
4. Re-run the debug flow with a known product after the provider recovers.
:::

## Too Many Review Candidates

::: steps
1. Sample candidates by source and provider.
2. Compare identity fields with page text and image evidence.
3. Tighten trusted-source rules or thresholds.
4. Keep examples for tests or operator training.
:::

## Broken Downloads

::: steps
1. Confirm the image URL is stable outside the worker.
2. Check storage disk permissions.
3. Review content type and file size constraints.
4. Reject sources that hotlink unstable assets.
:::
