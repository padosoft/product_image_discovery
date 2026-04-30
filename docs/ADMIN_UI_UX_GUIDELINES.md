# Product Image Discovery Admin UI/UX Guidelines

Guida per progettare e implementare nel backoffice ecommerce Laravel una sezione admin dedicata a `padosoft/product-image-discovery`.

Il package resta headless. Questa UI deve vivere nell'admin ecommerce esistente, usare Blade/Laravel e JavaScript vanilla, e consumare le API del package piu alcuni endpoint applicativi da aggiungere nel progetto host.

## Obiettivo Della UI

La sezione admin deve permettere a un operatore catalogo, a un junior developer e a un admin tecnico di capire rapidamente:

- quali richieste di discovery sono state inviate;
- quale immagine e stata trovata, da dove arriva e perche e stata scelta;
- quali candidati sono stati scartati e per quale motivo;
- quali score, evidenze, AI notes, quality checks e audit events hanno portato alla decisione;
- quali provider/search/trusted sources/thresholds sono configurati;
- se le credenziali sono presenti senza mostrarne il valore;
- se le API e la pipeline funzionano con un test guidato;
- come eseguire un debug completo caricando un JSON prodotto o creandolo da campi guidati.

La UI deve essere operativa, densa e leggibile. Non deve sembrare una landing page. Deve essere una console admin per review, diagnosi e configurazione.

## Principi Di UX

- Precisione prima di velocita: evidenziare sempre rischi di falso positivo.
- Ogni decisione deve essere spiegabile: mostrare score, evidence, mismatch, AI output e audit.
- Nessun segreto in chiaro: API key e secret sono write-only.
- L'operatore deve poter approvare, rigettare, ritentare e scaricare/reportare senza uscire dalla pagina.
- I dati importanti devono essere confrontabili: immagine candidata, prodotto ERP, pagina sorgente, score e AI notes devono stare nello stesso contesto visivo.
- Usare tabelle dense per liste operative, pannelli per dettaglio, card solo per singoli candidati/metriche ripetute.
- Evitare nested card. Radius massimo consigliato: 8px.
- Palette neutra da admin, con colori solo per stato/score/rischio.
- Tutte le azioni distruttive o irreversibili richiedono conferma.

## Struttura Di Navigazione

Voce menu principale: `Product Images`.

Sottosezioni consigliate:

- `Overview`
- `Requests`
- `Manual Review`
- `Debug Flow`
- `Debug Reports`
- `Configuration`
- `Providers`
- `Trusted Sources`
- `API Test`
- `Health`

Se lo spazio menu e limitato:

- `Product Images`
  - tab interna `Requests`
  - tab interna `Debug`
  - tab interna `Config`
  - tab interna `Health`

## Endpoint Del Package Disponibili

Prefix configurabile: `PRODUCT_IMAGE_DISCOVERY_ROUTE_PREFIX`, default:

```text
/api/product-image-discovery
```

Richiedono middleware API e abilita Sanctum configurate nel package.

### Requests

```http
GET /api/product-image-discovery/requests/search
POST /api/product-image-discovery/requests
GET /api/product-image-discovery/requests/{request}
POST /api/product-image-discovery/requests/{request}/retry
```

Filtri supportati da `requests/search`:

```text
client_id
status
brand
supplier
erp_model_id
erp_model_color_id
ean
barcode
source_domain
rejection_reason
min_score
max_score
has_candidates
has_selected_image
manual_review_required
created_from
created_to
updated_from
updated_to
sort_by: created_at|updated_at|final_score|status|brand|supplier|client_id
sort_direction: asc|desc
per_page: 1..100
```

### Candidates

```http
GET /api/product-image-discovery/requests/{request}/candidates
POST /api/product-image-discovery/requests/{request}/candidates/{candidate}/approve
POST /api/product-image-discovery/requests/{request}/candidates/{candidate}/reject
```

Reject payload:

```json
{
  "reason": "WRONG_COLOR",
  "notes": "Visible image is not the requested product-color variant."
}
```

### Settings

```http
GET /api/product-image-discovery/settings
POST /api/product-image-discovery/settings
GET /api/product-image-discovery/settings/{setting}
PUT /api/product-image-discovery/settings/{setting}
DELETE /api/product-image-discovery/settings/{setting}
```

Setting payload:

