Status: Draft
Jira: CSM-338
Date: 2026-02-19

# CSM-338 Plan: Modal Campaign Interaction Standardization

## Summary
Implement a modal-only standardization pass for interaction tracking and
accessibility behavior. This plan is limited to modal ID/class conventions,
GTM interaction classes, native `<dialog>` usage, ESC/backdrop handling,
synthetic/custom interaction events, and shared field terminology updates for
campaign-level fields.

## Scope and Branch
1. Branch: `codex/csm-338-modal-campaign`.
   1. `codex/` is a tooling convention for agent-created work branches.
2. In scope: `carlson_campaign` modal Twig/JS/CSS plus minimal PHP wiring
   required to pass modal unique ID into template/settings, and field
   terminology standardization for shared campaign fields.

## Requirements to Implement
1. Field terminology standardization:
   1. Use `field_campaign_*` for shared campaign fields.
   2. Keep `field_modal_*` only for modal-specific controls.
   3. Shared fields in this pass:
      1. `field_campaign_text`
      2. `field_campaign_dismiss_days`
      3. `field_campaign_session_key`
   4. Modal-only fields remain:
      1. `field_modal_title`
      2. `field_modal_eyebrow`
      3. `field_modal_cta_url`
      4. `field_modal_decline_text`
      5. `field_modal_show_decline`
2. Modal DOM ID format:
   1. `campaign-modal-[block_content_uuid]`
   2. Unique ID source is modal campaign `block_content` UUID (not placement
      config ID).
3. Common modal class: `.campaign-modal`.
4. GTM classes:
   1. `.campaign-banner-close` on close button (X) => action `dismissed`
   2. `.campaign-banner-backdrop` on backdrop dismiss target => action
      `dismissed`
   3. `.campaign-banner-acknowledge` on acknowledge actions => action
      `acknowledged`
   4. `.campaign-modal-decline` on decline action => action `declined`
5. Native `<dialog>` modal implementation.
6. ESC closes modal and is treated as `dismissed`.
7. Synthetic/custom event for interaction tracking, with detail payload:
   1. `event`
   2. `target` (DOM element)
   3. `text` (tap target text)
   4. `action` (`dismissed|acknowledged|declined`)
   5. `campaignId` (UUID)

## Implementation Details
1. Update modal campaign config:
   1. Add shared fields (`field_campaign_text`,
      `field_campaign_dismiss_days`, `field_campaign_session_key`) to
      `modal_campaign`.
   2. Keep modal-only fields (`field_modal_*`) for modal-specific UX.
   3. Update form display to show shared fields with campaign terminology.
2. Data handling:
   1. No content backfill/migration is required in this pass.
   2. Standardized fields can be created directly in config for new content.
3. Update `modules/custom/carlson_campaign/carlson_campaign.module` runtime
   field lookups:
   1. This step does not create fields in PHP; fields are created via config.
   2. Update hardcoded `->get('field_*')` lookups so runtime values map to the
      standardized machine names.
   3. Use `field_campaign_*` for shared behavior/content values passed to
      Twig/JS.
   4. Keep `field_modal_*` lookups for modal-only controls.
   5. Pass `campaignId` as modal `block_content` UUID to template/settings.
4. Update
   `modules/custom/carlson_campaign/templates/csm-modal-campaign.html.twig`:
   1. Use native `<dialog>`.
   2. Set `id="campaign-modal-[uuid]"`.
   3. Add class `.campaign-modal`.
   4. Add GTM classes:
      1. `.campaign-banner-close`
      2. `.campaign-banner-backdrop`
      3. `.campaign-banner-acknowledge`
      4. `.campaign-modal-decline`
5. Update
   `modules/custom/carlson_campaign/js/carlson-campaign.modal-campaign.js`:
   1. `<dialog>` open/close flow.
   2. ESC dismiss handling.
   3. Backdrop dismiss handling.
   4. Dispatch custom event `campaign:interaction` with detail:
      1. `event`
      2. `target`
      3. `text`
      4. `action`
      5. `campaignId` (UUID)
   5. Map actions:
      1. close button => `dismissed`
      2. backdrop => `dismissed`
      3. ESC => `dismissed`
      4. acknowledge targets => `acknowledged`
      5. decline target => `declined`
6. Update modal CSS only as needed for `<dialog>` behavior and GTM class hooks.

## Test Scenarios
1. Modal renders with id pattern `campaign-modal-[uuid]`.
2. ID uses `block_content` UUID and is unique per campaign.
3. Shared campaign fields exist and are populated:
   1. `field_campaign_text`
   2. `field_campaign_dismiss_days`
   3. `field_campaign_session_key`
4. Modal-only fields exist for modal-specific controls.
5. Close X and backdrop clicks fire `dismissed`.
6. ESC closes and fires `dismissed`.
7. Decline fires `declined`.
8. Acknowledge target fires `acknowledged`.
9. Custom event `campaign:interaction` includes `event`, `target`, `text`,
   `action`, and UUID `campaignId`.
10. `.campaign-modal` and GTM classes exist in rendered markup.
11. Modal root is rendered as native `<dialog>`.
12. GTM classes are present on the exact interactive targets:
    1. close button => `.campaign-banner-close`
    2. backdrop dismiss target => `.campaign-banner-backdrop`
    3. acknowledge action target(s) => `.campaign-banner-acknowledge`
    4. decline action target => `.campaign-modal-decline`
13. Shared campaign fields are consumed at runtime:
    1. `field_campaign_dismiss_days` controls dismiss behavior duration
    2. `field_campaign_session_key` controls campaign/session storage key
    3. `field_campaign_text` renders expected campaign body content
14. `campaign:interaction` dispatches once per user action and `campaignId`
    matches the UUID used in `campaign-modal-[uuid]`.
15. If acknowledge supports both links and buttons, both target types dispatch
    `acknowledged` and include `.campaign-banner-acknowledge`.

## Testing Approach
1. Add automated tests in
   `modules/custom/carlson_campaign/tests/src` for deterministic behavior:
   field lookups, rendered modal ID/class output, and event/action mappings.
2. Run automated tests locally in DDEV as part of implementation validation.
3. Execute manual browser QA for interaction behavior and tracking validation:
   `<dialog>` behavior, ESC/backdrop dismiss, and `campaign:interaction` detail
   payload verification.
