# Changelog

All notable changes to WPRaffle are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] — 2026-07-08

A feature-parity release that closes the competitive gaps identified against the
two main rival lottery/raffle plugins. Adds a full instant-win prize engine, a
raffle lifecycle (relist / extend / min-tickets fail with auto-refund), an
expanded email suite with per-email toggles and PDF ticket attachments, a
compatibility layer for ten third-party plugins and gateways, CSV import/export,
admin order-item ticket management, Q&A time/attempt limits, sequential /
shuffled ticket numbering, and a starter set of Gutenberg blocks — plus a
cancel/refund/failed order reversion path that previously did not exist.

### Added — Instant-Win Engine Overhaul (Phase 1)

- **Prize types.** Instant-win slots now support `coupon` (auto-generated
  single-use WooCommerce coupon), `product` (gift product added to the winning
  order at £0), `credit` (site credit), `physical`, and `custom` (extensible).
  Each prize is assigned automatically on payment and reversed on cancel/refund.
  (`includes/class-raffle-instant-win-prize-types.php`, `includes/class-raffle-instant-wins.php`)
- **Prize groups.** Prizes can be grouped with a shared image and display
  config; the Elementor instant-wins widget can render by group.
  (`wp_raffle_instant_win_groups` table, `class-widget-instant-wins.php`)
- **Stand-alone instant-win email** surfacing any generated coupon codes.
  (`Raffle_Email::send_instant_win`)

### Added — Raffle Lifecycle (Phase 2)

- **Min-tickets / min-unique-users thresholds.** A raffle whose draw runs
  before meeting its threshold now FAILS (status `failed`, `fail_reason`
  recorded) instead of drawing. (`Raffle_Lifecycle::evaluate_min_thresholds`)
- **Auto-refund on fail.** Opt-in `auto_refund_on_fail` triggers a WooCommerce
  refund for every participant of a failed raffle (idempotent).
- **Extend.** Push a raffle's draw date out and reopen it (`extend_raffle`).
- **Relist.** Reset a finished/failed raffle in place — snapshots history,
  clears entries, re-instantiates instant wins, reopens the WC product
  (reuses the same raffle id + permalink, unlike clone). Manual or scheduled
  via the `wpraffle_relist_check` cron with count + pause windows.
- New lifecycle actions: `wpraffle_raffle_failed`, `wpraffle_raffle_extended`,
  `wpraffle_raffle_relisted`. (`includes/class-raffle-lifecycle.php`)

### Added — Email Lifecycle Expansion (Phase 3)

- New transactional emails: `no_luck` (standalone loser email), `raffle_started`,
  `raffle_extended`, `failed_participant`, and admin notifications `admin_sale`,
  `admin_draw`, `admin_winner`, `admin_failed`, `admin_started`, `admin_relisted`.
- **Per-email enable/disable toggles** on the Email settings tab (all enabled by
  default; explicit off wins). Configurable admin notification recipients.
- **Ticket PDF attachment** on the purchase-confirmation email
  (`WPRaffle_PDF::ticket()` + `phpmailer_init` attachment helper).
- **"Raffle started" notification sweep** cron (`wpraffle_started_notify`).

### Added — Compatibility Layer (Phase 4)

- Conditional-load adapters (zero overhead when the target plugin is absent)
  for WPML/WCML, Polylang, CURCY Multi-Currency, Stripe, Square, WooPayments,
  Smart Coupons (as an instant-win prize type), Dokan/WC Vendors (vendor email
  recipients), Yoast/Rank Math (canonical/OG URLs), and page-cache plugins
  (W3TC/WPSC/Rocket/LiteSpeed flush on state change).
- New **Compatibility settings tab** reporting each adapter's live status.
  (`includes/compatibility/`)

### Added — Operational Breadth + Gutenberg (Phase 5)

- **CSV import/export** of tickets and instant-win rules/prize groups
  (`includes/class-raffle-import.php`).
- **Admin order-item UI** — a "View Tickets" button on each raffle line item
  in the WC order screen showing allocated ticket numbers.
  (`admin/includes/class-raffle-order-item-ui.php`)
- **Q&A time limit + attempt limit** for skill questions (compliance-grade).
  (`qa_time_limit`, `qa_max_attempts` columns)
- **Ticket numbering modes** — random (default), sequential, or shuffled, plus
  per-raffle prefix/suffix and a configurable start number.
  (`ticket_numbering`, `ticket_prefix`, `ticket_suffix`, `ticket_start_number`)
