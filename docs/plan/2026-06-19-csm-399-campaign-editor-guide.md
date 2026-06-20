Status: Active
Jira: CSM-399
Date: 2026-06-19

# Carlson School Campaign Blocks — Editor Guide

Client-facing instructions for building, placing, and testing Modal and Sticky Bar
campaigns. Derived from CSM-399 release prep, the CSM-227 Unbounce Alternative
epic, and the `CSM-227-unbounce-alternative` branch implementation.

## Overview

Drupal supports two campaign block types that replace Unbounce popups and sticky
bars:

| Block type | What it does |
|---|---|
| **Modal Campaign** | Full-screen overlay dialog with eyebrow, title, body, CTA, and optional decline button |
| **Sticky Bar Campaign** | Non-blocking banner fixed to the top or bottom of the page |

Both types use the same underlying system: you **create campaign content**, then
**place it** via Block layout. Placement controls **which pages** see the
campaign (URL visibility rules). It does **not** control where the banner
visually appears — modals always render at the bottom of the page markup; sticky
bars render at the top or bottom per the **Position** field.

A modal and a sticky bar **can appear on the same page**. Only **one sticky
bar** will show per page if multiple qualify.

## Step 1 — Create campaign content

**Admin path:** Content → Blocks → **Add content block**

### Modal Campaign

1. Choose block type **Modal Campaign**.
2. Fill in the fields:

| Field | What to enter |
|---|---|
| **Block description** | Internal label only (e.g. `Fall 2026 MBA Modal — Variant A`). Shown in the Blocks list. |
| **Campaign Key** | Required. Unique analytics/frequency key (e.g. `fall_2026_mba`). Use the **same key across A/B variants** so analytics groups them and visitors don't see conflicting variants. |
| **Variant Name** | Optional (e.g. `A` or `B`). Passed to analytics when sharing a Campaign Key. |
| **Eyebrow** | Optional small label above the title (e.g. `Support Our Mission`). |
| **Title** | Modal heading. Defaults to "Announcement" if blank. |
| **Campaign text** | Main body copy. Supports rich text. |
| **CTA Text / URL** | Primary button link. CTA text is required when a URL is set. Clicking the CTA counts as a **conversion**. |
| **Decline Button Text** | Optional (e.g. `Maybe Later`). If blank, visitors dismiss via the X button or by clicking the CTA only. |
| **Delay** | Seconds before the modal appears. Default: **0** (shows on page load). |
| **Repeat Delay** | Hours before the campaign can reappear after dismiss/decline. Default: **12**. Set to **0** to show on every page visit. Example: `729` ≈ 30 days. |
| **Do not show again after conversion** | Checked by default. Uncheck only if you want the campaign to reappear after someone clicks the CTA. |
| **Schedule (optional)** | Start/end date-time in **Central Time**. Leave blank for always active. |

3. Click **Save**.

### Sticky Bar Campaign

1. Choose block type **Sticky Bar Campaign**.
2. Fill in the fields:

| Field | What to enter |
|---|---|
| **Block description** | Internal label (e.g. `Spring 2026 Discount Sticky`). |
| **Campaign Key** | Required. Same rules as modal. |
| **Variant Name** | Optional. |
| **Campaign text** | Banner message. **Links are supported**; plain formatting only (no headings, etc.). Link clicks count as **conversions**. |
| **Position** | **Top** or **Bottom** (default: bottom). Top banners appear above the site header. |
| **Color scheme** | **Maroon background** (default) or **Ivory background**. |
| **Delay** | Seconds before the banner appears. Default: **5**. Set to **0** for immediate display. |
| **Repeat Delay** | Same hours-based behavior as modal. Default: **12**. |
| **Do not show again after conversion** | Checked by default. Uncheck to allow re-display after a link click. |
| **Schedule (optional)** | Same Central Time rules as modal. |

3. Click **Save**.

> **Scheduling note:** Scheduled campaigns are embedded in page HTML and hidden
> from visitors when inactive, but may still be visible to bots/crawlers. Do not
> use scheduling for content that must stay secret before launch.

## Step 2 — Place the campaign on pages

**Admin path:** Structure → Block layout → choose your theme → **Place block**

1. Click **Place block** in any region (region choice does not affect visual
   placement for campaigns).
