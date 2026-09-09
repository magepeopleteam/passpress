# PassPress — UI/UX Simplification Plan (for Claude Code)

> Scope of this task, per your decision: **keep every existing module** (booking, classes, coupons,
> visitors, business templates, WooCommerce bridge — all of it). This is a **presentation-layer and
> information-architecture pass only**, not a feature cut, not a rewrite, not a scope reduction.
> Nothing in the data model, payment logic, access-control logic, or module set changes because of this
> plan. Where a fix would touch backend logic, it's flagged and pushed to a separate pass.

Read alongside `PASSPRESS-ANALYSIS-BRIEF.md` — every file/class/table name below refers to what that
document already describes. Section numbers in `[§x]` refer to that brief.

---

## 0. Hard constraints (carried over from the brief's §13 — do not break any of these)

1. Never call `wp_localize_script()` inside a shortcode callback — enqueue+localize only in
   `PP_Frontend::maybe_enqueue_for_current_page()` on `wp_enqueue_scripts`.
2. Never sort "most recent" queries by `created_at DESC` — sort by `id DESC`.
3. Payment confirmation stays idempotent — `mark_paid()` before issuance, no exceptions.
4. WooCommerce stays fully optional — soft `class_exists`/`is_plugin_active` checks only.
5. One canonical render function per feature — blocks call `do_shortcode()`, no markup duplication.
6. Fail closed at the gate — every scan outcome writes an access-log row.
7. No build step, no framework — vanilla JS/CSS, `$wpdb->prepare()`, nonces, capability checks, escaping.
8. `day_of_week` is ISO-8601 1–7 in template data.

This plan is a pure UI refactor, so none of these should be at risk — but call it out explicitly in every
PR description so reviewers know the checklist was considered.

---

## 1. Goal

Make the **admin experience** and the **member experience** feel like a simple pass system first —
plan → pass → scan → renew — while every advanced module (booking, classes, coupons, visitors, business
templates) stays fully present and fully functional, just visually and navigationally de-emphasized so it
doesn't compete with the core loop.

Explicitly **not** in scope for this pass: gateways, DB schema, access-control logic, cron, notifications,
coupon engine, WooCommerce bridge internals, roles/capabilities, business templates content. The known
correctness bugs in the brief's §12.1–12.4 (guest-checkout silently skipped, dual order-status hook,
booking not checking membership status, unproven Stripe/PayPal) are **not** fixed here — flag them in
code comments where touched, but don't silently patch them inside a UI PR.

---

## 2. One open product decision before any code — resolve this first

Your basic idea says the admin should "see how many entries are left." Today the plan model only has
`_pp_max_entries_per_day` (a daily cap) — there's no concept of a total entry allotment for the life of a
pass (e.g. a "10-visit punch pass"). Two different things could be meant:

- **(a)** "Entries left today" — already derivable from the existing daily cap + today's access-log rows.
  Pure UI addition, zero schema change.
- **(b)** "Entries left on the whole pass" — a new optional field on the plan (e.g. `_pp_total_entries`)
  and a running counter on the membership. This is a small data-model addition, not just a UI change.

**Recommendation:** build (a) first since it needs no schema change and ships fast; add (b) as a small,
clearly-separated follow-up (one new optional meta field, one new counter query, no changes to anything
else in §8/§9 of the brief). Claude Code should implement (a) in this pass and stub (b) as a documented
TODO with the exact field name and query shape proposed, rather than guessing silently.

---

## 3. Admin navigation simplification

Current top-level menu has 14 items in a flat list `[§6]`. Restructure into two tiers without removing any
screen or route:

- **Primary (always visible):** Dashboard · Plans · Members · Scan Gate · Billing
- **Manage (grouped, one click away):** Facilities · Class Sessions · Coupons · Visitors · Bookings ·
  Reports · Attendance · Activity Log · Setup Wizard · Settings

"Members" is a rename of the existing Memberships screen label only — no slug/route change needed if you
want to avoid breaking bookmarks; keep the old slug, just change the visible menu text and icon.

Dashboard becomes 4 stat cards (active members, today's scans, this month's revenue, expiring within 7
days) plus a recent-scans feed — trim anything not tied to the core loop from the default view.

- **Touches:** `admin/PP_Admin.php` (menu registration), Dashboard template.
- **Size:** Small.
- **Verify:** every existing menu slug still resolves; capability checks (`CAP_MANAGE`, `CAP_SCAN`, per
  `[§5]`) still gate the same screens as before.

---

## 4. Unify the four card-grid screens into one shared component

This is the highest-leverage change and the direct fix for the brief's §12.8 observation (6,501-line CSS
file, four hand-rolled grids). Plans, Facilities, Class Sessions, and Coupons all use the same card-grid +
modal pattern today, implemented four separate times.

- Build one JS component (e.g. `PPCardGrid`) and one CSS block (namespaced `.pp-grid` / `.pp-card` /
  `.pp-modal`) driven by a per-screen config object: columns to show, fields in the create/edit modal,
  which AJAX actions to call.
- Each of the four screens becomes a small config file instead of a bespoke implementation.
- **Backend is untouched** — the existing AJAX handlers (`pp_create_plan`, `pp_get_plan`,
  `pp_update_plan`, and the Facilities/Class Sessions/Coupons equivalents) keep the same names, params,
  and nonces. This is purely a frontend consolidation, so it doesn't violate constraint #5 — it reduces
  duplicate markup, it doesn't add a second render path for anything.
- **Touches:** `assets/admin/passpress-admin.js`, `assets/admin/passpress-admin.css`, the four screen PHP
  templates.
