# Architecture

## Payment flow

```mermaid
sequenceDiagram
    participant S as Student
    participant A as App (Laravel)
    participant G as Payment gateway
    S->>A: POST /api/subscriptions
    A->>A: Plan + amount from the server (never the request)
    A->>G: createSubscription(amount, cycles)
    A-->>S: reference + schedule (enrollment = pending)
    G-->>A: Webhook: subscription.charged (cycle 1)
    A->>A: Verify HMAC, record cycle once, enrollment = active
    Note over G,A: If the webhook is lost...
    S->>A: GET /subscriptions/{ref}/status
    A->>G: paidCount()
    A->>A: Record missing cycles (self-heal)
    Note over A: Hourly: payments:reconcile does the same for every open subscription
```

## Why three layers

1. **Webhook** is the fast, normal path.
2. **Status self-heal** covers the user who is watching the page.
3. **Hourly reconcile** covers later cycles with nobody watching.

All three end in `SubscriptionFinalizer::applyCharge`, which records a cycle at most once.

## Host pool

```mermaid
flowchart LR
    C[New class] --> T{{Transaction + lock hosts}}
    T --> H1{Host A free?}
    H1 -- yes --> A[Assign A]
    H1 -- no --> H2{Host B free?}
    H2 -- yes --> B[Assign B]
    H2 -- no --> H3{Host C free?}
    H3 -- yes --> D[Assign C]
    H3 -- no --> E[NoHostAvailable]
```

Overlap rule: `existing.start < new.end AND existing.end > new.start`.
