=== PassPress – Membership, Subscription & Pass Management ===
Contributors: passpress
Tags: membership, gym management, access control, qr code, pass management
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Modular membership, subscription and pass management for gyms, parks, clubs and sports facilities.

== Description ==

PassPress issues membership/pass records tied to a plan, validates them at the door via QR scan or PIN
entry, and enforces per-plan entry restrictions (one entry per day, weekday/weekend only, time windows,
daily limits). Members can pay online, book facility time slots, book recurring classes, and invite guests.
This is Phases 1-5 of a larger modular product plan — see `CLAUDE.md` in the plugin directory for the full
architecture and roadmap.

**Included in this build:**

* Core: Dashboard, Setup Wizard, Settings (currency, date format, QR size, PIN visibility), Activity Log
* Membership & Pass: unlimited plan types, membership numbers, manual renewal, freeze/suspend/reactivate/cancel
* Access Control: QR scan (keyboard-wedge scanner friendly) and PIN entry, with one-per-day / weekday /
  weekend / time-window / max-entries-per-day restrictions — works identically for visitor passes
* Facility Management: facility records with type, capacity, and booking settings
* Subscription Billing: zero-config native checkout, Offline/Manual gateway (verified), Stripe & PayPal
  gateways (implemented, **not yet verified against a real account** — see CLAUDE.md), Billing History,
  renewal reminder emails, Coupon/Promo Code discounts at checkout
* Booking: per-facility time-slot calendar, capacity-aware booking, waitlist with promotion, member/admin
  cancellation rules
* Visitor Pass: front-desk walk-in registration, member-submitted guest invitations, visitor pass history —
  every visitor pass is a real membership, so it scans at the door exactly like a member's
* Attendance: daily/weekly/monthly totals, peak-hour breakdown, late class-arrival tracking
* Class & Session: recurring weekly classes (Yoga, Zumba, etc.) with an instructor, capacity-aware booking,
  and waitlist, independent of facility time-slot capacity
* Reports: revenue, membership growth, renewal rate, expired members, facility usage, popular plans,
  payment reports, trainer performance — all read-only queries over existing data
* Marketing: Coupon/Promo Code engine (percent/fixed discounts, plan restrictions, usage limits)
* Notifications: centralized email dispatcher — Welcome, Payment Failed, Booking Reminder, and Birthday
  Greeting triggers, on top of the existing Renewal Reminder and Waitlist-spot-opened emails
* Business Templates: all 26 business types from the product plan (Gym, Fitness Center, Health Club,
  Swimming Pool, Sports Club, Football/Cricket Academy, Tennis/Badminton/Basketball Club, Golf Club,
  Community Club, Kids Play Zone, Theme/Water Park, Public Park, Recreation Center, Library Membership,
  Museum Pass, Zoo Pass, Adventure Park, Ski Resort, Cycling Club, Martial Arts Academy, Yoga Studio, Dance
  Academy) with real, differentiated seed data
* Shop: optional WooCommerce bridge (only active if WooCommerce is installed) — every membership plan gets
  an auto-synced hidden WC product, purchasable through the normal WC cart/checkout as a second path
  alongside native checkout; order completion issues or renews the membership
* Gutenberg blocks: Membership Plans, My Pass, Booking Calendar, and Class Schedule — dynamic blocks with a
  ServerSideRender editor preview, wrapping the same shortcodes used on the frontend
* Frontend: `[passpress_my_pass]`, `[passpress_membership_plans]`, `[passpress_booking_calendar]`,
  `[passpress_class_schedule]` shortcodes (and matching Gutenberg blocks)

**Deferred** (see CLAUDE.md): true recurring/auto-charge billing, Elementor widgets (Elementor isn't
installed on the reference site, so there's nothing to verify a real integration against — a detection-only
stub is in place instead, same pattern as the WooCommerce Subscriptions bridge), "Early Exit" attendance
tracking, and SMS/WhatsApp/push notification channels (email-only for now).

== Installation ==

