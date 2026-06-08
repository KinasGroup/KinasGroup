# KINAS GROUP — MetaMap KYC setup

We use [MetaMap](https://www.metamap.com) for identity verification.
Documents are never stored on our servers.

## One-time setup

1. **Create a MetaMap account** at https://dashboard.metamap.com
2. **Create a Flow** (the verification journey the user will see).
   Add steps for ID, selfie/liveness, and any government checks
   you want (NIN, BVN, CAC, etc. for Nigeria).
3. **Get your credentials** from the dashboard:
   - `clientId`
   - `clientSecret`
   - `webhookSecret`
   - `flowId`
4. **Set the webhook URL** in the MetaMap dashboard to:
   ```
   https://kinas-group.com/api/webhooks/metamap.php
   ```
   MetaMap will sign all deliveries with HMAC-SHA256 using your
   `webhookSecret`.
5. **Add the credentials to `.env`** (see `.env.example` for keys):
   ```env
   METAMAP_ENABLED=true
   METAMAP_CLIENT_ID=...
   METAMAP_CLIENT_SECRET=...
   METAMAP_WEBHOOK_SECRET=...
   METAMAP_FLOW_ID=...
   METAMAP_API_BASE=https://api.getmati.com
   ```
6. **Run the database migration**:
   ```sql
   SOURCE database/metamap_integration.sql;
   SOURCE database/kyc_extra_fields.sql;
   ```

## Flow at a glance

```
agent (browser)                  KINAS server                MetaMap
       │                              │                        │
       │ click "Start Verification"   │                        │
       │ ────────────────────────────►│                        │
       │                              │ POST /v2/verifications │
       │                              │ ──────────────────────►│
       │                              │◄──── { id, ... } ──────│
       │◄── { hostedUrl } ───────────│                        │
       │ window.open(hostedUrl)                                     │
       │ ─────────────────────────────────────────────────────────►│
       │                              │                        │
       │                       (user completes flow)            │
       │                              │                        │
       │                              │◄── webhook: completed ─│
       │                              │   (HMAC verified)      │
       │                              │ update DB + flip flag  │
```

## Endpoints

| Method | Path                              | Purpose                       |
|--------|-----------------------------------|-------------------------------|
| POST   | `/api/agent/kyc-start.php`        | Create or resume a verification |
| GET    | `/api/agent/kyc-status.php`       | Current status (for polling)  |
| POST   | `/api/webhooks/metamap.php`       | MetaMap → us (HMAC-secured)   |

## Status mapping

| MetaMap status        | Our `agent_profiles.verification_status` |
|-----------------------|-------------------------------------------|
| `verified` / `approved` | `approved` (user.verified=1)            |
| `rejected` / `failed`   | `rejected`                              |
| `reviewNeeded`          | `review_needed` (admin must decide)     |
| `in_progress` / `pending` | `in_progress`                         |
| `expired` / `cancelled`   | `expired`                              |
| (default)                | `in_progress`                          |

When the agent is `review_needed`, the admin still decides
manually via `admin/agent-approvals.php`. The page now shows
the MetaMap `verification_id` and `mati_status` for context.

## Webhook security

The webhook endpoint verifies the `X-Mati-Signature` header
which has the format `t=<unix_ts>,v1=<hex_hmac>`. The expected
HMAC is computed over `<unix_ts>.<raw_body>` with the
`METAMAP_WEBHOOK_SECRET`. We also enforce a 5-minute timestamp
window to block replays.

## Local development

Set `METAMAP_ENABLED=false` in `.env` to short-circuit the
service. The agent verification page will still render, but the
"Start Verification" button will return a 503 until you
re-enable.

## Data we DO NOT collect

- ID document images / scans
- Live selfie photos
- Liveness video

These are all handled by MetaMap and are subject to their
privacy policy, not ours.
