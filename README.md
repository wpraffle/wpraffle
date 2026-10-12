# WPRaffle 1.4.6

A WooCommerce competition plugin with ticket allocation, instant wins, single- and multi-winner draws, account entry history, and optional companion-theme integration. This release extends the supplied 1.4.0 source and preserves its existing configuration and competition records.

## Design update in 1.4.6

- Lead the private account overview with the next awaiting draw and a direct My tickets action; group lifetime totals and provide a useful empty state.
- Render localized, absolute draw dates with timezone-aware machine-readable values and readable translated price qualifiers.
- Add component-scoped account styles and meaningful design integration tests; preserve the 1.4.5 purchase, draw, verification, migration and privacy safeguards.

Existing saved settings/content are preserved. New card defaults are calmer; previously saved card orders can be adjusted in Product Cards. No new database schema is introduced in 1.4.6. See RELEASE.md and AUDIT-1.4.6.md for design coverage and limits.

## Requirements and compatibility

Declared minimums remain WordPress 6.5, PHP 8.1 and WooCommerce 8.0. Elementor is optional. Integration testing for this release used WordPress 7.1.3, WooCommerce 11.2.0, Elementor 4.3.4, PHP 8.4.25 and MySQL 9.5 on a disposable installation. Minimum-version combinations and every third-party adapter were not tested. Reproducible draws require a 64-bit PHP runtime.

Use transactional InnoDB tables and a database that supports row locks, transactions and advisory locks. An administrator's database account must allow the additive purchase-table migration. The companion theme's new discovery, dashboard and card features should be paired with plugin 1.4.6.

## Existing functionality

The supplied plugin already contains competition schedules and automatic draws, live draw animation, random/sequential/shuffled or selected ticket numbers, reservations, quantity bundles, skill questions, free/postal entry tools, instant-win prize groups/types, prize tiers, featured winners, referrals, coupons, charity allocation tools, country restrictions, responsible-gambling controls, email templates, analytics, audit logs, privacy tools, Elementor widgets/dynamic tags, Gutenberg blocks and compatibility adapters. These systems were retained; earlier suggestions describing them as absent were not treated as established gaps.

Their presence is not a legal or regulatory certification. Gateway, wallet, mail, geolocation, currency, multilingual and marketplace integrations depend on the operator's configuration and need site-specific testing.

## Retained capabilities from 1.4.5

- Public JSON draw records, optional seed commitment and reproducible winner selection, plus an independent offline verifier.
- Recurring creation of fresh competition drafts with reset results and copied prize rules.
- Customer overview and optional email preferences inside the existing My Raffles account endpoint.
- Public competition REST discovery and restricted account/admin endpoints.
- Shared bounded discovery filters and administrator aggregate sales/returning-buyer insights.
- Checked allocation transactions, order-line idempotency, free-entry serialization, locked draw thresholds and safer refund reconciliation.
- Retained security fixes for completed-purchase draw pools, privacy ownership, early ticket closure and Elementor dynamic-tag inheritance.
- Executable regression, WordPress integration, concurrency and browser suites.

See [CHANGELOG.md](CHANGELOG.md), [RELEASE.md](RELEASE.md), [AUDIT-1.4.6.md](AUDIT-1.4.6.md) and the tests directories for details.

## Install or upgrade

1. Back up files and the database, and test the upgrade on staging.
2. Install and activate WooCommerce. Upload `wpraffle-1.4.6.zip` using Plugins → Add New → Upload Plugin, replacing the previous WPRaffle folder when prompted.
3. Activate WPRaffle. Review Raffles settings and WooCommerce checkout, mail and payment configuration. Do not publish sample competitions automatically.
4. If using the companion theme, install `wpraffle-theme-1.4.6.zip` separately. Clear relevant application/page caches after updating both.
5. Verify that checkout can allocate tickets and that the schema migration succeeded before opening sales.

The additive migration introduces nullable `wc_order_item_id` and its unique index in the purchases table. Legacy rows remain nullable, and existing per-item purchase metadata is recovered when replaying older orders. A separate feature-schema flag applies the migration even when an earlier build already reported version 1.4.5. Existing tickets, IDs and prices are preserved.

