# WPRaffle v1.3.1 Release Notes

**Release date:** 5 August 2026
**Version:** 1.3.1
**Previous version:** 1.3.0

> A maintenance + Elementor release that pairs with **WPRaffle Theme v1.3.0**.
> Fixes a raffle-save bug path and the validation-failure re-render, hard-codes
> the update repository, removes ~340 lines of redundant code, and ships a
> substantial Elementor expansion: a dynamic tag, three new widgets, editor
> previews for the rest, widget styling/a11y polish, and a shortcode
> auto-resolve fix.

---

## Headlines

- **Raffle save path hardened.** Fixed an undefined-variable warning in the
  bundle normalisation on every save, and the validation-failure re-render
  (which could produce a chromeless page + missing inline field errors). Saving
  and editing raffles is now silent and correct.
- **Elementor dynamic tag.** A single `Raffle Field` dynamic tag exposes any
  raffle field (title, price, progress, draw date, instant-win count, …) so
  **any** native Elementor widget can bind to live raffle data — not just the
  bespoke raffle widgets.
- **Three new Elementor widgets:** Featured Raffle (spotlight), Lifecycle
  Status (per-state banner), and Winner Announcement.
- **Update repository hard-coded.** The GitHub repo the auto-updater polls is
  now a constant (`wpraffle/wpraffle`) and can no longer be changed from the
  settings UI — update traffic can never be redirected.
- **~340 lines of dead/duplicate code removed,** including a redundant DB
  column, legacy duplicate migrations, and a dead WooCommerce guard that was
  silently dropping the billing email from privacy exports.

---

## Fixed

- **Undefined `$ticket_price` in the raffle save path.** When bundles were
  enabled the bundle-normalisation call referenced a non-existent local (a PHP
  warning on every save, and a `0.0` fallback that could drop the per-ticket
  price from bundle maths). Now reads `$data['ticket_price']`.
- **Validation-failure re-render returned an unstyled page.** A prior change
  rendered the form then `exit`-ed during `admin_init`, so a failed save showed
  the form before the admin chrome was emitted. The handler now sets the
  repopulation globals and returns, letting the normal page callback render the
  form once inside full admin chrome. The inline-error helper was also reading
  an unglobalised `$errors`, so per-field messages never appeared — both fixed.

---

## Security / Hardening

- **Update repository hard-coded** (`Raffle_Updater::REPO`). The editable
  "Repository" field on Settings → Updates is replaced with a fixed label +
  link to https://github.com/wpraffle/wpraffle, and `save_update_settings()`
  ignores any posted `github_repo`.

---

## Added — Elementor

- **`Raffle Field` dynamic tag** (group `🎟️ Raffle System`). Fields: Title,
  Ticket Price, Prize Value, Total/Sold/Remaining tickets, Progress %, Draw &
  Start dates, Status, Instant-Win count. Resolves the current product's raffle
  or an explicit selection. Auto-discovered via the new
  `includes/elementor-dynamic-tags/` directory.
- **Three new widgets** (drop-in via the autoloader):
  - **Featured Raffle** — spotlight card, resolves `is_featured = 1` (falls
    back to most recent active), with image/title/price/progress/CTA + full
    style tab.
  - **Lifecycle Status** — per-state coloured banner (upcoming → active →
    drawing → ended → failed), per-state colour controls.
  - **Winner Announcement** — winning ticket + buyer name with empty-state
    fallback.
- **Editor previews** (`content_template`) for the three widgets that
  previously rendered blank in the canvas: All Competitions, Ended Raffles,
  Entry List.
- **`[raffle]` shortcode auto-resolves** — with no `id` it now uses the raffle
  linked to the current product (fixes the broken theme single-raffle template
  placeholder).

---

## Changed — Elementor polish

- **Quantity Selector** gained a full Style tab (pill colours/radius, slider
  track/thumb, heading typography); **Modal** gained overlay/modal style
  controls; **Tabs** gained postal-pane chrome controls.
- **Accessibility pass:** `aria-pressed` on quantity pills + labelled range,
  `<fieldset>/<legend>` + `role="alert"` on the Skill Question widget,
  `role="tab"` / `aria-selected` / `aria-controls` on the Tabs widget,
  `aria-disabled` on the sold-out Enter button, `role="timer"` / `aria-live`
  on the Countdown.

---

## Changed — Redundancy cleanup (no behaviour change)

- **Legacy admin migrations removed.** `Raffle_Admin::run_migrations()` no
  longer re-runs the v2–v5 ALTER/CREATE statements that duplicated the activator
  schema; it delegates to `Raffle_Setup::run_migrations()`. (~190 lines.)
- **Duplicate charity helper consolidated.** `backfill_one_charity()` now
  delegates to the canonical `sync_charity_to_db()`.
- **Redundant v6 table definitions dropped** (the four tables guaranteed by the
  always-on backstops).
- **Dead `bundle_config` column dropped** (added in v9 but never read/written).
  New `migration_v17_drop_dead_bundle_config()` removes it idempotently.
- **Dead guards removed:** the always-true `wpraffle_table_exists()` guard, and
  the unreachable `wc_get_customer_email()` branch (the privacy exporter now
  always includes the billing email).
- **Shared `consolation_config` helper** replaces the duplicated defaults array.

---

## Schema migrations

- **`migration_v17_drop_dead_bundle_config`** — drops the unused
  `raffles.bundle_config` column (SHOW COLUMNS guarded, idempotent). Runs
  automatically on the next `admin_init`.

---

## Upgrade notes

- **No breaking changes.** All schema changes are additive or dead-column
  removals; the v17 drop is guarded.
- **Child themes / custom CSS** referencing the old `.diamond-*` classes or
  `--diamond-*` variables should be updated to `.wpr-*` / `--wpr-*` (that rename
  landed in the theme, not the plugin — the plugin's classes are unchanged).
- The update repo is now fixed; if you previously pointed it at a fork, that
  setting is ignored — update traffic always goes to `wpraffle/wpraffle`.
- Elementor dynamic tags require Elementor ≥ 3.0; the tag no-ops gracefully if
  Elementor is absent.

---

## What's next

- Live-draw fairness proof export to PDF.
- Wallet payout batch runner for failed-raffle auto-refunds at scale.
- Additional Elementor dynamic tags (per-ticket number, winner list).