- **Gutenberg blocks** (no-build) for countdown, progress, entry button,
  instant wins, and raffle list — server-side rendered, shared with the
  Elementor widgets and shortcodes. (`includes/blocks/`)

### Fixed

- **Order reversion (Prereq A).** Cancelling, refunding, or failing an order
  that had raffle tickets allocated now reverts the allocation (deletes tickets,
  decrements `sold_tickets`, deletes the purchase row, reverses instant-win
  prizes). Previously allocated tickets and won prizes persisted after the sale
  was undone — there was no reversion path at all. Idempotent via a
  `_raffle_tickets_reverted` meta flag. (`class-raffle-woocommerce.php`)

### Schema migrations

- `migration_v12` — instant-win engine (`prize_type`, `prize_config`,
  `prize_group_id`, `image_id`, `won_at` on `raffle_instant_wins`; new
  `raffle_instant_win_groups` table).
- `migration_v13` — lifecycle (`min_tickets`, `min_unique_users`, `fail_reason`,
  `extended_from`, `auto_refund_on_fail`, `relist_config` on `raffles`; new
  `raffle_relists` table; `status_draw` index).
- `migration_v14` — Q&A limits + ticket numbering (`qa_time_limit`,
  `qa_max_attempts`, `ticket_numbering`, `ticket_prefix`, `ticket_suffix`,
  `ticket_start_number` on `raffles`; new `raffle_ticket_sequences` table).
- `migration_v15` — featured winners (new `raffle_featured_winners` table).

### Added (post-release)

- **Manual wallet/credit payout re-sync.** A "Sync Wallet Payouts" button on
  each Raffle Details page (scoped to that raffle) and a "Sync All Wallet
  Payouts" button under Settings → Sync tab (global) re-process any instant-win
  credit prizes that didn't reach the live wallet — the safety net for a missed
  payout where, for example, the wallet plugin was inactive at win time or a
  transient failure left a payout `pending`. Both show a live count of pending
  payouts and a credited/processed summary on completion. Idempotent and safe to
  run repeatedly; already-credited payouts are skipped. Backed by
  `Raffle_Wallet_Adapter::sync_pending_payouts()` / `::count_pending_payouts()`.
- **Wallet/credit payout reconciliation.** The sync is a TRUE reconciliation:
  it scans `raffle_instant_wins` for `status='won'` credit prizes that have no
  credited payout row (LEFT JOIN), resolves the winner from the purchase email,
  and credits them — catching wins that were marked won but never paid because
  the auto-credit path failed (the old sync only scanned existing `pending`
  payout rows, which found nothing for these orphans).
- **My Coupons account tab.** A new tab in My Account → My Raffles showing the
  user's won coupons (instant-win coupon prizes + consolation coupons) with
  click-to-copy codes, expiry dates, and Ready/Used status badges.
- **Operator-facing admin form fields.** Every 1.3.0 raffle column now has an
  input in the create/edit form: min-tickets / min-unique-users thresholds,
  auto-refund-on-fail, auto-relist config (duration/pause/count), Q&A time
  limit + attempt cap, ticket-numbering mode + prefix/suffix + start number,
  and the instant-win prize-type selector with type-specific config fields
  (coupon discount/amount/expiry, gift product id/qty, credit amount). Plus
  server-side validation for all new fields, and Extend/Relist buttons on the
  Raffle Details page with status-aware badges (failed/extended/active).
- **CSV import/export UI.** Export Tickets and Export Instant Wins row actions
  on the Raffle Details page, plus a bulk-import instant-wins CSV upload form.
- **Raffle status filter expansion.** The admin raffle list now filters by
  finished / failed / extended (plus an "Ended" group), and the status column
  badge reflects the new states.
- **Featured winners.** Operators can flag a finished raffle's winner as a
  "featured winner" from the Raffle Details page, attach a photo of the winner
  (via the WP media uploader), and add an optional testimonial/quote. Data is
  stored in a new `wp_raffle_featured_winners` table (one row per raffle,
  queryable by `is_featured`) via `Raffle_Featured_Winners::get_featured()`,
  ready for a future "Featured Winners" carousel. Auto-saves via AJAX.

### Fixed (post-release) — additional

- **Raffle not saving after upgrade.** Migrations run on `admin_init` but the
  form-submission handler is also on `admin_init` and callback ordering isn't
  guaranteed — so the first save after a 1.2.x → 1.3.0 upgrade could fire
  before the v12–v14 columns existed, causing a silent failure. The migration
  runner is now invoked explicitly at the top of the form handler so the schema
  is guaranteed current before any write; as defense in depth, the save also
  strips any data keys whose columns don't yet exist in the live table, and the
  failure screen surfaces the underlying `$wpdb->last_error`.