```json
{
  "client_id": null,
  "setting_key": "manual_review_threshold",
  "setting_value": 60,
  "value_type": "integer",
  "description": "Minimum score for manual review",
  "is_active": true
}
```

### Search Providers

```http
GET /api/product-image-discovery/search-providers
POST /api/product-image-discovery/search-providers
GET /api/product-image-discovery/search-providers/{searchProvider}
PUT /api/product-image-discovery/search-providers/{searchProvider}
DELETE /api/product-image-discovery/search-providers/{searchProvider}
```

Provider payload:

```json
{
  "code": "brave-live",
  "name": "Brave Search",
  "driver": "brave",
  "base_url": "https://api.search.brave.com",
  "api_key": "write-only-new-key",
  "api_secret": null,
  "config": {
    "supports_image_search": true,
    "supports_site_filter": true,
    "max_results_per_request": 20
  },
  "priority": 1,
  "timeout_seconds": 20,
  "rate_limit_per_minute": 60,
  "is_active": true
}
```

Response espone solo:

```text
has_api_key
has_api_secret
```

Non mostrare mai `api_key` o `api_secret`.

### Trusted Sources

```http
GET /api/product-image-discovery/trusted-sources
POST /api/product-image-discovery/trusted-sources
GET /api/product-image-discovery/trusted-sources/{trustedSource}
PUT /api/product-image-discovery/trusted-sources/{trustedSource}
DELETE /api/product-image-discovery/trusted-sources/{trustedSource}
```

Trusted source payload:

```json
{
  "client_id": null,
  "domain": "herno.com",
  "source_name": "Herno official",
  "source_type": "brand",
  "trust_score": 95,
  "allow_search": true,
  "allow_scraping": true,
  "allow_download": true,
  "allow_auto_publish": false,
  "allow_description_import": false,
  "respect_robots_txt": true,
  "requires_manual_review": true,
  "rate_limit_per_minute": 30,
  "brand_scope": ["Herno"],
  "supplier_scope": [],
  "url_patterns": ["https://*.herno.com/*"],
  "permission_reference": "Contract or permission note",
  "notes": "Official source, manual review required.",
  "is_active": true
}
```

## Endpoint Applicativi Da Aggiungere Nel Tuo Admin

Questi non sono nel package. Vanno implementati nel tuo ecommerce admin come wrapper applicativi, usando modelli, Artisan command, storage e configurazione del package.

### Dashboard Summary

```http
GET /admin/product-image-discovery/dashboard-summary
```

Risposta consigliata:

```json
{
  "counts": {
    "total": 1240,
    "manual_review": 38,
    "ready_to_publish": 412,
    "failed": 12,
    "no_candidates_found": 71
  },
  "score_buckets": {
    "0_59": 83,
    "60_79": 146,
    "80_100": 398
  },
  "provider_status": [
    {
      "code": "brave-live",
      "active": true,
      "has_api_key": true,
      "last_test_status": "ok",
      "last_test_at": "2026-04-30T14:43:06+02:00"
    }
  ],
  "queue_status": {
    "ingest": "ok",
    "search": "ok",
    "verify": "ok",
    "download": "ok"
  }
}
```

### Audit Events

```http
GET /admin/product-image-discovery/requests/{request}/events
```

Serve per timeline completa: ingest, search, extract, verify, download, quality, approve/reject, retry.

### Candidate Image Preview

```http
GET /admin/product-image-discovery/candidates/{candidate}/image
```

Deve restituire una signed URL o stream protetto dell'immagine locale se `local_original_path` esiste. Se manca, usare `image_url` remoto come fallback, con indicatore chiaro `remote`.

### Debug Flow Run

```http
POST /admin/product-image-discovery/debug-runs
GET /admin/product-image-discovery/debug-runs/{debugRun}
GET /admin/product-image-discovery/debug-runs/{debugRun}/report
```

Il backend puo:

- salvare il JSON request in `storage/app/product-image-discovery/debug/requests`;
- lanciare `product-image-discovery:debug-flow`;
- salvare report JSON;
- restituire stato `queued|running|completed|failed`;
- streammare output se il tuo admin supporta polling, SSE o websocket.

Payload consigliato:

