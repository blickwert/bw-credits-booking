# BW Credits + Bookings

WordPress plugin for yoga studios: WooCommerce credit balances + a course booking system based on ACF-managed `course_slot` posts.

## What the plugin does

Customers buy credit packages (e.g. a 10-pack) through WooCommerce. Each credit is its own DB row. Booking a course spot consumes credits FIFO (oldest first). Cancellations refund the credit.

## Requirements

| Dependency | Version |
|---|---|
| WordPress | ≥ 6.0 |
| PHP | ≥ 7.4 |
| WooCommerce | ≥ 7.0 |
| Advanced Custom Fields (ACF) | any |
| Paid Memberships Pro *(optional)* | any |

## Installation

1. Download the ZIP (GitHub → Releases → Assets → `Source code (zip)`)
2. WordPress Admin → Plugins → Add New → Upload ZIP
3. Activate the plugin — DB tables are created automatically

**Auto-update:** The plugin registers itself with WordPress as an update source. New releases appear under *Plugins → Updates* (cache: 12 h).

## Database

The plugin creates two tables:

### `wp_bwallet_credits`

Each row = 1 credit.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT | Primary key |
| `user_id` | BIGINT | WP user |
| `order_id` | BIGINT | WooCommerce order |
| `order_item_id` | BIGINT | Order line item |
| `product_id` | BIGINT | WC product |
| `expires_at` | DATETIME | Expiry date (NULL = unlimited) |
| `status` | VARCHAR(16) | `available` / `used` / `expired` |
| `source` | VARCHAR(20) | `purchase` / `membership` |
| `booking_id` | BIGINT | Linked booking (when `used`) |
| `created_at` | DATETIME | Creation time |

### `wp_bwallet_bookings`

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT | Primary key |
| `user_id` | BIGINT | WP user |
| `course_slot_id` | BIGINT | Post ID of the `course_slot` |
| `status` | VARCHAR(16) | `booked` / `cancelled` |
| `created_at` | DATETIME | Booking time |
| `cancelled_at` | DATETIME | Cancellation time |

## WooCommerce product configuration

Each credit package is a simple WC product with three extra fields (*General* tab):

| Field | Meta key | Description |
|---|---|---|
| Credit Amount | `_bw_credit_amount` | Number of credits granted |
| Valid Days | `_bw_credit_valid_days` | Validity from purchase (0 / empty = unlimited) |
| Credit Source | `_bw_credit_source` | `purchase` (default) or `membership` |

Credits are granted automatically when the order status becomes `completed`.

## ACF dependency

The plugin only needs ACF for **one** field on the chosen post type
(*BW Credits → Settings → Course session post type*):

| Field name | Type | Description |
|---|---|---|
| `start_datetime` | Date Time Picker | Session start, return format `Y-m-d H:i:s` |

`capacity` and `booked_count` have been the plugin's own meta boxes since
v0.8.0 (`includes/metaboxes.php`) — **no longer** ACF fields. If they're
still in an ACF field group, remove them there, otherwise they'll appear
twice.

Taxonomies (`course_type`, `course_level`, `course_lang`) continue to be
maintained externally, independent of ACF.

## Shortcodes

Scheme: `bw_credits_{group}_{name}` — **course** refers to a session, **user** to the logged-in customer, **view** is a composite view.

On a single session page, `course_id` can be omitted — the current post is used instead. This lets you drop the shortcodes into an Elementor template once.

### Course

#### `[bw_credits_course_list]`
Session list, grouped by day, with free spots and a book button.

```
[bw_credits_course_list]
[bw_credits_course_list days="14" show_filter="true"]
[bw_credits_course_list type="hatha-yoga" limit="5" availability="false"]
```

| Attribute | Default | Meaning |
|---|---|---|
| `limit` | 20 | maximum number of sessions |
| `days` | 0 | only the next N days (0 = no limit) |
| `type` / `level` / `lang` | – | term slug to pre-filter by |
| `show_filter` | false | select fields for course type, level, and language |
| `show_action` | true | book button per session |
| `availability` | true | free spots per session |
| `group_by_day` | true | a heading per day |
| `empty` | *(text)* | message when there are no sessions |

With `show_filter="true"`, the form writes `bw_type`, `bw_level`, and `bw_lang` into the URL; attributes you set explicitly override these.

#### `[bw_credits_course_booking]`
A button that books or cancels depending on state and switches after the click without a reload. Shows a note instead when: not logged in, session over, fully booked, no credits, cancellation deadline passed.

`course_id`, `label_book`, `label_cancel`, `class`

#### `[bw_credits_course_availability]`
Free spots — **visible even without login**. Updates after booking and cancelling.

```
[bw_credits_course_availability format="Only {free} spots left" full="Sorry, fully booked"]
```

`course_id`, `format`, `full`

#### `[bw_credits_course_access]`
Meeting link and access details. **Visible only to logged-in users with an active booking for this session** — without a booking, nothing is output, not even a hint that the link exists.

`course_id`, `title`

### Customer