1. Upload the `passpress` folder to `/wp-content/plugins/`.
2. Activate the plugin. You'll be redirected to the Setup Wizard.
3. Import the Gym business template, or create your own Membership Plans, Facilities, and Class Sessions manually.
4. Configure a payment method under PassPress → Billing Settings (Offline needs no setup; Stripe/PayPal need
   API credentials from those providers).
5. Members can now subscribe from the Membership Plans page, or issue a membership manually from
   PassPress → Memberships. Register walk-in visitors from PassPress → Visitors. Check everyone in from
   PassPress → Scan Gate.

== Changelog ==

= 0.5.29 =
* UI/UX pass (plan §4): the Plans, Facilities, Class Sessions and Coupons screens now share
  one create/edit modal controller (assets/admin/passpress-modal-form.js) and one card-grid
  CSS block instead of four copies of each. No AJAX action, nonce, POST parameter or meta
  key changed, and no screen's computed styles changed; the per-screen class names are kept
  in every selector list. Real per-screen differences (monospace coupon titles, the class
  card gap, muted plan/coupon footers) are preserved as explicit overrides.
* UI/UX pass (plan §5): the plan create/edit form now shows name, type, price, duration and
  max entries/day up front, with entry restrictions, the time window and the "Most Popular"
  badge collapsed under "Advanced rules" (a native <details>, no JS). No field was removed —
  all 15 controls still post — and the section opens itself when you edit a plan that
  actually uses one of those rules. The "hour" duration unit sketched in the plan was NOT
  added: expiry_date is a DATE column and duration_units() has no hour, so that is a schema
  change rather than a form change.
* Setup is now a three-step popup instead of a dense full-page picker: "want a head start?"
  → pick your business type (searchable, filterable) → see exactly what will be created, then
  confirm. A fresh install lands on the Dashboard with that popup over it rather than on the
  wizard screen, so declining leaves you somewhere useful. The Setup screen keeps a single
  "Choose business type" button that opens the same popup — one implementation, not two.
  The import itself (PP_Setup_Wizard::handle_import, PP_Business_Templates::import) is
  unchanged; the success notice now reports what was actually created per template.
  Removed 55 now-dead CSS rules belonging to the old picker markup.
* Fix: the setup popup's steps all rendered at once instead of one at a time. Cause: a normal
  (non-!important) author CSS rule always outranks the browser's built-in `[hidden] { display:
  none }` rule regardless of selector specificity, and .pp-setup-step's unconditional
  `display: grid` was exactly that rule — the JS was correctly toggling the `hidden` attribute,
  the CSS was just winning the cascade. Fixed with explicit `[hidden] { display: none; }`
  overrides. Swept for the same pattern and found three more real instances, now also fixed:
  filtered-out template tiles in step 2 (.pp-setup-type), a hidden 0-count row in step 3
  (.pp-setup-counts li), and the Plans/Facilities/Class Sessions/Coupons modals' "Live on
  site" status box (.pp-checkbox-box) — that last one pre-dates this UI pass entirely. Modals
  that already toggled an inline style (jQuery's .show()/.hide(), or an explicit
  .css('display', ...) alongside .prop('hidden', ...)) were confirmed unaffected.

= 0.5.0 =
* Phase 4 + 5: Reports module, Coupon/Promo Code marketing engine, centralized Notifications dispatcher
  (Welcome/Payment-Failed/Booking-Reminder/Birthday triggers, birthdate field on My Pass), all 26 Business
  Templates with real seed data, 4 Gutenberg blocks with ServerSideRender previews, and a real WooCommerce
  Shop bridge (product-per-plan sync, order-completion issuance/renewal). Elementor remains a detection-only
  stub — not installed on the reference site, so unverifiable.

= 0.3.0 =
* Phase 3: Visitor Pass (real membership-backed guest passes), Attendance reporting, Class & Session module
  (recurring classes, capacity-aware booking/waitlist independent of facility slots, late-arrival tracking).

= 0.2.0 =
* Phase 2: Subscription Billing (native checkout, Offline/Stripe/PayPal gateways), Booking module
  (time-slot calendar, waitlist, cancellation rules), renewal reminder emails.

= 0.1.0 =
* Initial Phase 1 build: Core, Membership & Pass, Access Control, Facilities, Gym business template.
