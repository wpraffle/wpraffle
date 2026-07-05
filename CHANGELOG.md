# Changelog

All notable changes to WPRaffle are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.2.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.2.0
[1.1.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.1.0
[1.0.0]: https://github.com/wpraffle/wpraffle/releases/tag/v1.0.0