2. Under **Custom block library**, select your Modal or Sticky Bar campaign.
3. Configure **Visibility**:
   - **Pages:** Enter URL paths where the campaign should appear.
     - One path per line (e.g. `/graduate/masters/mba`).
     - Use `<front>` for the homepage.
     - Use wildcards as supported by Drupal (e.g. `/graduate/*`).
   - Add other visibility conditions as needed (content type, role, etc.).
4. Set **Region** and **Weight** if multiple campaigns could qualify on the same
   page — lower weight wins when tied.
5. Save the block placement.

**To disable a campaign without deleting it:** Block layout → find the placed
block → **Disable**, or remove it from layout.

**To edit campaign copy or timing:** Content → Blocks → find the campaign →
**Edit**. You do not need to re-place it.

## Step 3 — Test your campaign

### Quick test settings

While testing, temporarily set on the campaign block:

- **Repeat Delay** → `0` (re-shows on every page load after dismiss)
- **Do not show again after conversion** → unchecked (if testing the CTA
  repeatedly)
- **Delay** → `0` for modal; `0` or `5` for sticky depending on what you're
  verifying

Remember to restore production values before publishing broadly.

### Browser testing checklist

**Modal**

- [ ] Modal appears after the configured delay
- [ ] X button closes the modal
- [ ] Clicking outside the panel (backdrop) closes it
- [ ] `Esc` closes it
- [ ] CTA opens the correct URL
- [ ] Decline button appears only when text is entered
- [ ] After dismiss, modal respects Repeat Delay on reload
- [ ] After CTA click with "Do not show again after conversion" checked, modal
      does not return

**Sticky bar**

- [ ] Banner appears after the configured delay (default 5 seconds)
- [ ] Top vs. bottom position is correct
- [ ] Color scheme looks right (maroon vs. ivory)
- [ ] Close (X) dismisses the banner
- [ ] Links in the text work and dismiss/convert per your settings
- [ ] Only one sticky bar shows if multiple are placed on the same page

### Resetting your own test state

Campaign frequency is stored in the browser's **localStorage**. To see a
campaign again during testing:

1. Open an **Incognito/Private** window, **or**
2. Clear site data for `carlsonschool.umn.edu`, **or**
3. In DevTools → Application → Local Storage, delete keys matching:
   - `csm_campaign_modal__{your_campaign_key}`
   - `csm_campaign_sticky__{your_campaign_key}`

### Accessibility checks

Before sign-off, run your usual tools (Siteimprove, PopeTech, keyboard
navigation):

- Tab through modal controls; focus should be trapped inside the open modal
- Close button and links must have visible focus states
- Sticky bar close button must be keyboard-accessible

## Analytics (for reference)

Interactions push Google Tag Manager events automatically:

| User action | GTM event |
|---|---|
| Campaign shown | `campaign_view` |
| CTA / link click | `campaign_convert` |
| Close, backdrop, Esc | `campaign_dismiss` |
| Decline button | `campaign_decline` |

Events include `campaign_key`, `campaign_id`, `campaign_variant_name`, and page
path. No editor action is required beyond setting **Campaign Key** and
**Variant Name** correctly.

## Common gotchas

1. **Placement region is not visual placement.** Campaigns render in fixed page
   slots regardless of which block region you chose.
2. **One sticky bar per page.** If two sticky campaigns qualify, the system picks
   one deterministically (active schedule → region order → weight → block ID).
3. **Repeat Delay is in hours**, despite legacy internal naming. Default is 12
   hours, not 12 days.
4. **Campaign Key must be unique per logical campaign**, but shared across
   variants of the same campaign.
5. **Empty sticky text = no banner.** The sticky bar won't render if campaign
   text is blank.
6. **Modal requires a title or it defaults to "Announcement".**

## Example workflow

> **Goal:** Modal on the MBA tuition page for two weeks starting June 1.

1. Content → Blocks → Add **Modal Campaign**
2. Block description: `MBA Tuition Modal — June 2026`
3. Campaign Key: `mba_tuition_june_2026`
4. Title, body, CTA as needed
5. Delay: `3` seconds | Repeat Delay: `12` hours | Stop after conversion: checked
6. Schedule: start `2026-06-01`, end `2026-06-15` (Central Time)
7. Save
8. Structure → Block layout → Place block → select the modal
9. Visibility → Pages: `/graduate/masters/mba/tuition-aid`
10. Save, then test in an incognito window