- **Missing payouts/credits tables.** The v6 migration that creates
  `raffle_payouts` and `raffle_credits` silently no-op'd on some installs
  (dbDelta formatting quirks), but `raffle_system_db_migrated_v6` was still set
  — so the tables stayed missing and every wallet payout / credits-ledger write
  failed silently. A flag-independent backstop (`migration_v6_payouts_credits_backstop`,
  mirroring the v10 charity backstop) now runs on every admin load and creates
  them if absent. This was the root cause of instant-win credit prizes never
  reaching the wallet.
- **Instant-win credit prizes weren't reaching the live wallet.** The `credit`
  instant-win prize type wrote only to WPRaffle's internal ledger, not to the
  spendable WooWallet / TerraWallet balance. The wallet adapter now exposes
  `credit_instant_win()` / `debit_instant_win()` (idempotent, ledger-backed),
  and the credit prize type pushes the amount into the live wallet on award and
  debits it back on cancel/refund. Guest winners are recorded as `pending` for
  manual settlement rather than silently dropped.
- **Silent instant-win assignment failures.** `assign_winning_prizes()` now
  logs every failure to the audit log (`instant_win_assign_failed`) with the
  full error code, message, buyer, and prize details, instead of swallowing
  errors. A missed payout is now always visible and recoverable.
