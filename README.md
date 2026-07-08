<p align="center">
  <img src="https://img.shields.io/badge/version-1.3.0-blue?style=flat-square" alt="Version">
  <img src="https://img.shields.io/badge/WordPress-6.5%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce">
  <img src="https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square" alt="License">
</p>

<h1 align="center">WPRaffle</h1>

<p align="center">
  A comprehensive WordPress plugin for running online raffles and competitions.<br>
  Built on WooCommerce with full ticket management, live draws, instant wins, raffle lifecycle, charity fundraising, responsible-gambling controls, and UK compliance features.
</p>

<p align="center">
  <a href="https://docs.wpraffle.dev">Full documentation</a> &middot;
  <a href="../../releases">Releases</a> &middot;
  <a href="./CHANGELOG.md">Changelog</a> &middot;
  <a href="./RELEASE.md">Release notes</a>
</p>

---

## What's new in 1.3.0

A feature-parity release that closes every competitive gap against the main rival lottery/raffle plugins. Adds a full instant-win prize engine, a raffle lifecycle, an expanded email suite, a compatibility layer for ten third-party plugins, CSV import/export, Gutenberg blocks, featured winners, and a companion theme.

### Highlights

- **Instant-win engine, rebuilt** — prize types now deliver real prizes automatically: auto-generated WooCommerce coupons, gift products added to the winning order, or **wallet credit paid straight to the winner's WooWallet/TerraWallet balance**, with prize groups and automatic reversal on refund.
- **Raffle lifecycle** — undersold raffles can now FAIL (and auto-refund participants) instead of silently drawing, operators can EXTEND a deadline or RELIST a finished raffle in place (preserving its permalink), manually or on a schedule.
- **Complete email lifecycle** — distinct loser, instant-win, started, extended, failed-participant, and six admin notification emails, each with its own on/off toggle, plus optional PDF ticket attachments.
- **Ten compatibility integrations** — auto-activating adapters for WPML, Polylang, Multi-Currency, Stripe, Square, WooPayments, Smart Coupons, Dokan, Yoast/Rank Math, and page-cache plugins.
- **Gutenberg blocks** — a starter set of server-rendered blocks for non-Elementor sites, with no build step.
- **Featured winners** — flag winners as featured, upload a photo, add a testimonial — ready for a winners carousel.
- **Companion theme** — pair with the [WPRaffle Theme](https://github.com/wpraffle/wpraffle-theme) for a premium, purpose-built aesthetic.
- **Critical fix: order reversion** — cancelling, refunding, or failing an order now reverts the allocated tickets and instant-win prizes.

See [`RELEASE.md`](./RELEASE.md) for the full release notes and [`CHANGELOG.md`](./CHANGELOG.md) for the complete version history.

---

## Features

### Core
- **Configurable Raffles** — title, description, prize image, ticket count, price, packages, schedules, and lifecycle states
- **WooCommerce Integration** — full checkout with any payment gateway, cart and order management. Each raffle gets its own synced product
- **Ticket Numbering** — cryptographically secure random numbers by default, with optional sequential or shuffled modes and per-raffle prefix/suffix *(v1.3)*
- **Automated Emails** — a full lifecycle suite: purchase, winner, instant-win, no-luck, started, extended, and admin notifications — each with its own on/off toggle *(v1.3)* and PDF ticket attachment
- **User-Selected Numbers** — optional mode where buyers pick their own ticket numbers

### Competition Features
- **Instant Wins** — prizes automatically awarded at specific ticket numbers as coupons, gift products, wallet credit, physical, or custom types, with prize groups and reversal on refund *(v1.3)*
- **Raffle Lifecycle** — min-tickets thresholds with auto-fail and auto-refund, deadline extends, and one-click or scheduled relists *(v1.3)*
- **Featured Winners** — flag winners as featured, upload a photo, add a testimonial — queryable for a carousel *(v1.3)*
- **Multi-Winner Draws** — multiple winners with configurable prize tiers
- **Live Draw** — animated draw page with spinning numbers
- **Skill Questions** — multiple-choice gate before purchase (UK Gambling Act compliance), with optional time limit and attempt cap *(v1.3)*
- **Free / Postal Entry** — alternative entry route for compliance
- **Geo-Restriction** — restrict entry by country via IP geolocation
- **Referral System** — unique referral codes with bonus entries (paid-purchase verified)

### Engagement & Conversion
- **Ticket Bundles** — quantity bundles with custom pricing, savings %, and badges
- **Number Picker Grid** — visual grid where buyers pick their own ticket numbers
- **Consolation Coupons** — auto-issue WooCommerce coupons to non-winning entrants after the draw
- **Virality / Share** — per-user referral links + share buttons (WhatsApp, Facebook, X, copy-link)
- **Scarcity / Urgency** — live stock polling, "X people viewing now" social proof, low-stock alerts

### Buyer Experience
- **My Raffles Account** — permanent ticket history with live odds, a clear "YOU WON!" badge, and a results link
- **My Coupons** — a dedicated account tab showing won coupons with click-to-copy codes, expiry, and Ready/Used badges *(v1.3)*
- **Odds of Winning** — a live "1 in N" odds display updates as buyers change their ticket quantity
- **SOLD OUT & Ending Soon** — shop cards auto-badge with scarcity and end states
- **Guest Ticket Lookup** — guests get a single-use secure link emailed to view their tickets
- **Accessible Checkout** — keyboard-friendly purchase modal with focus trapping and screen-reader announcements

### Charity & Fundraising
- **Charity Registry** — CPT-based charity directory with `[raffle_charities]` shortcode
- **Live Totals** — public grid updates as tickets sell; allocations snapshotted at draw
- **Disbursement Workflow** — operator-only CSV export and mark-disbursed flow

### Responsible Gambling
- **Spend Limits** — day/week/month limits with a 24h cool-off on increases
- **Self-Exclusion** — including email-based guest exclusion; cannot be lifted early
- **Operator Locks** — per-account locks with reason + audit
- **Server-Side Enforcement** — all purchase gates enforced server-side

### Compatibility Layer *(v1.3)*
Auto-activating adapters (zero overhead when inactive) for:
- **WPML / WCML** — sync raffle config across translations
- **Polylang** — resolve translated products to the same raffle
- **Multi-Currency (CURCY)** — convert bundle/package prices
- **Stripe / Square / WooPayments** — enable express checkout (Apple Pay / Google Pay) on raffle products
- **Smart Coupons** — register a store-credit instant-win prize type
- **Dokan / WC Vendors** — route email notifications to the vendor
- **Yoast SEO / Rank Math** — canonical and Open Graph URL fixes
- **Page cache plugins** (W3TC, WPSC, Rocket, LiteSpeed) — flush on draw/sellout/extend/relist

### Admin
- **Analytics Dashboard** — revenue charts, sales trends, activity feed
- **Audit Log** — complete action log with actor, IP, timestamps, and structured details
- **Templates & Clone** — reusable raffle templates and one-click duplication
- **Ticket Reservations** — temporary holds during checkout with auto-cleanup
- **Duplicate Detection** — automatic detection and correction of duplicate tickets
- **CSV Import & Export** — export buyers, tickets, and instant-win rules; bulk-import instant-win prizes *(v1.3)*
- **Order-Item View Tickets** — a "View Tickets" button on each raffle line item in the WooCommerce order screen *(v1.3)*
- **Searchable Raffle List** — search by title, filter by status (including failed/extended), paginate
- **Winner Name Privacy** — optional initials-only display on public results pages

### Security
- **Multi-layer quantity validation** — cart lock, checkout validation, payment-time clamping
- **WordPress nonces** on all forms and AJAX (CSRF protection)
- **Input sanitization & output escaping** throughout
- **Prepared SQL statements** — no raw queries
- **Rate limiting** — configurable per-minute per-IP, proxy-aware
- **Privacy & GDPR** — personal data export/erasure via WordPress Privacy API; two-step deletion
- **Money integrity** — `FOR UPDATE` locks, `GET_LOCK` advisory locks, transactions on every credit/debit
- **Order reversion** — cancel/refund/failed reverts allocated tickets and instant-win prizes *(v1.3)*

### Developer
- **Elementor Widgets** — 18 custom widgets for visual page building
- **Gutenberg Blocks** — countdown, progress, entry button, instant wins, raffle list *(v1.3)*
- **Shortcodes** — 8 shortcodes for display flexibility
- **Compatibility SDK** — write your own adapter via the `Raffle_Compatibility` base class *(v1.3)*
- **Instant-win prize-type SDK** — register custom prize types via the `wpraffle_instant_win_assign_{type}` filter *(v1.3)*
- **GitHub Auto-Updates** — push updates from GitHub releases
- **Hooks & Filters** — extensible via WordPress actions and filters

### Companion Theme
Pair WPRaffle with the **[WPRaffle Theme](https://github.com/wpraffle/wpraffle-theme)** for a premium, purpose-built aesthetic with full Elementor Theme Builder templates, animated countdown timers, winner carousels, and deep plugin integration. Free and open source.

---

## Installation

### Requirements

| Component   | Minimum |
|-------------|---------|
| WordPress   | 6.5+    |
| PHP         | 8.1+    |
| WooCommerce | 8.0+    |
| Elementor   | *Optional* |

### Setup

1. Download the latest release `.zip` from the [Releases](../../releases) page
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the `.zip` and click **Install Now**
4. Activate the plugin
5. WPRaffle will automatically:
   - Create all required database tables
   - Create a WooCommerce shadow product
   - Create pages with shortcodes (Raffles, Past Raffles, Live Draw)
   - Schedule cron jobs for auto-draw, reminders, relist checks, charity refresh, and cleanup
6. Configure settings at **Raffles → Settings**

---

## Quick Start

1. Go to **Raffles → Create Raffle**
2. Fill in title, description, prize details, ticket count, price, and packages
3. Configure optional features (instant wins with prize types, skill question, lifecycle thresholds, geo-restriction, etc.)
4. Publish — copy the shortcode `[raffle id="X"]` to any page
5. Buyers select packages, complete WooCommerce checkout, and receive tickets by email
6. Draw winner from **Raffles → View Details** when ready
7. Flag the winner as featured, upload a photo, and add a testimonial

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[raffle id="X"]` | Display a single raffle with full UI |
| `[raffle_list columns="3" per_page="12"]` | Active raffles in a responsive grid |
| `[raffle_ended_list columns="3"]` | Completed/finished raffles with Live Draw, Auto-Draw, and Instant Wins tabs |
| `[raffle_lookup]` | Ticket lookup form — emails a single-use secure link |
| `[raffle_live_draw raffle_id="X"]` | Live animated draw page |
| `[raffle_entry_list raffle_id="X"]` | Entry/ticket list for a raffle |
| `[raffle_charities columns="3"]` | Charity directory grid with live totals |
| `[raffle_refer raffle_id="X"]` | Referral card with share buttons + earned bonus count |

---

## Settings Tabs

| Tab | Description |
|-----|-------------|
| **General** | Company name, address, currency, default limits, winner-name privacy, winners page tabs |
| **Pages** | Page assignments, shortcode reference, shortcode customisation |
| **Email** | Sender details, branding, per-email toggles, admin recipients, PDF attachment, test sender |
| **Legal** | Rules template, FAQ management with dynamic editor |
| **Sync** | Raffle ↔ WooCommerce product sync + wallet/credit payout re-sync *(v1.3)* |
| **Compatibility** *(v1.3)* | Live status of third-party plugin integrations |
| **Advanced** | Auto-fix, rate limiting, audit log, cron overview |
| **Styling** | Theme presets, CSS token overrides, companion theme banner *(v1.3)* |
| **Updates** | GitHub auto-updates, version check |

---

## Auto-Updates

WPRaffle supports GitHub-based auto-updates:

1. Tag a release on GitHub (e.g. `v1.3.0`)
2. Upload the `.zip` as a release asset
3. Users see the update within 12 hours (or immediately via manual check)
4. Configure in **Raffles → Settings → Updates**

---

## Privacy & Activation Notice

On first activation, WPRaffle sends a **single, anonymous notice** to `wpraffle.dev` so the project can display a unique-install count on the marketing site. It sends only a random install ID and the plugin version — no site URL, no user data. Opt out anytime at **Raffles → Settings → Updates**.

---

## License

This project is licensed under [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Full Documentation

The full documentation lives at **<https://docs.wpraffle.dev>** and covers every feature, the database schema, hooks & filters, AJAX endpoints, cron jobs, security model, compatibility layer, and more.

The source for the docs site is in the [`wpraffle-docs` repo](https://github.com/wpraffle/wpraffle-docs). See [`CHANGELOG.md`](./CHANGELOG.md) for a per-version changelog and [`RELEASE.md`](./RELEASE.md) for the latest release notes.
