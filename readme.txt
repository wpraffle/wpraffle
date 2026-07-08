=== WPRaffle ===
Contributors: wpraffle
Tags: raffle, competition, competition prize, woocommerce raffle, prize draw, sweepstakes, instant win
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 10.9
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fully-featured WooCommerce raffle & competition system. Run live competitions, manage tickets, instant wins, skill questions, postal entries, and lifecycle states.

== Description ==

WPRaffle turns WooCommerce into a complete raffle and prize-competition platform. Run live competitions, sell tickets through WooCommerce checkout, manage instant wins, skill questions, multi-winner draws, postal/free entry routes, geo-restriction, referrals, charity fundraising, and responsible-gambling controls.

= Key features =

* **Configurable raffles** — title, description, prize image, ticket count, price, and ticket packages.
* **WooCommerce integration** — full checkout with any payment gateway, cart, and order management.
* **Random ticket assignment** — cryptographically secure (`random_int()`) unique ticket numbers.
* **Instant wins** — prizes automatically awarded at specific ticket numbers.
* **Multi-winner draws** — multiple winners with configurable prize tiers.
* **Live draw** — animated draw page with spinning numbers and confetti.
* **Skill questions** — multiple-choice questions required before purchase (UK Gambling Act compliance).
* **Free / postal entry** — alternative entry route for compliance.
* **Geo-restriction** — restrict entry by country via IP geolocation.
* **Referral system** — unique referral codes with bonus entries.
* **Charity fundraising** — pledge a portion of proceeds to a charity.
* **Responsible-gambling controls** — spend limits, cooldowns, and self-exclusion.
* **Audit log** — a full, exportable trail of purchases, draws, and admin actions with SHA-256 fairness proofs.
* **Elementor widget pack** — 18 widgets for visually composing competition pages.
* **Shortcodes** — drop raffles, entry lists, and lookup forms onto any page.
* **GDPR** — full export and erasure handlers.

== Installation ==

1. Install and activate WooCommerce (required dependency).
2. Upload the `wpraffle` folder to `/wp-content/plugins/` or install via the Plugins → Add New uploader.
3. Activate WPRaffle through the Plugins screen.
4. Navigate to **Raffles → Settings** to configure currency, email, pages, and legal templates.
5. Create your first raffle under **Raffles → New Raffle**.
6. (Optional) Place the shortcode `[raffle id="X"]` on any page, or build a page with the Elementor widgets.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. WooCommerce handles checkout, payment gateways, and order management. WPRaffle layers the raffle mechanics on top.

= Is a skill question required? =

It's optional but recommended for UK compliance. Enable it per raffle in the UK Regulations section.

= Can entrants enter for free? =

Yes — the free / postal entry route is built in and can be enabled per raffle, satisfying "no purchase necessary" requirements in many jurisdictions.

== Screenshots ==

1. The raffle product page with image, price, odds, quantity selector, countdown, and enter button.
2. The admin dashboard with sales analytics and recent activity.
3. The raffle create/edit screen with the friendly Bundle Builder.
4. The audit log with structured, expandable entries and fairness proofs.
5. The Elementor widget pack.

== Changelog ==

