# KINAS GROUP — Deployment Guide

## Quick reference: the verification state machine

```
                                    ┌────────────────────┐
                                    │ ADMIN PORTAL       │
                                    │ approves CAC doc   │
                                    │ (Step 3)           │
                                    └────────┬───────────┘
                                             │
                                             ▼
   ┌────────────┐  SMS OTP  ┌────────────┐  MetaMap  ┌──────────────────┐
   │ REGISTER   │ ────────► │ PHONE      │ ────────► │ IDENTITY (MetaMap)│
   │ email link │           │ VERIFIED   │           │ auto-approved    │
   └────────────┘           └────────────┘           └────────┬─────────┘
                                                             │
                                                             ▼
                                                    ┌──────────────────┐
                                                    │ BUSINESS DOCS    │
                                                    │ (CAC/TIN/utility)│
                                                    │ agent uploads    │
                                                    └────────┬─────────┘
                                                             │
                                                             ▼
                                                    ┌──────────────────┐
                                                    │ ADMIN REVIEW     │
                                                    │ approves CAC doc │
                                                    └────────┬─────────┘
                                                             │
                                                             ▼
                                                    ┌──────────────────┐
                                                    │ ✓ APPROVED       │
                                                    │ can list         │
                                                    └──────────────────┘
```

## Deploy checklist

### 1. Database
```sql
SOURCE database/fresh_schema.sql;
```
This drops the existing `kinas_group` database and creates a fresh one with all 14 tables, the admin user, and 7 default marketplace categories.

**Default admin login:** `admin@kinasgroup.com` / `Admin@2026` — **change immediately in production**.

### 2. .env
Copy `.env.example` to `.env` and fill in:
- `DB_*` (your MySQL credentials)
- `TERMII_API_KEY` (the key Termii gave you — **rotate it first** since it was shared in chat)
- `TERMII_SENDER_ID` (your registered sender; "N-Alert" is flagged on some carriers, prefer a custom registered ID)
- `METAMAP_*` (from https://dashboard.metamap.com)
- `RESEND_API_KEY` (for email)
- `R2_*` (Cloudflare R2 for file storage)

### 3. MetaMap webhook
Set the webhook URL in your MetaMap dashboard to:
```
https://kinas-group.com/api/webhooks/metamap.php
```

### 4. Termii (no webhook needed for OTP)
- Default channel `generic` works for most flows
- `dnd` channel for users who opted in to DND
- `whatsapp` channel as fallback (not enabled by default)

### 5. File storage
CAC documents go to R2 (or local fallback) in the `business-documents` subdirectory. The file-upload class handles this automatically.

## Per-event SMS notification switches

All defaults to `true` except admin-notify which is `false`:

| Env var | Default | What it does |
|---|---|---|
| `SMS_NOTIFY_KYC_DECISION` | true | SMS agent on KYC approved/rejected |
| `SMS_NOTIFY_NEW_MESSAGE` | true | SMS user on new private message |
| `SMS_NOTIFY_LISTING_APPROVED` | true | SMS agent when listing goes live |
| `SMS_NOTIFY_NEW_INQUIRY` | true | SMS agent on new listing inquiry |
| `SMS_NOTIFY_ADMIN_NEW_AGENT` | false | SMS admin on new agent awaiting review |

## API endpoints summary

### Auth
- `POST /api/auth/send-otp.php` — request phone OTP
- `POST /api/auth/verify-otp.php` — verify phone OTP
- `GET  /api/auth/verify-email.php?code=...` — email link verification (auto-redirects agents to phone verify)

### KYC
- `POST /api/agent/kyc-start.php` — kick off MetaMap flow
- `GET  /api/agent/kyc-status.php` — current KYC state (used by polling UI)
- `POST /api/agent/upload-business-doc.php` — agent uploads CAC/TIN/etc.
- `POST /api/admin/review-business-doc.php` — admin approves/rejects a doc

### Webhooks
- `POST /api/webhooks/metamap.php` — MetaMap → us (HMAC-secured)

## Verification status enum (agent_profiles.verification_status)

| Value | Meaning | Next step |
|---|---|---|
| `pending` | Registered, no KYC yet | Verify email → verify phone → MetaMap |
| `phone_verified` | Phone OTP confirmed | MetaMap identity verification |
| `kyc_passed` | MetaMap approved the person | Upload CAC business docs |
| `documents_submitted` | CAC uploaded, awaiting admin | Admin reviews |
| `approved` | Admin approved everything | Can list, can message, full access |
| `rejected` | Admin or MetaMap rejected | Re-submit (button shown on verification page) |
| `suspended` | Post-approval suspension | Admin must un-suspend |

## Security notes

1. **OTP codes are bcrypt-hashed** in `phone_otps.code_hash`. Even if the DB is dumped, codes can't be replayed.

2. **HMAC-secured webhooks** — MetaMap webhooks verify `x-mati-signature`. Termii delivery reports verify `message_id` exists in our table (no HMAC, but message_ids are unguessable UUIDs).

3. **Rate limits:**
   - OTP: 1 per 30s, 5 per hour per phone
   - Inquiries: 5 per 10 min per IP
   - Login attempts: 5 per 15 min per IP (existing)

4. **CSRF** is enforced on all POST endpoints via `Security::verifyCSRFToken()`.

## Testing locally

Set `APP_ENV=development` in `.env` and:
- Termii is auto-bypassed — use code `000000` to verify
- MetaMap is bypassed — verification moves straight to "kyc_passed" after phone verify
- See `includes/termii.php` for the dev-mode hook

## Files that were removed in this KYC refresh

- `api/agent/upload-kyc.php` — old local KYC uploader (replaced by MetaMap + business-doc flow)
- `database/schema.sql` — superseded by `database/fresh_schema.sql`
- `database/complete_patch.sql` — superseded
- `database/kyc_extra_fields.sql` — folded into the new schema
- `database/metamap_integration.sql` — folded into the new schema
- `database/location_columns.sql` — folded into the new schema
- `database/migrations*.sql`, `database/schema_safe.sql`, `database/dashboard_patch.sql`, `database/migrate_otp_codes.sql`, `database/fix_missing_columns.sql` — superseded