```json
{
  "request": {
    "client_id": 1,
    "erp_model_id": "HERNO-PI002223D",
    "erp_model_color_id": "HERNO-PI002223D-CAMMELLO",
    "brand": "Herno",
    "supplier": "Herno",
    "supplier_sku": "PI002223D",
    "model_code": "PI002223D",
    "color_code": "CAMMELLO",
    "color_name": "Cammello",
    "category": "Donna > Maglie e camicie > Felpe e maglie",
    "material": "100% Nylon",
    "metadata": {
      "title": "Cappa In Nylon Ultralight Cammello",
      "description": "Cappa da donna in nylon ultralight Herno modello PI002223D."
    }
  },
  "options": {
    "fresh": true,
    "clean_storage": true,
    "max_candidates": 10,
    "download_all": false,
    "exhaustive": false,
    "good_score": 65,
    "no_download": false
  }
}
```

### Provider Health And Credential Status

```http
GET /admin/product-image-discovery/health
POST /admin/product-image-discovery/search-providers/{provider}/test
POST /admin/product-image-discovery/ai/test
POST /admin/product-image-discovery/storage/test
POST /admin/product-image-discovery/queue/test
```

La UI deve mostrare:

- provider attivo/non attivo;
- API key presente/non presente;
- secret presente/non presente;
- ultimo test riuscito/fallito;
- latenza;
- messaggio errore redatto;
- modello AI configurato;
- remote image attachment attivo/non attivo;
- storage disk scrivibile;
- queue dispatch ok.

Non mostrare mai chiavi, header o token.

### API Test Workbench

```http
POST /admin/product-image-discovery/api-test
```

Wrapper per eseguire test guidati:

- ping API package;
- create request test;
- search request;
- retry request;
- provider test;
- AI verification test se configurata;
- storage test;
- queue test.

## Stati E Colori

Usare badge con testo, icona e colore. Il colore non deve essere l'unico segnale.

Request status:

- `pending`, `queued`: grigio/blu, icona clock.
- `searching`, `extracting`, `verifying`, `quality_checking`: blu, spinner o progress.
- `candidates_found`, `matched`, `downloaded`: blu/verde, icona check parziale.
- `quality_passed`, `ready_to_publish`, `published`: verde, icona check.
- `manual_review`: amber, icona alert.
- `no_candidates_found`: grigio/amber, icona search off.
- `rejected`, `failed`: rosso, icona x/alert.

Candidate status:

- `candidate`: grigio/blu.
- `verified_match`: verde.
- `quality_passed`: verde.
- `selected`: verde pieno.
- `low_score_rejected`: grigio/rosso.
- `wrong_product`, `wrong_color`: rosso.
- `quality_failed`: amber/rosso.
- `rejected`: rosso.

Score:

- `0-59`: rosso, rischio alto.
- `60-79`: amber, manual review.
- `80-100`: verde, confidenza alta.
- score mancante: grigio, `not scored`.

Mostrare sempre il numero accanto al colore.

## Componenti UI Base

### Layout

- Header pagina con titolo, conteggi rapidi e azioni principali.
- Tab orizzontali per sezioni correlate.
- Sidebar filtri collassabile per liste lunghe.
- Content grid a due colonne nei dettagli: prodotto/request a sinistra, candidati/evidence a destra.
- Drawer laterale per dettagli tecnici JSON senza perdere contesto.

### Componenti Visivi

- `StatusBadge`: stato request/candidate.
- `ScorePill`: score numerico con colore.
- `ScoreBreakdownBar`: source/textual/structured/visual/quality/risk.
- `EvidenceList`: matches, mismatches, strong matches.
- `RiskFlags`: wrong color, wrong product, source not allowed, low quality.
- `ImageTile`: immagine candidata, dimensioni, mime, source domain, status.
- `ImageCompare`: selected/best/current candidate.
- `JsonViewer`: collapse/expand, copy path, copy JSON.
- `Timeline`: audit events in ordine cronologico.
- `ProviderBadge`: driver, active, has key, last test.
- `SecretStatus`: `Configured`, `Missing`, `Write-only update`.
- `SkeletonTable`: loading table rows.
- `SkeletonPanel`: loading details.
- `Toast`: success/error/warning.
- `ConfirmModal`: approve/reject/delete/retry.
- `EmptyState`: no results, no candidates, no providers.
- `InlineError`: validation and API errors next to fields.

### Icone Consigliate

Usare l'icon set gia presente nel tuo admin. Se serve introdurne uno, preferire icone SVG leggere in sprite.

Mapping:

