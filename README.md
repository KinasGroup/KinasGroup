# KINAS GROUP

The World's Luxury Marketplace — Homes, Cars, Solar Energy & Curated Goods.

**Stack:** PHP 8, MySQL/MariaDB, vanilla JS, custom CSS, JamesEdition-inspired design system.

**Status:** v3 — KYC refresh with Termii SMS OTP + MetaMap identity + admin-reviewed business documents.

---

## Quick start

```bash
# 1. Database
mysql -u root -p < database/fresh_schema.sql

# 2. Configure
cp .env.example .env
# Edit .env with your DB, Termii, MetaMap, Resend, R2 credentials

# 3. Point your web server at the KinasGroup11/ directory
# (Apache, Nginx, or PHP built-in: php -S 0.0.0.0:8080)
```

Default admin login: `admin@kinasgroup.com` / `Admin@2026` — **change in production**.

Full deployment guide: see [DEPLOY.md](./DEPLOY.md)
MetaMap setup: see [METAMAP_SETUP.md](./METAMAP_SETUP.md)

---

## File map

```
KinasGroup11/
├── index.php                    # Homepage (gold standard)
├── divisions/
│   ├── kinas-automobile/        # Luxury cars
│   ├── williams-connect-home/    # Real estate
│   ├── kinas-volt/               # Solar / energy
│   └── kinas-marketplace/        # Curated goods
│       ├── index.php             # Division landing
│       ├── search.php            # Filterable results
│       └── detail.php            # Single listing
├── auth/                         # login, register, phone-verify
├── agent/                        # Agent dashboard (incl. verification wizard)
├── user/                         # Buyer dashboard
├── admin/                        # Admin panel
├── pages/                        # about, contact, 404, legal
├── api/                          # REST endpoints
│   ├── auth/                     # login, register, send-otp, verify-otp
│   ├── agent/                    # kyc-start, kyc-status, upload-business-doc
│   ├── admin/                    # review-business-doc, agent-approvals actions
│   ├── webhooks/                 # metamap.php
│   ├── listings/                  # create, update, delete, search, filter
│   ├── messages/                  # send, send-inquiry, inbox
│   ├── uploads/                  # R2/local file uploads
│   └── config/                    # database connection
├── templates/                    # header.php, footer.php, modal partials
├── includes/                     # je-components, je-sidebar, termii, notify, …
├── assets/
│   ├── css/                      # style, james-edition, responsive
│   ├── js/                       # main, mobile-menu, agent, api
│   └── images/
├── database/
│   └── fresh_schema.sql          # THE one migration to run
├── DEPLOY.md                     # Deployment guide
├── METAMAP_SETUP.md              # MetaMap-specific guide
└── .env.example                  # Config template
```

---

## Verification flow (v3)

```
Register → Email link → Phone OTP (Termii) → MetaMap identity → Upload CAC → Admin review → Approved
```

See `DEPLOY.md` for the full state machine and how to configure each step.

---

## Design system

The site uses a single shared design system defined in:
- `assets/css/james-edition.css` (~930 lines, all `.je-*` classes)
- `includes/je-components.php` (PHP renderers: filter panel, listing card, pagination, sort row, footer)
- `includes/je-sidebar.php` (role-based dashboard sidebar)

When adding a new page, use the components rather than inlining styles. The 3 pages that were used as the "gold standard" are:
- `index.php` (homepage)
- `divisions/*/search.php` (search results with filters)
- `divisions/*/detail.php` (single listing)
