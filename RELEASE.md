# WPRaffle v1.2.1 Release Notes

**Release date:** 7 July 2026
**Version:** 1.2.1
**Previous version:** 1.2.0

> A user-experience focused patch: fixes several bugs that harmed real users — notably
> the "YOU WON!" detection failure — and adds five high-impact UX improvements across
> the public site, account area, and admin.

---

## Headlines

- **The "YOU WON!" badge now actually works** — a critical bug meant the celebration
  badge in My Raffles was virtually never shown to real winners (winner detection
  compared a ticket row ID against ticket numbers). Fixed and verified.
- **Odds of winning, displayed live** — buyers now see "1 in N" odds on the product page
  and account, updating as they change their ticket quantity. A proven conversion driver.
- **A friendly Bundle Builder** replaces the raw JSON packages field — no more
  hand-typing `[{"qty":5,"price":25}]` to configure ticket bundles.
- **The lookup form now keeps its promise** — `[raffle_lookup]` said it would email a
  secure link but never sent one. It now genuinely delivers a single-use, 30-minute
  tokenised link with a guest ticket view.
- **SOLD OUT / ENDING SOON badges, frozen closed raffles, and a more honest dashboard** —
  a sweep of fixes to the real-time UI that buyers and operators actually see.

---

## Fixed

### Critical

- **"YOU WON!" badge never appeared in My Raffles.** Winner detection compared the
  winning ticket's **row id** (`winner_ticket_id` stores `raffle_tickets.id`) against
  the user's ticket **numbers** — two different columns — so the `in_array()` check
  effectively never matched. Now resolved via a tickets-table JOIN that maps the stored
  row id to the actual ticket number. (`public/views/my-raffles.php`)
- **Ticket numbers sorted as strings** in My Raffles, so "100" appeared before "20".
  `get_col()` returns strings; now cast to int before sorting.
- **Lookup form promised an email that was never sent.** The `[raffle_lookup]` shortcode
  displayed "a secure link will be sent" but no email was ever dispatched. Now fully
  implemented — a single-use, 30-minute token is emailed on request, and clicking the
  link renders a read-only guest ticket view. Anti-enumeration response preserved (the
  on-page confirmation is identical whether or not the email has tickets).

### Real-time UI bugs

- **Invisible instant-win "Available" badge** — the badge set `color` and `background`
  to the same CSS variable (`var(--wpr-draw-color)`), rendering the scarcity text
  invisible. Text is now white.
- **Charity totals stuck enlarged** — jQuery's `.animate({transform})` is a no-op
  without a plugin, leaving the number at `scale(1.12)` forever. Replaced with a CSS
  keyframe pulse.
- **Manual number grid stranded users on network error** — the AJAX load had no
  `.fail()` handler, so any failure left "Loading numbers…" forever with no escape. Now
  shows a retry button.
- **Silent number-selection loss** — when a 30s poll detected that a selected number had
  been taken, it was removed without notice. Now surfaces a non-blocking toast so the
  buyer knows to pick another.

### Behaviour

- **Entry UI stayed live after the countdown hit zero.** Buyers could click "Enter
  Competition" only to be rejected server-side. The page now freezes the entry button,
  hides the entry panel, and clears the interval the instant the timer expires.
- **Shop cards weren't zero-padded** while the product page was — a raffle showed
  `9:5:3` on the shop listing but `09:05:03` on the product page. Shop countdowns now
  match.
- **Hardcoded `$` in dashboard secondary KPIs** regardless of the configured currency.
  Now uses the configured currency symbol via `formatMoney()`.
- **Clone redirect landed on the dashboard.** `action=edit` is served by the
  `raffle-list` page slug, not `raffle-system`. Clone now redirects correctly to the new
  raffle's edit screen.

---

## Added

### Buyer experience

- **Odds-of-winning display** — a live "Your odds: 1 in N" line on the product page and
  in the account ticket list, updating as the buyer changes ticket quantity (slider,
  manual input, or bundle pill).
- **Pending/processing purchases surfaced** — buyers whose payment is still pending or
  on-hold now see a "Processing" section in their account Tickets tab, instead of
  assuming their entry was lost (a top support-ticket driver).
- **"View Results" link** from finished My Raffles entries back to the competition page,
  plus draw date and a "view competition" link on the Wins tab.
- **SOLD OUT / ENDING SOON / ENDED badges** on shop cards. Expired and sold-out cards
  are visually de-emphasised (opacity + greyscale) and made non-clickable; the CTA flips
  to "VIEW RESULTS". Cards are now keyboard-focusable with Enter/Space activation.
- **Guest ticket lookup** — the new secure-link flow (see Fixed above) renders a
  read-only ticket view for guest buyers with no account.

### Admin

- **Friendly Bundle Builder** replacing the raw JSON packages field — a repeatable
  qty/price/label/badge row UI (matching the Instant Wins builder) that syncs into a
  hidden field with live validation. The lean bare-int JSON shape is still emitted for
  simple raffles, so existing server-side handling is unchanged.
- **Admin raffle list** now has search (by title), status filter (Live / Draft / Ended),
  pagination, and a "Showing X of Y" indicator.
- **Buyers CSV export** from the raffle details page, plus a client-side buyer search
  (name/email).
- **Winner name privacy setting** — a new "Publish full winner names" toggle (Settings →
  General, default ON for back-compat) reduces public winner names to initials (e.g.
  "J.S.") when disabled, matching how instant-win winners are already treated. The full
  name is always used in the winner email and audit log regardless.
- **Dashboard AJAX error handling** — a dismissible error banner now appears when
  analytics requests fail, and the refresh spinner reflects real completion rather than
  a fixed 1200ms timer.

### Accessibility

- **Modal focus management** — focus now moves into the purchase dialog on open, returns
  to the trigger button on close, and a Tab focus-trap keeps keyboard users inside the
  dialog.
- **Close controls are now real `<button>` elements** (were `<span role="button">` with
  no keyboard handler).
- **`role="alert"`** added to modal error messages so screen readers announce them.

---

## Developer

- New static method: `Raffle_Public::winner_display_name( $full_name )` — resolves the
  privacy-aware display name for public winner rendering.
- New AJAX endpoint: `raffle_lookup_send` (guest ticket-lookup email dispatch;
  anti-enumeration, rate-limited 1/email/60s).
- New admin action: `export_buyers` (CSV export of a raffle's buyers + ticket numbers;
  capability-checked + nonced).
- New general setting: `publish_winner_full_name` (0/1).

No new database tables, columns, or migrations — 1.2.1 is a pure patch release.

---

## Upgrade Instructions

1. **Replace the `wpraffle` plugin folder** (or accept the GitHub auto-update once the
   `v1.2.1` tag is published). No schema migration runs — there's nothing to migrate.
2. **No settings changes required** — all new behaviour is backwards-compatible:
   - The winner-privacy setting defaults to ON, preserving current public-name display.
   - The Bundle Builder emits the same JSON shapes the server already accepted.
3. **Optional:** if you'd prefer winner initials on public results pages, untick
   **Publish full winner names** under **Raffles → Settings → General**.

---

## What's next

1.2.1 closes the highest-impact UX gaps surfaced in the 1.2.0 review. The next minor
release will focus on the remaining admin polish items (server-side form validation with
inline error messages, a draw-winner confirmation dialog, and structured audit-log
rendering).

---

Full changelog: [`CHANGELOG.md`](./CHANGELOG.md). Docs: <https://docs.wpraffle.dev>.
