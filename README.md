<p align="center">
  <img src="https://img.shields.io/badge/version-1.2.2-blue?style=flat-square" alt="Version">
  <img src="https://img.shields.io/badge/WordPress-6.5%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce">
  <img src="https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square" alt="License">
</p>

<h1 align="center">WPRaffle</h1>

<p align="center">
  A comprehensive WordPress plugin for running online raffles and competitions.<br>
  Built on WooCommerce with full ticket management, live draws, instant wins, charity fundraising, responsible-gambling controls, and UK compliance features.
</p>

<p align="center">
  <a href="https://docs.wpraffle.dev">Full documentation</a> ·
  <a href="../../releases">Releases</a> ·
  <a href="./CHANGELOG.md">Changelog</a> ·
  <a href="./RELEASE.md">Release notes</a>
</p>

---

## What's new in 1.2.1

A user-experience focused patch — fixes the bugs that harmed real users and adds five
high-impact improvements:

- **Fixed "YOU WON!" detection** — the celebration badge in My Raffles was virtually never
  shown to real winners due to a row-id vs ticket-number mismatch. Now works.
- **Odds of winning, live** — buyers see "1 in N" odds that update as they change quantity.
- **Friendly Bundle Builder** — the raw JSON packages field is now a repeatable row UI.
- **Lookup form keeps its promise** — `[raffle_lookup]` now genuinely emails a single-use
  secure link with a guest ticket view.
- **SOLD OUT / ENDING SOON badges**, frozen closed raffles, pending-purchase visibility,
  buyers CSV export, winner-name privacy, dashboard error handling, and modal accessibility.

See [`RELEASE.md`](./RELEASE.md) for the full release notes.

---

## Features

### Core
- **Configurable Raffles** — Title, description, prize image, ticket count, price, packages
- **WooCommerce Integration** — Full checkout with any payment gateway, cart & order management
- **Random Ticket Assignment** — Cryptographically secure (`random_int()`) unique ticket numbers
- **Automated Emails** — Purchase confirmation, winner notification, instant win alerts, draw reminders, sold out alerts
- **User-Selected Numbers** — Optional mode where buyers pick their own ticket numbers

### Competition Features
- **Instant Wins** — Prizes automatically awarded at specific ticket numbers
- **Multi-Winner Draws** — Multiple winners with configurable prize tiers
- **Live Draw** — Animated draw page with spinning numbers and confetti
- **Skill Questions** — Multiple-choice questions required before purchase (UK Gambling Act compliance)
- **Free / Postal Entry** — Alternative entry route for compliance
- **Geo-Restriction** — Restrict entry by country via IP geolocation
- **Referral System** — Unique referral codes with bonus entries (paid-purchase verified)

### Engagement & Conversion (v1.2)
- **Ticket Bundles** — Quantity bundles with custom pricing, savings %, and badges (now configured via a friendly builder UI in v1.2.1)
- **Number Picker Grid** — Visual grid where buyers pick their own ticket numbers, with Lucky Dip
- **Consolation Coupons** — Auto-issue WooCommerce coupons to non-winning entrants after the draw
- **Virality / Share** — Per-user referral links + share buttons (WhatsApp, Facebook, X, copy-link)
- **Scarcity / Urgency** — Live stock polling, "X people viewing now" social proof, low-stock alerts

### Buyer Experience (v1.2.1)
- **Odds of Winning** — A live "1 in N" odds display updates as buyers change their ticket quantity
- **SOLD OUT & Ending Soon** — Shop cards auto-badge with scarcity and end states; expired competitions are non-clickable
- **Pending Purchase Visibility** — Buyers whose payment is still clearing see a "Processing" state
- **My Raffles Account** — Permanent ticket history with live odds, a clear "YOU WON!" badge, and a results link
- **Guest Ticket Lookup** — Guests get a single-use secure link emailed to view their tickets
- **Accessible Checkout** — Keyboard-friendly purchase modal with focus trapping and screen-reader announcements

### Charity & Fundraising (v1.2)
- **Charity Registry** — CPT-based charity directory with `[raffle_charities]` shortcode
- **Live Totals** — Public grid updates every 60s as tickets sell; allocations snapshotted at draw
- **Disbursement Workflow** — Operator-only CSV export and mark-disbursed flow

