# PassPress — Membership, Subscription & Pass Management

Status: **Phases 1-5 built and verified** (Billing's Stripe/PayPal gateways remain code-complete but
**unverified** — no sandbox credentials were available; Elementor remains a detection-only stub — Elementor
isn't installed on this site, so a real widget integration is unverifiable here; see
[Phase 2](#phase-2--whats-actually-built) and [Phase 4 & 5](#phase-4--5--whats-actually-built)). Everything
through Reports, Marketing (Coupons), Notifications, all 26 Business Templates, Gutenberg blocks, and a real
WooCommerce Shop bridge is implemented, lint-clean, and confirmed working end-to-end against a real
WordPress install (including a real, active WooCommerce 10.9.4 install on this site — HPOS-enabled). See
[Phase 1](#phase-1--whats-actually-built) / [Phase 2](#phase-2--whats-actually-built) /
[Phase 3](#phase-3--whats-actually-built) / [Phase 4 & 5](#phase-4--5--whats-actually-built) for the ground
truth of what exists in code today, and keep those sections in sync as later work lands.

## What it is

A modular WordPress plugin for businesses that sell **access** rather than products: gyms, fitness centers,
swimming pools, sports clubs, theme/water parks, libraries, museums, martial-arts/yoga/dance academies, etc.
Core concept: members hold a **membership/pass**, staff validate that pass at a **facility** (QR/PIN),
usage is tracked (attendance/visits), and billing can be one-time or recurring.

The plugin ships **modular**: each business type enables only the modules it needs (a library doesn't need a
swimming-lane booking calendar; a theme park doesn't need recurring subscription billing). A **Business
Template** picked in the Setup Wizard flips the right modules on and seeds sample plans/facilities/pages.

## Reference plugin: mage-eventpress

File/folder conventions here are deliberately modeled on the sibling plugin at
`../mage-eventpress` (Event Booking Manager for WooCommerce). Reuse its patterns rather than inventing new
ones:

- Flat `admin/` + `inc/` split: `admin/` = wp-admin screens & CPT/menu registration, `inc/` = hooks,
  querying, shortcodes, frontend rendering, and the "WooCommerce not active" fallback pair
  (`MPWEM_Global_Function.php` / `MPWEM_Global_Style.php` → see [WooCommerce-optional](#woocommerce-is-optional-not-required) below).
- `lib/classes/` for framework-y reusable helpers (`class-menu-page.php`, `class-meta-box.php`,
  `class-form-fields-generator.php`, `class-icon-library.php`, `class-taxonomy-edit.php`) — copy these
  verbatim from mage-eventpress rather than rewriting; they're generic, not event-specific.
  `lib/appsero/` (usage analytics) is optional/deferred.
  `lib/classes/class-mep-required-plugins.php` → adapt for PassPress's own admin notices (e.g. "install
  WooCommerce Subscriptions to enable recurring billing" instead of a hard dependency).
- `admin/settings/` = one file per settings tab, all registered through a central `MPWEM_Settings.php`-style
  hub → PassPress equivalent is `PP_Settings.php`.
- `templates/` overridable by the active theme (`layout/` = partials, `list/`/`themes/` = swappable
  full-page presentations, `screenshot/` = thumbnails shown in the template picker UI).
- `support/elementor/widget/` = one widget class per shortcode/block, only loaded if Elementor is active.
- `assets/{admin,frontend,blocks,helper,sass}` split by consumer, not by feature.
- Every subdirectory gets a blank `index.php` (`<?php // Silence is golden.`) to block directory listing.
- `admin/mep_dummy_import.php` is the direct precedent for **Business Templates** sample-data seeding —
  don't design that from scratch, adapt it.

Don't copy mage-eventpress's hard WooCommerce dependency or its two-prefix drift (`MPWEM_` classes vs `mep_`
functions were a historical accident there) — PassPress uses one consistent prefix pair everywhere (see below).

## Phase 1 — what's actually built

Everything listed here exists in code, passes `php -l`, and was verified against the real `passpress`
WordPress install (DB `passpress`, table prefix `wp_`). It deviates from the original tree/plan below in a
few places — those deviations are intentional simplifications, not oversights:

- **No separate `admin/PP_CPT.php`.** Each module registers its own CPT (`class-pp-membership-cpt.php`,
  `class-pp-facility-cpt.php`) — more modular, and there was no real content left for a central file to own.
- **No `admin/PP_Deactivation.php`, `admin/PP_Import_Export.php`.** Deferred — not needed for the
  create-a-plan → issue-a-pass → scan-it-at-the-door slice.
- **Settings collapsed to one file.** `admin/settings/PP_Settings.php` handles General + QR Code fields
  together on one screen. The separate `PP_General_Settings.php` / `PP_QRCode_Settings.php` /
  `PP_Roles_Permissions_Settings.php` / `PP_Currency_Tax_Settings.php` / `PP_Activity_Log_Settings.php`
  files are still reasonable splits once there are enough fields per group to justify them.
- **`lib/classes/` is empty (reserved).** Admin screens write their markup inline rather than through
  ported mage-eventpress helper classes (`class-menu-page.php`, `class-meta-box.php`, etc.) — Phase 1's
  screens were simple enough that the indirection wasn't worth it yet. Revisit if/when the admin UI grows.
- **`inc/PP_Install.php` added** (wasn't in the original tree): owns `dbDelta()` table creation and default
  option seeding, called from `register_activation_hook`. Kept separate from `PP_Roles.php` so
  `uninstall.php` can remove roles without loading the whole plugin.
- **No `languages/`, `support/elementor/`, `assets/blocks/`, `lib/appsero/`, `PP_Global_Function.php` /
  `PP_Global_Style.php`.** All deferred; PassPress has no hard WooCommerce dependency to fall back from yet
  since Billing (the module that would need it) isn't built.
- **QR code generation is client-side**, via a real, verified copy of the MIT-licensed `davidshimjs/qrcodejs`
  library fetched from jsDelivr into `assets/helper/qrcode/qrcode.min.js` — not hand-rolled and not a
  server-side PHP QR library. Scanning is a focused text input on the Scan Gate screen: any USB/Bluetooth QR
  scanner behaves as a keyboard (HID) and types the decoded token + Enter, so there's no camera/JS-decoder
  dependency for Phase 1.
- **Membership issuance is an admin/front-desk action** (`PP_Memberships_List` → "Issue New Membership"),
  not a checkout flow — there's no payment gateway yet (that's Phase 2, Subscription Billing).

Files that exist today: `passpress.php`, `uninstall.php`, `readme.txt`; `inc/PP_Install.php`,
`PP_Roles.php`, `PP_Functions.php`, `PP_Activity_Logger.php`, `PP_Query.php`, `PP_Dependencies.php`,
`PP_Shortcodes.php`, `PP_Frontend.php`, `PP_Hooks.php`; `inc/modules/membership/*`,
`inc/modules/facility/*`, `inc/modules/access-control/*`, `inc/modules/business-templates/*` (+ `data/gym.php`);
`admin/PP_Admin.php`, `PP_Dashboard.php`, `PP_Memberships_List.php`, `PP_Scan_Gate.php`,
`PP_Activity_Log_Page.php`, `PP_Setup_Wizard.php`, `PP_Welcome.php`, `admin/settings/PP_Settings.php`;
`templates/my-pass/my-pass.php`, `templates/layout/plan-list.php`; the `assets/` CSS/JS above.

## Phase 2 — what's actually built

Billing (native checkout + Offline gateway) and Booking are implemented and verified end-to-end. Stripe and
PayPal are implemented against their documented REST APIs but **could not be exercised against a real
account** — no sandbox/test credentials were available in this environment. Do not treat them as tested;
run a real test-mode payment through each before relying on them.

**Billing module** (`inc/modules/billing/`):

- `PP_Billing` — checkout orchestration + the zero-config checkout page. `/?passpress_checkout=1&plan_id=X`
  (add `&renew=<membership_id>` to renew instead of issuing new) needs no WP page created, same pattern as
  Phase 1's approach — rendered via `template_redirect`, wrapped in `get_header()`/`get_footer()` so it
  inherits the active theme's chrome.
- `PP_Billing_History` — `pp_billing_history` table. `mark_paid()` is a single atomic
  `UPDATE ... WHERE status != 'paid'` called **before** issuing/renewing the membership (not after) — this
  is what makes `PP_Billing::complete_payment()` safe to call twice for the same payment (a Stripe/PayPal
  webhook and the browser's return-URL request racing each other, or a webhook retried by the gateway).
  Verified directly: calling `complete_payment()` twice on the same checkout token issues exactly one
  membership.
- Gateways implement `PP_Gateway_Interface` (`initiate()` returns `redirect|completed|pending|error`, never
  redirects/exits itself — `PP_Billing` acts on the return value):
  - ✅ **Offline/Manual** (`class-pp-gateway-offline.php`) — auto-confirm or manual-confirm (stays `pending`
    in Billing History until staff clicks "Mark as Paid"). This is the gateway everything else is verified
    against, since it needs no external credentials.
  - ⚠️ **Stripe** (`class-pp-gateway-stripe.php`) — Checkout Sessions via `wp_remote_post`, no SDK. Webhook
    signature verification follows Stripe's documented `t=...,v1=...` HMAC-SHA256 scheme exactly. **Unverified
    against a real account.**
  - ⚠️ **PayPal** (`class-pp-gateway-paypal.php`) — Orders v2 REST (OAuth token → create order with our
    checkout token as `custom_id` → redirect to the approve link → capture on return). Webhook handler calls
    PayPal's own `verify-webhook-signature` API rather than a self-implemented check. **Unverified against a
    real sandbox account.**
  - `class-pp-gateway-woo-subscriptions.php` — deliberately **not** a real integration. WooCommerce isn't
    installed on this site at all, so a real bridge (hidden `WC_Product_Subscription` per plan, subscription
    status ↔ membership status sync, renewal-payment hook) couldn't be verified even superficially. The class
    only detects WC Subscriptions and shows an admin notice that the bridge isn't built yet — see its
    docblock before assuming otherwise.
  - Both real gateway credential sets have a Billing Settings screen (`admin/settings/PP_Billing_Settings.php`)
    with the exact webhook URLs to register in each provider's dashboard.
- **No true zero-touch recurring billing.** "Renew" means the member (or `PP_Cron`'s reminder email) is sent
  back through the *same* one-time-charge checkout for the same plan; there's no stored card/subscription
  object and no automatic re-charge. Building real recurring billing (Stripe Subscriptions API or PayPal
  Billing Plans, each with their own webhook lifecycle) is a distinct, larger problem — treat it as its own
  future phase, not a Billing sub-task.
- `PP_Cron` (`inc/PP_Cron.php`) — daily WP-Cron event, emails members whose membership expires within the
  configured reminder window (default 7 days) with a renew link. Plain `wp_mail()`, not the future
  Notifications module.
- Membership issuance changed from Phase 1: `[passpress_membership_plans]` now shows a real "Subscribe"
  button (linking to checkout) whenever any gateway is enabled+configured, falling back to the old "visit the
  front desk" message otherwise. `PP_Memberships_List`'s admin "Issue New Membership" flow still exists
  unchanged, for walk-in/manual signups.

**Booking module** (`inc/modules/booking/`):

- **Deliberate deviation from the original tree**: `pp_booking` is a **custom DB table**
  (`{prefix}pp_bookings`), not a CPT as the original plan sketched. This matches the Phase 1 precedent
  (memberships/access-logs are tables, not CPTs) and is a better fit for date-range availability queries than
  postmeta would be. `{prefix}pp_booking_waitlist` is a second new table.
- `PP_Booking` (CRUD + capacity check), `PP_Booking_Slots` (pure slot-time generation from a facility's open
  hours/duration/buffer/open-days meta), `PP_Booking_Calendar` (combines slots + existing bookings into an
  availability list) — verified: correct slot count, capacity enforcement (a full slot rejects a second
  booking), waitlist join, and waitlist promotion (cancelling a booking emails the next waitlisted person).
- `PP_Booking_Waitlist` — promotes by **emailing** the next person rather than auto-booking them; they still
  have to complete a real booking (re-checks availability at that point) rather than trusting stale state.
- `PP_Facility_CPT` extended with a second "Booking Settings" meta box: `_pp_booking_required`,
  `_pp_slot_duration`, `_pp_buffer_minutes`, `_pp_open_time`/`_pp_close_time`, `_pp_days_open` (checkbox
  array), `_pp_cancellation_lead_hours`, `_pp_staff_ids` (assigned `pp_staff`/`pp_trainer`/administrator
  users).
- Cancellation lead-time is enforced **only for member self-service cancellation** (`PP_Booking::cancel($id,
  $by_user_id)` — pass `0` for an admin/staff override that bypasses it). Verified: a same-day booking blocks
  a member's own cancellation but not an admin's.
- `class-pp-booking-frontend.php` (not in the original tree, same rationale as Phase 1's `PP_Install.php` —
  request-handling code the CRUD class shouldn't own): the `[passpress_booking_calendar facility_id="X"]`
  shortcode + its `wp_ajax_pp_*` handlers (get_availability, create_booking, join_waitlist, cancel_booking).
  Booking only requires being logged in today — it does **not** check for an active membership; gating
  facility booking on membership status is a reasonable future enhancement, out of scope here.
- "My Bookings" (cancel button included) was added directly to the existing `templates/my-pass/my-pass.php`
  template rather than a new shortcode, since it's the same member-facing surface.
- New `templates/checkout/checkout.php` and `templates/booking/booking-calendar.php` (both new subtrees, not
  in the original plan).

**Two real bugs found and fixed while verifying this phase over actual HTTP** (both worth knowing before
touching frontend asset code in this plugin again):

1. **Block-theme (full site editing) content pre-rendering breaks the common
   "`wp_localize_script()` inside a shortcode callback" pattern.** Confirmed via a temporary debug mu-plugin:
   on this site's block theme, `the_content` (and therefore any shortcode's callback) runs **before**
   `wp_enqueue_scripts` fires — the opposite of the order classic PHP themes guarantee. A
   `wp_localize_script()` call made inside a shortcode callback gets silently discarded once the *real*
   `wp_register_script()` call for that handle runs afterward on `wp_enqueue_scripts` — the script tag still
   prints, but its localized data never does. This silently broke the Phase 1 QR code (the container/token
   HTML was always correct, so Phase 1's verification missed it — the localized `qrSize` data simply never
   reached the page). **Fix:** `PP_Frontend::maybe_enqueue_for_current_page()` (hooked to `wp_enqueue_scripts`
   itself, not a shortcode) detects each shortcode via `has_shortcode( $post->post_content, ... )` and does
   the enqueue+localize there; shortcode callbacks now only call the same `PP_Frontend::enqueue_*` methods as
   a harmless redundant fallback (for shortcodes invoked outside normal post content, e.g. from a template
   file). **Any new frontend script needing localized data must follow this pattern, not the naive one.**
2. **`ORDER BY created_at DESC` alone is not a stable sort** — `created_at` columns here only have
   1-second resolution, so two rows created within the same second (very plausible: automated flows, or a
   user clicking twice) tie, and MySQL doesn't guarantee tie-break order without a secondary key. This caused
   a genuinely flaky "which row is the *most recent* one" bug in `PP_Billing_History::get_recent()` /
   `get_for_membership()` and `PP_Activity_Logger::get_recent()`, reproduced by running the same smoke test
   twice with different results. **Fixed** by sorting on `id DESC` instead (monotonic, unambiguous) —
   apply the same fix to any future "get the most recent row" query on these tables.

## Phase 3 — what's actually built

Visitor Pass, Attendance, and Class & Session are all implemented and verified end-to-end (CLI + real HTTP/AJAX).

**Visitor Pass** (`inc/modules/visitor/`) — **resolves a CLAUDE.md inconsistency**: an earlier draft of this
doc said Visitor Pass both "reuses `pp_memberships` with a type flag" (module-map note) *and* listed
`pp_visitor_pass` as a planned CPT (Data-model note) — those two statements conflicted. Resolved by explicit
product decision: **every visitor gets a real WP user account** (not a parallel table, not a CPT) —
`PP_Visitor::create_visitor_user()` creates one (role `pp_member`), reusing an existing user if a real email
matches, or generating a `*@passpress.invalid` placeholder email (RFC 2606-reserved, guaranteed never to
resolve) when the visitor gives none. `PP_Membership::issue()` gained a 4th param, `$member_type` (`member`
default, `visitor` for guests) — a new `member_type` column on `pp_memberships` — and a visitor's pass is
otherwise a completely normal membership row (its own `plan_id`, `pass_token`, `pin_code`, status machine).
**The payoff of this design**: `PP_Access_Control`, the QR scanner, PIN entry, and Scan Gate needed **zero
changes** — a visitor pass scans exactly like a member pass, verified directly. `PP_Query::get_memberships()`
and the Dashboard stats now default to `member_type = 'member'`, excluding visitors, with a `member_type`
filter (`member`/`visitor`/`all`) for the admin screens that need to see them.
- `PP_Visitor::register()` — front-desk walk-in flow (name/email/phone/plan → account + issued pass).
- `PP_Visitor::invite_guest()` / `get_pending_invitations()` / `finalize_invitation()` — a member can invite a
  guest from My Pass (creates the account, flags it pending); staff pick the plan and issue the actual pass
  from PassPress → Visitors when the guest arrives. Deliberately not fully automated — a real front desk still
  wants to choose the plan/collect a fee.
- `admin/PP_Visitors_List.php` — register form, pending-invitations queue, visitor pass history (reuses
  `PP_Membership_Renewal`/`PP_Membership_Status` directly, since a visitor pass **is** a membership row).
- No WP user account is ever deleted on uninstall — deleting real user accounts is a heavier, riskier
  operation than the plugin's own table/post cleanup; visitor accounts are left in place intentionally.

**Attendance** (`inc/modules/attendance/`) — read-only queries over `pp_access_logs` (general daily/monthly
counts, peak-hour histogram) and `pp_bookings` (class late-arrivals), no independent data entry, per the
original module-map note. **"Early Exit" is deliberately not implemented** — there's no dedicated checkout
step for a class booking (only Complete/No-show), so there's no reliable signal for when someone left early;
building a fake one was rejected in favor of documenting the gap. "Late Entry" is real data: `PP_Booking::set_status()`
now captures `checked_in_at = now()` specifically when a booking transitions to `completed`, and
`PP_Attendance::get_late_class_arrivals()` flags any `completed` class booking where that timestamp landed
after the class's scheduled `start_time`. `admin/PP_Attendance_Reports_Page.php` shows daily/weekly/monthly
totals, a peak-hour bar chart (plain CSS, no charting library), and the late-arrivals list.

**Class & Session** (`inc/modules/class-session/`) — a new `pp_class_session` CPT (Yoga, Zumba, Fitness,
Swimming, Karate, Dance, Football Training, Cricket Coaching), meta-box modeled directly on Facility's Phase 2
booking settings: class type, instructor (any `pp_staff`/`pp_trainer`/administrator user — no separate
Instructor data structure, deliberately simpler than the original tree's `class-pp-instructor.php`), facility/room,
capacity, one fixed weekly day+time. **A class meeting multiple days a week is modeled as separate posts**
(e.g. "Morning Yoga (Mon)", "Morning Yoga (Wed)") — a documented simplification, not a bug.
- **Capacity is keyed by `class_session_id` + date, not `facility_id` + time** like Booking's facility slots
  — a new `class_session_id` column was added to both `pp_bookings` and `pp_booking_waitlist` specifically so
  two different classes sharing a facility/time slot don't incorrectly share capacity. `PP_Booking` gained
  `get_class_booked_count()` / `create_for_class()`; `PP_Booking_Waitlist` gained `join_class()` /
  `maybe_promote_class()` — parallel to the facility-keyed originals, not a rewrite of them.
  `PP_Booking::cancel()` now branches to the class-keyed waitlist promotion when `booking->class_session_id`
  is set.
- `PP_Class_Session::get_upcoming_occurrences()` computes the next 8 real dates matching the class's weekly
  day from today forward (pure date math, no stored occurrence rows) — verified: correct date, correct
  7-day spacing, correct capacity/availability per occurrence.
- Cancelling a class booking reuses the **existing** `wp_ajax_pp_cancel_booking` handler from
  `PP_Booking_Frontend` completely unchanged — it already just operates on a booking id regardless of whether
  `class_session_id` is set.
- `[passpress_class_schedule]` — unlike the facility booking calendar (which needs a date-picker + AJAX
  availability lookup), a class's occurrences are deterministic from today's date, so they're rendered
  **server-side at page load**; only the actual Book/Waitlist clicks are AJAX (`pp_book_class`,
  `pp_join_class_waitlist`).
- The Gym Business Template now also seeds a "Guest Day Pass" plan and a sample "Morning Yoga" class (Monday
  07:00–08:00, capacity 15) plus a Class Schedule page — `PP_Business_Templates::import()` gained a
  `class_sessions` template-data key, resolving each class's `facility_name` to the facility post id created
  earlier in the same import pass.
- `admin/PP_Bookings_List.php` gained a class filter dropdown and shows the class title (not the facility
  title) for class bookings, plus a "Checked In" column.

**A real, reproducible bug found and fixed while verifying this phase**: `PP_Booking::create_for_class()`'s
capacity check must run before insert (it does), but the *only* way to correctly exercise "book again after a
prior booking on the same class/date was completed" is to confirm completed/cancelled bookings don't count
toward `get_class_booked_count()` — they don't (only `STATUS_CONFIRMED` is counted, matching the pre-existing
facility-booking convention), verified directly via the smoke test's "book again after completion frees
capacity" case.

## Phase 4 & 5 — what's actually built

Built together in one pass since they overlap in practice (Reports/Marketing/Notifications from Phase 4;
remaining Business Templates/Gutenberg blocks/Shop/Elementor from Phase 5). Scope was explicitly narrowed by
product decision in a few places — see each subsection.

**Reports** (`inc/modules/reports/class-pp-reports.php`) — 100% read-only queries over existing tables, same
philosophy as Attendance: no new data storage. `PP_Reports` has 8 static methods (`get_revenue`,
`get_membership_growth`, `get_expired_members`, `get_renewal_rate`, `get_facility_usage`, `get_popular_plans`,
`get_payment_report`, `get_trainer_performance`); Peak Hours is deliberately **not** duplicated here — the
Reports admin page links out to the existing Attendance page for that instead of re-querying it. Renewal rate
= distinct memberships with a paid `type='renewal'` billing_history row in the window, divided by
(renewed + lapsed), where lapsed = memberships that hit `status='expired'` in the window with no matching
renewal. `admin/PP_Reports_Page.php` reuses the same `.passpress-peak-bar` CSS from the Attendance page for
its own bar-chart visualizations rather than introducing a charting library.

**Marketing — Coupons only** (`inc/modules/marketing/`) — **explicit product decision**: of the full Marketing
plan (Coupons, Loyalty Points, Gift Cards, Referral Program), only **Coupons/Promo Codes** was built. The
directory does **not** contain `class-pp-referral.php` / `class-pp-gift-card.php` / `class-pp-loyalty-points.php`
/ `class-pp-campaign.php` from the original tree sketch — those are simply not built, not hidden/disabled.
- `pp_coupon` is a **CPT**, not `WC_Coupon` and not a custom table — confirmed working whether or not
  WooCommerce is active. `PP_Coupon_CPT` registers it (`show_ui` but not `public`, title = the coupon code,
  force-uppercased on save) with one meta box: active flag, discount type (percent/fixed), amount, applicable
  plans (empty = all), usage limit total, usage limit per user (default 1), optional expiry date.
- `PP_Coupon::validate( $code, $plan_id, $user_id, $amount )` is the single entrypoint: checks exists → active
  → not expired → plan applicable → under total usage limit → under per-user usage limit, then computes the
  discount. Usage counting queries `pp_billing_history` directly (`coupon_code` + `status='paid'`) — there is
  **no separate redemptions table**; the billing ledger already is the record of what was actually charged.
- `pp_billing_history` gained two columns (`coupon_code`, `discount_amount`, DB version bumped to 1.2.0).
  `PP_Billing::process_checkout_submit()` validates the posted coupon code before charging and passes the
  **discounted** amount to the gateway (the discount is never applied after the fact) — verified end-to-end
  over real HTTP: GET the checkout form → scrape the real nonce → POST with a coupon code (lowercase, proving
  case-insensitivity) → confirmed the billing_history row records the discounted charge and the coupon code.
  Verified: per-user usage limit blocks a repeat use by the same user but not a different user; plan
  restriction rejects a non-applicable plan; expired/inactive coupons are rejected.

**Notifications** (`inc/modules/notifications/class-pp-notifications.php`) — **explicit product decision:
email dispatcher only**, no SMS/WhatsApp/Push. `PP_Notifications` is one class with a private `send()` that
wraps `wp_mail()` — the one place a future channel would plug in — and public trigger methods: `welcome()`,
`receipt()`, `expiry_reminder()`, `waitlist_spot_opened()` (these last three are the **same emails that
already existed**, just refactored to route through this dispatcher instead of calling `wp_mail()` directly
from `PP_Billing`/`PP_Cron`/`PP_Booking_Waitlist`), plus three genuinely new triggers: `booking_reminder()`,
`payment_failed()`, `birthday()`.
- **Welcome** fires from inside `PP_Membership::issue()` itself (after the activity log call) — so it fires
  for *every* issuance path (admin manual issue, paid checkout, WooCommerce Shop order, visitor
  register/invite) with no per-caller wiring. Visitors with a placeholder `*@passpress.invalid` address
  simply fail `send()`'s `is_email()` guard and are silently skipped — no special-casing needed.
- **Payment Failed** fires from both places `PP_Billing_History::mark_failed()` is called: a failed gateway
  `initiate()` in `PP_Billing::process_checkout_submit()`, and staff manually marking an Offline payment
  failed from `PP_Billing_History_Page`.
- **Booking Reminder** and **Birthday** are new daily `PP_Cron` checks (same hook, `passpress_daily_renewal_check`,
  as the existing renewal reminder — no new `wp_schedule_event()` needed), each idempotent via the same
  same-day activity-log guard pattern as the existing renewal reminder (`booking_reminder_sent` /
  `birthday_greeting_sent` events). Birthday matches on `DATE_FORMAT(meta_value, '%m-%d')` against the
  `pp_birthdate` usermeta, in any birth year.
- All 5 new/refactored triggers (except the pre-existing three) are individually toggleable from
  `admin/settings/PP_Notification_Settings.php` (`passpress_notification_settings` option) —
  `welcome_enabled`, `booking_reminder_enabled` (+ `booking_reminder_days`, default 1), `payment_failed_enabled`,
  `birthday_enabled`. Renewal reminder timing stays on the *existing* Billing Settings page
  (`renewal_reminder_days`) rather than being relocated — it predates this module and already worked.
- Birthdate is member-editable from a small plain-POST (not AJAX) form added to `templates/my-pass/my-pass.php`,
  saved via `PP_Notifications::maybe_save_birthdate_from_post()` called at the top of
  `PP_Shortcodes::render_my_pass()`. Deliberately not AJAX: the shortcode callback already runs during normal
  content rendering, so there's no localize-timing concern to work around (see Phase 2's FSE bug note) and a
  plain form POST is simpler.
- Verified via CLI (mocking `wp_mail`/`pre_wp_mail` to capture sent messages without a real mail server) and
  real HTTP: every trigger fires under the right condition and is suppressed when its setting is off; both new
  cron checks are idempotent (a second same-day run does not resend).

**Business Templates — all 26** (`inc/modules/business-templates/data/`) — **explicit product decision**: the
user chose "all 25 remaining templates" over a smaller representative sample, so every business type from the
original plan now has a real, differentiated data file (not a copy-pasted clone with renamed labels):
Fitness Center, Health Club, Swimming Pool, Sports Club, Football Academy, Cricket Academy, Tennis Club,
Badminton Club, Basketball Club, Golf Club, Community Club, Kids Play Zone, Theme Park, Water Park, Public
Park, Recreation Center, Library Membership, Museum Pass, Zoo Pass, Adventure Park, Ski Resort, Cycling Club,
Martial Arts Academy, Yoga Studio, Dance Academy (plus the original Gym). `PP_Business_Templates::get_available()`
now simply returns `get_roadmap()` — every roadmap entry is real. Facility/class/plan-type values used are
the actual enum values from `PP_Membership_Plan_CPT`/`PP_Facility_CPT`/`PP_Class_Session_CPT` (verified via a
CLI sweep asserting every value used across all 26 files is a real, valid one — not a typo); `day_of_week` is
**1-7, ISO-8601 (1=Monday)**, not 0-6 — a correction worth remembering if adding more classes later. Some
business types genuinely have no `class_sessions` (a museum, library, golf club, or ski resort doesn't have
"classes" the way a gym or yoga studio does) and some have no `facilities` at all (Museum Pass — there's no
facility_type enum value that fits an exhibit hall, and museum rooms aren't booked/scanned as separate
facilities in practice) — these are deliberate, honest omissions, not gaps. Verified: a CLI sweep of all 26
files for structural/enum correctness, plus an actual `import()` of 5 structurally-diverse templates
(one with classes+facilities, one with zero facilities, one with multiple facilities and no classes, one with
a lifetime-duration plan, one using the `weekly` plan_type) — confirmed real posts created with correct meta,
facility_name→facility_id resolution for classes, and the "already imported" guard blocking a re-import.

**Gutenberg blocks** (`inc/PP_Blocks.php` + `assets/blocks/passpress-blocks.js`) — 4 dynamic blocks
(`passpress/plan-list`, `passpress/my-pass`, `passpress/booking-calendar`, `passpress/class-schedule`), each
`render_callback` simply wrapping the *same* shortcode via `do_shortcode()` rather than re-implementing markup
(per the "one canonical render function per feature" rule below) — `booking-calendar` is the only one with a
real attribute (`facilityId`, edited via a plain `TextControl` in `InspectorControls`, no REST-backed facility
picker — kept simple deliberately). The editor side is hand-written vanilla JS (`wp.element.createElement`,
no JSX/build step — matches every other JS file in this plugin) using the `wp-server-side-render` package so
the editor preview is guaranteed to match the frontend output exactly (it *is* the same render_callback,
fetched via the `/wp/v2/block-renderer/{name}` REST route).
- **A real bug found and fixed while building this**: `PP_Frontend::maybe_enqueue_for_current_page()`
  (the FSE enqueue-ordering fix from Phase 2) only checked `has_shortcode()` — a page using the **block**
  form instead of the shortcode would silently get no localized script data, the exact same class of bug
  Phase 2 fixed for shortcodes. Fixed by also checking `has_block( 'passpress/my-pass', $post )` etc.
  alongside each `has_shortcode()` check. Verified directly: a real page with only
  `<!-- wp:passpress/my-pass /-->` (no shortcode at all) in its content correctly gets `passpress-my-pass`
  enqueued.
- Verified: all 4 blocks registered, each `render_callback` produces non-empty real output, the
  `/wp/v2/block-renderer/{name}` REST endpoint (what the browser's ServerSideRender component actually calls)
  returns 200 with real rendered HTML for each, and the editor script is confirmed enqueued on the block
  editor screen over real HTTP.

**Shop — real WooCommerce integration, not a stub** (`inc/modules/shop/class-pp-shop-woocommerce.php`) —
**this deviates from the original plan/earlier phase notes**, which assumed neither WooCommerce nor Elementor
was installed. Mid-build, WooCommerce turned out to be genuinely installed and *active* (v10.9.4, HPOS/custom
order tables enabled) on this site, so unlike the WC Subscriptions billing bridge (still a stub — see Phase 2
notes) or Elementor (still a stub — see below), a real integration was buildable and testable here, and the
user chose to build it rather than defer it. Entirely **additive**: native checkout (`PP_Billing`) is
completely unchanged and keeps working with or without WooCommerce; this just gives every plan a *second*,
optional purchase path through the normal WC cart/checkout.
- Every `pp_membership_plan` gets one auto-synced, **hidden** (`catalog_visibility = 'hidden'`, not shown in
  shop/search — reached only via the "Buy via Shop" link) `WC_Product_Simple`: virtual, sold individually,
  name/price mirroring the plan. Sync is hooked to `save_post_pp_membership_plan` at priority 20 (after the
  plan CPT's own `save_meta()` at priority 10, so the fresh price is already saved) and guarded on the *raw*
  `_pp_price` meta value being non-empty — this matters because `PP_Business_Templates::import()` calls
  `wp_insert_post()` *then* `update_post_meta()` separately, so the hook fires once prematurely (before price
  exists, correctly a no-op) and `import()` now calls `PP_Shop_WooCommerce::sync_product_for_plan()` again
  explicitly once the real price is set.
- `buy_url( $plan_id )` returns a real `add-to-cart` URL, shown as a "Buy via Shop" button next to the
  existing "Subscribe" button on the plan list template (only rendered when a real linked product exists).
- `handle_order_completed()` hooks `woocommerce_order_status_completed` (not `processing` — deliberately the
  safer, standard signal that payment truly cleared and fulfillment is done). For each order line item linked
  to a plan (via product meta, HPOS-safe — read/written entirely through the CRUD API, never raw postmeta),
  issues a new membership or **renews** an existing active one for that same plan (buying the same plan again
  = renewal, not a duplicate row — a deliberate, reasonable design choice). Idempotent via a
  `_pp_membership_id` flag stored on the order item itself, so a status transition firing twice can't
  double-issue. Welcome/receipt notifications already fire for free, since they're wired into
  `PP_Membership::issue()`/`PP_Membership_Renewal::renew()` regardless of caller.
- A small "WooCommerce Shop" meta box on the plan edit screen shows the linked product (or a note that one
  will be created on save).
- Verified end-to-end via CLI (product creation/attributes/idempotent updates, `buy_url()`, a real
  `wc_create_order()` → `handle_order_completed()` → membership issued, calling it twice does not double-issue,
  a second order for the same plan renews instead of duplicating) and real HTTP (plan list page shows the Buy
  via Shop link with a real add-to-cart URL; plan edit screen shows the linked-product meta box).

**Elementor — still a detection-only stub** (`support/elementor/elementor-support.php`) — same shape and same
reasoning as the WC Subscriptions bridge: Elementor is genuinely not installed on this site (confirmed via
`class_exists('\Elementor\Plugin')` returning false), so there's no way to verify real
`\Elementor\Widget_Base` subclasses, the `elementor/widgets/register` hook signature, or the controls API even
superficially. `PP_Elementor_Support::init()` only registers an `admin_notices` callback when
`pp_is_elementor_active()` is true (verified: on this site, with Elementor absent, the callback is never even
registered) — pointing users at the existing shortcodes/blocks as a workaround in the meantime. Real widget
classes (`support/elementor/widget/`, one per shortcode, each wrapping the same canonical render function used
by the shortcode and Gutenberg block) should happen in an environment where Elementor can actually be
installed and tested against.

## Naming conventions

| Thing | Convention | Example |
|---|---|---|
| Main plugin file | `passpress.php` | `Plugin Name: PassPress – Membership, Subscription & Pass Management` |
| Constants | `PASSPRESS_` | `PASSPRESS_PLUGIN_DIR`, `PASSPRESS_PLUGIN_URL`, `PASSPRESS_PLUGIN_VERSION`, `PASSPRESS_PLUGIN_FILE` |
| Functions / hooks / actions / filters | `pp_` | `pp_get_membership()`, `pp_render_my_pass()`, `do_action('pp_membership_renewed', ...)` |
| Classes | `PP_` | `PP_Membership`, `PP_Booking_Calendar`, `PP_Access_Control` |
| Class file names (new code) | WPCS `class-pp-*.php` | `class-pp-membership.php` |
| Legacy-style flat admin/inc files (mirroring mage-eventpress) | `PP_Xxx.php` | `PP_Admin.php`, `PP_CPT.php` |
| Text domain | `passpress` | |
| DB table prefix | `{$wpdb->prefix}pp_` | `wp_pp_access_logs` |
| User roles | `pp_member`, `pp_staff`, `pp_trainer`, `pp_gate_operator` | mirrors the `mpwpb_staff` role added in the sibling `wpbookingly` plugin — see [[project_staff_my_service_appointment]] |

## Directory structure

```
passpress/
├── passpress.php                      # ✅ Bootstrap: constants, activation/deactivation hooks, module loader
├── uninstall.php                      # ✅
├── readme.txt                         # ✅
├── LICENSE.txt
├── package.json                       # sass build, same pattern as mage-eventpress (not set up yet)
├── admin/
│   ├── PP_Admin.php                   # ✅ Admin menu registration, asset enqueue
│   ├── PP_Dashboard.php                # ✅ Core "Dashboard" screen
│   ├── PP_Memberships_List.php          # ✅ Issue/search/filter memberships, row actions (renew/freeze/suspend/reactivate/cancel), member_type filter
│   ├── PP_Visitors_List.php             # ✅ Register walk-in, pending guest invitations, visitor pass history (not in original tree)
│   ├── PP_Bookings_List.php             # ✅ Filter bookings/classes, cancel/complete/no-show, checked-in-at column (not in original tree)
│   ├── PP_Scan_Gate.php                 # ✅ QR + PIN check-in/out screen — works unchanged for visitor passes
│   ├── PP_Billing_History_Page.php      # ✅ Ledger + manual confirm/fail for pending Offline payments (not in original tree)
│   ├── PP_Attendance_Reports_Page.php   # ✅ Daily/weekly/monthly totals, peak-hour chart, late class arrivals (not in original tree)
│   ├── PP_Reports_Page.php              # ✅ Revenue/growth/renewal-rate/expired/facility-usage/popular-plans/payment/trainer reports (not in original tree)
│   ├── PP_Activity_Log_Page.php         # ✅ Recent activity viewer
│   ├── PP_Setup_Wizard.php             # ✅ Business Type Selection + Business Templates one-click setup (all 26 types now real)
│   ├── PP_Welcome.php                  # ✅ Activation redirect to Setup Wizard
│   ├── index.php
│   └── settings/
│       ├── PP_Settings.php             # ✅ General + QR Code fields, combined (see Phase 1 notes)
│       ├── PP_Billing_Settings.php      # ✅ Payment Method + Offline/Stripe/PayPal config + webhook URLs + renewal reminder days
│       ├── PP_Notification_Settings.php # ✅ Per-trigger on/off toggles for Welcome/Booking-Reminder/Payment-Failed/Birthday (not in original tree)
│       └── index.php
├── inc/
│   ├── PP_Hooks.php                    # ✅ Central loader
│   ├── PP_Install.php                  # ✅ dbDelta table creation + default options (not in original tree)
│   ├── PP_Cron.php                     # ✅ Daily WP-Cron: renewal reminders + booking reminders + birthday greetings (not in original tree)
│   ├── PP_Blocks.php                   # ✅ Registers 4 dynamic Gutenberg blocks, each wrapping a shortcode via do_shortcode() (not in original tree)
│   ├── PP_Functions.php                # ✅
│   ├── PP_Query.php                    # ✅
│   ├── PP_Shortcodes.php                # ✅
│   ├── PP_Frontend.php                 # ✅ Also owns has_shortcode()/has_block()-based early enqueue — see Phase 2 & Phase 4/5 notes
│   ├── PP_Dependencies.php             # ✅ Soft-checks for WooCommerce / WC Subscriptions / Elementor, never a hard require
│   ├── PP_Activity_Logger.php          # ✅
│   ├── PP_Roles.php                    # ✅ Registers pp_member / pp_staff / pp_trainer / pp_gate_operator
│   └── modules/                        # One folder per plan module — each toggleable per Business Template
│       ├── membership/                 # ✅ Membership & Pass Module
│       │   ├── class-pp-membership.php
│       │   ├── class-pp-membership-cpt.php        # pp_membership_plan CPT
│       │   ├── class-pp-membership-renewal.php     # manual renewal (auto-renew is a Billing/Phase 2 concern)
│       │   └── class-pp-membership-status.php      # freeze / suspend / reactivate / cancel
│       ├── billing/                     # ✅ Subscription Billing Module (Stripe/PayPal ⚠️ unverified, see Phase 2 notes)
│       │   ├── interface-pp-gateway.php
│       │   ├── class-pp-billing.php
│       │   ├── class-pp-billing-history.php
│       │   └── gateways/
│       │       ├── class-pp-gateway-offline.php     # ✅ verified — everything else is checked against this one
│       │       ├── class-pp-gateway-stripe.php       # ⚠️ code-complete, unverified (no test API key available)
│       │       ├── class-pp-gateway-paypal.php        # ⚠️ code-complete, unverified (no sandbox creds available)
│       │       └── class-pp-gateway-woo-subscriptions.php   # detection-only stub, WooCommerce not installed here
│       ├── access-control/              # ✅ QR Scan / PIN entry / restrictions
│       │   ├── class-pp-access-control.php
│       │   ├── class-pp-qr-scanner.php
│       │   ├── class-pp-pin-entry.php
│       │   └── class-pp-entry-restrictions.php
│       ├── facility/                    # ✅ Facility Management (capacity only — time slots/staff assignment are Phase 2/Booking)
│       │   ├── class-pp-facility.php
│       │   └── class-pp-facility-cpt.php            # pp_facility CPT
│       ├── booking/                     # ✅ Booking Module (courts, lanes, trainers, rooms...)
│       │   ├── class-pp-booking.php                 # CRUD — pp_bookings is a table, not a CPT (deviation, see Phase 2 notes)
│       │   ├── class-pp-booking-calendar.php
│       │   ├── class-pp-booking-slots.php
│       │   ├── class-pp-booking-waitlist.php
│       │   └── class-pp-booking-frontend.php         # shortcode + AJAX handlers (not in original tree)
│       ├── visitor/                     # ✅ Visitor Pass Module — every visitor is a real pp_memberships row, see Phase 3 notes
│       │   ├── class-pp-visitor.php                 # register/invite/finalize/history, no separate table or CPT
│       │   └── class-pp-visitor-frontend.php         # "Invite a Guest" AJAX (not in original tree)
│       ├── attendance/                  # ✅ Attendance Module — read-only queries, no data entry, no Early Exit (see Phase 3 notes)
│       │   └── class-pp-attendance.php
│       ├── class-session/               # ✅ Class & Session Module (Yoga, Zumba, coaching...)
│       │   ├── class-pp-class-session-cpt.php        # pp_class_session CPT
│       │   ├── class-pp-class-session.php            # occurrence generation
│       │   └── class-pp-class-frontend.php           # shortcode + book/waitlist AJAX (no separate Instructor class — see Phase 3 notes)
│       ├── shop/                        # ✅ Real WooCommerce bridge (product-per-plan sync, order-completion issuance/renewal) — see Phase 4 & 5 notes
│       │   └── class-pp-shop-woocommerce.php
│       ├── marketing/                   # ✅ Coupons only — Loyalty/Gift Cards/Referral/Campaigns NOT built (explicit scope decision, see Phase 4 & 5 notes)
│       │   ├── class-pp-coupon-cpt.php              # pp_coupon CPT + meta box (title = code, force-uppercased on save)
│       │   └── class-pp-coupon.php                  # validation engine — custom CPT-based, NOT WC_Coupon — see [[project_booking_coupon_engine]]
│       ├── reports/                     # ✅ Reports Module — 8 read-only report types (Peak Hours reuses Attendance's, not duplicated)
│       │   └── class-pp-reports.php
│       ├── notifications/               # ✅ Email-only dispatcher (explicit scope decision — no SMS/WhatsApp/Push) — see Phase 4 & 5 notes
│       │   └── class-pp-notifications.php
│       └── business-templates/          # ✅ All 26 business types have real, differentiated seed data (not just Gym)
│           ├── class-pp-business-templates.php
│           └── data/                    # gym.php + all 25 others — see Phase 4 & 5 notes
├── lib/
│   ├── classes/                         # Reserved/empty for Phase 1 — see "lib/classes/ is empty" note above
│   │   ├── class-menu-page.php
│   │   ├── class-meta-box.php
│   │   ├── class-form-fields-generator.php
│   │   ├── class-form-fields-wrapper.php
│   │   ├── class-icon-library.php
│   │   ├── class-taxonomy-edit.php
│   │   ├── class-required-plugins.php
│   │   └── class-qrcode-generator.php    # thin wrapper around a QR code library
│   └── appsero/                          # optional usage-analytics SDK, deferred until release
├── assets/
│   ├── admin/                            # ✅ passpress-admin.css/js — scan gate, memberships list, dashboard, setup wizard
│   ├── frontend/                         # ✅ passpress-frontend.css, passpress-my-pass.js
│   ├── blocks/                           # ✅ passpress-blocks.js — 4 dynamic blocks, hand-written vanilla JS + ServerSideRender (no build step)
│   ├── helper/                           # ✅ qrcode/qrcode.min.js — real davidshimjs/qrcodejs build fetched from jsDelivr
│   └── sass/
├── templates/
│   ├── layout/                           # ✅ plan-list.php (real Subscribe button)
│   ├── my-pass/                          # ✅ my-pass.php — QR, status, expiry, PIN, Renew Now, My Bookings, Invite a Guest
│   ├── checkout/                         # ✅ checkout.php — zero-config checkout page (not in original tree)
│   ├── booking/                          # ✅ booking-calendar.php — slot picker front template
│   ├── class-session/                    # ✅ class-schedule.php — server-rendered occurrence list (not in original tree)
│   ├── themes/                           # ⏳ optional full-page presentation themes
│   └── screenshot/
├── support/
│   └── elementor/
│       ├── elementor-support.php         # ✅ Detection-only stub — Elementor not installed here, see Phase 4 & 5 notes
│       └── widget/                       # ⏳ membership-plan-list, booking-calendar, my-pass, facility-list... (needs a real Elementor install to verify)
└── languages/                            # ⏳ no .pot generated yet
```

## Data model

**Custom Post Types** (one CPT class per module under `inc/modules/*/class-pp-*-cpt.php` — there's no
central `admin/PP_CPT.php`, see Phase 1 notes above):

- ✅ `pp_membership_plan` — plan/pass type definitions (Monthly, Yearly, Weekly, Daily Pass, One-time, Family, Student, VIP, Corporate, Lifetime), with meta for price, duration, entry restriction, time window, max entries/day
- ✅ `pp_facility` — Gym, Pool, Court, Ground, Library, Club House, etc., with meta for facility type + capacity + (Phase 2) booking settings (required?, slot duration/buffer, open hours/days, cancellation lead time, assigned staff)
- ✅ `pp_class_session` — Yoga, Zumba, coaching sessions, with meta for class type, instructor (a WP user id — no separate Instructor CPT/table), facility, capacity, one fixed weekly day+time
- ✅ `pp_coupon` — marketing (Phase 4). Title = the coupon code (force-uppercased on save); meta for
  active flag, discount type/amount, applicable plans, usage limits, expiry date. No `pp_gift_card` — Gift
  Cards weren't built, see Phase 4 & 5 notes.
- **Not a CPT** — `pp_booking` is a custom DB table instead (`{prefix}pp_bookings`), a deliberate Phase 2
  deviation from the original plan; see the table list below and the Phase 2 notes above for why.
- **Not a CPT, not a separate table** — visitor passes are real `pp_memberships` rows (`member_type = 'visitor'`)
  tied to a real (often auto-created) WP user; there is no `pp_visitor_pass` anything. This resolved a
  contradiction that existed in an earlier draft of this doc — see the Phase 3 notes above.

**Custom DB tables** (high-volume/relational, not postmeta — created via `dbDelta()` in `inc/PP_Install.php`,
called from `register_activation_hook`):

- ✅ `{prefix}pp_memberships` — the actual member↔plan record: `membership_number`, `pass_token` (QR payload),
  `pin_code`, `status` (active/frozen/suspended/expired/cancelled), `member_type` (`member`/`visitor` — Phase 3),
  `start_date`/`expiry_date`, `auto_renew`
- ✅ `{prefix}pp_access_logs` — every scan attempt: `membership_id`, `facility_id`, `direction` (entry/exit),
  `method` (qr/pin), `result` (allowed/denied), `reason`, `operator_id` — used unchanged for visitor passes too
- ✅ `{prefix}pp_activity_log` — plugin-wide event log (issued/renewed/status-changed/scan/billing/booking/visitor events), feeds the Activity Log admin screen
- ✅ `{prefix}pp_billing_history` — every checkout attempt: `membership_id`, `type` (initial/renewal), `gateway`,
  `gateway_ref`, `amount`/`currency`, `status` (pending/paid/failed/refunded/cancelled), `checkout_token`
  (correlates a pending attempt with its gateway return/webhook), `coupon_code`/`discount_amount` (Phase 4 —
  DB version 1.2.0; this is also the sole source of truth for coupon usage counting, no separate redemptions table)
- ✅ `{prefix}pp_bookings` — `facility_id`, `user_id`, `membership_id`, `class_session_id` (Phase 3 — 0 for
  plain facility bookings), `booking_date`, `start_time`/`end_time`, `status` (confirmed/cancelled/completed/no_show),
  `checked_in_at` (Phase 3 — set when a booking transitions to `completed`, the only attendance signal that exists)
- ✅ `{prefix}pp_booking_waitlist` — same shape plus `class_session_id`, `status` (waiting/notified/expired)
- No loyalty points table — Loyalty Points wasn't built, see Phase 4 & 5 notes (Marketing scope was narrowed
  to Coupons only).
- Daily/monthly/peak-hour attendance rollups derive from `pp_access_logs` at query time (`PP_Attendance`) —
  no dedicated attendance table, confirmed in Phase 3.
- All "most recent row" queries (`get_recent()`, `get_for_membership()`, etc.) sort by `id DESC`, not
  `created_at DESC` — see the Phase 2 "ORDER BY" bug note above before adding a new one that doesn't.

**User roles** — `pp_member`, `pp_staff`, `pp_trainer`, `pp_gate_operator` (front-desk scanner role with
minimal capabilities, just enough to scan/validate passes). Register these explicitly on activation; don't
assume `manage_options`/`administrator` cover staff workflows — the sibling `wpbookingly` plugin shipped
without a dedicated staff role initially and had to backfill `mpwpb_staff` later (see [[project_staff_my_service_appointment]]). Get the role matrix right from the start here.

## WooCommerce is optional, not required

Unlike mage-eventpress (hard WooCommerce dependency), PassPress's Billing module talks to Stripe/PayPal
directly and only *bridges* to WooCommerce when it's active — now in **two** places: the still-stubbed WC
Subscriptions billing bridge (`PP_Gateway_Woo_Subscriptions`, unverified — WC Subscriptions itself isn't
installed even though WooCommerce is) and the real, verified Shop bridge (`PP_Shop_WooCommerce` — see Phase 4
& 5 notes), which sells plans as WC products through the normal cart/checkout as an *additional* path, never
replacing native checkout. This follows the pattern already proven out in the sibling `wpbookingly` plugin —
see [[project_wc_optional_native_checkout]] for the native Stripe+PayPal checkout approach reused here.

Practical implication: `inc/PP_Dependencies.php` does soft capability checks (`class_exists`,
`is_plugin_active`) — now also `pp_is_elementor_active()` — and toggles UI/menu items accordingly; it never
blocks activation or forces an install popup the way `MPWEM_Woo_Installer.php` does.

## Marketing / coupon engine

Built as a custom `pp_coupon`-CPT engine (not `WC_Coupon`), mirroring the `mpwpb_coupon`-style engine from
`wpbookingly` — see [[project_booking_coupon_engine]]. `class-pp-coupon.php` is the validation engine,
`class-pp-coupon-cpt.php` is the CPT/meta-box registration; both work whether or not WooCommerce is present.
Loyalty Points, Gift Cards, and Referral Program from the original Marketing plan were **not built** — an
explicit, narrower scope decision (Coupons/Promo Codes only), not an oversight — see Phase 4 & 5 notes.

## Module → plan feature map

| Status | Plan module | Code location | Notes |
|---|---|---|---|
| ✅ | Core (Dashboard, Setup Wizard, Business Type, Activity Log, Settings) | `admin/`, `admin/settings/`, `inc/PP_Roles.php`, `inc/PP_Activity_Logger.php` | Import/Export and split Currency/Tax/Email/SMS/Roles settings screens deferred — see Phase 1 notes |
| ✅ | Membership & Pass | `inc/modules/membership/` | Freeze/Suspend/Reactivate/Cancel are status transitions on `pp_memberships`, not separate CPTs. Issuance is an admin action, not checkout, until Billing exists |
| ✅ / ⚠️ | Subscription Billing | `inc/modules/billing/` | Offline gateway + checkout verified; Stripe/PayPal code-complete but unverified (no sandbox creds); WC Subscriptions is a detection-only stub |
| ✅ | Access Control | `inc/modules/access-control/` | QR scan + PIN share one validation entrypoint (`PP_Access_Control::validate_and_log()`); restrictions are evaluated at scan time. Verified: one-per-day, weekday/weekend, time-window, max-entries-per-day |
| ✅ | Facility Management | `inc/modules/facility/` | Type + capacity + (Phase 2) booking settings: required?, slots, hours/days, cancellation lead time, staff |
| ✅ | Booking | `inc/modules/booking/` | `pp_bookings`/`pp_booking_waitlist` tables, not CPTs (deviation). Verified: capacity enforcement, waitlist join+promotion, member-vs-admin cancellation lead-time rule |
| ✅ | Visitor Pass | `inc/modules/visitor/` | Every visitor pass is a real `pp_memberships` row (`member_type='visitor'`) tied to a real WP user — Scan Gate/Access Control needed zero changes, verified |
| ✅ | Attendance | `inc/modules/attendance/` | Derived/aggregated from `pp_access_logs` + `pp_bookings`, not independently entered. Early Exit not tracked (no reliable signal) |
| ✅ | Class & Session | `inc/modules/class-session/` | Reuses Booking's tables (added `class_session_id`) rather than duplicating capacity/waitlist logic. Verified: capacity, waitlist, late-arrival detection |
| ✅ | Product & Shop | `inc/modules/shop/` | Real WooCommerce bridge, only active if WooCommerce present (it is, on this site). Product-per-plan sync + order-completion issue/renew, verified end-to-end |
| ✅ (Coupons only) | Marketing | `inc/modules/marketing/` | Coupons/Promo Codes built and verified; Loyalty Points/Gift Cards/Referral Program explicitly out of scope, see Phase 4 & 5 notes |
| ✅ | Reports | `inc/modules/reports/` | Read-only queries over the custom tables above, 8 report types |
| ✅ (email only) | Notifications | `inc/modules/notifications/` | One dispatcher; Welcome/Payment-Failed/Booking-Reminder/Birthday triggers verified, plus 3 pre-existing emails refactored through it. SMS/WhatsApp/Push explicitly out of scope |
| ✅ (via shortcode + block) | Mobile QR ("My Pass") | `inc/PP_Shortcodes.php`, `inc/PP_Blocks.php`, `templates/my-pass/` | `[passpress_my_pass]` and the `passpress/my-pass` Gutenberg block both cover this; no separate `inc/modules/mobile-qr/` class was ever needed |
| ✅ (all 26) | Business Templates | `inc/modules/business-templates/` | Setup Wizard step; every business type from the plan now has real, differentiated seed data |
| ✅ | Gutenberg Blocks | `inc/PP_Blocks.php`, `assets/blocks/` | 4 dynamic blocks with ServerSideRender previews, verified via the real block-renderer REST endpoint |
| ⏳ (stub only) | Elementor | `support/elementor/` | Detection-only stub — Elementor isn't installed on this site, unverifiable; see Phase 4 & 5 notes |

## Frontend surfaces

- Shortcodes (`inc/PP_Shortcodes.php`, `PP_Booking_Frontend`, `PP_Class_Frontend`) + Gutenberg blocks
  (`inc/PP_Blocks.php`, `assets/blocks/`) for: membership plan list, booking calendar, class schedule, "My
  Pass" — both surfaces call the *same* canonical render function (the block's `render_callback` literally
  calls `do_shortcode()`), so there's no triplicated markup. Elementor widgets (`support/elementor/widget/`)
  are the one surface still not built — see the Elementor stub notes above.
- WooCommerce, when active, adds a *second* purchase path via `PP_Shop_WooCommerce` (see Phase 4 & 5 notes) —
  not a WooCommerce My Account tab/staff dashboard mirroring `wpbookingly`'s pattern
  ([[project_staff_my_service_appointment]]); that's a reasonable future enhancement, not built here.
- `templates/my-pass/` is the member-facing page: QR code, membership status, expiry date, PIN, Renew Now, My
  Bookings, birthdate (Phase 4), Invite a Guest.

## Business Templates (one-click setup)

**All 26 built** — every business type from the original plan now has a real, differentiated data file: Gym,
Fitness Center, Health Club, Swimming Pool, Sports Club, Football Academy, Cricket Academy, Tennis Club,
Badminton Club, Basketball Club, Golf Club, Community Club, Kids Play Zone, Theme Park, Water Park, Public
Park, Recreation Center, Library Membership, Museum Pass, Zoo Pass, Adventure Park, Ski Resort, Cycling Club,
Martial Arts Academy, Yoga Studio, Dance Academy.

Each template is a data file under `inc/modules/business-templates/data/{slug}.php` returning a plain array
(`label`, `plans`, `facilities`, `class_sessions`, `pages`) consumed by `class-pp-business-templates.php`. Not
every template has every key populated — see Phase 4 & 5 notes for which business types genuinely have no
`class_sessions` or no `facilities`, and why (real product judgment, not laziness). Modeled on
`admin/mep_dummy_import.php` from mage-eventpress rather than a bespoke importer.

Every template with `class_sessions` references each class's facility by `facility_name` (a string), which the
importer resolves to the facility post id created earlier in the *same* import pass via a
`$facility_ids_by_name` map built while creating facilities — not a positional/index assumption. `import()`
also calls `PP_Shop_WooCommerce::sync_product_for_plan()` explicitly after each plan's meta is set (Phase 4 &
5), since the generic `save_post_pp_membership_plan` hook fires once prematurely during `wp_insert_post()`,
before the price meta exists.

## Coding standards

- Follow WordPress coding standards: `$wpdb->prepare()` for all custom-table queries, nonces on every form/AJAX
  action, capability checks (`current_user_can`) gated on the roles above, escape on output
  (`esc_html`/`esc_attr`/`esc_url`), sanitize on input.
- Every module file is only `require`d when its module is enabled for the current Business Type — the module
  loader in `passpress.php` reads enabled-modules from the Core settings, mirroring how mage-eventpress
  conditionally loads WooCommerce-dependent files.
- New code uses WPCS file naming (`class-pp-*.php`); only the admin/inc "hub" files mirror mage-eventpress's
  flat `PP_Xxx.php` naming so the two plugins stay visually comparable side by side.

## Suggested build phases

Given the scope, don't attempt all modules at once:

1. ✅ **Core + Membership + Access Control (QR/PIN) + one Business Template (Gym)** — done, see
   [Phase 1 — what's actually built](#phase-1--whats-actually-built). Smallest end-to-end slice: create a
   plan, issue a pass, scan it at the door — verified working.
2. ✅ / ⚠️ **Subscription Billing + Facility booking/time-slots + the Booking module** — done, see
   [Phase 2 — what's actually built](#phase-2--whats-actually-built). Checkout + Offline gateway + Booking are
   verified working; Stripe/PayPal need a real test-mode run with actual credentials before trusting them;
   true recurring/auto-charge billing was deliberately scoped out (see Phase 2 notes) and would be its own
   future phase, not a Billing follow-up.
3. ✅ **Visitor Pass + Attendance + Class & Session** — done, see
   [Phase 3 — what's actually built](#phase-3--whats-actually-built). Also resolved a pre-existing
   contradiction in this doc about how visitor passes are stored (real memberships, not a CPT/table).
4. ✅ **Marketing (Coupons only) + Reports + Notifications (email only)** — done, see
   [Phase 4 & 5 — what's actually built](#phase-4--5--whats-actually-built). Loyalty Points/Gift
   Cards/Referral Program and SMS/WhatsApp/Push were explicit, deliberate scope reductions, not gaps.
5. ✅ / ⏳ **Remaining Business Templates (all 26) + Gutenberg blocks + a real WooCommerce Shop bridge** —
   done, see [Phase 4 & 5 — what's actually built](#phase-4--5--whats-actually-built). Elementor remains a
   detection-only stub (genuinely not installed/testable here); Mobile QR polish was never a separate task —
   `[passpress_my_pass]` plus its Gutenberg block equivalent already cover that ground.
