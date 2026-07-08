# WPRaffle v1.3.0 Release Notes

**Release date:** 8 July 2026
**Version:** 1.3.0
**Previous version:** 1.2.2

> A feature-parity release that closes the competitive gaps identified against
> the two main rival lottery/raffle plugins. Adds a full instant-win prize
> engine, a raffle lifecycle (relist / extend / min-tickets fail with
> auto-refund), an expanded email suite with per-email toggles and PDF ticket
> attachments, a compatibility layer for ten third-party plugins and gateways,
> CSV import/export, admin order-item ticket management, Q&A time/attempt
> limits, sequential / shuffled ticket numbering, and a starter set of
> Gutenberg blocks — plus a cancel/refund/failed order reversion path that
> previously did not exist.

---

## Headlines

- **Instant-win engine, rebuilt** — instant-win slots now deliver real prizes
  automatically: auto-generated WooCommerce coupons, gift products added to
  the winning order at zero cost, or site credit, with prize groups and
  automatic reversal on refund. Previously a slot held only a free-text prize
  name.
- **A proper raffle lifecycle** — undersold raffles can now FAIL (and
  auto-refund participants) instead of silently drawing, operators can EXTEND
  a deadline or RELIST a finished raffle in place (preserving its permalink),
  manually or on a schedule.
- **A complete email lifecycle** — distinct loser, instant-win, started,
  extended, failed-participant and six admin notification emails, each with
  its own on/off toggle, plus optional PDF ticket attachments.
- **Ten compatibility integrations** — auto-activating adapters for WPML,
  Polylang, Multi-Currency, Stripe, Square, WooPayments, Smart Coupons, Dokan,
  Yoast/Rank Math, and page-cache plugins, with zero overhead when the target
  is absent. A new Compatibility settings tab reports what's live.
- **Gutenberg blocks** — a starter set of server-rendered blocks (countdown,
  progress, entry button, instant wins, raffle list) for non-Elementor sites,
  with no build step required.
- **Critical fix: order reversion** — cancelling, refunding, or failing an
  order now reverts the allocated tickets and instant-win prizes. Previously
  there was no reversion path at all; allocated tickets persisted after the
  sale was undone.

---

## Added

### Instant-Win Engine Overhaul

- **Prize types.** Each instant-win slot carries a `prize_type`: `coupon`
  (auto-generated single-use WooCommerce coupon, email-restricted to the
  winner), `product` (gift product added to the winning order at £0),
  `credit` (site credit), `physical` (operator-fulfilled), or `custom`
  (extensible). Assignment is automatic on payment; reversal is automatic on
  cancel/refund/failed.
- **Prize groups.** Prizes can be grouped with a shared image and display
  config; the Elementor instant-wins widget can render grouped sections.
- **Standalone instant-win email** surfacing any generated coupon codes,
  distinct from the purchase confirmation.
- **Extensibility.** A `wpraffle_instant_win_assign_{type}` /
  `wpraffle_instant_win_reverse_{type}` filter pair lets compatibility classes
  register new prize types (Smart Coupons ships via this mechanism).

### Raffle Lifecycle

- **Min-tickets / min-unique-users thresholds.** A raffle whose draw runs
  before meeting its threshold now FAILS (`status = 'failed'`, `fail_reason`
  recorded) instead of drawing. Driven by `min_tickets` /
  `min_unique_users` columns.
- **Auto-refund on fail.** An opt-in `auto_refund_on_fail` flag triggers a
  WooCommerce refund for every participant of a failed raffle, idempotently.
- **Extend.** Push a raffle's draw date out and reopen it
  (`Raffle_Lifecycle::extend_raffle`).
- **Relist.** Reset a finished/failed raffle in place — snapshots history into
  a `raffle_relists` table, clears entries, re-instantiates instant wins,
  reopens the WC product. Reuses the same raffle id + permalink (unlike
  clone). Manual or scheduled via the `wpraffle_relist_check` cron with count
  and pause-window configuration.
- New lifecycle actions: `wpraffle_raffle_failed`, `wpraffle_raffle_extended`,
  `wpraffle_raffle_relisted`.

### Email Lifecycle Expansion

- New transactional emails: `no_luck` (standalone loser email), `instant_win`,
  `raffle_started`, `raffle_extended`, `failed_participant`, and admin
  notifications `admin_sale`, `admin_draw`, `admin_winner`, `admin_failed`,
  `admin_started`, `admin_relisted`.
- **Per-email enable/disable toggles** on the Email settings tab. All are
  enabled by default; an explicit "off" wins. Configurable admin notification
  recipients (comma-separated).
- **Ticket PDF attachment** on the purchase-confirmation email
  (`WPRaffle_PDF::ticket()` + a `phpmailer_init` attachment helper).
- **"Raffle started" notification sweep** cron (`wpraffle_started_notify`).

### Compatibility Layer

- Conditional-load adapters (zero overhead when the target plugin is absent)
  for WPML/WCML, Polylang, CURCY Multi-Currency, Stripe, Square, WooPayments,
  Smart Coupons (as an instant-win prize type), Dokan/WC Vendors (vendor
  email recipients), Yoast/Rank Math (canonical/OG URLs), and page-cache
  plugins (W3TC/WPSC/Rocket/LiteSpeed flush on state change).
- New **Compatibility settings tab** reporting each adapter's live status.

### Operational Breadth + Gutenberg

