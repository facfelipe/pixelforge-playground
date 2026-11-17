# Pixelforge Playground

Small Laravel + Vue + Inertia app for async image generation against the Pixelforge API. Credits are a demo wallet (no real payments).

Provider access is a **driver switch**: `PIXELFORGE_DRIVER=mock` (default, offline/tests) or `http` (live API). Webhook verify → dedupe → apply and poll are the same either way.

## What’s in

- Estimate before submit (typical + **up to** / worst-case)
- Charge **provider × 1.25** once on success; failures are **$0**
- Exact money: `decimal(28,9)` + bcmath (UI shows 6 places)
- Live status history with estimated, actual, and image
- Webhooks: signature check, event-id dedupe, out-of-order safe
- Poll fallback using the same apply path as webhooks
- Balances: Add $5; gate generate when balance &lt; max estimate
- Admin: spend / provider / profit by day and by user (`ADMIN_EMAILS`)

## Run locally

```bash
composer install
cp .env.example .env   # if needed; php artisan key:generate
npm install && npm run build
php artisan migrate --seed
```

Herd: http://pixelforge-playground.test

| | Email | Password |
|---|---|---|
| User | `user@test.com` | `password` |
| Admin | `admin@test.com` | `password` |

```bash
php artisan test --compact
```

### Live Pixelforge (optional)

```env
PIXELFORGE_DRIVER=http
PIXELFORGE_API_KEY=pf_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
QUEUE_CONNECTION=database
```

1. Create a **local** API key in the Pixelforge dashboard (separate key later for production).
2. Tunnel your site (e.g. `herd share`) and set endpoint  
   `https://your-tunnel/webhooks/pixelforge`.
3. Run a worker: `php artisan queue:work --tries=3`.
4. Keep the tunnel + worker running while generating.

## Code shape

- Local generation row first, then `ProcessGenerationJob` → `PixelforgeClient`
- Mock driver: signed events into `HandlePixelforgeWebhook` → `ApplyPixelforgeGeneration` (same apply path as `POST /webhooks/pixelforge`)
- `PollPixelforgeGenerationJob` as backup
- Prompt keywords: `[fail]`, `[blocked]`, `[overloaded]`, `[duplicate]`, `[slow]`
- Estimates use cached `GET /v1/pricing` (fallback: `PricingCatalog`); actuals use the generation’s frozen `rates`
- Verified webhooks are queued (`ProcessPixelforgeWebhookJob`) so the endpoint can 204 quickly

## Decisions

- Gate on **max / worst-case** estimate, not typical — avoid accepting work that can overshoot the wallet
- Local row before provider create; match webhooks by `pixelforge_id` or `metadata.local_id`
- Mock only at the client / outbound webhook boundary — verify, dedupe, apply, and poll stay real

## Tests

`php artisan test --compact` — Pest coverage for estimate/markup, charge-once, fail = $0, balance gate, webhook signature/dedupe/out-of-order, poll-without-webhooks, credits pack, and admin report. See `tests/Unit` and `tests/Feature`.

## Next

Deploy with a **production** Pixelforge key + webhook URL, leave sample generations, and reconcile admin provider totals with `GET /v1/usage`.