### Responsible Gambling (enforced in v1.2)
- **Spend Limits** — Day/week/month limits with a 24h cool-off on increases
- **Self-Exclusion** — Including email-based guest exclusion; cannot be lifted early
- **Operator Locks** — Per-account locks with reason + audit
- **Server-Side Enforcement** — All six purchase gates; client UI is advisory only

### Styling (overhauled in v1.2)
- **Five Theme Presets** — Diamonds / Golf / Car / Retro / Elite, each driving radius, shadow, button shape, and typography (not just hue)
- **CSS Custom Properties** — Override any `--wpr-*` token in your theme
- **Icon Pack** — Single SVG sprite with brand/social icons (WhatsApp, Facebook, X, copy-link)

### Admin
- **Analytics Dashboard** — Revenue charts, sales trends, activity feed
- **Audit Log** — Complete action log with actor, IP, and timestamps
- **Templates & Clone** — Reusable raffle templates and one-click duplication
- **Ticket Reservations** — Temporary holds during checkout with auto-cleanup
- **Duplicate Detection** — Automatic detection and correction of duplicate tickets
- **Shop Integration** — Custom raffle cards in WooCommerce shop pages
- **Searchable Raffle List** (v1.2.1) — Search by title, filter by status, paginate
- **Buyers CSV Export** (v1.2.1) — One-click export of a raffle's full buyer list with ticket numbers
- **Winner Name Privacy** (v1.2.1) — Optional initials-only display on public results pages

### Security
- **Multi-layer quantity validation** — Cart lock, checkout validation, payment-time clamping
- **WordPress nonces** on all forms and AJAX (CSRF protection)
- **Input sanitization & output escaping** throughout
- **Prepared SQL statements** — No raw queries
- **Rate limiting** — Configurable per-minute per-IP, proxy-aware (trusted-proxy allowlist)
- **Privacy & GDPR** — Personal data export/erasure via WordPress Privacy API; two-step deletion
- **Money integrity** — `FOR UPDATE` locks, `GET_LOCK` advisory locks, transactions on every credit/debit
- **Product Sync** — Detect and fix WooCommerce product mismatches
- **Shortcode Customisation** — Configure shortcode defaults from settings UI

### Developer
- **Elementor Widgets** — 18 custom widgets for visual page building
- **Shortcodes** — Display raffles, lists, lookup, and live draws anywhere
- **GitHub Auto-Updates** — Push updates from GitHub releases
- **Hooks & Filters** — Extensible via WordPress actions and filters

---

## Installation

### Requirements

| Component   | Minimum |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 8.0+    |
| WooCommerce | 8.0+    |
| MySQL       | 5.7+    |
| Elementor   | *Optional* |

### Setup

1. Download the latest release `.zip` from the [Releases](../../releases) page
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the `.zip` and click **Install Now**
4. Activate the plugin
5. WPRaffle will automatically:
   - Create all required database tables (15 tables)
   - Create a WooCommerce shadow product
   - Create pages with shortcodes (Raffles, Past Raffles, Live Draw)
   - Schedule cron jobs for auto-draw, reminders, charity refresh, and cleanup
6. Configure settings at **Raffles → Settings**

### Manual Install

```bash
git clone https://github.com/wpraffle/wpraffle.git
# Copy to wp-content/plugins/wpraffle/
# Activate from WordPress Plugins page
```

---

## Quick Start

