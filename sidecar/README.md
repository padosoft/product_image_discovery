# Playwright Sidecar

Servizio HTTP interno per rendering pagine prodotto.

## Scripts

- `npm start` avvia il server.
- `npm test` esegue i test offline Node (`node --test`).

## Endpoint

- `GET /health`
- `POST /render`

Request minima:

```json
{
  "url": "https://example.com/product/sku-123",
  "wait_until": "networkidle",
  "timeout_ms": 15000,
  "extract": {
    "html": true,
    "images": true
  }
}
```

Response shape:

```json
{
  "ok": true,
  "final_url": "https://example.com/product/sku-123",
  "html": "<html>...</html>",
  "images": [
    {
      "url": "https://example.com/image.jpg",
      "width": 1200,
      "height": 1600,
      "alt": "..."
    }
  ],
  "error": null
}
```

Errore:

```json
{
  "ok": false,
  "final_url": null,
  "html": null,
  "images": [],
  "error": {
    "code": "TIMEOUT",
    "message": "Render timeout after 15000 ms"
  }
}
```

## Config env

Copy `sidecar/.env.example` when you want a local env file:

```bash
cp .env.example .env
```

- `SIDECAR_HOST` (default `127.0.0.1`)
- `SIDECAR_PORT` (default `3100`)
- `SIDECAR_SHARED_SECRET` (opzionale, header `x-sidecar-secret` o `Authorization: Bearer ...`)
- `SIDECAR_DEFAULT_TIMEOUT_MS` (default `15000`)
- `SIDECAR_MAX_TIMEOUT_MS` (default `30000`)

## Note Playwright

Il sidecar prova Playwright come renderer primario.  
Se Playwright package/browser non è disponibile, usa fallback statico HTTP+HTML extraction.
