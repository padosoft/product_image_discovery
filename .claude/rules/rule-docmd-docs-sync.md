# Rule: docmd Docs Stay In Sync

When repository behavior changes, update the docmd site in `docs-site/` in the same branch.

- Source docs are Markdown files in `docs-site/docs/`.
- Site config is `docs-site/docmd.config.json`.
- Navigation must include every docs page.
- Raw HTML, MDX, JSX, and `::: button` are not allowed.
- Run `npm run check` and `npm run build` before completing docs-related work.