- **CSV import/export** of tickets and instant-win rules/prize groups.
- **Admin order-item UI** — a "View Tickets" button on each raffle line item
  in the WooCommerce order screen showing allocated ticket numbers.
- **Q&A time limit + attempt limit** for skill questions (compliance-grade).
- **Ticket numbering modes** — random (default), sequential, or shuffled,
  plus per-raffle prefix/suffix and a configurable start number.
- **Gutenberg blocks** (no-build) for countdown, progress, entry button,
  instant wins, and raffle list — server-side rendered, sharing output with
  the Elementor widgets and shortcodes.

---

## Fixed

- **Order reversion (critical).** Cancelling, refunding, or failing an order
  that had raffle tickets allocated now reverts the allocation: deletes the
  tickets, decrements `sold_tickets`, deletes the purchase row, and reverses
  any instant-win prizes. Previously there was no reversion path — allocated
  tickets and won prizes persisted after the sale was undone. Idempotent via a
  `_raffle_tickets_reverted` order meta flag.

---

## Schema migrations

Three additive, backward-compatible migrations (following the existing
per-version-flag pattern). All default to legacy behaviour so existing
raffles are unaffected until an operator opts in.

- `migration_v12` — instant-win engine (`prize_type`, `prize_config`,
  `prize_group_id`, `image_id`, `won_at` on `raffle_instant_wins`; new
  `raffle_instant_win_groups` table).
- `migration_v13` — lifecycle (`min_tickets`, `min_unique_users`,
  `fail_reason`, `extended_from`, `auto_refund_on_fail`, `relist_config` on
  `raffles`; new `raffle_relists` table; `status_draw` index).
- `migration_v14` — Q&A limits + ticket numbering (`qa_time_limit`,
  `qa_max_attempts`, `ticket_numbering`, `ticket_prefix`, `ticket_suffix`,
  `ticket_start_number` on `raffles`; new `raffle_ticket_sequences` table).
- `migration_v6_payouts_credits_backstop` — flag-independent backstop that
  creates `raffle_payouts` and `raffle_credits` if the original v6 dbDelta run
  silently no-op'd on a given install (a known dbDelta footgun). Runs on every
  admin load, mirroring the v10 charity backstop.
- `migration_v15` — featured winners (new `raffle_featured_winners` table).

---

## Post-release additions & fixes

The following were added or fixed during pre-deploy testing on top of the
initial 1.3.0 cut:

- **Operator-facing admin form fields.** Every new raffle column now has an
  input: lifecycle (min-tickets, min-unique-users, auto-refund, auto-relist
  config), Q&A limits (time + attempts), ticket numbering (mode, prefix/suffix,
  start number), and the instant-win prize-type selector with type-specific
  config. Plus server-side validation, Extend/Relist buttons on Raffle Details,
  CSV import/export buttons, and status-aware list filtering.
- **My Coupons account tab** — a new My Account → My Raffles tab showing won
  coupons with click-to-copy codes, expiry, and Ready/Used badges.
- **Wallet/credit bridge.** Instant-win credit prizes now push to the live
  WooWallet/TerraWallet balance (not just the internal ledger), via the
  idempotent `credit_instant_win()` / `debit_instant_win()` adapter methods.
- **Manual wallet payout re-sync.** "Sync Wallet Payouts" (Raffle Details) and
  "Sync All Wallet Payouts" (Settings → Sync) — a true reconciliation that
  finds orphaned won-but-unpaid credit prizes and credits them. Idempotent.
- **Raffle not saving after upgrade** — migrations now run before the form
  handler on the same request, plus a column-guard so a missing optional column
  can't break the save.
- **Missing payouts/credits tables** — flag-independent backstop (above).
- **Silent instant-win failures** now log to the audit log
  (`instant_win_assign_failed`) instead of being swallowed.
- **Winners page instant-wins tab** now shows wins across all raffles, not just
  ended ones (instant wins are claimed live during a competition).
- **Featured winners.** Operators can flag a finished raffle's winner as a
  "featured winner" from the Raffle Details page, attach a winner photo (via
  the WP media uploader), and add an optional testimonial/quote. Stored in a
  new `raffle_featured_winners` table, queryable via
  `Raffle_Featured_Winners::get_featured()` for a future "Featured Winners"
  carousel. Auto-saves via AJAX.

---

## Upgrade notes

1.3.0 is an in-place upgrade from 1.2.2. The schema migrations run
automatically on the next admin page load (gated by per-version flags, each
column guarded with `SHOW COLUMNS` so they are idempotent; the payouts/credits
backstop is flag-independent). No data is lost; existing instant-win rows
backfill to `prize_type = 'physical'` so the legacy `prize_name` keeps working
unchanged. Standard upgrade path (GitHub auto-update or upload the new zip)
applies all changes.

After upgrading, review the new **Compatibility** settings tab to confirm
which integrations are active on your site, the **Email** settings tab to
tune the new per-email toggles and admin recipients, and the **Sync** tab's
"Sync All Wallet Payouts" if you have existing instant-win credit prizes that
predate the wallet bridge.

---

## What's next

1.3.0 achieves feature parity with the main rival plugins and ships the full
operator-facing UI for every feature. The next release will focus on
**performance and scale** (object-cache integration for inventory/odds, batch
purchase processing) and **deeper theming** (a child-theme-friendly template
override system).

---

Full changelog: [`CHANGELOG.md`](./CHANGELOG.md). Docs: <https://docs.wpraffle.dev>.
