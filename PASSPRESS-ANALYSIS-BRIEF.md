# PassPress — Codebase Analysis Brief

> **Purpose of this document.** It is a factual, self-contained snapshot of a WordPress plugin called
> **PassPress**, written so someone who has never seen the code can reason about it. It was produced by
> reading the actual source at version **0.5.26**, not by reading the project's own documentation.
> Use it to propose an improvement plan (see [§14 What I want back from you](#14-what-i-want-back-from-you)).
>
> Everything in §§1–11 is *what exists today*. §12 lists problems found while reading. §13 lists rules that
> must not be broken by any proposal.

---

## 1. What the plugin is

A modular WordPress plugin for businesses that sell **access** rather than products — gyms, fitness centers,
swimming pools, sports clubs, theme/water parks, libraries, museums, martial-arts/yoga/dance academies.

The core product loop:

```
create a plan  →  member buys / staff issues a pass  →  member shows QR or PIN at a facility
      →  staff scans it at the gate  →  entry allowed or denied by plan rules  →  usage logged
      →  expiry approaches  →  reminder email  →  renewal (re-run of a one-time charge)
```

Around that spine sit optional modules: facility booking, weekly class sessions, visitor/guest passes,
attendance analytics, coupons, reports, and email notifications.

**Key positioning decision:** WooCommerce is *optional*. The plugin has its own native checkout with its own
Stripe/PayPal/Offline gateways, and only *bridges* to WooCommerce when the site owner chooses that mode.

- Version: `0.5.26`
- Requires: WordPress 5.8+, PHP 7.4+
- Size: ~148 files, ~26,500 lines (PHP + vanilla JS + CSS)
- No build step. No Composer, no npm, no JSX — hand-written vanilla JS everywhere.
- Development site has WooCommerce 10.9.4 active with HPOS (custom order tables) enabled.
- Elementor is **not** installed on the development site.

---

## 2. Naming and file conventions

| Thing | Convention | Example |
|---|---|---|
| Constants | `PASSPRESS_` | `PASSPRESS_PLUGIN_DIR`, `PASSPRESS_DB_VERSION` |
| Classes | `PP_` | `PP_Membership`, `PP_Booking_Calendar` |
| Functions / hooks | `pp_` | `pp_get_setting()`, `pp_generate_pass_token()` |
| New class files | WPCS `class-pp-*.php` | `class-pp-membership.php` |
| Hub files in `admin/` + `inc/` | flat `PP_Xxx.php` | `PP_Admin.php`, `PP_Hooks.php` |
| Text domain | `passpress` | |
| DB tables | `{$wpdb->prefix}pp_` | `wp_pp_access_logs` |
| Options | `passpress_*` | `passpress_settings`, `passpress_billing_settings` |

Every subdirectory contains a blank `index.php` to block directory listing.

---

## 3. Bootstrap and loading

`passpress.php` (122 lines) does the following, in order:

1. Defines constants, registers activation/deactivation hooks pointing at `PP_Install`.
2. `require_once`s **every** core file and **every** module file — unconditionally.
3. `require_once`s admin files behind a single `is_admin()` check.
4. On `plugins_loaded` → `passpress_init()` → loads the text domain → `PP_Hooks::init()`.

`inc/PP_Hooks.php` is the central wiring point. It instantiates each module class (each attaches its own
`init` / `save_post_*` / `wp_ajax_*` hooks in its constructor), calls the `::init()` static of the
procedural-style classes, registers the two gateway webhook endpoints, and — inside `is_admin()` — boots
the admin screens.

> ⚠️ The project's own documentation claims modules are "only required when enabled for the current business
> type". **That is not true in the code** — everything loads on every request. See §12.6.

---

## 4. Data model

### 4.1 Custom post types (4)

All four are registered with **`show_in_menu => false`**, because each one has a bespoke card-grid admin
screen instead of a native WordPress list table.

| CPT | Public? | Purpose | Key meta |
|---|---|---|---|
| `pp_membership_plan` | yes | Plan/pass definitions (Monthly, Yearly, Day Pass, Family, VIP, Lifetime…) | `_pp_price`, `_pp_plan_type`, `_pp_duration_value`, `_pp_duration_unit`, `_pp_entry_restriction`, `_pp_time_restriction_start/end`, `_pp_max_entries_per_day`, `_pp_most_popular`, `_pp_wc_product_id` |
| `pp_facility` | yes | Gym, Pool, Court, Ground, Club House… | facility type, capacity, plus booking settings: `_pp_booking_required`, `_pp_slot_duration`, `_pp_buffer_minutes`, `_pp_open_time`/`_pp_close_time`, `_pp_days_open`, `_pp_cancellation_lead_hours`, `_pp_staff_ids` |
| `pp_class_session` | yes | Weekly recurring classes (Yoga, Zumba, coaching) | class type, instructor (a WP user id), facility, capacity, one fixed weekly day + time |
| `pp_coupon` | no (`show_ui` only) | Promo codes. **Post title _is_ the code**, force-uppercased on save | active flag, percent/fixed, amount, applicable plans, total usage limit, per-user limit, expiry |

**A class meeting several days a week is modeled as several separate posts** ("Morning Yoga (Mon)",
"Morning Yoga (Wed)"). There is no recurrence engine.

### 4.2 Custom tables (6)

Created by `dbDelta()` in `inc/PP_Install.php` on activation. `PASSPRESS_DB_VERSION` is `1.2.0`.

| Table | Holds |
|---|---|
| `pp_memberships` | the member↔plan record: `membership_number` (unique, `PP-XXXXXX`), `pass_token` (unique, 32-char QR payload), `pin_code` (4-digit), `status`, `member_type`, `start_date`, `expiry_date`, `auto_renew` |
| `pp_access_logs` | every scan attempt: `membership_id`, `facility_id`, `direction` (entry/exit), `method` (qr/pin), `result` (allowed/denied), `reason`, `operator_id` |
| `pp_activity_log` | plugin-wide event log feeding the Activity Log screen |
| `pp_billing_history` | every checkout attempt: `type` (initial/renewal), `gateway`, `gateway_ref`, `amount`, `currency`, `status`, `checkout_token` (unique), `coupon_code`, `discount_amount`, `raw_response` |
| `pp_bookings` | `facility_id`, `user_id`, `membership_id`, `class_session_id` (0 for plain facility bookings), `booking_date`, `start_time`/`end_time`, `status`, `checked_in_at` |
| `pp_booking_waitlist` | same shape + `status` (waiting/notified/expired) |

**Three deliberate modeling choices worth knowing:**

- **Bookings are a table, not a CPT** — date-range availability queries are a poor fit for postmeta.
- **A visitor pass is a normal `pp_memberships` row** with `member_type = 'visitor'`, attached to a real WP
  user account (auto-created, with an `*@passpress.invalid` placeholder email if the visitor gives none).
  Because of this, the QR scanner, PIN entry, and access-control code needed **zero** visitor-specific
  branches — a guest pass scans exactly like a member pass.
- **Coupon redemptions have no table.** Usage is counted by querying `pp_billing_history` for
  `coupon_code` + `status = 'paid'`. The payment ledger *is* the redemption record.

### 4.3 Statuses

- Membership: `active` / `frozen` / `suspended` / `expired` / `cancelled`
- Booking: `confirmed` / `cancelled` / `completed` / `no_show`
- Billing: `pending` / `paid` / `failed` / `refunded` / `cancelled`

Only `confirmed` bookings count toward capacity, so completing or cancelling a booking frees a seat.

---

## 5. Roles and capabilities

Registered in `inc/PP_Roles.php` on activation. Three custom capabilities:

- `pp_manage_memberships` (`CAP_MANAGE`)
- `pp_scan_access` (`CAP_SCAN`)
- `pp_manage_classes` (`CAP_CLASSES`)

| Role | read | scan | manage | classes |
|---|:--:|:--:|:--:|:--:|
| `pp_member` | ✅ | | | |
| `pp_gate_operator` | ✅ | ✅ | | |
| `pp_trainer` | ✅ | ✅ | | ✅ |
| `pp_staff` | ✅ | ✅ | ✅ | ✅ |
| `administrator` | ✅ | ✅ | ✅ | ✅ |

The **top-level admin menu uses `CAP_SCAN`** so gate operators can see it at all; the Dashboard branches
internally to a simplified view for anyone without `CAP_MANAGE`, so they never hit a permissions wall on the
very link the menu points at. Every other submenu is `CAP_MANAGE` (Scan Gate excepted).

---

## 6. Admin surfaces

One top-level menu, `passpress`, with these screens:

Dashboard · Membership Plans · Coupons · Facilities · Class Sessions · Memberships · Visitors · Bookings ·
Scan Gate · Billing History · Attendance · Reports · Activity Log · Setup Wizard · Settings

Plus two hidden legacy slugs (`passpress-billing-settings`, `passpress-notification-settings`) that redirect
into the unified Settings page's tabs.

**Settings** is a single page with three tabs: **General** (currency, date format, QR size, show-PIN),
**Payment Method** (the mode switch + Offline/Stripe/PayPal config + webhook URLs + renewal reminder days),
and **Notifications** (per-trigger on/off toggles).

**The four catalog screens (Plans, Coupons, Facilities, Class Sessions) are custom card grids**, not WP list
tables. Create and edit both open the same in-page modal, and saves go through dedicated AJAX handlers
(`pp_create_plan`, `pp_get_plan`, `pp_update_plan`, and equivalents) that reuse the same meta keys and
sanitization as the CPT's own `save_meta()`.

`assets/admin/passpress-admin.css` is **6,501 lines** — by far the largest file in the plugin — with a
1,189-line `passpress-admin.js` beside it. Both are single flat files with no partials and no build step.

---

## 7. Frontend surfaces

Four shortcodes, each with a matching Gutenberg block whose `render_callback` simply calls `do_shortcode()`
on the same shortcode — so there is exactly one render path per feature, not two:

| Shortcode | Block | Template |
|---|---|---|
| `[passpress_my_pass]` | `passpress/my-pass` | `templates/my-pass/my-pass.php` |
| `[passpress_membership_plans]` | `passpress/plan-list` | `templates/layout/plan-list.php` |
| `[passpress_booking_calendar facility_id="X"]` | `passpress/booking-calendar` | `templates/booking/booking-calendar.php` |
| `[passpress_class_schedule]` | `passpress/class-schedule` | `templates/class-session/class-schedule.php` |

The **My Pass** page is the member's home: QR code, membership status, expiry, PIN, "Renew Now", My Bookings
(with cancel), a birthdate form, and "Invite a Guest".

QR codes are generated **client-side** by a vendored copy of the MIT `davidshimjs/qrcodejs` library.
Scanning is a focused text input on the Scan Gate screen — any USB/Bluetooth QR scanner acts as a keyboard
and types the token + Enter, so there is no camera or JS decoder dependency.

Elementor support is a **detection-only stub** (`support/elementor/elementor-support.php`): it registers an
admin notice pointing at the shortcodes when Elementor is active, and nothing else. No widget classes exist.

---

## 8. The payment-mode switch (the heart of the system)

A single setting, `passpress_billing_settings['payment_method_type']`, takes one of three values:

- **`native`** — PassPress's own checkout and gateways.
- **`woocommerce`** — plans are sold as hidden WooCommerce products through the WC cart/checkout.
- **`none`** — no online purchasing; passes are issued by staff only.

Legacy values (`wc_subscriptions`, `custom`) are silently migrated to `native` on read.

### 8.1 The five ways a membership gets issued

**① Plan-list checkout modal — the primary buy flow.**
`PP_Frontend::enqueue_plan_list_assets()` decides the mode, localizes `PassPressCheckout`, and sets a flag so
the modal markup is printed **once in `wp_footer`** (not inline in the shortcode), so Buy buttons work
regardless of render timing. `passpress-checkout-modal.js` then branches:

- *native* → AJAX `passpress_modal_checkout` → `PP_Billing::ajax_modal_checkout()`: verifies the
  `passpress_checkout_<plan_id>` nonce, requires name/phone/email/address, requires login, saves `billing_*`
  usermeta, validates any coupon, creates a `pp_billing_history` row, calls `$gateway->initiate()`, and
  returns `redirect` | `success` | `pending` | error as JSON.
- *woocommerce* → AJAX `passpress_wc_prepare_checkout` → `PP_Shop_WooCommerce::ajax_prepare_checkout()`:
  creates-or-reuses a WP user (auto-logging in newly created ones), empties the cart, adds the plan's hidden
  product, stores `pp_modal_*` WooCommerce session keys, and returns a checkout URL carrying
  `passpress_wc_embed=1`, which the modal loads **in an iframe**.

**② WooCommerce embed checkout.** On `template_redirect` priority 5,
`PP_Shop_WooCommerce::maybe_serve_embed_checkout()` detects the embed flag, sends
`X-Frame-Options: SAMEORIGIN` and `Content-Security-Policy: frame-ancestors 'self'`, hides the admin bar,
registers ~10 field-compacting filters (trimmed checkout fields, no order notes, no shipping address, a
`gettext` override for labels), includes `templates/checkout/wc-embed-checkout.php` and `exit`s.
PassPress coupons are applied here as a **negative cart fee**, not as a `WC_Coupon`.

**③ Zero-config native checkout page.** `/?passpress_checkout=1&plan_id=X[&renew=<membership_id>]` is rendered
directly on `template_redirect` — no WordPress page needs to exist. It handles gateway returns
(`passpress_return` + `gateway`), cancellations, and classic form POSTs. In WooCommerce mode this URL instead
redirects to the shop buy URL.

**④ WooCommerce order completion.** Each published plan gets one auto-synced, **hidden**, virtual
`WC_Product_Simple` (`catalog_visibility = 'hidden'`, sold individually), synced on
`save_post_pp_membership_plan` at priority 20 and guarded on the raw `_pp_price` meta being non-empty.
`handle_order_completed()` is hooked to **both** `woocommerce_order_status_completed` **and**
`woocommerce_order_status_processing`; for each line item it reads the product's `_pp_plan_id`, skips if the
item already carries a `_pp_membership_id` (the idempotency guard), skips orders with no customer id, then
**renews** an existing active membership for that plan or issues a new one. All order data is read and
written through the WooCommerce CRUD API, never raw postmeta, so it is HPOS-safe.

**⑤ Front desk and visitors.** `PP_Memberships_List`'s "Issue New Membership" action;
`PP_Visitor::register()` for walk-ins; `PP_Visitor::invite_guest()` → staff `finalize_invitation()` for a
member inviting a guest. The invite flow is deliberately *not* fully automated — a real front desk wants to
choose the plan and collect a fee when the guest actually arrives.

### 8.2 The single confirmation funnel

Every native gateway — both the browser return URL **and** the server-to-server webhook — funnels into
`PP_Billing::complete_payment( $checkout_token, $gateway_id, $gateway_ref, $raw_response )`.

It is idempotent by construction: `PP_Billing_History::mark_paid()` performs a single atomic
`UPDATE … WHERE status != 'paid'` **before** the membership is issued or renewed. Whichever request loses
that race returns early, so a webhook and a browser return arriving simultaneously can never both create a
membership. This was verified directly by calling it twice on the same token.

Notifications ride along for free: `PP_Notifications::welcome()` fires from *inside*
`PP_Membership::issue()`, so every issuance path in §8.1 sends a welcome email with no per-caller wiring.
Visitors with placeholder `*@passpress.invalid` addresses fail the `is_email()` guard and are silently
skipped — no special-casing needed.

### 8.3 Gateways

All implement `PP_Gateway_Interface`. `initiate()` returns `redirect` | `completed` | `pending` | `error`
and **never redirects or exits by itself** — `PP_Billing` acts on the return value.

| Gateway | State |
|---|---|
| **Offline / Manual** | ✅ Verified. Auto-confirm, or stay `pending` until staff click "Mark as Paid". Everything else was tested against this one. |
| **Stripe** | ⚠️ Code-complete, **never run against a real account.** Checkout Sessions via `wp_remote_post`, no SDK. Webhook signature verification implements Stripe's documented `t=…,v1=…` HMAC-SHA256 scheme. |
| **PayPal** | ⚠️ Code-complete, **never run against a sandbox account.** Orders v2 REST: OAuth token → create order with the checkout token as `custom_id` → redirect to approve link → capture on return. The webhook handler calls PayPal's own `verify-webhook-signature` API rather than self-implementing the check. |
| **WC Subscriptions** | Detection-only stub. WooCommerce Subscriptions is not installed. |

**There is no true recurring billing.** No stored card, no subscription object, no automatic re-charge.
"Renew" means the member (or a reminder email) is sent back through the *same* one-time-charge checkout for
the same plan, which calls `PP_Membership_Renewal::renew()` instead of `PP_Membership::issue()`.

---

## 9. Access control (the gate)

`PP_Access_Control::validate_and_log()` is the single entry point shared by the QR scanner and PIN entry, so
both share one definition of a valid entry. In order it:

1. Fails closed if no membership resolves from the code.
2. Calls `PP_Membership_Status::maybe_expire()` (lazy expiry on read).
3. Rejects any non-`active` status with a specific reason string.
4. On `direction = entry` only, evaluates the plan's restriction rule.
5. Writes a row to `pp_access_logs` **on every outcome, allowed or denied**, then mirrors it to the activity log.

`PP_Entry_Restrictions::check()` supports: `one_per_day`, `weekday_only`, `weekend_only`,
`time_restricted` (a start/end window), and an independent `_pp_max_entries_per_day` counter. Exits are
never restricted.

---

## 10. The other modules

**Booking** — `PP_Booking` (CRUD + capacity), `PP_Booking_Slots` (pure slot generation from a facility's open
hours / duration / buffer / open days), `PP_Booking_Calendar` (slots minus existing bookings),
`PP_Booking_Waitlist`, `PP_Booking_Frontend` (shortcode + four AJAX handlers). Waitlist promotion
**emails** the next person rather than auto-booking them; they must complete a real booking, which re-checks
availability rather than trusting stale state. Cancellation lead time is enforced **only** for member
self-service — `PP_Booking::cancel( $id, 0 )` is the staff override.

**Class sessions** — reuse the booking tables via a `class_session_id` column rather than duplicating
capacity/waitlist logic. Capacity is keyed by *class + date*, not *facility + time*, so two classes sharing a
room don't share a limit. `get_upcoming_occurrences()` computes the next 8 dates by pure date math from
today; no occurrence rows are stored. The schedule renders server-side at page load; only Book/Waitlist
clicks are AJAX.

**Attendance** — 100% read-only aggregation over `pp_access_logs` (daily/weekly/monthly counts, peak-hour
histogram) and `pp_bookings` (late class arrivals, detected by comparing `checked_in_at` against the class's
scheduled start). No attendance table, no independent data entry. **Early Exit is deliberately not
implemented** — there is no checkout step for a class booking, so there is no honest signal for it.

**Reports** — also 100% read-only, eight methods: `get_revenue`, `get_membership_growth`,
`get_expired_members`, `get_renewal_rate`, `get_facility_usage`, `get_popular_plans`, `get_payment_report`,
`get_trainer_performance`. Peak Hours is intentionally *not* duplicated here; the Reports page links to the
Attendance page instead. Charts are plain CSS bars — no charting library.

**Notifications** — one class, one private `send()` wrapping `wp_mail()`, which is the single place a future
channel would plug in. Triggers: `welcome()`, `receipt()`, `expiry_reminder()`, `waitlist_spot_opened()`,
`booking_reminder()`, `payment_failed()`, `birthday()`. **Email only** — no SMS, WhatsApp, or push.

**Cron** — one daily WP-Cron hook (`passpress_daily_renewal_check`) runs three checks: renewal reminders,
booking reminders, and birthday greetings. Each is idempotent via a same-day activity-log guard, so a second
run on the same day does not resend.

**Coupons** — a custom `pp_coupon` CPT engine, deliberately *not* `WC_Coupon`, so it works with or without
WooCommerce. `PP_Coupon::validate( $code, $plan_id, $user_id, $amount )` is the single entry point:
exists → active → not expired → plan applicable → under total limit → under per-user limit → compute
discount. The **discounted** amount is what reaches the gateway; the discount is never applied after the fact.

**Business templates** — 26 one-click setup packs (Gym, Fitness Center, Swimming Pool, Sports Club, five
sports academies, Golf Club, Theme/Water/Adventure Park, Kids Play Zone, Library, Museum, Zoo, Ski Resort,
Yoga Studio, Dance Academy, Martial Arts, and more). Each is a plain PHP data file returning
`label` / `plans` / `facilities` / `class_sessions` / `pages`, consumed by `PP_Business_Templates::import()`.
Classes reference their facility by *name*, which the importer resolves to the post id created earlier in the
same pass. Some templates genuinely have no classes (a museum, a golf club) or no facilities (Museum Pass) —
those are considered honest omissions, not gaps. The Setup Wizard presents them in five categories with
per-template blurbs.

---

## 11. Current state matrix

| Area | State |
|---|---|
| Core, roles, activity log, settings | ✅ Built and verified |
| Membership & pass, status transitions | ✅ Built and verified |
| Access control (QR + PIN + restrictions) | ✅ Built and verified |
| Facility management + booking + waitlist | ✅ Built and verified |
| Class sessions | ✅ Built and verified |
| Visitor / guest passes | ✅ Built and verified |
| Attendance + Reports | ✅ Built and verified |
| Coupons | ✅ Built and verified end-to-end over real HTTP |
| Notifications (email) | ✅ Built and verified (via mocked `wp_mail`) |
| Native checkout + Offline gateway | ✅ Built and verified |
| Checkout modal + WC embed checkout | ✅ Built; the newest work, least documented |
| WooCommerce shop bridge | ✅ Built and verified against real WC 10.9.4 + HPOS |
| Gutenberg blocks (4) | ✅ Built and verified via the block-renderer REST route |
| All 26 business templates | ✅ Built; 5 of 26 actually imported and checked |
| **Stripe gateway** | ⚠️ Code-complete, never run against a real account |
| **PayPal gateway** | ⚠️ Code-complete, never run against a sandbox account |
| **Elementor** | ⏳ Detection-only stub, no widgets |
| **WC Subscriptions bridge** | ⏳ Detection-only stub |
| Recurring / auto-charge billing | ❌ Not built, by decision |
| Loyalty points, gift cards, referrals, campaigns | ❌ Not built, by decision |
| SMS / WhatsApp / push | ❌ Not built, by decision |
| Import/export, `.pot` translations, `lib/classes/` helpers | ❌ Not built |
| Early-exit attendance tracking | ❌ Not built — no honest signal exists |

---

## 12. Problems and risks found while reading the code

Ordered roughly by severity. Each is a real observation from the source, not a hypothetical.

### 12.1 WooCommerce guest checkout silently issues no pass
In `handle_order_completed()`, `$user_id = $order->get_customer_id()` returns `0` for a guest order and the
loop does `continue`. The customer pays, the order completes, and **no membership is created and nothing is
logged**. There is no admin warning and no setting forcing account creation at WC checkout.

### 12.2 The order hook fires on two statuses
`handle_order_completed()` is registered on **both** `woocommerce_order_status_processing` and
`…_completed`. Only the item-level `_pp_membership_id` meta flag prevents a double issue, which makes that
guard load-bearing. It also means a membership activates at *payment received*, not at *fulfilment* — which
may well be correct for a virtual product, but it is the opposite of what the project's own documentation
says was "deliberately" chosen.

### 12.3 Booking never checks membership status
`PP_Booking_Frontend`'s AJAX handlers require only that the user is **logged in**. A user with no membership,
or an expired/frozen/cancelled one, can book courts, lanes, and class seats. For an access-control product
this is a significant hole.

### 12.4 Two of three payment gateways are unproven
Stripe and PayPal were written against documented REST APIs and never executed against real credentials —
not even in test mode. Their webhook verification, error paths, currency handling, and return-URL flows are
all untested. The plugin cannot honestly ship online payments until these are exercised.

### 12.5 `pp_find_shortcode_page_url()` is fragile
It runs a `LIKE '%[passpress_my_pass%'` query over `wp_posts`, cached in a 1-hour transient. It therefore:
misses a My Pass page built with the **block** instead of the shortcode (the exact bug class already fixed
once in the enqueue path); misses pages of any post type other than `page`; and serves a stale URL for up to
an hour after the page changes. Its result is used for the post-purchase "view your pass" redirect.

### 12.6 The "modular" claim is aspirational
`passpress.php` loads every module on every request regardless of the business template chosen. There is no
enabled-modules setting and no conditional loading, even though modularity is stated as a core design
principle. A library site pays the cost of the booking, class, and billing modules it will never use.

### 12.7 Coupons in WooCommerce mode bypass WooCommerce
They are applied as a negative cart **fee**, not as a `WC_Coupon`. Consequences: WooCommerce's own coupon
reports show nothing; tax treatment of a fee differs from a discount; refunds and order edits behave
differently; and the two coupon systems on the site (PassPress's and WooCommerce's) can be applied to the
same order independently.

### 12.8 The admin CSS is unmanageable
6,501 lines in one file, with no partials, no variables discipline, and no build step, growing with each new
card-grid screen. The four catalog screens each hand-roll their own grid, modal, and form markup rather than
sharing a component.

### 12.9 The project's own `CLAUDE.md` is materially stale
It documents "Phases 1–5" thoroughly but predates roughly the last dozen commits. It does not mention the
checkout modal, the WooCommerce embed checkout, the `payment_method_type` switch, any of the four card-grid
admin screens, or the unified settings hub — i.e. most of the current user-facing surface. It also states two
things the code contradicts (conditional module loading; the order hook being `completed`-only). Anyone,
human or AI, working from that document will build against the wrong picture.

### 12.10 Smaller observations
- The embed checkout mutates WooCommerce checkout globally via `gettext` and `woocommerce_checkout_fields`
  filters at priority 999 whenever the session flag is set — a wide blast radius for a modal.
- `ensure_member_account()` auto-creates a user and calls `wp_set_auth_cookie()` from an AJAX handler that is
  reachable by logged-out visitors; it is nonce-guarded, but it is still an account-creation surface.
- Failed logins aside, there is no rate limiting on PIN entry at the Scan Gate — a 4-digit PIN has 10,000
  combinations.
- `date()` (server timezone) is used in the entry-restriction weekday/weekend checks rather than a
  timezone-aware call, while `current_time()` is used elsewhere.
- No unit or integration test suite exists at all; all verification to date was manual CLI + HTTP smoke tests.
- No `.pot` file, so nothing is actually translatable despite every string being wrapped.

---

## 13. Constraints any plan must respect

These are hard-won rules already encoded in the codebase. Breaking them reintroduces bugs that were already
found and fixed once.

1. **Never call `wp_localize_script()` from inside a shortcode callback.** On block themes, post content
   pre-renders *before* `wp_enqueue_scripts` fires, so the localized data is silently discarded while the
   script tag still prints. All enqueue+localize must happen in `PP_Frontend::maybe_enqueue_for_current_page()`
   on `wp_enqueue_scripts`, detected with `has_shortcode()` **and** `has_block()`.
2. **Never sort "most recent row" queries by `created_at DESC`.** Those columns have 1-second resolution;
   two rows in the same second tie and MySQL gives no stable order. Sort by `id DESC`.
3. **Keep payment confirmation idempotent.** The atomic `mark_paid()` claim must stay *before* membership
   issuance, or a webhook racing a browser return will double-issue.
4. **WooCommerce must remain optional.** No hard dependency, no forced install prompt. Soft
   `class_exists` / `is_plugin_active` checks only, in `inc/PP_Dependencies.php`.
5. **One canonical render function per feature.** Blocks call `do_shortcode()`; any future Elementor widget
   must do the same. No triplicated markup.
6. **Fail closed at the gate.** Every scan outcome, allowed or denied, writes an access-log row.
7. **No build step, no framework.** Vanilla JS, WordPress core APIs, `$wpdb->prepare()` on every custom-table
   query, nonces on every form and AJAX action, capability checks, escape on output, sanitize on input.
8. **`day_of_week` in business-template data is ISO-8601 1–7 (1 = Monday)**, not 0–6.

---

## 14. What I want back from you

Produce a **prioritized improvement plan** for this plugin. Please:

1. **Rank the work by (risk to real users) × (effort)** — I want to know what to do first, not a wish list.
   Treat §12.1–12.4 as candidates for "must fix before anyone takes money with this", but challenge that
   ranking if you disagree.
2. **Separate the plan into tracks** — something like: *correctness/security fixes*, *finish the unfinished*
   (Stripe/PayPal verification, Elementor, recurring billing), *architecture/maintainability*
   (real module toggling, CSS/JS structure, tests), and *product gaps*. Say which track blocks which.
3. **For each item, state**: the problem in one sentence, the proposed fix, which files it touches, roughly
   how big it is, and how it would be verified. Verification matters — this codebase has no test suite, so
   "how would we know it works" is a real question for every item.
4. **Call out anything in §11's "not built by decision" list that you think was the wrong call** — especially
   recurring billing, which is arguably the single biggest gap for a *subscription* product, and the absence
   of any test suite.
5. **Flag anything in this document that looks internally inconsistent or architecturally suspect** to you,
   even if I did not list it as a problem. Fresh eyes on §8 (the payment-mode switch and the five issuance
   paths) would be especially useful — that is the newest and least-reviewed part of the system.
6. **Tell me what you would need to know** that this document does not say, rather than assuming.

Do **not** propose a rewrite, a framework migration, a build pipeline, or a change that breaks any constraint
in §13 without explicitly arguing why the constraint should be dropped.