- search: lente
- retry: refresh
- approve: check
- reject: x
- manual review: alert triangle
- settings: cog
- provider: plug/cloud
- trusted source: shield
- API key present: key/check
- API key missing: key/x
- JSON report: file code
- download: download
- external URL: external link
- AI: spark/brain se gia disponibile
- queue: list/clock

## JavaScript Vanilla: Architettura Consigliata

Usare ES modules o moduli organizzati, evitando logica globale sparsa.

Struttura suggerita nell'app host:

```text
resources/js/product-image-discovery/
  api-client.js
  state.js
  router.js
  components/
    data-table.js
    filter-bar.js
    status-badge.js
    score.js
    image-tile.js
    json-viewer.js
    timeline.js
    modal.js
    toast.js
    form-builder.js
  pages/
    overview.js
    requests-index.js
    request-detail.js
    debug-flow.js
    debug-report-viewer.js
    configuration.js
    api-test.js
```

### API Client

Responsabilita:

- centralizzare `fetch`;
- aggiungere CSRF header o bearer token secondo il tuo admin;
- gestire `Accept: application/json`;
- gestire errori 401/403/422/500;
- supportare `AbortController` per filtri live;
- normalizzare paginazione Laravel resource.

Esempio contratto:

```js
export async function pidFetch(path, options = {}) {
  const response = await fetch(path, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      ...(options.headers || {}),
    },
    ...options,
  });

  const payload = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    throw { status: response.status, payload };
  }

  return payload;
}
```

### Pattern Di Interazione

- Filtri con debounce 250-400ms.
- Cambio filtro aggiorna query string.
- Tabelle paginated, non infinite scroll per liste admin.
- Polling solo su request in stato attivo: `queued/searching/extracting/verifying/quality_checking`.
- Stop polling quando stato terminale.
- Immagini lazy-load con `loading="lazy"`.
- Preview immagine in lightbox solo al click.
- JSON viewer lazy-render per payload grandi.
- Salvataggio bozze form debug in `localStorage`.

## Maschera 1: Overview

### Scopo

Vista rapida dello stato della pipeline e delle urgenze operative.

### Componenti

- KPI row:
  - total requests;
  - manual review;
  - failed;
  - no candidates;
  - ready to publish;
  - average score;
  - providers active/missing credentials.
- Mini trend ultimi 7/30 giorni se il tuo admin ha dati aggregati.
- Queue health panel.
- Provider health panel.
- Latest manual review table.
- Latest failed table.
- Shortcut buttons:
  - `Run Debug Flow`
  - `Create Request`
  - `Configure Providers`
  - `Open Manual Review`

### API

- App wrapper: `GET /admin/product-image-discovery/dashboard-summary`
- Package fallback: `GET /api/product-image-discovery/requests/search?manual_review_required=1&per_page=10`

### Stati

- Loading: skeleton KPI + skeleton table.
- Empty: nessuna richiesta ancora inviata, CTA `Create Request`.
- Error: panel rosso con retry.

## Maschera 2: Request History

### Scopo

Lista completa delle request con tutti i filtri utili.

### Componenti

- Filter bar avanzata:
  - client id;
  - status multi-select;
  - brand;
  - supplier;
  - ERP model id;
  - ERP model color id;
  - EAN;
  - source domain;
  - rejection reason;
  - min/max score slider o numeric inputs;
  - manual review required toggle;
  - has candidates toggle;
  - has selected image toggle;
  - created date range;
  - updated date range;
  - sort.
- Data table:
  - status;
  - score;
  - brand;
  - supplier;
  - ERP model/color;
  - selected/best candidate id;
  - rejection reason;
  - created/updated;
  - actions.
- Bulk actions opzionali:
  - retry selected failed/no candidates;
  - export CSV current filters;
  - mark for review in host app.

### Azioni Riga

- Open detail.
- Retry.
- Copy request id.
- Copy ERP model color id.
- Open selected image if present.

### API

```http
GET /api/product-image-discovery/requests/search?...filters
POST /api/product-image-discovery/requests/{request}/retry
```

### UX Note

- Le colonne tecniche devono poter essere nascoste.
- Salvare filtri preferiti per utente se il tuo admin lo supporta.
- Lo stato `manual_review` deve essere molto visibile.

## Maschera 3: Request Detail And Response History

### Scopo

Mostrare tutti i dettagli della richiesta, la risposta pipeline relativa e le decisioni.

### Layout