1. Go to **Raffles → Create Raffle**
2. Fill in title, description, prize details, ticket count, price, and packages
3. Configure optional features (instant wins, skill question, geo-restriction, etc.)
4. Publish — copy the shortcode `[raffle id="X"]` to any page
5. Buyers select packages, complete WooCommerce checkout, and receive tickets by email
6. Draw winner from **Raffles → View Details** when ready

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[raffle id="X"]` | Display a single raffle with full UI |
| `[raffle_list columns="3" per_page="12"]` | Active raffles in a responsive grid |
| `[raffle_ended_list columns="3"]` | Completed/finished raffles |
| `[raffle_lookup]` | Ticket lookup form — emails a single-use secure link (v1.2.1) |
| `[raffle_live_draw raffle_id="X"]` | Live animated draw page |
| `[raffle_entry_list raffle_id="X"]` | Entry/ticket list for a raffle |
| `[raffle_charities columns="3"]` | Charity directory grid with live totals *(v1.2)* |
| `[raffle_refer raffle_id="X"]` | Referral card with share buttons + earned bonus count *(v1.2)* |

---

## Elementor Widgets

When Elementor is active, 18 custom widgets are available. Every single-raffle
widget has a **Raffle** selector in its Content tab — pick a specific raffle to
display, or leave it on "current page" to use the raffle linked to the product.
Each widget renders an editor preview and exposes full styling controls
(colours, typography, borders, shadows, responsive spacing).

| Widget | Description |
|--------|-------------|
| Raffle Full Page | Complete all-in-one raffle layout |
| Raffle Title | Raffle title with styling |
| Raffle Image | Prize image with optional cash-alternative badge |
| Raffle Price | Ticket price (or prize value) display |
| Raffle Progress | Sales progress bar with configurable colours/height |
| Raffle Countdown | Live countdown timer with custom labels |
| Raffle Quantity Selector | Package selection grid + slider |
| Raffle Enter Button | CTA button (or SOLD OUT badge) |
| Raffle Description | Raffle description text in a card |
| Raffle Stats Header | Key stats (max tickets, available, draw date) |
| Raffle Tabs | Online / Postal entry tabs |
| Raffle Instant Wins | Instant win prizes grid |
| Raffle Question | Skill question form |
| Raffle Trust Badges | Secure / confirmation / random-draw badges |
| Raffle Purchase Modal | Purchase modal (place once per page) |
| All Competitions | Raffle list/grid |
| Ended Raffles | Past competitions grid |
| Entry List | Ticket/entry list |


---

## Project Structure

```
wpraffle/
├── raffle-system.php                  # Plugin entry point
├── README.md                          # This file
├── DOCUMENTATION.md                   # Full documentation
├── .gitignore
│
├── admin/
│   ├── class-raffle-admin.php         # Admin menus, CRUD, settings
│   ├── class-raffle-analytics.php     # Analytics data API
│   └── views/
│       ├── dashboard.php              # Dashboard view
│       ├── raffle-list.php            # All raffles table
│       ├── raffle-form.php            # Create/edit form
│       ├── raffle-details.php         # Raffle details & stats
│       ├── audit-log.php              # Audit log viewer
│       └── settings.php              # Settings (7 tabs)
│
├── includes/
│   ├── functions-icons.php            # SVG sprite icon system
│   ├── class-raffle-activator.php     # Activation: tables, pages, cron
│   ├── class-raffle-tickets.php       # Ticket generation
│   ├── class-raffle-purchase.php      # Purchase processing
│   ├── class-raffle-draw.php          # Winner selection & draw
│   ├── class-raffle-email.php         # Email system
│   ├── class-raffle-woocommerce.php   # WooCommerce integration
│   ├── class-raffle-instant-wins.php  # Instant win logic
│   ├── class-raffle-audit.php         # Audit logging
│   ├── class-raffle-prizes.php        # Multi-prize management
│   ├── class-raffle-duplicates.php    # Duplicate detection
│   ├── class-raffle-referrals.php     # Referral system
│   ├── class-raffle-free-entry.php    # Free/postal entry
│   ├── class-raffle-templates.php     # Templates & clone
│   ├── class-raffle-reservations.php  # Ticket reservations
│   ├── class-raffle-geo.php           # Geo-restriction
│   ├── class-raffle-live-draw.php     # Live draw page
│   ├── class-raffle-pdf.php           # PDF generation
│   ├── class-raffle-dashboard-widgets.php
│   ├── class-raffle-elementor.php     # Elementor registration
│   ├── class-raffle-updater.php       # GitHub auto-updates
│   └── elementor-widgets/             # 18 Elementor widgets
│
├── public/
│   ├── class-raffle-public.php        # Shortcodes, assets, frontend
│   └── views/
│       ├── single-raffle.php          # Single raffle template
│       ├── raffle-display.php         # Raffle display (shortcode)
│       ├── raffle-loop-card.php       # Card for grids/shop
│       ├── entry-list.php             # Entry list view
│       ├── my-raffles.php             # My Raffles page
│       └── account/                   # Account tabs (tickets, wins, etc.)
│
└── assets/
    ├── css/
    │   ├── admin.css                  # Admin styles
    │   ├── public.css                 # Frontend styles
    │   └── icons.css                  # Icon styles
    ├── js/
    │   ├── admin.js                   # Admin JavaScript
    │   ├── dashboard.js               # Dashboard charts
    │   ├── public.js                  # Frontend JavaScript
    │   └── shop-countdown.js          # Shop page countdown
    └── icons/
        └── wpraffle-icons.svg         # SVG sprite