Deactivation clears the new recurring cron hook. Uninstallation follows the existing explicit delete-data setting; destructive uninstall also removes new schedules, seed options and notification preference metadata. Retain backups of published commitments and result receipts.

## Typical competition workflow

Create a competition in Raffles, configure its dates, capacity, price, user limit, entry rules and optional prizes, then review its linked WooCommerce product before publishing. Existing scheduled lifecycle actions continue to use the shared draw implementation. New draws exclude purchases that are not completed. Drawing twice is rejected; failed winner/receipt writes roll back.

A paid line either receives its full requested quantity or remains unfulfilled for retry/manual reconciliation. `_raffle_tickets_generated` can be `yes`, `partial` or `no`; `_raffle_allocation_incomplete=yes` identifies failures. Review order notes. This release does not silently reduce paid quantities or automatically invoke a new gateway-refund policy.

Full cancellation/refund reversion is checked under order/competition locks. Completed draw history is retained for manual reconciliation. Partial monetary refunds retain allocations for an explicit line-level decision, rather than deleting all tickets in an order. Instant-win external effects and gateway reconciliation still need staging tests.

## Draw verification

Every successful new draw stores a privacy-preserving receipt containing a draw UUID, timestamp, competition ID, sorted eligible ticket numbers, ordered winners, selection algorithm and SHA-256 digest. Buyer names, emails and purchase IDs are excluded.

For opt-in reproducible selection:

1. Before any tickets are allocated, open Raffles → Draw verification and enable a commitment for the new competition ID.
2. Publish `[raffle_draw_receipt raffle_id="123"]` and save its public commitment JSON before opening entries. The same shortcode shows the receipt after the draw.
3. Run the draw normally. The receipt reveals the seed and eligible pool, allowing independent reproduction of the winning numbers.
4. Download the receipt and use `python3 tools/verify-draw.py receipt.json commitment.json` with Python 3. The second file is the commitment saved earlier.

The algorithm `sha256-rejection-v1` hashes the hex seed, sorted comma-separated pool digest and an incrementing counter; it uses unsigned 32-bit rejection sampling and removes selected numbers. Without opt-in, the existing `random_int` selection remains and the receipt checks result integrity only.

Commitments cannot be replaced. A missing or mismatched private seed blocks the draw. A committed competition cannot be destructively relisted; create a new competition. Protect the seed option and database backups until the draw. Save commitment records outside the operator's database to establish that they were published beforehand. Receipt hashes are not an independent immutable ledger, fairness certificate, proof of pool completeness, or regulatory approval; an operator controlling the database can alter records and participation.

## Recurring competitions

Raffles → Recurring competitions schedules 1–52 fresh drafts from an existing source every 1–365 days, with at most 50 schedules and 10 due drafts processed per run. Drafts receive fresh review-window dates, a new product, copied instant-win groups/rules and prize tiers, with winner and purchase data reset. Previous competitions are preserved.

Review prize inventory, images, pricing, dates, entry rules and eligibility before publishing each draft. The new scheduler deliberately creates drafts; it does not publish them, take payments, or reuse past entrants. Its hourly WP-Cron hook is `wpraffle_recurring_check`; traffic or a reliable system cron must trigger WordPress cron. Missed runs create one due draft per schedule, rather than a burst of historical copies. Removing a schedule retains already created drafts.

## Customer account and notifications

WooCommerce My Account → My Raffles includes Overview, Tickets and Notifications alongside existing tabs. Overview shows completed entries, competitions, wins and upcoming draws; wallet information is shown when the existing wallet API is available.

Registered users can choose draw reminders, non-winning results, launch announcements and extension announcements. Defaults preserve existing optional-email behaviour. Purchase/prize/security messages remain essential; existing operator email settings still apply. Guests retain existing behaviour. Preferences use account ownership and nonce protection and are included in WordPress privacy export/erasure tools. SMS delivery is not implemented or configured in this release; it requires a provider, phone-verification and consent design.

## Discovery and shortcodes