#### `[bw_credits_user_balance]`
Available credit balance. `format="inline"` (default) outputs just the number, `format="block"` a labeled paragraph. Updated via JavaScript.

**Only appears when a credit package is in the cart** — a targeted note during checkout instead of an ever-present counter, e.g. on the cart or checkout page. Without a matching product in the cart (or without login), the shortcode outputs nothing, regardless of `mode`.

With **`mode="empty_only"`**, it becomes a prompt to top up: visible only if the customer is logged in, **has had a credit balance before**, and now has none. Anyone who never had credits sees nothing — they're meant to enter through the shop.

"Had a credit balance before" counts every origin, including manual grants from welcome promotions (newsletter signup, promotional periods).

```
[bw_credits_user_balance mode="empty_only"]
[bw_credits_user_balance mode="empty_only" empty_text="No credits left." empty_link="Buy a pack"]
```

The note appears immediately as soon as the customer uses their last credit — without a reload.

| Attribute | Default | Meaning |
|---|---|---|
| `mode` | always | `always` or `empty_only` |
| `format` | inline | `inline` or `block` (only with `mode="always"`) |
| `label` | Available credits: | Label before the number |
| `empty_text` | Your credit balance is used up. | Text when the balance is empty |
| `empty_link` | Top up now | Label of the shop link |
| `shop_url` | – | overrides the *Shop page* setting |
| `logged_out` | – | Text for visitors who aren't logged in |

The link's target comes from *BW Credits → Settings → Shop page*; if nothing is set there, the WooCommerce shop page is used. If none is found, the note appears without a link.

#### `[bw_credits_user_credits]`
Credit balance in detail: count, origin (purchase / membership / manual credit), and expiry date, grouped rather than shown individually. Anything expiring within the next 30 days is highlighted.

`show_expired`, `empty`

#### `[bw_credits_user_bookings]`
The customer's bookings with status, course type/level/language, a cancel button, and — if available — the access details.

`limit`, `show_access`

### View

#### `[bw_credits_view_overview]`
Credit balance, a short list of upcoming sessions (with availability and a book/cancel button, as in the session list), and entry-point links. Appears automatically in the WooCommerce account dashboard.

`show_balance`, `show_next`, `next_limit` (default 5), `show_links`, `list_url`

## Customizing templates

Every shortcode has exactly one template file, named after the shortcode itself — overridable in the theme, following the same pattern as WooCommerce:

```
wp-content/plugins/bw-credits-booking/templates/
  course_list/course_list.php               [bw_credits_course_list]
  course_availability/course_availability.php [bw_credits_course_availability]
  course_access/course_access.php           [bw_credits_course_access]
  course_booking/course_booking.php         [bw_credits_course_booking]
  user_balance/user_balance.php             [bw_credits_user_balance]
  user_credits/user_credits.php             [bw_credits_user_credits]
  user_bookings/user_bookings.php           [bw_credits_user_bookings]
  view_overview/view_overview.php           [bw_credits_view_overview]
```

**Overriding:** On *BW Credits → Templates*, click **"Copy to theme"** next to the row you want — this creates the file automatically under `wp-content/themes/<your-theme>/bw-credits-booking/<path>.php`. Or copy it by hand. WordPress finds the copy automatically — first in the child theme, then the parent theme, otherwise the plugin's own version.

