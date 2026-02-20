Status: Draft
Jira: CSM-339
Date: 2026-02-20

# CSM-339 Plan: Sticky Bar Campaign Block Type

## Summary
Implement a new `Sticky Bar Campaign` block type in `carlson_campaign`,
reusing shared campaign fields from CSM-338 and adding sticky-bar-specific
render/runtime behavior, tracking hooks, and delay/frequency controls.

## Scope and Branch
1. Branch from current CSM-338 branch into
   `codex/csm-339-sticky-bar-campaign`.
2. In scope:
   1. `modules/custom/carlson_campaign`
   2. `config/sync`
   3. `modules/custom/carlson_campaign/tests/src/Unit`
3. Out of scope:
   1. Scheduling start/end date-time logic
   2. Variant toggles/options from Unbounce

## Locked Decisions
1. Shared required fields apply to both Modal and Sticky now.
2. Delay machine name: `field_campaign_delay_seconds`.
3. Modal and Sticky can coexist on the same page.
4. Dismiss default is `1` day.
5. Scheduling deferred to future "Improve Configuration" ticket.

## Requirements to Implement
1. Create block type `sticky_bar_campaign` with label `Sticky Bar Campaign`.
2. Reuse shared fields:
   1. `field_campaign_text`
   2. `field_campaign_dismiss_days`
   3. `field_campaign_session_key`
3. Add sticky-specific delay field:
   1. label: `Delay`
   2. `field_campaign_delay_seconds` (integer, required)
   3. suffix: `seconds`
   4. default: `5`
   5. description: `Set to 0 to show at page load`
4. Shared frequency field semantics (`field_campaign_dismiss_days`):
   1. required on modal and sticky
   2. default `1`
   3. min `0`
   4. description:
      `Set to 0 to show on every visit; Set to 365 to show once per year, Recommendation: 1 (once per day to avoid annoyance)`
5. Shared session key field semantics (`field_campaign_session_key`):
   1. required on modal and sticky
   2. used by runtime tracking and storage key behavior
6. Sticky runtime behavior:
   1. load after configured delay (default 5 seconds)
   2. remain visible and sticky at page bottom until user dismisses
   3. subsequent visibility controlled by dismiss window/session key behavior

## Markup and Tracking Contract
1. Sticky bar unique ID format: `campaign-sticky-bar-[uuid]`.
2. Sticky bar base class: `.campaign-sticky-bar`.
3. GTM classes:
   1. close control: `.campaign-sticky-bar-close`
   2. links in body: `.campaign-sticky-bar-text a`
4. Event contract:
   1. close action => `dismissed`, text `Sticky Bar Close`
   2. link click action => `acknowledged`, text from clicked link
   3. include `target` dom element and `campaignId`
   4. dispatch synthetic `campaign:interaction` event
5. Any link in text counts as acknowledgement.
6. More targeted reporting via editor-added classes is out of scope.

## Accessibility and Semantics
1. Use `<aside>` for sticky campaign container.
2. Provide accessible close button label.
3. Keep keyboard focus/focus-visible states for close and links.
4. Include inline SVG close icon.
5. Avoid modal-style focus trapping; sticky remains non-blocking content.

## Content Safety and Rendering
1. Campaign text is rendered from shared long text field.
2. Strip all HTML except `<a>` tags before output.
3. Ensure safe link attribute/protocol handling.

## Multi-Banner and Selection Logic
1. Render only one eligible sticky bar per page.
2. Reuse modal candidate selection strategy:
   1. placed block visibility rules
   2. theme region ordering
   3. block weight
   4. deterministic tie-break by block id
3. Suppress additional sticky bars once first eligible is selected.
4. Modal and sticky can both render if both qualify.

## Implementation Details

### Config
1. Add:
   1. `block_content.type.sticky_bar_campaign.yml`
   2. `field.storage.block_content.field_campaign_delay_seconds.yml`
   3. sticky field instance configs for text/delay/dismiss/session
   4. `core.entity_form_display.block_content.sticky_bar_campaign.default.yml`
2. Update:
   1. `field.field.block_content.modal_campaign.field_campaign_dismiss_days.yml`
   2. `field.field.block_content.modal_campaign.field_campaign_session_key.yml`
   3. `core.entity_form_display.block_content.modal_campaign.default.yml`

### PHP (`carlson_campaign.module`)
1. Add theme hook for sticky template.
2. Add sticky builder invoked from `hook_page_bottom()`.
3. Hide placed sticky source blocks in `hook_preprocess_block()` (same pattern
   as modal).
4. Attach sticky library only when sticky render array exists.
5. Pass sticky settings through `drupalSettings.csmStickyBarCampaign`:
   1. `campaignId`
   2. `stickyId`
   3. `sessionKey`
   4. `dismissDays`
   5. `delaySeconds`

### Twig/CSS/JS
1. Add template:
   1. `templates/csm-sticky-bar-campaign.html.twig`
2. Add JS behavior:
   1. `js/carlson-campaign.sticky-bar-campaign.js`
3. Add CSS:
   1. `css/carlson-campaign.sticky-bar-campaign.css`
4. Update libraries:
   1. `carlson_campaign.libraries.yml` with sticky library definition

### Visual/Theming
1. Sticky to page bottom.
2. Brand colors from comps:
   1. maroon background
   2. white text
   3. gold links

## Tests and Validation
1. Add unit test for sticky markup and behavior hooks.
2. Add unit test for sticky field config and shared-field required/default
   semantics.
3. Keep existing modal tests green.
4. Manual QA:
   1. delay 5 seconds default behavior
   2. delay 0 shows at page load
   3. dismiss days 0 shows every visit
   4. close/link interactions dispatch expected actions and payload
   5. only one sticky shows when multiple are configured
   6. modal + sticky coexistence works as expected

## Rollout Notes
1. Config import required after deployment.
2. No remote CLI assumptions; follow ADR-0002 process.
3. No scheduling fields in this ticket; defer to future
   configuration-improvement work.
4. Caching should reuse current modal-style cache contexts/tags; no additional
   scheduling-related cache complexity is introduced in this ticket.

## Assumptions
1. CSM-338 branch is the baseline.
2. No data backfill/update hook required for existing modal content in this
   ticket.
3. Editors will provide valid campaign text links where needed.