- Header sticky:
  - request id;
  - status;
  - final score;
  - brand/model/color;
  - actions: retry, open JSON, export report, back.
- Colonna prodotto ERP:
  - client id;
  - ERP model id;
  - ERP model color id;
  - ERP size id;
  - brand;
  - supplier;
  - sku/supplier sku/model code;
  - color code/name;
  - EAN;
  - season/category/material;
  - raw payload viewer.
- Colonna decisione:
  - best candidate;
  - selected candidate;
  - final status;
  - rejection reason;
  - attempts;
  - timestamps.
- Tabs:
  - `Candidates`
  - `Evidence`
  - `AI`
  - `Quality`
  - `Raw Payload`
  - `Audit Timeline`
  - `Debug Report` se collegato.

### API

```http
GET /api/product-image-discovery/requests/{request}
GET /api/product-image-discovery/requests/{request}/candidates
GET /admin/product-image-discovery/requests/{request}/events
```

### Dati Da Mostrare

- Response `GET request` completa.
- Candidate response completa.
- Evidence:
  - matches;
  - mismatches;
  - strong_matches;
  - source policy;
  - component scores.
- AI:
  - available;
  - provider;
  - model;
  - match;
  - variant_safe;
  - confidence;
  - brand_match;
  - model_match;
  - color_match;
  - product_type_match;
  - notes;
  - error.
- Quality:
  - passed;
  - score;
  - width/height;
  - MIME;
  - bytes;
  - issues;
  - SHA256.

## Maschera 4: Candidate Review

### Scopo

Permettere a un operatore di approvare o rigettare candidati in manual review.

### Componenti

- Product identity summary sticky.
- Candidate gallery ordinata per `final_score desc`.
- Ogni candidate tile:
  - immagine;
  - status;
  - final score;
  - source domain;
  - dimensions;
  - MIME/file size;
  - badges: trusted, AI match, wrong color, wrong product, quality.
- Candidate detail drawer:
  - source page URL;
  - image URL;
  - local path/download info;
  - evidence;
  - AI notes;
  - quality analysis;
  - structured data;
  - raw JSON.
- Compare mode:
  - ERP expected fields;
  - selected candidate;
  - candidate under review;
  - side-by-side image zoom.

### Azioni

- Approve candidate.
- Reject candidate con reason e notes.
- Open source page in new tab.
- Copy image URL.
- Copy evidence JSON.
- Retry request.

### API

```http
GET /api/product-image-discovery/requests/{request}/candidates
POST /api/product-image-discovery/requests/{request}/candidates/{candidate}/approve
POST /api/product-image-discovery/requests/{request}/candidates/{candidate}/reject
GET /admin/product-image-discovery/candidates/{candidate}/image
```

### Reject Reasons

Select con valori:

```text
LOW_RESOLUTION
BLURRY_IMAGE
WATERMARK_DETECTED
TEXT_OVERLAY_DETECTED
WRONG_PRODUCT
WRONG_COLOR
WRONG_BRAND
LOW_CONFIDENCE
SOURCE_NOT_ALLOWED
DUPLICATE_WORSE_QUALITY
ROBOTS_OR_PERMISSION_BLOCKED
DOWNLOAD_FAILED
INVALID_MIME_TYPE
```

Richiedere notes quando reason e `WRONG_PRODUCT`, `WRONG_COLOR`, `WRONG_BRAND`, `LOW_CONFIDENCE`.

## Maschera 5: Configuration

### Scopo

Gestire settings, thresholds e comportamento pipeline senza modificare `.env`.

### Tabs

- `Thresholds`
- `Search`
- `Quality`
- `AI`
- `Queues`
- `Storage`
- `Client Overrides`

### Thresholds

Campi consigliati:

- auto publish threshold;
- manual review threshold;
- reject below threshold;
- min candidate score;
- min quality score;
- brand mismatch penalty;
- color mismatch penalty;
- model mismatch penalty;
- non trusted source penalty;
- AI mismatch confidence threshold;
- debug good score threshold.

Per ogni campo mostrare:

- valore globale;
- override client se presente;
- tipo dato;
- descrizione;
- ultimo aggiornamento;
- reset to default.

### API

```http
GET /api/product-image-discovery/settings
POST /api/product-image-discovery/settings
PUT /api/product-image-discovery/settings/{setting}
DELETE /api/product-image-discovery/settings/{setting}
```

