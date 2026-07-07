# Changelog

All notable changes to WPRaffle are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.2.1]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.1
[1.2.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.0
[1.1.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.1.0
[1.0.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.0.0