- **Winners page instant-wins tab.** The Instant Wins tab on the public winners
  page (`[raffle_ended_list]`) was gated to ended raffles only, so instant wins
  from active competitions never appeared. Instant wins are now surfaced across
  ALL raffles (they're claimed live during a competition, not at the draw), and
  the page no longer early-returns when there are instant wins but no ended
  raffles yet.

## [1.2.2] — 2026-07-07

An admin-polish and Elementor-experience release. Closes the three remaining
admin UX gaps flagged in 1.2.1's release notes — server-side form validation
with inline errors, a draw-winner confirmation dialog, and structured
audit-log rendering — rebuilds the entire Elementor widget pack so widgets
are functional, previewable in the editor, and fully styleable, declares
WooCommerce HPOS compatibility, and resolves all actionable WordPress Plugin
Check findings.

### Added

- **Server-side form validation with inline error messages.** The raffle
  create/edit form now validates server-side before any data is written: title,
  prize/ticket values, total tickets (locked once sales exist), date ordering
  (draw after start), jackpot/charity percentages, multi-winner counts, bundle
  integrity, and consolation-coupon config. On failure every field is
  repopulated from the submission and per-field inline errors are shown with
  an ARIA-live summary banner — no more silent `wp_die()` on bad input. The
  Email and Advanced settings tabs gained the same validation, surfaced as
  error notice banners matching the existing "Settings saved." pattern.
  (`includes/class-raffle-admin-validation.php`, `admin/class-raffle-admin.php`,
  `admin/views/raffle-form.php`, `admin/views/settings.php`)
- **Draw-winner confirmation dialog.** Drawing a winner now opens a styled,
  accessible modal (the documented copy: *"This will draw a winner and the
  raffle will be marked as finished. This action cannot be undone."*) instead
  of a native browser `confirm()`. Draw errors and connection failures are
  surfaced through the same modal in alert mode, replacing native `alert()`.
  The `rsConfirm`/`rsAlert` helpers are reusable for future destructive
  actions. (`assets/js/admin.js`, `assets/css/admin.css`)
- **Structured audit-log rendering.** JSON log details now render as a labelled
  key/value definition list (Winning Ticket, Fairness Proof, Coupon Code,
  Amount, etc.) instead of a truncated raw-JSON blob. Each row expands to show
  the full details plus the complete SHA-256 fairness proof with a one-click
  copy button. A new **actor/user filter** narrows the log to a specific admin
  (closing a documented-but-unimplemented gap). (`includes/class-raffle-audit.php`,
  `admin/views/audit-log.php`, `assets/js/admin.js`)
- **Raffle ID control on every single-raffle Elementor widget.** Operators can
  now pick a specific raffle in the editor, so widgets render in the canvas
  even when there is no current product page — and the documented "Raffle ID
  control" that didn't exist is now real. (`includes/class-raffle-elementor.php`)
- **Editor previews (`content_template`)** on 15 widgets so the Elementor
  canvas shows placeholder content instead of blank blocks when a raffle isn't
  available. (`includes/elementor-widgets/class-widget-*.php`)
- **Modern styling controls** (Elementor `selectors`, `Group_Control_Typography`,
  `Group_Control_Border`, `Group_Control_Box_Shadow`, responsive dimensions)
  across all previously inline-styled widgets — Title, Price, Progress,
  Countdown, Image, Description, Stats Header, Quantity, Instant Wins, Trust,
  Enter Button, Tabs, Question. Countdown labels, show/hide seconds, expired
  state text, image aspect ratio, progress bar height/track colour, card
  backgrounds/borders/padding, and instant-win ticket numbers are now
  configurable. (`includes/elementor-widgets/class-widget-*.php`)
- **Shared purchase-modal partial** so the Elementor Modal widget and the
  master `raffle-display.php` template share one source of truth for the
  modal markup. (`public/views/widgets/purchase-modal.php`)

### Fixed

- **Enter Button widget did nothing** — it emitted `class="raffle-enter-comp-btn"`
  but `public.js` binds `#raffle-enter-comp-submit-btn`. The widget now renders
  the canonical button with the correct id, so the purchase handler binds.
  (`class-widget-enter-btn.php`)
- **Modal widget was dead code** — it emitted `#raffle-modal-overlay` /
  `#raffle-purchase-form-elementor`, ids that exist in no JS file. It now
  includes the shared purchase-modal partial (`#raffle-modal`), matching what
  `public.js` binds. (`class-widget-modal.php`)
- **Tabs widget showed nothing when "Online" was clicked** — only the postal
  pane existed. It now renders both the `#tab-online` and `#tab-postal` panes
  using the canonical `.raffle-tab-content` structure that `public.js` toggles.
  (`class-widget-tabs.php`)
- **Skill Question widget read the wrong columns** — it used `$raffle->question`
  / `$raffle->options`, but the schema and template use `question_text` /
  `question_answers`. It also emitted `name="raffle_answer"` instead of the
  canonical `name="raffle_skill_answer"` that `public.js` reads, so the answer
  was never captured. Both fixed. (`class-widget-question.php`)
- **Instant Wins `show_ticket_numbers` control was unused** — the toggle now
  actually renders the triggering ticket numbers. (`class-widget-instant-wins.php`)
- **Quantity widget lost bundle metadata** by decoding raw packages instead of
  using `wpraffle_normalise_packages()`. Now routes through the shared helper
  so bundle quantities match the canonical template. (`class-widget-quantity.php`)

### Changed

- **Elementor widget registration** switched from two parallel hard-coded
  arrays (files + classes) to a glob-based autoloader. Adding a widget is now
  just dropping in a `class-widget-*.php` file — no manifest edits.
  (`includes/class-raffle-elementor.php`)
- **Widget data access** now goes through the cached `wpraffle_get_raffle()`
  helper via a shared `get_raffle_for_widget()` method, replacing the uncached
  raw `$wpdb` query that ran on every widget render. (`class-raffle-elementor.php`)
- **Widget styling** migrated from inline `style=""` attributes to CSS classes
  targeted by Elementor `selectors`, so the editor's responsive preview and the
  `--wpr-*` theme-preset system apply correctly.

### Added — WooCommerce & compatibility

- **WooCommerce HPOS compatibility declared.** The plugin now explicitly
  declares compatibility with High-Performance Order Storage (`custom_order_tables`)
  and the orders cache (`orders_cache`) via the `before_woocommerce_init` hook.
  WPRaffle stores its own data in custom tables and never touches shop-order
  postmeta, so it is fully HPOS-safe. (`raffle-system.php`)
- **Version headers updated** to current releases: Tested up to WordPress 7.0,
  Requires at least 6.5 (needed for `wp_deregister_script_module`), Requires
  PHP 8.1, WC tested up to 10.9. (`readme.txt`, `raffle-system.php`, `README.md`)

### Fixed

- **Fatal error on template apply (memory exhaustion).** When applying a saved
  template, the `$raffle` object was built from a partial config array cast to
  `stdClass`, causing "Undefined property" warnings for fields not in the
  template (e.g. `wc_product_id`). The template object is now merged with the
  full column set so every property the form reads exists. (`class-raffle-admin.php`)
- **`wpr_icon()` infinite recursion.** A find-and-replace during the escaping
  fix accidentally made `wpr_icon()` call itself instead of `wpr_get_icon()`,
  exhausting memory on any page render. Restored. (`functions-icons.php`)
- **Template-apply "Undefined property" warnings** for `wc_product_id` on the
  category/tags rows. Added `isset()` guards. (`raffle-form.php`)

### Security & WordPress.org Plugin Check compliance

This release resolves all actionable Plugin Check findings. The remaining
flags are either the trademarked-term note ("raffle"), which requires manual
review, or the inherent custom-table/direct-query warnings that WordPress.org
accepts for custom-table plugins.

- **`readme.txt`** added in the WordPress.org canonical format with all
  required headers (`Stable Tag`, `License`, `Tested up to`, `Contributors`,
  `Requires at least`, `Requires PHP`, `WC tested up to`).
- **Chart.js vendored locally** (`assets/vendor/chart.umd.min.js`) instead of
  loaded from a CDN — offloaded resources are forbidden on .org.
- **Escaped output**: all `echo wpr_get_icon()` call sites converted to the
  void `wpr_icon()` form (echoes internally with escaping); all bare-variable
  prints wrapped in `esc_html`/`esc_attr`; `number_format_i18n`/`date_i18n`
  outputs wrapped in `esc_html`; binary PDF stream and generated CSS marked
  with justified `phpcs:ignore`.
- **Database queries**: `$wpdb->prepare()` added to `SHOW COLUMNS` migration
  checks; all custom-table queries inlined `{$wpdb->prefix}tablename` directly
  (the linter flags variable-assigned table names but trusts prefix
  interpolation); `$wpdb->prepare()` inlined as the direct argument to
  `get_results`/`get_var` in the audit log and raffle list.
- **Nonce verification** added to AJAX handlers (`raffle_lookup_send`,
  `raffle_get_viewers`) and WooCommerce hook callbacks (`save_raffle_product_data`,
  `validate_checkout_quantities`).
- **`wp_unslash()`** added before every numeric cast in the raffle form handler
  and several settings handlers.
- **`date()` → `gmdate()`** everywhere (timezone-safe).
- **Translators comments** added to every `__()`/`_n()` with placeholders;
  unordered placeholders in wallet-adapter fixed (`%1$s`, `%2$d`).
- **Email heredoc** converted to a standard concatenated string.
- **`$wpdb->prepare()`** used for all identifier-parameterized queries;
  `rename()` in the updater replaced with `WP_Filesystem::move()`.
- Removed stray `wpraffles-1.2.1.zip` and `.DS_Store` files from the build.

---

## [1.2.1] — 2026-07-07

A user-experience focused patch: fixes several bugs that harmed real users (notably
the "YOU WON!" detection failure), and adds five high-impact UX improvements across
the public site, account area, and admin.

### Fixed

- **"YOU WON!" badge never appeared in My Raffles (critical):** winner detection
  compared the winning ticket's **row id** against the user's ticket **numbers**
  (two different columns), so the celebration badge was effectively never shown to
  real winners. Now resolved via a tickets-table JOIN that maps the stored row id to
  the actual ticket number. (`public/views/my-raffles.php`)
- **Ticket numbers sorted as strings** in My Raffles, so "100" appeared before "20".
  Now cast to int before sorting. (`my-raffles.php`)
- **Invisible instant-win "Available" badge:** the badge set `color` and `background`
  to the same CSS variable, rendering the scarcity text invisible. Text now white.
  (`raffle-display.php`)
- **Charity totals stuck enlarged:** jQuery `.animate({transform})` is a no-op without
  a plugin, leaving numbers at `scale(1.12)`. Replaced with a CSS keyframe pulse.
  (`public.js`)
- **Manual number grid stranded users on network error:** the AJAX load had no
  `.fail()` handler, leaving "Loading numbers…" forever. Now shows a retry button.
  (`public.js`)
- **Silent number-selection loss:** when a 30s poll detected a selected number was
  taken, it was removed without notice. Now surfaces a non-blocking toast. (`public.js`)
- **Entry UI stayed live after the countdown hit zero:** buyers could click "Enter
  Competition" only to be rejected by AJAX. The page now freezes the entry button +
  hides the entry panel the instant the timer expires, and clears the interval.
  (`public.js`)
- **Shop cards weren't zero-padded** while the product page was — `9:5:3` vs `09:05:03`.
  Shop countdowns now match. (`shop-countdown.js`)
- **Hardcoded `$` in dashboard secondary KPIs** regardless of configured currency.
  Now uses the configured currency symbol via `formatMoney()`. (`dashboard.php`)
- **Clone redirect landed on the dashboard:** `action=edit` is served by the
  `raffle-list` page slug, not `raffle-system`. Clone now redirects correctly to the
  new raffle's edit screen. (`admin.js`)
- **Lookup form promised an email that was never sent:** the `[raffle_lookup]`
  shortcode said "a secure link will be sent" but no email was ever dispatched. Now
  fully implemented — single-use, 30-minute token emailed on request; clicking the
  link renders a read-only guest ticket view. Anti-enumeration response preserved.
  (`class-raffle-public.php`, `class-raffle-email.php`)

### Added

- **Odds-of-winning display:** the product page and account ticket list now show
  "1 in N" odds that update live as the buyer selects ticket quantity. (`raffle-display.php`,
  `tickets.php`, `public.js`)
- **Pending/processing purchases surfaced** in the account Tickets tab, so buyers
  whose payment is still clearing see their entry rather than assuming it was lost.
  (`tickets.php`)
- **"View Results" link** from finished My Raffles entries back to the competition
  page, plus draw date and a "view competition" link on the Wins tab. (`my-raffles.php`,
  `wins.php`)
- **SOLD OUT / ENDING SOON / ENDED badges** on shop cards, with expired/sold-out
  cards visually de-emphasised and made non-clickable. Cards are now keyboard-focusable.
  (`raffle-loop-card.php`, `shop-countdown.js`)
- **Friendly Bundle Builder** replacing the raw JSON packages field — operators get a
  repeatable qty/price/label/badge row UI (matching the Instant Wins builder) that
  syncs to a hidden field, with live validation. The lean bare-int JSON shape is
  preserved for simple raffles. (`raffle-form.php`, `admin.js`)
- **Admin raffle list** now has search (by title), status filter (Live/Draft/Ended),
  pagination, and a "Showing X of Y" indicator. (`raffle-list.php`)
- **Buyers CSV export** from the raffle details page, plus a client-side buyer
  search (name/email). (`raffle-details.php`, `class-raffle-admin.php`)
- **Winner name privacy setting:** a new "Publish full winner names" toggle
  (Settings → General, default ON for back-compat) reduces public winner names to
  initials (e.g. "J.S.") when disabled — matching the instant-win treatment.
  (`settings.php`, `class-raffle-admin.php`, `class-raffle-public.php`)
- **Dashboard AJAX error handling:** a dismissible error banner now appears when
  analytics requests fail, and the refresh spinner reflects real completion rather
  than a fixed timer. (`dashboard.js`)

### Changed — Accessibility

- **Modal focus management:** focus now moves into the purchase dialog on open,
  returns to the trigger button on close, and a Tab focus-trap keeps keyboard users
  inside the dialog. The close controls are now real `<button>` elements (were
  `<span role="button">`).
- **`role="alert"`** added to modal error messages so screen readers announce them.
- **Shop cards** gained keyboard (Enter/Space) activation and `role="link"`.

### Developer

- New static method: `Raffle_Public::winner_display_name( $full_name )` — resolves
  the privacy-aware display name for public winner rendering.
- New AJAX endpoint: `raffle_lookup_send` (guest ticket lookup email dispatch).
- New admin action: `export_buyers` (CSV export of a raffle's buyers + ticket numbers).
- New general setting: `publish_winner_full_name` (0/1).

---

## [1.2.0] — 2026-07-05

The biggest release in WPRaffle's history: a full security & compliance overhaul, five
new engagement features, a styling preset rebuild, the charity fundraising module, and a
fixed template system.

### Security & Compliance

#### Responsible Gambling — now actually enforced
- **RG-1 (critical):** the `raffle_pre_purchase_check` filter was registered but never
  applied anywhere — self-exclusion, operator locks, and spend limits were decorative.
  The filter is now enforced at **all six purchase gates**: `ajax_add_to_cart`,
  `ajax_create_order`, `validate_checkout_quantities`, `on_payment_complete`,
  `handle_free_entry`, and `handle_purchase`.
- Extended the RG class to **guest buyers**: `check_purchase_allowed()` now accepts a
  `$buyer_email` and enforces email-keyed exclusion/limits, so excluded guests can no
  longer bypass RG by checking out without an account.
- The 24h cool-off on spend-limit **increases** is now actually enforced via a new
  `pending_spend_limit_amount` column (the old `cool_off_change_until` was written but
  never read).
- New guest self-exclusion (`self_exclude_guest`) and a 5-year cap on exclusions.

#### Money integrity
- Wallet payout (`credit_winnings`) now wraps insert + wallet credit + status flip in a
  single transaction; a transient wallet failure rolls back instead of leaving a
  `failed` row that blocked retries.
- Credits `debit()` serialised with a `GET_LOCK` advisory lock to close the SUM→INSERT
  race that could push a balance negative.
- Instant-win admin insert wrapped in `START TRANSACTION … FOR UPDATE`; both
  `array_rand` calls replaced with CSPRNG `random_int()`.
- Cumulative per-user ticket limit TOCTOU closed with a `GET_LOCK` keyed on the buyer
  email (re-validated inside the transaction).
- Cart now carries `buyer_email` so the cumulative-limit check fires for `ajax_add_to_cart`
  items (previously silently no-op'd).
- Price fallback no longer trusts a client-tamperable session value; zeroes the price and
  removes the stale cart item instead.
- Wallet payout wired into the draw (`raffle_draw_completed`, priority 15) — was dead code.

#### Anti-farming
- Free-entry rate limiter re-keyed on the proxy-aware client IP (not raw `REMOTE_ADDR` +
  attacker-controlled email); both IP and email per-day transient caps now set.
- Referral `referred_has_purchase` gate now requires a **paid** purchase — free/referral
  entries no longer satisfy it, closing the free-entry → referral-bonus farming chain.
- Referral `bonus_entries` increment made atomic (`bonus_entries = bonus_entries + N`
  inside `FOR UPDATE`).

#### Other hardening
- **S1:** the four settings sync/recalc handlers now check `manage_options` explicitly
  (were nonce-only, relying on the menu wrapper).
- **S3:** `wpraffle_get_client_ip()` only honours `X-Forwarded-For` / `CF-Connecting-IP`
  when `REMOTE_ADDR` is in a configurable trusted-proxy allowlist.
- **S5:** account deletion is now two-step — emailed single-use token + 24h grace —
  instead of immediate anonymisation. Capability tightened to `is_user_logged_in()`.
- **Draw split:** `Raffle_Draw::handle_draw()` is now AJAX-only (capability + nonce); the
  transactional body is `Raffle_Draw::do_draw()`, callable by cron/internals.
- **XSS:** `esc_attr()` added to the skill-question radio `value` in `raffle-display.php`.
- **Updater:** `github_repo` validated against `^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$`.
- Clean deactivation hook clears all scheduled cron events.

### Bug Fixes

- **Charity totals showed £0 / didn't update live:** the charity tables
  (`wp_raffle_charities`, `wp_raffle_charity_allocations`) were never created on installs
  where the v6 migration flagged itself complete despite `dbDelta` silently failing. The
  new **v10 self-healing backstop** verifies table existence and creates them idempotently,
  a one-shot backfill recomputes totals from existing sold tickets, and the live estimate
  is computed directly from `wp_raffles` (table-existence-aware). A 60s polling endpoint
  updates the grid without a page reload. The hourly `raffle_charity_allocations_refresh`
  cron is now actually scheduled.
- **Templates didn't work:** four bugs fixed — (1) Save-as-Template AJAX field-name
  mismatch (`template_name`/`raffle_id` vs `name`/`config`) resolved by building the config
  server-side; (2) added the missing **Raffles → Templates** submenu + library page; (3)
  `render_form_page()` now pre-fills from `template_id`; (4) instant-wins from a template
  are restored on raffle creation. Clone was unaffected.
- **Stale charity ID ambiguity:** new `resolve_charity_ids()` helper consolidates the
  dual-identity problem (CPT post id vs DB row id).
- **Icons rendered large on charity pages:** icon CSS wasn't enqueued for
  `[raffle_charities]` / `[raffle_refer]` shortcodes; added to the conditional enqueue +
  defensive inline sizing in `wpr_get_icon()`.
- **Scarcity poller ReferenceError:** fixed `scarityBox` typo.
- Dead "Check for Updates" admin link now includes its nonce.
- Removed dead `$key = array_search(...)` line in the activation notice.

### Added — Five Engagement Features

All off-by-default, per-raffle toggles in a new "Engagement & Marketing" meta-box.

- **Ticket Bundles** — JSON bundle objects with custom pricing and badges; savings %
  displayed; price validated server-side against operator-configured bundles.
- **Number Picker Grid** — visual grid of ticket numbers with sold/reserved cells, Lucky
  Dip, and 30s live refresh. Integrates with the existing `selected_numbers` flow.
- **Consolation Coupons** — auto-issues single-use, email-restricted WooCommerce coupons
  to non-winning entrants after the draw (idempotent).
- **Virality / Share** — `?ref=` capture + 30-day cookie consumed at payment-complete;
  `[raffle_refer]` shortcode with referral link + share buttons (WhatsApp, Facebook, X,
  copy-link) using icon-pack brand icons.
- **Scarcity / Urgency** — live stock polling (15s) + "viewing now" social-proof badge
  with a coarse, privacy-safe count (60s heartbeat).

### Changed — Styling

- The five built-in presets (Diamonds / Golf / Car / Retro / Elite) expanded from 8
  accent-only tokens to ~20 each (neutrals, status, radius, shadow, button shape/weight,
  card padding). Switching preset now changes the whole design language, not just hue.
- New tokens: `--wpr-radius-sm/lg/pill`, `--wpr-btn-*`, `--wpr-card-*`,
  `--wpr-letter-spacing`, `--wpr-urgency-*`.
- `icons.css` colour helpers repointed from undefined `--rf-*` vars to the active
  `--wpr-*` theme tokens.
- Icon sprite extended: `chevron-down` (fixes 3 broken refs), brand icons (WhatsApp,
  Facebook, X, TikTok, copy-link), `users`, `flame`, `tag`, `eye`, `clock-filled`.
- Six emoji HTML entities in Elementor widgets replaced with `wpr_get_icon()` calls.

### Developer

- New filter: `raffle_pre_purchase_check` (`$allowed, $user_id, $amount, $buyer_email`).
- New action: `raffle_draw_completed` (`$raffle_id, $winner_ticket`).
- New cron action: `raffle_charity_allocations_refresh` (hourly).
- New filter: `wpraffle_trusted_proxies`.
- New AJAX endpoints: `raffle_viewers`, `raffle_charity_totals`, `raffle_confirm_deletion`.
- New shortcodes: `[raffle_charities]`, `[raffle_refer]`.
- New migrations: v8 (RG email + payouts), v9 (raffle feature flags), v10 (charity tables
  self-healing backstop + backfill).

### Upgrade Notes

- On the first admin page load after updating, v8/v9/v10 migrations run automatically and
  idempotently. No manual SQL or reactivation required.
- If you operate behind CloudFlare/nginx, set your proxy IPs in **Raffles → Settings →
  Advanced → Trusted Proxy IPs** so the rate limiter and geo-restriction honour the real
  client IP.
- Existing raffles are unaffected — all new feature flags default to OFF.

---

## [1.1.0] — June 2026

Comprehensive security audit pass across the entire codebase.

### Security
- **SEC-01..03:** Input sanitisation — `sanitize_text_field`, `absint`, `floatval`,
  `sanitize_email`, `esc_url_raw`, `sanitize_hex_color`, `wp_kses_post` on all superglobals;
  `wp_unslash()` before sanitisation; explicit `(array)` casts.
- **SEC-04..06:** Output escaping — `esc_html`/`esc_attr`/`esc_url`/`esc_textarea`
  throughout admin and public views; inline JS/CSS escaped.
- **SEC-07..09:** CSRF — `wp_nonce_field` + `wp_verify_nonce` on all forms; nonce per form
  to prevent cross-form replay.
- **SEC-10..12:** SQL injection — `$wpdb->prepare()` everywhere; dynamic `IN()` clauses
  via `array_fill` placeholders.
- **SEC-13..15:** Access control — `manage_options` on admin handlers; `ABSPATH` guards on
  every file.
- **SEC-16:** WordPress Privacy API integration (personal data export/erasure),
  anonymisation that retains financial rows for compliance.
- Geo-restriction fails closed; HTTPS for geo-lookup; IP validation rejects
  private/reserved ranges.
- Draw fairness: `random_int()` / `random_bytes()`; `SELECT … FOR UPDATE` + transactions;
  skill-question answer never sent to the client.
- H1 fix: entry-list PDF no longer exposes entrant PII to unauthenticated users.

---

## [1.0.0] — Initial release

- WooCommerce-based raffle & competition system.
- Configurable raffles (tickets, packages, prize tiers), instant wins, live draw,
  multi-winner, skill questions, free/postal entry, geo-restriction, referrals.
- Random ticket assignment via `random_int()` with `UNIQUE (raffle_id, ticket_number)`.
- Analytics dashboard, audit log, templates & clone, ticket reservations, duplicate
  detection.
- Elementor widget pack (18 widgets), shortcodes, GitHub auto-updates.
- Full GDPR export/erasure, rate limiting, responsible-gambling settings.

[1.2.2]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.2
[1.2.1]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.1
[1.2.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.0
[1.1.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.1.0
[1.0.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.0.0
