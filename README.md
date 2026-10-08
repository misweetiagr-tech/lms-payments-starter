# lms-payments-starter

A small Laravel project that shows how I design the **payment and scheduling parts of a learning platform** so they stay correct when things go wrong: duplicate webhooks, missed webhooks, two people booking the same resource, and permission mistakes.

Everything here is written from scratch with **fake data and a fake payment gateway**. No real keys, customers or company code.

## What it demonstrates

| Area | Where | Idea |
|---|---|---|
| Idempotent webhook | `WebhookController`, `SubscriptionFinalizer` | HMAC-verified raw body. A duplicate or re-sent event is recorded once. Real failures return 500 so the provider retries. |
| Self-healing | `SubscriptionController::status` | If the provider charged but the webhook never arrived, the status call catches up. |
| Reconcile job | `payments:reconcile` (hourly) | Safety net for missed webhooks. Safe to run any number of times. `--dry-run` shows what it would do. |
| Per-course installment plans | `InstallmentPlanResolver` | One source of truth. No plan means full payment only. The last part absorbs rounding. |
| Server-side guard | `SubscriptionController::store` | A hand-made request cannot split a full-payment course; amounts come from the server. |
| Resource pool with locking | `HostAllocator` | Picks a free host inside a transaction with `lockForUpdate`. Back-to-back slots do not clash. |
| Section + action permissions | `User::hasPermission`, `EnsurePermission` | `perm:enrollments,view` middleware. The old flat token keeps full access so nobody is locked out. |

## Quick start

```bash
git clone <this repo>
cd lms-payments-starter
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan test
```

Requires PHP 8.2+ and the `pdo_sqlite` extension. No account or API key is needed.

## Try the flow

1. Start the server: `php artisan serve`
2. Create a subscription (course 1 has a 3-part plan):

   ```bash
   curl -X POST http://127.0.0.1:8000/api/subscriptions \
     -H "Content-Type: application/json" -d '{"user_id":3,"course_id":1}'
   ```

3. Send a signed webhook for cycle 1 (set the same secret as `PAYMENTS_WEBHOOK_SECRET`):

   ```bash
   BODY='{"event":"subscription.charged","data":{"subscription_id":"<gateway_subscription_id>","cycle":1,"payment_id":"pay_1","amount":10000}}'
   SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "change-me-locally" | sed 's/^.* //')
   curl -X POST http://127.0.0.1:8000/api/webhooks/payments \
     -H "Content-Type: application/json" -H "X-Signature: $SIG" -d "$BODY"
   ```

4. Send the same request again. The response says `"applied": false` and nothing is double counted.

## Design decisions

- **One place records a charge.** The webhook and the reconcile job both call `SubscriptionFinalizer`, so the rules cannot drift apart.
- **Two unique keys, not one.** A cycle is unique per subscription *and* a provider payment id is unique. The reconcile job invents its own ids, so without the cycle key a late real webhook would be counted twice. A test covers exactly this.
- **Sign the raw body.** Re-encoding parsed JSON would change the bytes and break the signature.
- **Unknown subscription returns 202.** Returning an error would make the provider retry forever.
- **Missing secret returns 500, never "allow".** A configuration mistake must not turn into an open endpoint.
- **A gateway interface.** The flow depends on `PaymentGateway`. A real provider is one new class.

## Tests

`php artisan test` runs 29 tests covering the cases above: bad signature, duplicate event, same cycle with a different id, completion, unknown subscription, missing secret, reconcile twice, late webhook after reconcile, dry run, plan rounding and date overflow, host overlap and back-to-back slots, and permission checks.

## Limits and what I would improve

- Tests run on SQLite, which ignores row locks, so the `lockForUpdate` concurrency path is **not** proven by the tests. I would add a MySQL job in CI and a parallel-request test.
- `user_id` comes from the request body to keep the demo small. A real app takes it from authentication.
- The fake gateway keeps state in the cache. A real one would call the provider's API and handle its errors and rate limits.
- No queue is used for post-payment work (invoices, emails). In production I would dispatch those as jobs on a real queue driver, not `sync`.
- API versioning and an audit log of permission changes would come next.

## Stack

PHP 8.2, Laravel 12, SQLite (swap for MySQL in `.env`), PHPUnit.
