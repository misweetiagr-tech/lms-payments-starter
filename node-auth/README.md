# node-auth (sample)

A tiny Node service that logs a user in and issues the JWT the Laravel app accepts.
It shows the **bridge** idea: one login, two backends, nothing existing rewritten.

```bash
cd node-auth
npm install
npm test                                 # token tests
NODE_JWT_SECRET=change-me-locally npm start
```

```bash
curl -X POST localhost:4000/auth/login -H "Content-Type: application/json" \
  -d '{"user_id":3,"otp":"123456"}'      # fake OTP, fake users
```

Use the `access_token` against Laravel:

```bash
curl localhost:8000/api/app/me -H "Authorization: Bearer <access_token>"
```

Rules both sides follow:

- HS256 with a shared secret (`NODE_JWT_SECRET` in both places).
- `sub` is the user id; `token_type` is `access` or `refresh`.
- Access tokens last 30 minutes. A refresh token only works at `/auth/refresh`, and an access token never does.
- The algorithm is pinned, so unsigned or re-signed tokens are rejected.

The OTP here is a fixed demo value. A real service verifies a one-time code sent to the user.
