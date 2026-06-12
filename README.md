<p align="center">
  <img src="https://img.shields.io/badge/version-1.1.0-blue?style=flat-square" alt="Version">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce">
  <img src="https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square" alt="License">
</p>

<h1 align="center">🎟️ WPRaffle</h1>

<p align="center">
  A comprehensive WordPress plugin for running online raffles and competitions.<br>
  Built on WooCommerce with full ticket management, live draws, instant wins, and UK compliance features.
</p>

---

## ✨ Features

### Core
- 🎫 **Configurable Raffles** — Title, description, prize image, ticket count, price, packages
- 🛒 **WooCommerce Integration** — Full checkout with any payment gateway, cart & order management
- 🎰 **Random Ticket Assignment** — Cryptographically secure (`random_int()`) unique ticket numbers
- 📧 **Automated Emails** — Purchase confirmation, winner notification, instant win alerts, draw reminders, sold out alerts
- 🔢 **User-Selected Numbers** — Optional mode where buyers pick their own ticket numbers

### Competition Features
- 🏆 **Instant Wins** — Prizes automatically awarded at specific ticket numbers
- 🎯 **Multi-Winner Draws** — Multiple winners with configurable prize tiers
- 📺 **Live Draw** — Animated draw page with spinning numbers and confetti
- ❓ **Skill Questions** — Multiple-choice questions required before purchase (UK Gambling Act compliance)
- 🆓 **Free / Postal Entry** — Alternative entry route for compliance
- 🌍 **Geo-Restriction** — Restrict entry by country via IP geolocation
- 🔗 **Referral System** — Unique referral codes with bonus entries

### Admin
- 📊 **Analytics Dashboard** — Revenue charts, sales trends, activity feed
- 📝 **Audit Log** — Complete action log with actor, IP, and timestamps
- 📋 **Templates & Clone** — Reusable raffle templates and one-click duplication
- 🎫 **Ticket Reservations** — Temporary holds during checkout with auto-cleanup
- 🔍 **Duplicate Detection** — Automatic detection and correction of duplicate tickets
- 🏪 **Shop Integration** — Custom raffle cards in WooCommerce shop pages

### Security
- 🔒 **Multi-layer quantity validation** — Cart lock, checkout validation, payment-time clamping
- 🛡️ **WordPress nonces** on all forms and AJAX (CSRF protection)
- 🧹 **Input sanitization & output escaping** throughout
- 📐 **Prepared SQL statements** — No raw queries
- ⏱️ **Rate limiting** — Configurable per-minute per-IP
- 🔐 **Privacy & GDPR** — Personal data export/erasure via WordPress Privacy API
- 🔄 **Product Sync** — Detect and fix WooCommerce product mismatches
- 🎨 **Shortcode Customisation** — Configure shortcode defaults from settings UI

### Developer
- 🧩 **Elementor Widgets** — 18 custom widgets for visual page building
- 📐 **Shortcodes** — Display raffles, lists, lookup, and live draws anywhere
- 🔄 **GitHub Auto-Updates** — Push updates from GitHub releases
- 🪝 **Hooks & Filters** — Extensible via WordPress actions and filters

---

## 📦 Installation

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
   - Create all required database tables (10 tables)
   - Create a WooCommerce shadow product
   - Create pages with shortcodes (Raffles, Past Raffles, Live Draw)
   - Schedule cron jobs for auto-draw, reminders, and cleanup
6. Configure settings at **Raffles → Settings**

### Manual Install

```bash
git clone https://github.com/wpraffle/wpraffle.git
# Copy to wp-content/plugins/wpraffle/
# Activate from WordPress Plugins page
```

---

## 🚀 Quick Start

1. Go to **Raffles → Create Raffle**
2. Fill in title, description, prize details, ticket count, price, and packages
3. Configure optional features (instant wins, skill question, geo-restriction, etc.)
4. Publish — copy the shortcode `[raffle id="X"]` to any page
5. Buyers select packages, complete WooCommerce checkout, and receive tickets by email
6. Draw winner from **Raffles → View Details** when ready

---

## 📄 Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[raffle id="X"]` | Display a single raffle with full UI |
| `[raffle_list columns="3" per_page="12"]` | Active raffles in a responsive grid |
| `[raffle_ended_list columns="3"]` | Completed/finished raffles |
| `[raffle_lookup]` | Ticket lookup form by email |
| `[raffle_live_draw raffle_id="X"]` | Live animated draw page |
| `[raffle_entry_list raffle_id="X"]` | Entry/ticket list for a raffle |

---

## 🧩 Elementor Widgets

When Elementor is active, 18 custom widgets are available:

| Widget | Description |
|--------|-------------|
| Raffle Full Page | Complete all-in-one raffle layout |
| Raffle Title | Raffle title with styling |
| Raffle Image | Prize image with lightbox |
| Raffle Price | Ticket price display |
| Raffle Progress | Sales progress bar |
| Raffle Countdown | Live countdown timer |
| Raffle Quantity Selector | Package selection grid |
| Raffle Enter Button | CTA button |
| Raffle Description | Raffle description text |
| Raffle Stats Header | Key stats (sold, remaining, price) |
| Raffle Tabs | Tabbed content |
| Raffle Instant Wins | Instant win prizes grid |
| Raffle Question | Skill question form |
| Raffle Trust Badge | Trust/verification badge |
| Raffle Modal | Purchase/entry modal |
| All Competitions | Raffle list/grid |
| Ended Raffles | Past competitions grid |
| Entry List | Ticket/entry list |

---

## 📁 Project Structure

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
│       └── settings.php              # Settings (6 tabs)
│
├── includes/
│   ├── functions-icons.php            # SVG sprite icon system
│   ├── class-raffle-activator.php     # Activation: tables, pages, cron
│   ├── class-raffle-tickets.php       # Ticket generation
│   ├── class-raffle-purchase.php      # Purchase processing
│   ├── class-raffle-draw.php          # Winner selection & draw
│   ├── class-raffle-email.php         # Email system (5 types)
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
│       └── my-raffles.php             # My Raffles page
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

## ⚙️ Configuration

### Settings Tabs

| Tab | Description |
|-----|-------------|
| **General** | Company name, address, currency, default limits, winners page tabs |
| **Pages** | Page assignments, shortcode reference, shortcode customisation |
| **Email** | Sender details, branding, accent colour, test sender |
| **Legal** | FAQ management with dynamic editor, placeholder reference |
| **Sync** | Raffle ↔ WooCommerce product sync, health checks |
| **Advanced** | Auto-fix, rate limiting, audit log, cron overview |
| **Updates** | GitHub auto-updates, version check |

### Database Tables

The plugin creates **10 custom tables**:

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

---

## 🛡️ Security

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

## 🔄 Auto-Updates

WPRaffle supports GitHub-based auto-updates:

1. Tag a release on GitHub (e.g. `v1.1.0`)
2. Upload the `.zip` as a release asset
3. Users see the update within 12 hours (or immediately via manual check)
4. Configure in **Raffles → Settings → Updates**

---

## 📜 License

This project is licensed under [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html).

---

## 📖 Full Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for comprehensive documentation covering all features, database schema, hooks, AJAX endpoints, and more.