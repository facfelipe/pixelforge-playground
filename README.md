# Pixelforge Playground

Small Laravel + Vue + Inertia app for async image generation against the Pixelforge API. Credits are a demo wallet (no real payments).

Pixelforge HTTP is **mocked** today; the webhook verify → dedupe → apply → poll path is real. Live API is mostly `PIXELFORGE_DRIVER=http` + credentials + a public webhook URL.

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

## Code shape

- Local generation row first, then `ProcessGenerationJob` → `PixelforgeClient`
- Mock client delivers signed events into `HandlePixelforgeWebhook` → `ApplyPixelforgeGeneration` (same as `POST /webhooks/pixelforge`)
- `PollPixelforgeGenerationJob` as backup
- Mock keywords: `[fail]`, `[blocked]`, `[overloaded]`, `[duplicate]`, `[slow]`
- Estimates/mock use `PricingCatalog` (docs snapshot); live path should use `GET /v1/pricing` + generation `rates`

## Decisions

- Gate on **max / worst-case** estimate, not typical — avoid accepting work that can overshoot the wallet
- Local row before provider create; match webhooks by `pixelforge_id` or `metadata.local_id`
- Mock only at the client / outbound webhook boundary — verify, dedupe, apply, and poll stay real

## Tests

`php artisan test --compact` — Pest coverage for estimate/markup, charge-once, fail = $0, balance gate, webhook signature/dedupe/out-of-order, poll-without-webhooks, credits pack, and admin report. See `tests/Unit` and `tests/Feature`.

## Next

Real Pixelforge key, webhook tunnel, deploy, and reconcile admin provider totals with `GET /v1/usage`.
