# Pixelforge Playground

Laravel + Vue + Inertia app for async image generation against the Pixelforge API. Credits are a demo wallet (no real payments).

**Live:** https://pixelforge-playground-production-nghahq.laravel.cloud

Provider access is a **driver switch**: `PIXELFORGE_DRIVER=mock` (default, offline/tests) or `http` (live API). Webhook verify → dedupe → apply and poll are the same either way.

## What’s in

- Estimate before submit (typical + **up to** / worst-case)
- Charge **provider × 1.25** once on success; failures are **$0**
- Exact money: `decimal(28,9)` + bcmath (costs UI: 6 places; wallet/pack: 2)
- Live status history with estimated, actual, and image
- Webhooks: signature check, event-id dedupe, out-of-order safe
- Poll fallback using the same apply path as webhooks
- Balances: Add $5; gate generate when balance < max estimate
- Admin: spend / provider / profit by day and by user (`ADMIN_EMAILS`)

## Demo logins

| | Email | Password |
|---|---|---|
| User | `user@test.com` | `password` |
| Admin | `admin@test.com` | `password` |

Admin report: `/admin`.

## Run locally

```bash
composer install
cp .env.example .env   # if needed; php artisan key:generate
npm install && npm run build
php artisan migrate --seed
php artisan test --compact
```

Herd: http://pixelforge-playground.test

### Live Pixelforge (local)

```env
PIXELFORGE_DRIVER=http
PIXELFORGE_API_KEY=pf_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
QUEUE_CONNECTION=database
```

1. Use a **local** API key (separate from production).
2. Tunnel your site and set webhook  
   `https://your-tunnel/webhooks/pixelforge`.
3. Run a worker: `php artisan queue:work --tries=3`.

## Code shape

- Local generation row first, then `ProcessGenerationJob` → `PixelforgeClient`
- Mock driver: signed events into `HandlePixelforgeWebhook` → `ApplyPixelforgeGeneration` (same apply path as `POST /webhooks/pixelforge`)
- `PollPixelforgeGenerationJob` as backup; verified webhooks queued via `ProcessPixelforgeWebhookJob`
- Prompt keywords: `[fail]`, `[blocked]`, `[overloaded]`, `[duplicate]`, `[slow]`
- Estimates: cached `GET /v1/pricing` (fallback `PricingCatalog`). Actuals: that generation’s saved `rates` × real usage tokens

## Decisions

- Gate on **max / worst-case** estimate, not typical — avoid accepting work that can overshoot the wallet
- Local row before provider create; match webhooks by `pixelforge_id` or `metadata.local_id`
- Mock only at the client / outbound webhook boundary — verify, dedupe, apply, and poll stay real

## Tests

Pest coverage for estimate/markup, charge-once, fail = $0, balance gate, webhook signature/dedupe/out-of-order, poll-without-webhooks, credits pack, and admin report. See `tests/Unit` and `tests/Feature`.