= 1.3.0 =
* Added: Full instant-win prize engine — coupon, gift-product, site-credit (pushed to the live WooWallet/TerraWallet balance), physical, and custom prize types, with prize groups and automatic assignment + reversal on payment / refund.
* Added: Raffle lifecycle — min-tickets / min-unique-users thresholds with auto-fail and opt-in auto-refund, extend (push draw date), and relist (manual or scheduled, reusing the same raffle id + permalink).
* Added: Expanded email suite — standalone no-luck, instant-win, raffle-started/extended, failed-participant, plus admin notifications (sale/draw/winner/failed/started/relisted), with per-email enable toggles and configurable admin recipients.
* Added: Ticket PDF attachment on the purchase-confirmation email.
* Added: Compatibility layer — auto-activating adapters for WPML/WCML, Polylang, CURCY Multi-Currency, Stripe, Square, WooPayments, Smart Coupons, Dokan/WC Vendors, Yoast/Rank Math, and page-cache plugins; new Compatibility settings tab.
* Added: CSV import/export of tickets and instant-win rules/prize groups, with an admin import form and per-raffle export buttons.
* Added: Admin order-item "View Tickets" UI for per-order ticket management.
* Added: Skill-question time limit and attempt limit (compliance-grade).
* Added: Ticket numbering modes — random, sequential, or shuffled, with per-raffle prefix/suffix and start number.
* Added: Gutenberg blocks (countdown, progress, entry button, instant wins, raffle list) — no build step required.
* Added: Operator-facing admin form fields for every new raffle column (lifecycle, Q&A limits, numbering, instant-win prize-type selector) with server-side validation, plus Extend/Relist buttons and CSV import on the Raffle Details page.
* Added: "My Coupons" account tab in My Account → My Raffles showing won coupons with click-to-copy codes, expiry, and Ready/Used badges.
* Added: Manual wallet/credit payout re-sync — "Sync Wallet Payouts" on Raffle Details + "Sync All Wallet Payouts" under Settings → Sync, a true reconciliation that catches orphaned won-but-unpaid credit prizes. Idempotent and safe to run repeatedly.
* Added: Featured winners — flag a finished raffle's winner as featured, upload a winner photo, and add an optional testimonial. Stored in a queryable `raffle_featured_winners` table for a future winners carousel. Auto-saves via AJAX.
* Fixed: Cancelling, refunding, or failing an order now reverts the allocated tickets and instant-win prizes (previously there was no reversion path at all).
* Fixed: Raffle not saving after upgrade — migrations now run before the form handler on the same request, plus a column-guard so a missing optional column can never break the save.
* Fixed: Missing payouts/credits tables — a flag-independent backstop now creates `raffle_payouts` / `raffle_credits` if the v6 dbDelta run silently no-op'd.
* Fixed: Instant-win credit prizes now reach the live WooWallet/TerraWallet balance (previously only the internal ledger was updated).
* Fixed: Silent instant-win assignment failures now log to the audit log (`instant_win_assign_failed`) instead of being swallowed.
* Fixed: Winners page Instant Wins tab now shows wins across all raffles (claimed live during a competition), not just ended ones.

= 1.2.2 =
* Added: Server-side form validation with inline error messages on the raffle create/edit form and key settings tabs.
* Added: Draw-winner confirmation dialog (accessible modal replacing the native browser confirm).
* Added: Structured audit-log rendering — labelled details, expandable rows, copyable fairness proof, and an actor/user filter.
* Added: Raffle ID control on every single-raffle Elementor widget, plus editor previews and modern styling controls.
* Fixed: Four non-functional Elementor widgets (Enter Button, Modal, Tabs, Question) now work.
* Fixed: Instant Wins `show_ticket_numbers` control now renders ticket numbers.
* Fixed: Quantity widget now preserves bundle metadata via `wpraffle_normalise_packages()`.
* Changed: Elementor widget registration is now a glob-based autoloader; widget data access goes through the cached `wpraffle_get_raffle()` helper.

= 1.2.1 =
* Fixed: "YOU WON!" badge never appeared in My Raffles (row-id vs ticket-number mismatch).
* Fixed: Lookup form now sends the promised tokenised secure link.
* Added: Odds-of-winning display, pending-purchase visibility, SOLD OUT/ENDING SOON badges, friendly Bundle Builder, admin list search/filter/pagination, buyers CSV export.

= 1.2.0 =
* Added: Charity fundraising, credits/wallet integration, responsible-gambling controls, account area, and a styling preset system.

= 1.1.0 =
* Added: Live draw, multi-winner draws, templates & clone, ticket reservations, and the audit log.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.2 =
Admin polish (form validation, draw confirmation dialog, structured audit log) and a full rebuild of the Elementor widget pack. Drop-in upgrade — no database migration required.

= 1.2.1 =
Fixes the "YOU WON!" detection bug and adds odds-of-winning, a friendly Bundle Builder, and lookup email delivery. Drop-in upgrade.