```

---

## Configuration

### Settings Tabs

| Tab | Description |
|-----|-------------|
| **General** | Company name, address, currency, default limits, winner-name privacy (v1.2.1), winners page tabs |
| **Pages** | Page assignments, shortcode reference, shortcode customisation |
| **Email** | Sender details, branding, accent colour, test sender |
| **Legal** | FAQ management with dynamic editor, placeholder reference |
| **Sync** | Raffle ↔ WooCommerce product sync, health checks |
| **Advanced** | Auto-fix, rate limiting, audit log, cron overview |
| **Updates** | GitHub auto-updates, version check |

### Database Tables

The plugin creates **15 custom tables**:

| Table | Purpose |
|-------|---------|
| `wp_raffles` | Main raffle data |
| `wp_raffle_purchases` | Purchase records |
| `wp_raffle_tickets` | Individual ticket numbers |
| `wp_raffle_instant_wins` | Instant win prizes |
| `wp_raffle_prizes` | Multi-winner prize tiers |
| `wp_raffle_referrals` | Referral tracking |
| `wp_raffle_reservations` | Temporary ticket holds |
| `wp_raffle_audit_log` | Audit trail |
| `wp_raffle_templates` | Reusable templates |
| `wp_raffle_free_entries` | Free/postal entries |
| `wp_raffle_charities` | Charity registry (mirrors the CPT) *(v1.2)* |
| `wp_raffle_charity_allocations` | Immutable draw-time charity allocations *(v1.2)* |
| `wp_raffle_credits` | Append-only site-credit ledger *(v1.2)* |
| `wp_raffle_payouts` | Idempotent wallet payout ledger *(v1.2)* |
| `wp_raffle_rg_settings` | Responsible-gambling per-user settings *(v1.2)* |

---

## Security

WPRaffle implements multi-layer security:

- **WordPress nonces** on all forms and AJAX requests (CSRF protection)
- **Capability checks** (`manage_options`) on all admin actions
- **Input sanitization** — `sanitize_text_field()`, `sanitize_email()`, `absint()`, etc.
- **Output escaping** — `esc_html()`, `esc_attr()`, `esc_url()` in all views
- **Prepared SQL statements** via `$wpdb->prepare()` — no raw queries
- **UNIQUE constraint** on `(raffle_id, ticket_number)` prevents duplicates at DB level
- **Cryptographically secure randomness** via `random_int()`
- **Rate limiting** — configurable per-minute per-IP
- **Cart quantity lock** — raffle items cannot have quantity changed in cart
- **Multi-layer validation** — cart enforcement, checkout validation, payment-time clamping
- **Audit logging** for all critical actions
- **Direct access prevention** in all PHP files

---

## Auto-Updates

WPRaffle supports GitHub-based auto-updates:

1. Tag a release on GitHub (e.g. `v1.2.1`)
2. Upload the `.zip` as a release asset
3. Users see the update within 12 hours (or immediately via manual check)
4. Configure in **Raffles → Settings → Updates**

---

## Privacy & Activation Notice

On first activation, WPRaffle sends a **single, anonymous notice** to `wpraffle.dev` so the project can display a unique-install count on the marketing site.

**What is sent (once per install):**
- A random 32-character install ID (generated locally, no relationship to your site)
- The literal event `activated`
- The plugin version string

**What is never sent:** site URL, admin email, WordPress/PHP/WooCommerce versions, visitor IPs, customer data, raffle data, or any personal information. The endpoint is HTTPS-only.

The count is deduped by install ID on the server, so deactivating and reactivating the plugin on the same site never inflates the number. A full uninstall (with the *Delete data on uninstall* option enabled) removes the install ID, so a later reinstall counts as a new install.

**Opt out** anytime at **Raffles → Settings → Updates → Anonymous Activation Notice**. The ping fires on activation, so if you want to prevent it, uncheck the option before the next activation (or set the `wpraffle_tracking_opted_out` option directly).

---

## License

This project is licensed under [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Full Documentation

The full documentation lives at **<https://docs.wpraffle.dev>** and covers every feature,
the database schema, hooks & filters, AJAX endpoints, cron jobs, security model, and more.

The source for the docs site is in the
[`wpraffle-docs` repo](https://github.com/wpraffle/wpraffle-docs). See
[`CHANGELOG.md`](./CHANGELOG.md) for a per-version changelog and
[`RELEASE.md`](./RELEASE.md) for the latest release notes.
