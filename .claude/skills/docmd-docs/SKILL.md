---
name: docmd-docs
description: Keep the docmd documentation site synchronized with product_image_discovery source, README, API routes, commands, and configuration.
---

# docmd Docs Sync

Use this skill when changing public behavior, API routes, commands, configuration, database tables, queue jobs, search providers, AI verification, or operational guidance.

1. Update Markdown only under `docs-site/docs`.
2. Keep every page listed in `docs-site/docmd.config.json` navigation.
3. Do not add MDX, JSX, raw HTML, or `::: button`.
4. Reuse docmd containers such as `callout`, `tabs`, `steps`, `collapsible`, `grids`, `grid`, and `card`.
5. Run `npm run check` and `npm run build` from `docs-site`.
6. Confirm `_site/index.html`, `_site/llms.txt`, `_site/sitemap.xml`, and search artifacts exist before publishing.