The templates contain **no wording** — every text comes via `bw_text()` from the [text catalogue](#customizing-texts). A theme copy only sets the layout, never the wording.

**Keeping track of status:** *BW Credits → Templates* lists all eight templates, shows which are overridden in the theme, and flags a copy as outdated as soon as its `@version` header falls behind the plugin's version.

### For small tweaks without a theme copy

| Hook | Purpose |
|---|---|
| `bw_before_course_list` / `bw_after_course_list` *(action)* | around the session list |
| `bw_before_slot_item` / `bw_after_slot_item` *(action, `$slot`)* | before/after each session row |
| `bw_course_list_query_args` *(filter)* | adjust the session list's `WP_Query` arguments — e.g. change sorting or exclude sessions |
| `bw_before_bookings_item` / `bw_after_bookings_item` *(action, `$booking_id`, `$slot_id`)* | before/after each booking-list row |
| `bw_before_credits_item` / `bw_after_credits_item` *(action, `$group`)* | before/after each credit-details row |

```php
// Hide already-booked sessions from the list
add_filter('bw_course_list_query_args', function ($args, $atts, $selected) {
    // your own logic
    return $args;
}, 10, 3);
```

## Customizing texts

All 57 texts customers see in the frontend live in a central catalogue and can be changed under *BW Credits → Texts* — no code required. This includes the error messages shown when booking and cancelling.

An empty field uses the default text. Placeholders in curly braces are preserved, e.g. `{free}` in "{free} spots available" or `{date}` in "valid until {date}".

### Three layers

| Layer | How |
|---|---|
| A single placement | Shortcode attribute, e.g. `label_book="Reserve a spot"` |
| The whole site | *BW Credits → Texts* |
| Another language | WPML String Translation, or a `.po` file |

Resolution runs top to bottom: a set shortcode attribute wins, otherwise the admin text applies, otherwise the translated default.

### Translation

The plugin uses the text domain `bw-credits-booking`. The template lives under `languages/bw-credits-booking.pot` and is generated from the catalogue:

```
php tools/make-pot.php
```

With WPML active, all texts also appear under *String Translation* in the context **BW Credits Texte**.

## Booking logic

- **Race conditions**: booking runs in a DB transaction with `SELECT … FOR UPDATE` on the capacity check
- **Past-session lock**: bookings for sessions that have already started/passed are rejected
- **FIFO credit consumption**: credits with an earlier `expires_at` are consumed first
- **Cancellation window**: configurable in hours before the session start (`bw_booking_cancel_cutoff_hours`, default: 2)
- **Credit refund**: on cancellation, the `used` credit is automatically reset to `available`

## REST API

All endpoints under `/wp-json/bw-credits/v1/`:

| Method | Path | Description |
|---|---|---|
| POST | `/book` | Book a slot (`slot_id`) |
| POST | `/cancel` | Cancel a booking (`booking_id`) |
| GET | `/balance` | Credit balance of the logged-in user |

All endpoints require a `nonce` header (`X-WP-Nonce`).

## PMPro Membership Integration (optional)

If Paid Memberships Pro is active:

- Membership products can be set to `Credit Source: Membership`
- Credits with `source = membership` expire automatically when the membership is cancelled (`pmpro_after_change_membership_level` → level 0)
- `purchase` credits (one-time purchases, packs) are unaffected
- Rollover: on monthly renewal, new credits are granted, existing ones are kept

**Without PMPro**: no error — the membership code is fully wrapped in `function_exists()`.

## Admin

Menu **BW Credits** (capability `manage_options`):

| Page | Content |
|---|---|
| Settings | Session post type, default capacity, cancellation deadline, reminder lead time, shop page |
| Sessions | All sessions with occupancy and utilization, upcoming/past filter |
| Bookings | Filtered list, cancellation, form for walk-in bookings |
| Credits | User search, view credit balance, grant and revoke manually |
| Emails | Subject and body of all notifications |

**On the course session** (meta boxes):
- **Capacity** — leave empty to use the default; occupancy shown read-only next to it, warning on overbooking
- **Online Access** — meeting link and access details, with a button to resend
- **Participants** — list with cancel, "No-show", and CSV export of the attendance list

**List view**: columns Start, Level, Type, Language — all sortable.

**Auto-title**: on save, the title is generated as `"Monday, June 2, 10:00 – Hatha Yoga"`. Weekday and month come from the WordPress locale. The format can be changed via the `bw_slot_title_format` filter:

```php
add_filter('bw_slot_title_format', fn() => 'D, j.n. H:i');
```

**Editor**: course sessions are edited in the Classic Editor, so the meta boxes stay in their usual place.

## Emails

Five types, each with its own toggle, subject, and body under *BW Credits → Emails*:

| Type | Trigger |
|---|---|
| Booking confirmation | after a successful booking |
| Cancellation confirmation | after a cancellation |
| Reminder | X hours before the session starts (hourly cron) |
| Access details | see below |
| Admin copy | every new booking (off by default) |

Placeholders: `{kundenname}` `{kurs_titel}` `{datum}` `{uhrzeit}` `{credits_verbleibend}` `{meeting_link}` `{zugangsdaten}` `{kurs_link}` `{konto_link}`

`{kurs_link}` and `{konto_link}` link to the session and the WooCommerce My Account page respectively, where customers can view and cancel their own bookings. Both are automatically present in the booking confirmation, cancellation, and reminder emails; a URL-shaped placeholder value always becomes clickable in the email automatically, regardless of which one it is.

If you've already customized one of these three texts under *BW Credits → Emails*, you won't automatically see the new placeholders in your own text — only the default text was extended. To add them, just insert `{kurs_link}`/`{konto_link}` into your own text.

### Credit-balance note in the WooCommerce order email

After a credit purchase, the customer gets **no additional email**, but a section directly in WooCommerce's own "order completed" email — how many credits were newly added, the current total balance, and a link to My Account. Runs via the `woocommerce_email_order_details` hook, no interference with Woo mail templates. Wording under *BW Credits → Texts*, group "Order Confirmation Email".

### Access details for online sessions

Delivery is event-driven:

1. The instructor enters the meeting link on the session and saves → all existing participants receive the access details
2. Anyone who books **afterwards** gets them right away with the booking confirmation
3. `access_sent_at` per booking prevents duplicate sends
4. **Resend access details** button, in case the link changes later

### WPML

Subject and body are registered under WPML String Translation, in the context *BW Credits*, when WPML is active. The session's language determines the email's language.

## Auto-Update Workflow

```
1. Commit + push the code
2. git tag v0.8.0 && git push origin v0.8.0
3. GitHub: Releases → "Draft a new release" → select the tag → Changelog → Publish
4. WordPress shows the update under "Plugins → Updates" (cache up to 12 h)
```
