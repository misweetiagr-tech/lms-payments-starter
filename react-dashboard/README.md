# react-dashboard (sample)

A small student dashboard that logs in through the Node service and then reads data from Laravel with the **same token**.

```bash
npm install
npm test          # 5 tests for the token and refresh logic
npm run build     # type-check + build
npm run dev       # needs node-auth on :4000 and `php artisan serve` on :8000
```

Sign in with a seeded user id (for example `3`) and the demo code `123456`.

## What it shows

- **Two clients, one identity** (`src/api/client.ts`): one for the Node auth service, one for Laravel under `/api/app`.
- **Token refresh on 401**: the Laravel client asks Node for a new access token, then retries the request once.
- **One refresh for many failures**: if several requests fail at the same moment, they share a single refresh call.
- **No infinite loops**: a request is retried at most once, and a rejected refresh token ends the session.
- **Testable without a network**: the client accepts an adapter, so tests run with fake responses.
- **Typed, with loading, error and empty states** on every screen.

## Honest limits

- Tokens are in `localStorage`, which is simple but readable by any script on the page. A production app would prefer `httpOnly` cookies.
- State is plain React. For a bigger app I would use TanStack Query for server state.
- No routing or component tests yet. Only the API client logic is covered.