| Shortcode | Purpose |
|---|---|
| `[raffle id="123"]` | Existing competition/entry interface |
| `[raffle_list columns="3" per_page="12"]` | Bounded competition grid |
| `[raffle_ended_list columns="3"]` | Existing ended competition listing |
| `[raffle_lookup]` | Existing guest lookup with emailed secure link |
| `[raffle_live_draw raffle_id="123"]` | Existing animated live draw |
| `[raffle_entry_list raffle_id="123"]` | Existing configured ticket/entry list |
| `[raffle_charities columns="3"]` | Existing charity directory |
| `[raffle_refer raffle_id="123"]` | Existing referral display |
| `[raffle_draw_receipt raffle_id="123"]` | Commitment before the draw; receipt afterward |

`raffle_list` supports `category`, `status`, `min_price`, `max_price`, `sort`, `availability`, `closing`, `page` and `per_page`. Sort: newest, closing, price_asc, price_desc, remaining. Availability: available or sold_out. Closing: 24h or 7d. Page size is capped at 48. New theme discovery also provides category, upcoming/ended status and AJAX enhancement. Published, unprotected linked products are required for public results.

## REST API

Base path: `/wp-json/wpraffle/v1` (or WordPress's equivalent REST URL when pretty permalinks are disabled).

| Method/path | Access and purpose |
|---|---|
| GET `/competitions` | Public bounded discovery, same filter keys; `X-WP-Total` and `X-WP-TotalPages` |
| GET `/competitions/{id}` | Public whitelisted competition data |
| GET `/competitions/{id}/commitment` | Public seed digest/timestamp; never the private seed |
| GET `/competitions/{id}/receipt` | Public validated result JSON |
| GET `/me/entries?page=1` | Authenticated user's own account-email entries, 25 per page; no-store |
| GET `/templates` | Administrator (`manage_options`) only |
| POST `/competitions/{id}/clone` | Administrator only; creates a draft |

Use WordPress cookie authentication with a REST nonce, or configured Application Password authentication over HTTPS. No new API key store was added. Drafts/private/password-protected linked products and buyer details are excluded from public responses. There is no REST endpoint for purchasing tickets, running draws, or looking up another buyer's email. WordPress/plugins may further restrict REST access; configure ordinary edge caching/rate limits for traffic needs.

## Insights

Raffles → Insights aggregates completed purchases: gross amounts, tickets, purchase counts, unique/returning buyers, average purchase value and a bounded top-competition table. This complements existing analytics. It does not add cookies or advertising attribution and does not calculate net profit, deleted/refunded historical revenue, full conversion funnels or cohorts. Amounts are meaningful only in the source store's currency model.

## Developers, testing and security

New public filters include `wpraffle_card_fields` (whitelisted card-part order) and the theme's private preview settings filter. Existing hooks/adapters remain. Do not call internal draw/purchase helpers from an unauthenticated endpoint; endpoint callers must enforce capabilities/nonces.

Run the supplied unit suites from `tests/`. WordPress integration/concurrency tests are opt-in and require a disposable database explicitly marked in wp-config.php; see `tests/integration/README.md`. They create and delete fixtures. Theme browser tests cover filters, preview, mobile layout and keyboard focus. The offline verifier needs only the Python standard library.

Release checks include PHP/JavaScript syntax, unit/security suites, actual WordPress activation/integration, duplicate payment callbacks, injected write rollback, real simultaneous free-entry/draw/capacity requests and headless Chrome. PHPCS/PHPStan and the minimum-version matrix were unavailable. Outbound HTTP and email were suppressed; no real charges, SMS, emails or production-site writes were performed.

Retest your gateways/refunds, coupons/gifts/wallets, reservations, postal entries, translations/currencies, cron timing, mail delivery, existing-site upgrades and accessibility with real content. Bundled third-party components were preserved, not independently audited. Server crashes and remote instant-win effects need operational reconciliation; transaction checks do not provide distributed exactly-once guarantees.

## Privacy, updates and license

Account self-service exports use the authenticated account email, never customer-editable billing email as proof of ownership. Public draw JSON contains ticket numbers only; other existing public entry/winner displays depend on operator settings and consent. Review the existing activation tracking, privacy and update settings before enabling optional services.

Use controlled releases and staging validation for updates; upgrading from 1.4.5 follows the normal ZIP replacement workflow. GPL-2.0-or-later; see bundled license notices. No guarantee of competition legality or independent draw certification is implied.