- **Size:** Medium–Large.
- **Verify:** manual create/edit/delete pass on all four screens; confirm no change in AJAX request
  payloads (diff before/after in browser devtools network tab is enough, no backend tests needed since
  backend code isn't touched).

---

## 5. Simplify the Plan creation form

Match the form to your basic idea directly: the fields someone actually needs to create a plan, up front.

- **Visible by default:** name, price, duration value + unit (hour / day / week / month / year), optional
  max entries.
- **Collapsed under "Advanced rules" (closed by default):** weekday/weekend-only, time-restricted window,
  per-day entry cap, "most popular" flag, WooCommerce product linkage.
- No meta keys change, no fields are removed from the data model — only the form's default visual state.
- **Touches:** Plan modal template/config within the shared card-grid component from §4.
- **Size:** Small, once §4 exists.

---

## 6. Memberships list + My Pass page — surface "entries left"

- **Admin Memberships screen:** add an "Entries" column (used/remaining today, per §2(a)), a plain-language
  expiry ("expires in 4 days" instead of a raw date), and keep the status badge.
- **Member-facing My Pass page** `[§7]`: keep QR, status, expiry, PIN, Renew — add one simple line, e.g.
  "3 of 5 entries left today" or "Unlimited entries" for plans with no cap. Visually de-emphasize (move
  lower on the page, don't remove) the birthdate form and "Invite a Guest" block, since they're not part of
  the core loop you described but the modules stay live.
- **Touches:** `templates/my-pass/my-pass.php`, Memberships list screen, a small read query against
  `pp_access_logs` for today's count per membership (no schema change — this is §2(a)).
- **Size:** Small–Medium.

---

## 7. Scan Gate — clearer result, same logic

`PP_Access_Control::validate_and_log()` `[§9]` doesn't change. Only the result panel changes:

- Big color-coded Allow/Deny state, one-line reason (already returned today), and now "Entries left: N"
  read from the same query used in §6.
- **Touches:** Scan Gate screen JS/template only.
- **Size:** Small.

---

## 8. Checkout modal — fewer visible steps

The five issuance paths and the payment-mode switch `[§8]` stay exactly as-is, including the WooCommerce
option you're keeping. Only the **visible** modal changes:

- Step indicator: Plan → Details → Pay → Done.
- Show only the fields the selected gateway actually requires up front; don't ask for address unless a
  gateway needs it.
- **Touches:** `passpress-checkout-modal.js` and its markup only. No changes to `PP_Billing`,
  `PP_Shop_WooCommerce`, or any gateway class — this keeps constraint #3 (idempotent confirmation) and
  constraint #4 (WooCommerce optional) untouched by construction, since nothing on the confirmation path
  is edited.
- **Size:** Medium.

---

## 9. CSS/JS structure cleanup

Supports every section above rather than being separate work.

- Keep the "no build step" constraint (#7) — don't introduce Sass/webpack. Instead: split
  `passpress-admin.css` into one file per screen area, and only enqueue the CSS for the screen currently
  being viewed (`PP_Admin` already knows which screen it's rendering). This shrinks per-page payload and
  makes each file reviewable on its own.
- Same idea for `passpress-admin.js` where practical, though the new shared `PPCardGrid` from §4 will
  naturally absorb most of the duplicated JS.
- **Size:** Medium. Do this incrementally, screen-by-screen, as each screen above gets touched — don't do
  it as one giant separate PR.

---

## 10. Suggested build order

1. Resolve §2 (or proceed with the (a) default and log the (b) TODO clearly).
2. §4 shared card-grid component — unlocks Plans/Facilities/Class Sessions/Coupons at once.
3. §3 navigation restructuring.
4. §5 Plan form defaults.
5. §6 Memberships + My Pass entries-left.
6. §7 Scan Gate result panel.
7. §8 Checkout modal steps.
8. §9 CSS/JS split — folded into each step above as it's touched, not a separate pass.

---

## 11. Verification approach

No test suite exists in this codebase today, and this plan doesn't add one — verification is manual, as it
was for the original build. Per screen touched:

- Create / edit / delete a record through the new UI; confirm the AJAX payload is unchanged from before
  the refactor (network tab diff).
- Confirm capability checks still gate the screen the same way for `pp_gate_operator`, `pp_trainer`,
  `pp_staff`, and `administrator`.
- For §6/§7 (entries-left): scan a test membership up to its daily cap and confirm the count and the
  Allow→Deny transition both display correctly.
- For §8 (checkout): run one full purchase through Offline, and one through the WooCommerce path, to
  confirm the visible-step changes didn't touch the underlying five-path logic in `[§8.1]`.

Keep a short changelog note per screen redesigned so a future pass on the correctness issues (§12.1–12.4)
has a clean record of what was touched for UI reasons only.

---

## 12. Prompt starter for Claude Code

> "Implement the plan in `passpress-uiux-simplification-plan.md`, following the build order in its §10.
> Treat the constraints in its §0 as hard rules — do not violate any of them. This is a UI/UX and
> information-architecture pass only: every module, table, gateway, and capability in
> `PASSPRESS-ANALYSIS-BRIEF.md` stays exactly as functional as it is today. Do not fix the correctness
> issues listed in the brief's §12 as part of this work — flag them in a comment if you touch nearby code,
> but leave the logic itself alone. Start with §4 (the shared card-grid component) since it unlocks three
> other screens at once, and confirm with me before starting §8 (checkout) since it's the most
> recently-built and least-reviewed part of the system."
