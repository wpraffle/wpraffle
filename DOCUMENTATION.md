# WPRaffle — Plugin Documentation

**Plugin Name:** WPRaffle  
**Version:** 1.1.0  
**Author:** WPRaffle  
**License:** GPL-2.0+  
**Requires:** WordPress 6.0+, PHP 8.0+, WooCommerce 8.0+  
**Optional:** Elementor (for visual page building)

---

## Index

1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [Auto-Created Pages](#auto-created-pages)
4. [Shortcode Reference](#shortcode-reference)
5. [Elementor Widgets](#elementor-widgets)
6. [Database Schema](#database-schema)
7. [Admin Panel](#admin-panel)
8. [Settings](#settings)
9. [Raffle Lifecycle States](#raffle-lifecycle-states)
10. [Ticket System](#ticket-system)
11. [Purchase Process](#purchase-process)
12. [WooCommerce Integration](#woocommerce-integration)
13. [Instant Wins](#instant-wins)
14. [Multi-Winner & Multi-Prize Draws](#multi-winner--multi-prize-draws)
15. [Skill Questions](#skill-questions)
16. [Free / Postal Entry](#free--postal-entry)
17. [Geo-Restriction](#geo-restriction)
18. [Referral System](#referral-system)
19. [Templates & Clone](#templates--clone)
20. [Live Draw](#live-draw)
21. [Winner Draw](#winner-draw)
22. [Email System](#email-system)
23. [Countdown Timer](#countdown-timer)
24. [Progress Bar](#progress-bar)
25. [Audit Log](#audit-log)
26. [Duplicate Detection & Correction](#duplicate-detection--correction)
27. [Reservations](#reservations)
28. [Analytics Dashboard](#analytics-dashboard)
29. [GitHub Auto-Updates](#github-auto-updates)
30. [AJAX Endpoints](#ajax-endpoints)
31. [Security](#security)
32. [Cron Jobs](#cron-jobs)
33. [File Structure](#file-structure)
34. [Hooks & Filters](#hooks--filters)
35. [Privacy & GDPR](#privacy--gdpr)
36. [Rate Limiting](#rate-limiting)
37. [Product Sync](#product-sync)
38. [Shortcode Customisation](#shortcode-customisation)

---

## Overview

WPRaffle is a comprehensive WordPress plugin for running online raffles and competitions. Built on WooCommerce, it provides a complete solution for ticket management, prize distribution, live draws, and regulatory compliance (UK Gambling Act).

**Key Features:**
- Random or user-selected ticket numbers
- Instant win prizes at specific ticket numbers
- Multi-winner / multi-prize draws
- Skill-based questions (UK compliance)
- Free / postal entry route
- Geo-restriction by country
- Referral system with bonus entries
- Live animated draw page
- Customisable email templates with branding
- Audit logging for all actions
- Elementor widget integration
- GitHub-powered auto-updates

---

## Installation & Setup

### Requirements
- WordPress 6.0+
- PHP 8.0+
- WooCommerce 8.0+
- Elementor (optional, for visual page building)

### Install
1. Upload the `wpraffle` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. WPRaffle will automatically:
   - Create all required database tables
   - Create a WooCommerce shadow product
   - Create pages with shortcodes (Raffles, Past Raffles, Live Draw, My Raffles)
   - Schedule cron jobs for auto-draw, reminders, and cleanup
4. Configure settings at **Raffles → Settings**

### First Raffle
1. Go to **Raffles → Create Raffle**
2. Fill in title, description, prize details, ticket count, price, and packages
3. Configure optional features (instant wins, skill question, geo-restriction, etc.)
4. Publish — the raffle will be available at your Raffles page

---

## Auto-Created Pages

On activation, WPRaffle creates these pages automatically:

| Page | Content | Purpose |
|------|---------|---------|
| **Raffles** | `[raffle_list]` | Browse all active raffles |
| **Past Raffles** | `[raffle_ended_list]` | Browse completed raffles |
| **Live Draw** | `[raffle_live_draw]` | Live animated draw page |
| **My Raffles** | WooCommerce endpoint | User's ticket history |

Manage pages at **Raffles → Settings → Pages**. Missing pages can be recreated individually.

---

## Shortcode Reference

### `[raffle id="X"]`
Display a single raffle with full UI — prize image, title, packages, countdown, progress bar, purchase modal.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | — | **Required.** Raffle ID |

### `[raffle_list]`
Display all active raffles in a responsive grid with cards.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `columns` | int | 3 | Grid columns (2, 3, or 4) |
| `per_page` | int | 12 | Items per page (pagination) |

### `[raffle_ended_list]`
Display all finished/completed raffles.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `columns` | int | 3 | Grid columns (2, 3, or 4) |

### `[raffle_lookup]`
Ticket lookup form — users enter their email to find all their tickets across all raffles.

No attributes.

### `[raffle_entry_list]`
Display closed raffles with PDF entry list download buttons.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `columns` | int | 2 | Grid columns (1–4) |
| `layout` | string | grid | `grid` or `list` |
| `button_text` | string | Download Entry List | Download button label |
| `button_bg` | hex | #1e40af | Button background colour |
| `button_color` | hex | #ffffff | Button text colour |
| `button_radius` | int | 8 | Button border radius (px) |
| `show_image` | yes/no | yes | Show prize image |

### `[raffle_live_draw raffle_id="X"]`
Live animated draw page with spinning numbers and winner reveal.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `raffle_id` | int | — | **Required.** Raffle ID |

---

## Elementor Widgets

When Elementor is active, these custom widgets are available:

| Widget | Description |
|--------|-------------|
| Raffle Title | Raffle title with styling options |
| Raffle Image | Prize image with lightbox |
| Raffle Price | Ticket price or prize value display |
| Raffle Progress | Sales progress bar with stats |
| Raffle Countdown | Live countdown timer to draw date |
| Raffle Quantity Selector | Package selection grid |
| Raffle Enter Button | CTA button to enter the raffle |
| Raffle Description | Raffle description text |
| Raffle Stats Header | Key stats (sold, remaining, price) |
| Raffle Tabs | Tabbed content (description, instant wins, question) |
| Raffle Instant Wins | Instant win prizes grid |
| Raffle Question | Skill question form |
| Raffle Trust Badge | Trust/verification badge |
| Raffle Full Page | Complete raffle layout (all-in-one) |
| Raffle Modal | Purchase/entry modal |

---

## Database Schema

The plugin creates **10 tables**:

### `wp_raffles` — Main Raffle Table
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| title | varchar(255) | Raffle name |
| description | text | Detailed description |
| prize_value | decimal(10,2) | Monetary value of the prize |
| prize_image | varchar(500) | Prize image URL |
| total_tickets | int(11) | Total tickets available |
| sold_tickets | int(11) | Tickets sold |
| ticket_price | decimal(10,2) | Price per ticket |
| packages | text | JSON array of package sizes |
| start_date | datetime | Start date/time |
| draw_date | datetime | Scheduled draw date |
| status | varchar(20) | `active`, `finished`, `draft` |
| winner_ticket_id | bigint(20) | Winning ticket ID |
| wc_product_id | bigint(20) | Linked WooCommerce product |
| enable_cash_alternative | tinyint(1) | Cash alternative toggle |
| cash_alternative_amount | decimal(10,2) | Cash alternative value |
| ticket_selection | varchar(20) | `random` or `user` |
| draw_type | varchar(20) | `manual` or `auto` |
| live_draw_url | varchar(500) | External live draw URL |
| jackpot_type | varchar(20) | `fixed` or `percentage` |
| jackpot_percent | int(11) | Percentage of sales for jackpot |
| discount_rules | text | JSON discount rules |
| enable_question | tinyint(1) | Skill question toggle |
| question_text | text | Skill question |
| question_answers | text | JSON array of answers |
| correct_answer_index | int(11) | Index of correct answer |
| postal_instructions | text | Postal entry instructions |
| max_tickets_per_user | int(11) | Per-user ticket limit |
| reminder_sent | tinyint(1) | 24h reminder sent flag |
| multi_winner | tinyint(1) | Multi-winner toggle |
| number_of_winners | int(11) | Number of winners to draw |
| allow_free_entry | tinyint(1) | Free entry toggle |
| free_entry_question | text | Free entry question |
| free_entry_answers | text | JSON free entry answers |
| free_entry_correct_index | int(11) | Correct free entry answer |
| geo_restricted | tinyint(1) | Geo-restriction toggle |
| geo_allowed_countries | text | JSON array of country codes |
| allow_referrals | tinyint(1) | Referral system toggle |
| referral_bonus_entries | int(11) | Bonus entries per referral |
| template_id | bigint(20) | Template used for creation |
| created_at | datetime | Creation timestamp |

### `wp_raffle_purchases` — Purchases
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| buyer_name | varchar(255) | Buyer's name |
| buyer_email | varchar(255) | Buyer's email |
| quantity | int(11) | Tickets purchased |
| total_amount | decimal(10,2) | Total paid |
| payment_status | varchar(20) | `pending`, `completed` |
| wc_order_id | bigint(20) | WooCommerce order ID |
| purchase_date | datetime | Transaction timestamp |
| referral_code | varchar(50) | Referral code used |
| entry_type | varchar(20) | `paid`, `free`, `referral` |

### `wp_raffle_tickets` — Individual Tickets
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| purchase_id | bigint(20) FK | Purchase ID |
| ticket_number | int(11) | Ticket number (1 to total) |
| buyer_email | varchar(255) | Ticket holder's email |
| is_reserved | tinyint(1) | Reservation status |
| reserved_at | datetime | Reservation timestamp |

**UNIQUE key** on `(raffle_id, ticket_number)` prevents duplicates.

### `wp_raffle_instant_wins` — Instant Win Prizes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| ticket_number | int(11) | Ticket that wins the prize |
| prize_name | varchar(255) | Prize description |
| status | varchar(20) | `available`, `won` |
| winner_email | varchar(255) | Winner's email |
| purchase_id | bigint(20) | Purchase ID |

### `wp_raffle_prizes` — Multi-Winner Prizes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| position | int(11) | Prize position (1st, 2nd, etc.) |
| prize_name | varchar(255) | Prize description |
| prize_value | decimal(10,2) | Prize value |
| prize_image | varchar(500) | Prize image URL |
| winner_ticket_id | bigint(20) | Winning ticket ID |

### `wp_raffle_referrals` — Referral Tracking
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| user_email | varchar(255) | Referrer's email |
| referral_code | varchar(50) | Unique referral code |
| referred_email | varchar(255) | Referred user's email |
| bonus_entries | int(11) | Bonus entries awarded |
| created_at | datetime | Creation timestamp |

### `wp_raffle_reservations` — Ticket Reservations
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| ticket_numbers | text | Reserved ticket numbers |
| user_email | varchar(255) | User's email |
| session_id | varchar(100) | Session identifier |
| expires_at | datetime | Expiry timestamp |

### `wp_raffle_audit_log` — Audit Trail
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| action | varchar(100) | Action type |
| actor | varchar(255) | User who performed action |
| details | longtext | JSON details |
| ip_address | varchar(45) | IP address |
| created_at | datetime | Timestamp |

### `wp_raffle_templates` — Raffle Templates
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| name | varchar(255) | Template name |
| config | longtext | JSON configuration |
| created_at | datetime | Creation timestamp |

### `wp_raffle_free_entries` — Free/Postal Entries
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) PK | Auto-increment |
| raffle_id | bigint(20) FK | Raffle ID |
| buyer_name | varchar(255) | Entrant's name |
| buyer_email | varchar(255) | Entrant's email |
| answer_index | int(11) | Selected answer |
| ticket_number | int(11) | Assigned ticket |
| status | varchar(20) | `pending`, `approved` |
| created_at | datetime | Timestamp |

---

## Admin Panel

The plugin adds a **"Raffles"** menu in the WordPress dashboard:

### All Raffles (List)
- Table with all created raffles
- Columns: ID, Title, Prize Value, Sold/Total, Ticket Price, Draw Date, Status
- Actions per raffle: View, Edit, Delete (with confirmation)
- Shortcode usage instructions displayed

### Dashboard
- Revenue overview with charts
- Ticket sales graph
- Active raffles summary
- Recent activity feed

### Create / Edit Raffle
Full form with sections:
- **General Details** — Title, description, prize value, prize image
- **Ticket Settings** — Total tickets, ticket price, packages, max per user, selection mode
- **Schedule** — Start date, draw date, draw type (manual/auto)
- **Instant Wins** — Configure instant win prizes on specific ticket numbers
- **Multi-Winner** — Multiple winners, prize tiers
- **Skill Question** — Question text, multiple choice answers, correct answer
- **Free Entry** — Free entry question, postal instructions
- **Geo-Restriction** — Restrict to specific countries
- **Referrals** — Referral toggle, bonus entries
- **Advanced** — Cash alternative, jackpot type, discount rules

### Raffle Details
- Statistical cards: sold, remaining, revenue, draw date
- Copy-to-clipboard shortcode
- Draw section with winner display
- Duplicate control (check, fix, auto-fix toggle)
- Purchases table with all orders and assigned tickets

---

## Settings

Unified settings page at **Raffles → Settings** with 7 tabs:

### General
- Company name and address (for postal entry compliance)
- Site logo URL
- Currency code (GBP, USD, EUR, COP)
- Default max tickets per user
- Winners page tab visibility (Live Draw, Auto-Draw, Instant Wins)

### Pages
- Page assignments — dropdown selectors for each feature page (Raffles, Past Raffles, Entry Lists, Live Draw)
- Create missing pages individually
- Edit / View links for assigned pages
- My Raffles endpoint status
- Full shortcode reference
- Elementor widget reference
- **Shortcode Customisation** — Toggle-based panels for configuring shortcode defaults:
  - `[raffle_ended_list]` — columns, show/hide image, winner, video button, verified button, date, entries
  - `[raffle_entry_list]` — layout, columns, button text, button colours, border radius, show image
  - `[raffle_list]` — default status filter (active, finished, draft, all)

### Email
- Sender name and email
- Accent colour (for email branding)
- Logo URL (for email header)
- Footer text
- Test email sender
- Email types reference (5 automated email types)

### Legal
- **FAQ Management** — Dynamic editor with add/remove buttons
- Individual question and answer fields
- Automatic migration from legacy text-based FAQ
- Placeholder reference table (`{{max_tickets}}`, `{{total_tickets}}`, etc.)

### Sync
- Raffle ↔ WooCommerce product sync status table
- Detects: missing products, deleted products, status mismatches, price mismatches
- **Sync All** — batch fix all issues
- Individual per-raffle sync and product creation
- Visual health indicator (green "All in sync" or yellow warning)

### Advanced
- Auto-fix duplicates toggle
- Rate limiting (requests per minute per IP)
- Audit logging toggle and retention period
- Scheduled tasks overview with next run times

### Updates
- Current version display
- Latest available version from GitHub
- Manual "Check for Updates" button
- GitHub repository configuration
- Auto-update toggle

---

## Raffle Lifecycle States

| Status | Description |
|--------|-------------|
| `draft` | Not yet published, not visible on frontend |
| `active` | Live and accepting entries |
| `finished` | Draw completed, winner(s) selected |

---

## Ticket System

- Numbers assigned **randomly** using `random_int()` (cryptographically secure)
- **Pool-based**: available numbers built excluding sold tickets
- **Leading zeros**: formatted based on total tickets (e.g. `0042` for 1000-ticket raffle)
- **UNIQUE constraint** at database level prevents duplicate numbers
- **User selection** mode: buyers can pick their own numbers (if enabled)

---

## Purchase Process

1. User selects a package on the public page
2. Modal opens requesting name and email
3. AJAX request sent to server
4. **Server validations:**
   - Required fields complete
   - Valid email format
   - Raffle exists and is active
   - Quantity matches a valid package
   - Enough tickets remain
   - Rate limit not exceeded
5. Purchase record created
6. Random ticket numbers generated
7. Instant wins checked against winning ticket numbers
8. Confirmation email sent
9. Duplicate auto-fix runs (if enabled)
10. Formatted ticket numbers returned to frontend
11. Confirmation modal displayed

---

## WooCommerce Integration

Each raffle automatically creates/updates a WooCommerce simple product:
- Product price = ticket price
- Product synced with raffle status
- Orders link back to raffle purchases
- WooCommerce handles checkout flow and payment processing
- Shadow product for generic raffle entry purchases

---

## Instant Wins

Configure prizes that are won automatically when specific ticket numbers are purchased:
- Assign prize names to ticket numbers
- Status tracks `available` or `won`
- Winner receives instant notification email
- Instant wins displayed in raffle UI

---

## Multi-Winner & Multi-Prize Draws

- Configure multiple winners per raffle
- Assign different prizes to different positions (1st, 2nd, 3rd, etc.)
- Each prize can have its own image and value
- Winners selected randomly from sold tickets

---

## Skill Questions

UK Gambling Act compliance feature:
- Optional skill question required before purchase
- Multiple choice (3 options)
- Configurable correct answer
- Question displayed in the purchase flow
- Must answer correctly to proceed

---

## Free / Postal Entry

Alternative entry route for UK compliance:
- Free entry via skill question answer
- Postal entry instructions auto-generated
- Company name and address from settings
- Free entries tracked separately
- Ticket numbers assigned the same way

---

## Geo-Restriction

Restrict raffle entry to specific countries:
- IP-based geolocation using free API
- Configurable allowed countries list
- Blocked users see a notice explaining restriction
- Country detection cached per session

---

## Referral System

- Each raffle can generate unique referral codes
- Referrers earn bonus entries when their code is used
- Referral tracking in dedicated table
- Configurable bonus entries count

---

## Templates & Clone

- Save raffle configurations as reusable templates
- Create new raffles from templates
- Clone existing raffles with one click
- Template library in admin

---

## Live Draw

Animated live draw page:
- Spinning number animation
- Progressive number reveal
- Winner announcement with confetti
- Real-time or on-demand activation
- Dedicated shortcode `[raffle_live_draw]`

---

## Winner Draw

- Only users with `manage_options` can execute
- Confirmation dialog before proceeding
- **Algorithm**: All sold tickets retrieved → random selection via `random_int()`
- Multi-winner: sequential random draws without replacement
- Raffle updated: `winner_ticket_id` saved, status → `finished`
- Audit log entry created
- Winner notification email sent
- Cannot draw if already drawn or no tickets sold

---

## Email System

### Automated Emails (5 types)
| Email | Trigger | Recipient |
|-------|---------|-----------|
| Purchase Confirmation | After successful order | Buyer |
| Winner Notification | After draw | Winner |
| Instant Win Alert | When instant win triggered | Buyer |
| Draw Reminder | 24 hours before draw | All entrants |
| Sold Out Alert | All tickets sold | Admin |

### Branding
- Customisable accent colour
- Logo in email header
- Custom footer text
- Responsive HTML design
- Sender name and email from settings

---

## Countdown Timer

- Visual countdown with days, hours, minutes, seconds
- Updates every second via JavaScript
- Auto-hides when time expires
- Shows "Draw time!" message when complete
- Dark gradient design with glass-morphism

---

## Progress Bar

- Shows sold vs total tickets
- Animated gradient bar
- Percentage display
- Statistics: individual price, remaining tickets
- Auto-updates after purchase

---

## Audit Log

- Logs all raffle actions: draws, purchases, admin changes
- Records actor, IP address, timestamp, and details
- Configurable retention period
- Automatic cleanup of old entries
- Toggle on/off in settings

---

## Duplicate Detection & Correction

### Manual
- "Check Duplicates" button in raffle details
- SQL `GROUP BY` + `HAVING COUNT(*) > 1` detection
- Shows affected ticket numbers

### Auto-Correction
- Keeps lowest ID ticket, reassigns duplicates to new random numbers
- Recalculates `sold_tickets` counter
- Toggleable per raffle or globally

### Database Protection
- UNIQUE constraint on `(raffle_id, ticket_number)`
- Serves as final safety net

---

## Reservations

- Temporary ticket holds during checkout
- Session-based tracking
- Automatic expiry (configurable)
- Hourly cleanup via cron job

---

## Analytics Dashboard

- Revenue charts over time
- Ticket sales trends
- Active raffle statistics
- Recent purchase feed
- Currency-aware formatting

---

## GitHub Auto-Updates

### How It Works
1. Plugin checks GitHub releases API twice daily
2. Compares latest release tag with installed version
3. If newer version found, injects into WordPress update transient
4. Update appears on **Plugins** page with install button
5. Auto-update can be enabled/disabled in settings

### Configuration
- GitHub repository in `owner/repo` format
- Manual "Check for Updates" button
- Auto-update toggle
- Update status display (current vs latest)

### Release Process
1. Tag a release on GitHub (e.g. `v1.1.0`)
2. Upload `.zip` file as release asset
3. Users see update within 12 hours (or immediately on manual check)

---

## AJAX Endpoints

| Endpoint | Access | Description |
|----------|--------|-------------|
| `raffle_purchase` | Public | Process ticket purchase |
| `raffle_draw` | Admin | Select random winner |
| `raffle_check_duplicates` | Admin | Detect duplicate tickets |
| `raffle_fix_duplicates` | Admin | Fix duplicate tickets |
| `raffle_toggle_auto_fix` | Admin | Toggle auto-correction |
| `raffle_analytics_data` | Admin | Dashboard chart data |
| `raffle_instant_win_check` | Public | Check instant win status |
| `raffle_validate_referral` | Public | Validate referral code |
| `raffle_free_entry_submit` | Public | Submit free entry |
| `raffle_check_geo` | Public | Check geo-restriction |

---

## Security

- **WordPress Nonces** on all forms and AJAX requests (CSRF protection)
- **Permission checks** (`manage_options`) on all admin actions
- **Input sanitization:**
  - `sanitize_text_field()` for text
  - `sanitize_textarea_field()` for long text
  - `sanitize_email()` + `is_email()` for emails
  - `absint()` for integers
  - `floatval()` for decimals
  - `esc_url_raw()` for URLs
- **Output escaping** (`esc_html()`, `esc_attr()`, `esc_url()`) in all views
- **Prepared statements** (`$wpdb->prepare()`) in all SQL queries
- **UNIQUE key** on `(raffle_id, ticket_number)`
- **`random_int()`** for cryptographically secure randomness
- **Rate limiting** (configurable per-minute per-IP)
- **Audit logging** for all critical actions
- **Direct access prevention** (`if (!defined('ABSPATH')) exit;`) in all PHP files
- **Multi-layer cart quantity enforcement:**
  1. **Cart UI Lock** — Quantity input replaced with static display + hidden field
  2. **Cart Calculation Enforcement** — On `woocommerce_before_calculate_totals`, forces WC cart quantity back to original `raffle_quantity` if tampered
  3. **Price Integrity** — Subtotal verified against `raffle_quantity × ticket_price` from database
  4. **Checkout Validation** — On `woocommerce_checkout_process`, re-checks max per user, availability, raffle status, cumulative email purchases
  5. **Payment Complete Re-validation** — Before ticket generation, clamps quantity to database limits and logs security notes if clamping occurred
  6. **Safe Meta Storage** — Order item meta reads from `raffle_quantity` (tamper-proof) instead of WC `quantity`

---

## Cron Jobs

| Hook | Interval | Purpose |
|------|----------|---------|
| `raffle_system_auto_draw_cron` | Hourly | Auto-draw expired raffles |
| `raffle_draw_reminder_cron` | Hourly | Send 24h draw reminder emails |
| `raffle_cleanup_reservations` | Hourly | Remove expired reservations |
| `wpraffle_check_updates` | Twice daily | Check GitHub for updates |

---

## File Structure

```
wpraffle/
├── raffle-system.php                      # Main plugin file
├── DOCUMENTATION.md                       # This file
├── README.md                              # Plugin README
├── .gitignore                             # Git ignore rules
│
├── admin/
│   ├── class-raffle-admin.php             # Admin menus, CRUD, settings handlers
│   ├── class-raffle-analytics.php         # Analytics data API
│   └── views/
│       ├── dashboard.php                  # Admin dashboard view
│       ├── raffle-list.php                # Raffle list table
│       ├── raffle-form.php                # Create/edit raffle form
│       ├── raffle-details.php             # Raffle details & stats
│       ├── audit-log.php                  # Audit log viewer
│       └── settings.php                   # Unified settings (6 tabs)
│
├── includes/
│   ├── functions-icons.php                # SVG sprite system & helper
│   ├── class-raffle-activator.php         # Activation: tables, pages, cron
│   ├── class-raffle-tickets.php           # Ticket generation logic
│   ├── class-raffle-purchase.php          # Purchase processing (AJAX)
│   ├── class-raffle-draw.php              # Winner selection & draw
│   ├── class-raffle-email.php             # All email sending
│   ├── class-raffle-duplicates.php        # Duplicate detection & correction
│   ├── class-raffle-woocommerce.php       # WooCommerce integration
│   ├── class-raffle-instant-wins.php      # Instant win logic
│   ├── class-raffle-audit.php             # Audit logging
│   ├── class-raffle-prizes.php            # Multi-prize management
│   ├── class-raffle-referrals.php         # Referral system
│   ├── class-raffle-free-entry.php        # Free/postal entry
│   ├── class-raffle-templates.php         # Templates & clone
│   ├── class-raffle-reservations.php      # Ticket reservations
│   ├── class-raffle-geo.php               # Geo-restriction
│   ├── class-raffle-live-draw.php         # Live draw page
│   ├── class-raffle-pdf.php               # PDF generation
│   ├── class-raffle-dashboard-widgets.php # WP admin dashboard widgets
│   ├── class-raffle-elementor.php         # Elementor widget registration
│   ├── class-raffle-updater.php           # GitHub auto-update system
│   └── elementor-widgets/
│       ├── class-widget-title.php         # Raffle Title
│       ├── class-widget-image.php         # Raffle Image
│       ├── class-widget-price.php         # Raffle Price
│       ├── class-widget-progress.php      # Raffle Progress Bar
│       ├── class-widget-countdown.php     # Raffle Countdown
│       ├── class-widget-quantity.php      # Raffle Quantity Selector
│       ├── class-widget-enter-btn.php     # Raffle Enter Button
│       ├── class-widget-description.php   # Raffle Description
│       ├── class-widget-stats-header.php  # Raffle Stats Header
│       ├── class-widget-tabs.php          # Raffle Tabs
│       ├── class-widget-instant-wins.php  # Raffle Instant Wins
│       ├── class-widget-question.php      # Raffle Question
│       ├── class-widget-trust.php         # Raffle Trust Badge
│       ├── class-widget-full-page.php     # Raffle Full Page
│       ├── class-widget-modal.php         # Raffle Modal
│       ├── class-widget-all-competitions.php # All Competitions list
│       ├── class-widget-ended-raffles.php # Ended Raffles grid
│       └── class-widget-entry-list.php    # Entry list
│
├── public/
│   ├── class-raffle-public.php            # Shortcodes, assets, frontend
│   └── views/
│       ├── single-raffle.php              # Single raffle template
│       ├── raffle-display.php             # Raffle display (shortcode)
│       ├── raffle-loop-card.php           # Raffle card for grids/shop
│       ├── entry-list.php                 # Entry list view
│       └── my-raffles.php                 # My Raffles page
│
├── assets/
│   ├── css/
│   │   ├── admin.css                      # Admin panel styles
│   │   ├── public.css                     # Frontend styles
│   │   └── icons.css                      # WPRaffle icon system
│   ├── js/
│   │   ├── admin.js                       # Admin JavaScript
│   │   ├── dashboard.js                   # Dashboard charts
│   │   ├── public.js                      # Frontend JavaScript
│   │   └── shop-countdown.js              # Shop page countdown
│   └── icons/
│       └── wpraffle-icons.svg             # SVG sprite (custom icons)
```

---

## Hooks & Filters

### Actions
- `raffle_system_auto_draw_cron` — Auto-draw expired raffles
- `raffle_draw_reminder_cron` — Send draw reminder emails
- `raffle_cleanup_reservations` — Cleanup expired reservations
- `wpraffle_check_updates` — Check GitHub for updates

### Filters
- `pre_set_site_transient_update_plugins` — Inject plugin updates
- `plugins_api` — Plugin info for update screen

---

## Privacy & GDPR

WPRaffle integrates with the WordPress Privacy API (Tools → Export/Erase Personal Data) for GDPR compliance.

### Personal Data Export
Registered via `wp_privacy_personal_data_exporters` hook. Exports all data associated with a given email address across 5 tables:

| Table | Data Exported |
|-------|---------------|
| `wp_raffle_purchases` | Name, email, quantity, amount, status, date, entry type |
| `wp_raffle_tickets` | Ticket numbers, raffle associations |
| `wp_raffle_instant_wins` | Prize names, status, won dates |
| `wp_raffle_referrals` | Referral codes, bonus entries |
| `wp_raffle_free_entries` | Free entry records, answers, status |

### Personal Data Erasure
Registered via `wp_privacy_personal_data_erasers` hook. For each email:
- Purchases: buyer name anonymised, email cleared
- Tickets: buyer email cleared
- Instant wins: winner email cleared
- Referrals: user and referred emails cleared
- Free entries: name and email cleared

### Implementation
- File: `includes/class-raffle-privacy.php`
- Hooked in `raffle-system.php` on `plugins_loaded`
- Uses WordPress core privacy data exporter/eraser callbacks

---

## Rate Limiting

Standalone rate limiting class using WordPress transients for storage.

### Features
- Per-IP, per-action rate limiting
- Configurable window (seconds) and request limit
- Uses WordPress transients (no additional database tables)
- Automatic cleanup via transient expiry

### Usage
```php
// Check if rate limited
$limiter = new Raffle_Rate_Limiter();
if ( $limiter->is_rate_limited( 'raffle_purchase', 5, 60 ) ) {
    wp_send_json_error( array( 'message' => 'Rate limit exceeded.' ) );
}
```

### Configuration
- Configured in **Settings → Advanced** (requests per minute per IP)
- Applied to: purchase AJAX, free entry submission, referral validation

### Implementation
- File: `includes/class-raffle-rate-limiter.php`
- Uses `$_SERVER['REMOTE_ADDR']` (with `sanitize_text_field()`)
- Transient key format: `wpraffle_rate_{action}_{ip_hash}`

---

## Product Sync

Raffle ↔ WooCommerce product sync tool for detecting and fixing data mismatches.

### What It Checks
- **Missing product** — Raffle exists but has no WooCommerce product
- **Deleted product** — Raffle references a product ID that no longer exists
- **Status mismatch** — Raffle is active but product is draft (or vice versa)
- **Price mismatch** — Product price differs from raffle ticket price
- **Meta mismatch** — Product `_raffle_id` meta missing or incorrect

### How to Use
1. Go to **Raffles → Settings → Sync**
2. Review the sync status table showing all raffles and their product state
3. Use **Sync All** to batch-fix all issues
4. Or use individual **Fix** / **Create Product** buttons per raffle

### Sync Actions
| Action | Description |
|--------|-------------|
| **Create Product** | Generates a new WooCommerce product for raffles missing one |
| **Fix** | Syncs status, price, and meta for existing products |
| **Sync All** | Batch processes all raffles with issues |

### Implementation
- Admin handler in `class-raffle-admin.php`
- Product creation via `Raffle_WooCommerce` class
- Nonce verification on all sync AJAX actions

---

## Shortcode Customisation

Shortcode defaults can be configured from **Settings → Pages** in the **Shortcode Customisation** section. Each shortcode has a toggle panel — when enabled, stored settings override defaults.

### Available Customisations

#### `[raffle_ended_list]`
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `columns` | int | 3 | Grid columns (1–6) |
| `show_image` | yes/no | yes | Show prize image |
| `show_winner` | yes/no | yes | Show winner information |
| `show_video_btn` | yes/no | yes | Show "Watch Draw" button |
| `show_verified_btn` | yes/no | yes | Show "Verified Draw" button |
| `show_date` | yes/no | yes | Show draw date badge |
| `show_entries` | yes/no | yes | Show entry count |

#### `[raffle_entry_list]`
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `layout` | grid/list | grid | Card layout style |
| `columns` | int | 2 | Grid columns (1–4) |
| `button_text` | text | Download Entry List | Download button label |
| `button_bg` | hex | #1e40af | Button background colour |
| `button_color` | hex | #ffffff | Button text colour |
| `button_radius` | int | 8 | Button border radius (px) |
| `show_image` | yes/no | yes | Show prize image |

#### `[raffle_list]`
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `status` | text | active | Default status filter (active/finished/draft/all) |

### Override Priority
1. **Inline shortcode attributes** (highest priority) — e.g. `[raffle_ended_list columns="4"]`
2. **Stored settings** from Shortcode Customisation
3. **Plugin defaults** (lowest priority)

### Storage
Settings stored in `wpraffle_shortcode_settings` option as a serialised array.