### UX Note

- Validare tipo prima del submit.
- Mostrare preview del JSON `setting_value`.
- Evidenziare impostazioni inattive.
- Confermare delete.

## Maschera 6: Search Providers

### Scopo

Configurare provider come Brave, SerpAPI, Google Custom Search o fake/local.

### Lista Provider

Colonne:

- active;
- priority;
- code;
- name;
- driver;
- base URL;
- has API key;
- has API secret;
- timeout;
- rate limit;
- last health test;
- actions.

### Form Provider

Campi:

- code;
- name;
- driver;
- base_url;
- api_key write-only;
- api_secret write-only;
- config JSON editor;
- priority;
- timeout_seconds;
- rate_limit_per_minute;
- is_active.

### UX Credenziali

- Se `has_api_key=true`, mostrare `Configured`, non valore.
- Campo API key vuoto significa "non modificare" in edit, se backend lo gestisce cosi; se il package riceve `api_key` vuota la cancella. La UI deve essere esplicita:
  - button `Replace key`;
  - button `Clear key`;
  - non inviare `api_key` se l'utente non vuole modificarla.
- Stesso pattern per `api_secret`.

### API

```http
GET /api/product-image-discovery/search-providers
POST /api/product-image-discovery/search-providers
PUT /api/product-image-discovery/search-providers/{searchProvider}
DELETE /api/product-image-discovery/search-providers/{searchProvider}
POST /admin/product-image-discovery/search-providers/{provider}/test
```

## Maschera 7: Trusted Sources

### Scopo

Gestire domini attendibili e policy di utilizzo immagini.

### Lista

Filtri:

- client id;
- domain;
- source type;
- trust score min/max;
- allow search;
- allow download;
- allow auto publish;
- requires manual review;
- active;
- brand scope;
- supplier scope.

Colonne:

- trust score;
- domain;
- source name;
- source type;
- policy flags;
- brand/supplier scope;
- active;
- updated.

### Form

Campi:

- client_id;
- domain;
- source_name;
- source_type;
- trust_score slider 0-100;
- allow_search;
- allow_scraping;
- allow_download;
- allow_auto_publish;
- allow_description_import;
- respect_robots_txt;
- requires_manual_review;
- rate_limit_per_minute;
- brand_scope tag input;
- supplier_scope tag input;
- url_patterns repeatable input;
- permission_reference;
- notes;
- is_active.

### API

```http
GET /api/product-image-discovery/trusted-sources
POST /api/product-image-discovery/trusted-sources
PUT /api/product-image-discovery/trusted-sources/{trustedSource}
DELETE /api/product-image-discovery/trusted-sources/{trustedSource}
```

## Maschera 8: Debug Report Viewer

### Scopo

Leggere un report JSON completo generato dal debug command e renderlo in modo navigabile.

### Input

- Upload file `.json`.
- Select report salvato nel server.
- Paste JSON.
- Link da una request/detail se il backend collega debug run e request.

### Layout

- Summary header:
  - started/completed;
  - request file;
  - max candidates;
  - download enabled;
  - stop on first good;
  - candidates checked;
  - verified match count;
  - has verified match;
  - storage cleaned ids.
- Tabs:
  - `Request`
  - `Search`
  - `Candidates`
  - `AI`
  - `Downloads`
  - `Quality`
  - `Events`
  - `Raw JSON`

### Search View

Mostrare:

- generated queries;
- executed query attempts;
- provider;
- result count;
- each result:
  - domain;
  - title;
  - page URL;
  - image URL;
  - size;
  - score.

### Candidate View

Ogni candidato:

- order/index;
- status;
- score breakdown;
- matches/mismatches;
- AI decision;
- source policy;
- local path;
- quality analysis.

### JS Components

- JSON parser with validation.
- Collapsible tree.
- Copy path and copy value.
- Search within JSON.
- "Only mismatches" filter.
- "Only AI failures" filter.
- "Only downloaded" filter.
- "Only verified" filter.

## Maschera 9: Run Debug Flow

### Scopo

Eseguire tutta la pipeline da UI caricando un JSON prodotto o compilandolo da campi guidati.

### Modalita

1. Guided Form.
2. JSON Editor.
3. Upload JSON file.
4. Recent examples.

### Guided Form Fields

Required:

- client_id;
- erp_model_id;
- erp_model_color_id.

Recommended:

- brand;
- supplier;
- sku;
- supplier_sku;
- model_code;
- color_code;
- color_name;
- ean;
- barcode, gtin, gtin13, gtin14 aliases;
- season;
- category;
- material;
- title;
- description;
- extended description;
- composition;

Options:

- fresh;
- clean storage;
- max candidates;
- stop on first good;
- exhaustive;
- download all;
- no download;
- good score threshold;
- no env brave;
- fail on no match.

### UX Flow

1. User compila form o incolla JSON.
2. UI valida JSON lato client.
3. UI mostra preview request.
4. User clicca `Run debug`.
5. Backend crea debug run.
6. UI mostra progress stepper:
   - ingest;
   - search;
   - extract;
   - verify;
   - download;
   - quality;
   - final decision.
7. UI aggiorna output via polling/SSE.
8. A fine run, apre report viewer.

### API

```http
POST /admin/product-image-discovery/debug-runs
GET /admin/product-image-discovery/debug-runs/{debugRun}
GET /admin/product-image-discovery/debug-runs/{debugRun}/report
```

## Maschera 10: Credential And Environment Status

### Scopo

Mostrare se l'ambiente e pronto senza esporre segreti.

### Componenti

- Env status table:
  - `BRAVE_SEARCH_API_KEY`: configured/missing.
  - `ANTHROPIC_API_KEY`: configured/missing.
  - `OPENAI_API_KEY`: configured/missing.
  - `OPENROUTER_API_KEY`: configured/missing.
  - AI enabled.
  - AI provider.
  - AI vision model.
  - AI description model.
  - AI attach remote image.
  - storage disk.
  - queue connection.
- Provider health.
- AI test result.
- Storage write test.
- Queue dispatch test.

### UX

- Badge `configured` o `missing`.
- Non mostrare mai valori parziali tipo primi/ultimi caratteri, salvo policy aziendale esplicita.
- Mostrare istruzione operativa: "Update this value in `.env` or provider settings", non la key.

## Maschera 11: API Test Workbench

### Scopo

Permettere a un tecnico o junior di verificare rapidamente che tutto funzioni.

### Test Cards

- `List requests`
- `Create sample request`
- `Retry request`
- `List candidates`
- `Approve/reject dry run` solo in ambiente test o con conferma forte.
- `Search provider health`
- `AI verifier health`
- `Storage write/read`
- `Queue dispatch`
- `Debug flow sample`

### Output

- request method/path;
- status code;
- duration;
- sanitized response JSON;
- error body;
- copy curl;
- copy JSON.

### Sicurezza

- Non permettere approve/reject su produzione senza doppia conferma.
- Evidenziare environment `production/staging/local`.
- Loggare chi ha eseguito il test.

## Componenti Form

### Input Tipi

- Text input per code/id/domain.
- Number input per score/timeout/rate limit.
- Slider solo quando il valore e 0-100 e serve percezione rapida.
- Toggle per boolean.
- Tag input per brand/supplier scope.
- Repeatable rows per URL patterns.
- Textarea per notes e JSON.
- Segmented control per mode: guided/json/upload.
- Date range picker per history.

### Validazione Client

- Required fields.
- Domain valido.
- URL valido.
- JSON valido.
- Score 0-100.
- Timeout 1-300.
- Per page 1-100.
- Required notes for risky reject.

La validazione client e solo UX; il backend resta source of truth.

## Tabelle E Filtri

Ogni tabella deve avere:

- search/filter form;
- reset filters;
- active filter chips;
- pagination Laravel;
- page size;
- sortable columns se supportate;
- loading skeleton;
- empty state;
- error state;
- copy current filtered URL;
- export CSV se utile nel tuo admin.

Filtri da prevedere quasi ovunque:

- client;
- brand;
- supplier;
- status;
- score range;
- source domain;
- created date;
- updated date;
- has selected image;
- manual review required;
- provider active;
- trusted source active.

## JSON Viewer

Feature richieste:

- pretty print;
- collapse/expand;
- search text;
- copy full JSON;
- copy selected node;
- show path like `candidates[0].ai_verification.notes`;
- highlight booleans/null/numbers/strings;
- protect huge JSON with lazy sections;
- render arrays as tables when possible.

No dependency obbligatoria. Se il tuo admin ha gia un editor JSON, usarlo. Altrimenti implementare viewer vanilla con `details/summary`.

## Immagini

### Requisiti

