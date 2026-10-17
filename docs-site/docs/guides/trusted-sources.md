---
title: Trusted Sources
description: Configure source policy for safer discovery.
---

# Trusted Sources

Trusted sources constrain the search space to places you are allowed to query and review. They also provide a positive signal during scoring.

## Source Types

| Type | Example | Notes |
| --- | --- | --- |
| Brand | `brand.example` | Strong identity signal when product pages are structured. |
| Supplier | `supplier.example` | Often best for B2B catalog feeds. |
| Marketplace | `marketplace.example` | Useful but can contain duplicate or mixed listings. |
| Search provider | Brave, Tavily, Exa, Firecrawl | Should be rate limited and audited. |

::: callout warning
Trusted does not mean automatically accepted. Product-color identity still needs evidence.
:::

## Recommended Policy

::: steps
1. Prefer official brand and supplier URLs.
2. Add exact URL patterns before broad domain rules.
3. Store source decisions in the database, not only environment variables.
4. Revisit source quality after catalog teams reject candidates.
:::

## Research New Sources With Parallel

When the pipeline finds no convincing match, use [Parallel Search MCP](https://docs.parallel.ai/integrations/mcp/search-mcp) to research product pages before configuring a new trusted source. It provides free web search and page fetching without an account or API key; anonymous search uses Fast mode. This is an optional, manual catalog-research step. The Laravel pipeline continues to use its configured search providers.

### Connect a Research Client

With [Claude Code](https://code.claude.com/docs/en/mcp) installed, run these commands from your catalog-research workspace:

```bash
claude mcp add --scope local --transport http parallel-search \
  https://search.parallel.ai/mcp \
  --header "User-Agent: product-image-discovery-source-research/1.0"
claude mcp list
```

Confirm that `parallel-search` is connected, then open Claude Code in the same workspace. The local scope keeps this connection specific to that workspace. For other HTTP MCP clients, use the [Parallel installation guide](https://docs.parallel.ai/integrations/mcp/search-mcp#installation). Anonymous access is intended for exploration and light use; higher rate limits require a Parallel account.

### Search for Product-Color Evidence

Use only sources you are authorized to access, following the [responsible-use guidance](/operations/security/). Start with the brand and strongest available identifier, then add color evidence. For example, adapt this prompt for the Herno product used in the package examples:

```text
Use Parallel web_search to find official brand or supplier product pages
for Herno PI002223D in CAMMELLO. Search the exact model code first, then
the model code with the color. Prefer official brand and supplier pages.

Use web_fetch on shortlisted, permitted pages when search excerpts do
not establish the product identity. Return a table with the page URL,
domain, quoted model/identifier evidence, quoted color evidence, and
the time checked. Mark missing or conflicting evidence explicitly.
Do not infer an exact color match from a model-only result. Keep image
usage permission separate from the product match.
```

Search ranking and fetched text are leads for a reviewer. Check that each page describes the requested model and color, rather than a category, another variant or an unrelated product. A text match alone does not verify the image or grant permission to download or publish it.

### Record the Reviewed Source

Use the existing [trusted-source API](/reference/api/) in your host application to record a source after review:

| Field | What to record |
| --- | --- |
| `client_id`, `domain` | The intended client and reviewed source hostname. |
| `source_name`, `source_type` | A recognizable name and the source's role, such as brand or supplier. |
| `notes` | Product-page URLs, identity/color evidence, unresolved conflicts and when they were checked. |
| `permission_reference` | Independently confirmed permission or another valid legal basis, not a search result or an AI assertion. |
| `url_patterns` | Only verified, stable page/image URL templates supported by that supplier; leave empty when unknown. |

Set `allow_search`, `allow_scraping` and `allow_download` deliberately according to the source's terms. Keep `allow_auto_publish=false` and `requires_manual_review=true` while evaluating it. A higher `trust_score` must not substitute for product-color evidence.

Submit or retry the normal identity-based discovery request and review the resulting candidates through the [pipeline workflow](/guides/pipeline-workflow/). Keep ERP request payloads focused on product identity; MCP results are research notes, not a candidate-import format. This workflow does not register Parallel as a Laravel search-provider driver.
