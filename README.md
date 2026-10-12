# WPRaffle

Version 1.4.6

WPRaffle adds competitions, ticket sales, instant wins and prize draws to WooCommerce. Customers can manage their entries through My Account, while operators manage competitions, winners and reporting in WordPress.

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- WooCommerce 8.0 or later
- InnoDB database tables with transaction and locking support
- A 64-bit PHP runtime for reproducible draws

Elementor is optional. Use WPRaffle Theme 1.4.6 for the companion layouts and discovery controls.

## Features

- Scheduled competitions and single- or multiple-winner draws
- Random, sequential, shuffled and customer-selected ticket numbers
- Ticket reservations, quantity bundles and skill questions
- Instant-win prize groups, prize tiers and cash alternatives
- Free and postal entries, referrals and coupons
- Competition templates and recurring competition drafts
- Customer entry history, upcoming draws and email preferences
- Public draw receipts and optional reproducible winner selection
- Competition filters, REST API endpoints and sales insights
- Privacy export and erasure, country restrictions and responsible-gambling controls
- Elementor widgets, dynamic tags and Gutenberg blocks

## What's new in 1.4.6

- The account overview highlights the next awaiting draw and links directly to the customer's tickets.
- Account totals are grouped for easier reading, with a helpful message when no draws are pending.
- Competition cards show localized draw dates with timezone-aware values.
- Price labels and account styles have been updated for readability.

## Installation

1. Back up your website and database.
2. Install and activate WooCommerce.
3. Upload `wpraffle-1.4.6.zip` through Plugins → Add New → Upload Plugin.
4. Activate WPRaffle and configure your competition, checkout and email settings.
5. Install the companion theme separately if required.

When upgrading, replace the existing plugin and clear your website caches. Version 1.4.6 preserves existing competitions, entries and settings and introduces no additional database schema changes over 1.4.5. Upgrades from older versions apply the required migrations automatically.

## Creating a competition

Open Raffles in WordPress and create a competition. Set the prize, opening and closing dates, ticket capacity, price, entry limits and rules. Add any instant-win prizes or ticket bundles, then check the linked WooCommerce product before publishing.

Paid entries are allocated after payment confirmation. Failed allocations remain available for retry or manual reconciliation; check the order notes. Partial monetary refunds require a separate decision about the associated entries. Completed draw records are retained.

## Customer accounts

My Account → My Raffles includes Overview, Tickets and Notifications. The overview displays completed entries, competitions, wins and upcoming draws. Wallet information appears when a supported wallet integration is available.

Customers can choose whether to receive optional reminders, results, launch and extension announcements. Purchase, prize and security messages remain essential. Email delivery uses the site's existing mail configuration.

## Recurring competitions

Raffles → Recurring Raffles creates fresh drafts from a selected competition. Prize rules are copied, while entries and results are reset. Review the prize inventory, dates, prices and entry rules before publishing each draft.

Schedules use WordPress cron. Configure a reliable cron service for sites that require predictable processing times.

## Draw receipts

Add `[raffle_draw_receipt raffle_id="123"]` to display a competition's public commitment or completed draw receipt. Receipts contain eligible ticket numbers and results without customer names or email addresses.

For reproducible draws, enable a commitment in Raffles → Draw verification before allocating any entries. Save the published commitment before sales begin. After the draw, download the receipt and verify it with:

```sh
python3 tools/verify-draw.py receipt.json commitment.json
```

Without a commitment, draws use the standard random selection method. A receipt records the result; it does not establish the completeness of the entry pool. Keep copies of published commitments and receipts.

## Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[raffle id="123"]` | Competition and entry interface |
| `[raffle_list columns="3" per_page="12"]` | Competition grid |
| `[raffle_ended_list columns="3"]` | Ended competitions |
| `[raffle_lookup]` | Guest ticket lookup |
| `[raffle_live_draw raffle_id="123"]` | Live draw display |
| `[raffle_entry_list raffle_id="123"]` | Entry list |
| `[raffle_charities columns="3"]` | Charity directory |
| `[raffle_refer raffle_id="123"]` | Referral links |
| `[raffle_draw_receipt raffle_id="123"]` | Draw commitment and receipt |

The competition grid supports category, status, price range, closing window, availability, sorting and pagination. Page size is limited to 48 competitions.

## REST API

Endpoints use `/wp-json/wpraffle/v1`.

| Endpoint | Access |
| --- | --- |
| `GET /competitions` | Public competition listing |
| `GET /competitions/{id}` | Public competition details |
| `GET /competitions/{id}/commitment` | Public commitment |
| `GET /competitions/{id}/receipt` | Public draw receipt |
| `GET /me/entries` | Signed-in customer's own entries |
| `GET /templates` | Administrators |
| `POST /competitions/{id}/clone` | Administrators; creates a draft |

Use WordPress cookie authentication with a REST nonce, or Application Passwords over HTTPS. Public responses exclude private products and customer details.

## Reporting

Raffles → Insights shows completed-purchase totals, tickets, purchase counts, returning buyers and leading competitions. Sales totals are gross amounts in the store's currency, rather than net profit.

## Updating and customization

Use a staging site to check checkout, refunds, email delivery, cron and any payment, wallet or currency extensions before updating production. Keep template customizations in a child theme. Developers can use `wpraffle_card_fields` to adjust the supported card field order.

Uninstalling follows the plugin's delete-data setting. Back up competition records before enabling data removal.

## License

GPL-2.0-or-later. See the bundled license notices.

See [CHANGELOG.md](CHANGELOG.md) for version history and [RELEASE.md](RELEASE.md) for release notes.