- Lazy load.
- Placeholder skeleton.
- Fallback image broken.
- Zoom lightbox.
- Open remote image in new tab.
- Open source page in new tab.
- Show dimensions.
- Show MIME.
- Show bytes.
- Show local path if present.
- Show SHA256 if present.
- Mark remote/local.

### Sicurezza

- Le immagini locali devono essere servite da endpoint admin protetto o signed URL.
- Evitare di esporre path storage assoluti.

## Progress E Polling

Per request attive:

- poll ogni 2-5 secondi;
- ridurre frequenza dopo 60 secondi;
- stop se terminale;
- mostrare last updated.

Per debug flow:

- progress stepper;
- console output opzionale;
- report link quando completo;
- errore con stack redatto solo agli admin tecnici.

## Empty, Loading, Error States

### Empty

- Requests: "No discovery requests yet", CTA create/run debug.
- Candidates: "No candidates found", CTA retry.
- Providers: "No search providers configured", CTA add provider.
- Trusted sources: "No trusted sources configured", CTA add source.
- Reports: "No debug reports", CTA run debug flow.

### Loading

- Skeleton rows per table.
- Skeleton image tile.
- Skeleton score pills.
- Disable submit buttons with spinner.

### Error

- Inline API error.
- Full error panel for page load.
- Retry button.
- Copy error id if backend logs one.
- 403: show missing permission message.
- 422: field-level validation errors.

## Accessibilita

- Contrasto WCAG AA.
- Tutti i bottoni icon-only devono avere `aria-label` e tooltip.
- Focus visibile.
- Modali con focus trap.
- Tabelle navigabili da tastiera.
- Non usare solo colore per comunicare stato.
- Testare zoom browser 125% e 150%.
- Testare viewport desktop stretto e tablet.

## Permessi

Mappare UI su abilities:

- Read-only user:
  - requests;
  - candidates;
  - reports;
  - health read.
- Reviewer:
  - approve/reject;
  - retry;
  - debug flow.
- Settings admin:
  - settings;
  - providers;
  - trusted sources.
- Technical admin:
  - API test;
  - credential health;
  - debug report raw JSON.

Nascondere o disabilitare azioni non autorizzate. Se disabilitate, spiegare il motivo nel tooltip.

## Copywriting Operativo

Usare testo breve e concreto.

Esempi:

- `Manual review required`
- `Wrong color risk`
- `AI agrees with visible color`
- `Source is not trusted`
- `API key configured`
- `API key missing`
- `Retry discovery`
- `Approve selected image`
- `Reject candidate`
- `Run debug flow`
- `Download report`

Evitare testi marketing o spiegazioni lunghe nella UI.

## Deliverable Per Il Designer

Il designer dovrebbe produrre:

- Sitemap della sezione admin.
- Wireframe desktop per tutte le maschere.
- Stato mobile/tablet minimo per liste e detail.
- Design system locale:
  - badge status;
  - score;
  - tables;
  - filters;
  - forms;
  - modals;
  - drawer;
  - image tile;
  - JSON viewer.
- Stati:
  - loading;
  - empty;
  - error;
  - permission denied;
  - success.
- Icon mapping.
- Token colore status.
- Regole spacing e density.
- Esempi con dati reali Herno/Nike sanitizzati.

## Priorita Di Implementazione

### Fase 1

- Request History.
- Request Detail.
- Candidate Review.
- Providers.
- Trusted Sources.
- Settings thresholds.

### Fase 2

- Debug Flow runner.
- Debug Report Viewer.
- Credential/health status.
- API Test Workbench.

### Fase 3

- Dashboard aggregata.
- Export CSV/report.
- Saved filters.
- Advanced queue/latency panels.
- Bulk actions.

## Checklist Finale

- La UI non espone segreti.
- Ogni immagine approvabile mostra source, score, evidence e AI notes.
- Ogni rigetto richiede reason.
- I filtri principali sono disponibili in request history.
- Il report JSON e leggibile senza aprire file esterni.
- Il debug flow puo partire da JSON upload o guided form.
- Le credential sono mostrate solo come stato.
- Le API test mostrano method/path/status/duration/response sanitizzata.
- Tutti gli stati loading/empty/error sono disegnati.
- Approve/reject/retry/delete hanno conferma.
- Le schermate sono usabili da keyboard.
- Il layout resta ordinato su desktop stretto.
