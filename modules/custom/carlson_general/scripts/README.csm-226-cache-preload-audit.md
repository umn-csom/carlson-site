# CSM-226 Cache Preload Audit Script

## Purpose

`audit_cache_preload_candidates.php` supports CSM-226 Phase 2. It captures
warm-request `cachetags` lookup groups for representative anonymous pages and
creates a Markdown report with preload recommendations.

The script does not modify `$settings['cache_preload_tags']`. It only produces
evidence for that decision.

## Current Context

Two Phase 1 preload candidates are currently commented out because the
lookup-only Phase 2 audit has not proven that they reduce checksum queries:

```php
// $settings['cache_preload_tags'] = [
//   'views_data',
//   'config:core.extension',
// ];
```

Phase 2 checks whether warm-request lookup evidence supports re-enabling those
tags or adding any new tags. Cache-table presence and page-level cache-tag
headers are not enough by themselves.

## Mental Model

1. Drupal cache items carry cacheability metadata, including cache tags.
2. A rendered page is built from multiple cached items/components.
3. During a warm request, Drupal validates cached items by checking current
   checksum/invalidation values for their cache tags.
4. One `cachetags` SQL query validates one lookup group: the tag set needed by
   one cache validation operation.
5. Drupal stores loaded checksum values in request-local memory, so the same
   tag usually will not appear in multiple SQL lookup groups during one request.
6. A useful preload candidate is stable and appears in a later lookup group that
   request-start preload could avoid. The clearest single-tag case is a later
   lookup group containing only that candidate tag.
7. Preloading registers that tag so Drupal can load its checksum value during
   the first checksum validation. Later validation operations in the same
   request can then reuse that value without another lookup for that tag.

The decision rule is:

```text
warm lookup groups showing avoidable stable tags
+ representative-page verification
= candidate worth testing in cache_preload_tags
```

The script no longer uses `x-drupal-cache-tags` response headers for candidate
selection. Headers show final page-level cacheability metadata, but they do not
prove that a tag can remove a later checksum lookup during one request.

## Page Sample Sources

The default lookup sample uses:

- The homepage.
- First- and second-level anonymous-accessible links from active front-end menu
  blocks.
- One recent anonymous-accessible published node per content type.

This is the fallback strategy when traffic reports are unavailable. It focuses
on main navigation pages and representative templates instead of trying to
validate every anonymous URL.

The default seed mode is `auto`, which discovers enabled menu block plugins in
the default front-end theme, such as `menu_block:*` and
`system_menu_block:*`.

Before a menu link is added, the script switches Drupal's current user to an
anonymous session and applies Drupal's menu tree access manipulators. This
keeps the sample focused on links that anonymous visitors can access through
rendered navigation, not every enabled link in the menu table.

Menu links are added breadth-first so top-level section roots are sampled
before deeper child pages. Menus from earlier theme regions are sampled before
menus from later regions, so primary navigation is considered before footer
navigation when `--lookup-path-limit` is set. External URLs, `<nolink>` /
button-style menu items, admin paths, and dynamic paths are skipped.

You can pass an explicit comma-separated page set when analytics, access logs,
or manual investigation identify better representative pages. This is the
recommended mode when checking whether specific existing preload tags are
actually useful.

```bash
--lookup-paths=/,/graduate/resources/find-degree,/graduate/mba/full-time
```

## Options

Default options:

- `--base-url=https://carlsonschool.ddev.site`
- `--seed-menus=auto`
- `--cache-read-limit=250`
- `--lookup-paths=auto`
- `--lookup-menu-depth=2`
- `--lookup-content-samples-per-bundle=1`
- `--lookup-path-limit=0`
- `--lookup-warmups=1`
- `--lookup-mysql-user=root`
- `--lookup-mysql-pass=root`
- `--compare-tags=` (empty means skip comparison)

Useful adjustments:

- Use `--lookup-paths=/,/some-page` to audit a specific page list.
- Use `--lookup-menu-depth=1` to focus only on top-level menu pages.
- Use `--lookup-content-samples-per-bundle=0` to skip content-type examples.
- Use `--lookup-path-limit=60` to cap auto-discovered lookup pages.
- Use `--cache-read-limit=50` for a faster supporting cache-read measurement.
- Use `--compare-tags=auto` to rerun pages where candidates were found and
  compare baseline requests with requests that preload each candidate.
- Use `--compare-tags=config:user.role.anonymous` to verify one specific tag.
- Use `--skip-lookup-capture` only when you want cache-table evidence without
  the MariaDB general-log query capture.

## Run Locally

Run from the DDEV project root:

```bash
HOST_SITE="/Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu"
SCRIPT_DIR="docroot/sites/carlsonschool.umn.edu/modules/custom"
SCRIPT="$SCRIPT_DIR/carlson_general/scripts/audit_cache_preload_candidates.php"
OUT="$HOST_SITE/csm-226-cache-preload-audit.md"

ddev drush @carlsonschool.ddev scr "$SCRIPT" -- \
  --base-url=https://carlsonschool.ddev.site \
  --seed-menus=auto \
  --lookup-paths=auto \
  --lookup-menu-depth=2 \
  --lookup-content-samples-per-bundle=1 \
  --lookup-warmups=1 \
  --compare-tags=auto \
  > "$OUT"
```

Run `ddev drush @carlsonschool.ddev cr` first when you want a fresh cache
sample after local config or code changes.

The warm lookup capture uses the local MariaDB general log through DDEV's
default root database account. It temporarily enables `general_log`, requests
the selected page anonymously, reads only `SELECT ... FROM cachetags ... WHERE
tag IN (...)` lookup queries, and turns `general_log` off again. If the local
database user cannot toggle the general log, the report marks lookup capture as
unavailable.

When `--compare-tags` is used, the script stores a short-lived audit token in
Drupal state and sends that token in private request headers. The
`carlson_general.cache_preload_audit_subscriber` service uses the token to
register one extra preload tag only for that audit request, then the script
cleans the token up.

Lookup capture does not follow redirects. A redirect and its destination are
separate HTTP requests, and combining both would make one URL look like it has
more lookup groups than it really does. The auto content-type sample also skips
the configured front page node because `/` is already sampled directly.

## Test Disabled Phase 1 Tags

To check whether disabled Phase 1 candidates such as `views_data` or
`config:core.extension` are useful, run a focused local comparison:

1. Choose representative pages that are likely to exercise the tag. For Views
   tags, use Views-heavy pages, high-traffic landing pages, or pages that embed
   several Views blocks.
2. Confirm the local site preload list is disabled:

   ```php
   // $settings['cache_preload_tags'] = [
   //   'views_data',
   //   'config:core.extension',
   // ];
   ```

3. Rebuild cache:

   ```bash
   ddev drush @carlsonschool.ddev cr
   ```

4. Run the script with explicit paths:

   ```bash
   SITE_ROOT="/Users/marthinal/projects/umn-d9"
   HOST_SITE="$SITE_ROOT/docroot/sites/carlsonschool.umn.edu"
   SCRIPT_DIR="docroot/sites/carlsonschool.umn.edu/modules/custom"
   SCRIPT="$SCRIPT_DIR/carlson_general/scripts"
   SCRIPT="$SCRIPT/audit_cache_preload_candidates.php"
   OUT="$HOST_SITE/csm-226-cache-preload-audit-existing-tags.md"
   PATHS="/,/graduate,/news,/news/faculty,/graduate/mba/full-time"

   ddev drush @carlsonschool.ddev scr "$SCRIPT" -- \
     --base-url=https://carlsonschool.ddev.site \
     --lookup-paths="$PATHS" \
     --lookup-warmups=1 \
     > "$OUT"
   ```

5. Restore the original commented-out state if you changed it during testing.
6. Read `Candidate stable tags` and `Preload Comparison` in the report.

If `views_data` or `config:core.extension` appear there, rerun with
`--compare-tags=auto` or `--compare-tags=views_data`. If comparison lowers the
warm `cachetags` lookup count, the report promotes the tag to `Tags to add now`.
If it does not appear or comparison does not reduce lookups, the script did not
prove the tag is useful for the tested pages.

## Reading Findings

The `Final Preload Recommendation` section is the report's decision summary. It
lists tags to add only when request-level comparison lowered the warm
`cachetags` lookup count. Without `--compare-tags`, discovered tags remain
candidates only.

- The tag is stable and reusable.
- The tag appears as the only tag in a later warm lookup group, or otherwise has
  direct lookup evidence worth testing.

If the list is `none`, the audit did not produce enough evidence to change
`settings.site.php`.

The `Preload Comparison` section is the A/B verification:

- `Baseline lookups`: lookup count with current effective preload tags.
- `With preload`: lookup count when one candidate tag is preloaded for the
  request through the audit header.
- `Single-tag lookups before/after`: whether the avoidable single-tag lookup
  disappeared.
- `Query delta`: lookup reduction. Positive values support adding the tag.
- `Avg ms before/after`: average local wall-clock request time for the same
  warm requests.
- `Avg ms delta`: average local request-time change. Positive values mean the
  preloaded request was faster in the local run.

Timing is directional evidence only. Local DDEV request time is noisy and does
not prove production latency impact by itself. The strongest local signal is
still fewer warm `cachetags` lookup queries, with timing used as supporting
evidence.

The `Warm Request Lookup Groups` section is the main preload evidence:

- `Lookup groups`: number of `cachetags` validation queries captured.
- `Largest group`: number of tags in the largest validation query.
- `Candidate stable tags`: non-preloaded tags found in lookup groups that
  request-start preload could avoid.
- `Repeated preloaded tags`: tags that repeated but are already covered by
  core defaults or site settings.

If a page has only one lookup group, there is usually no new single tag to
preload for that page. Request-start preload can add tags to that first query,
but it cannot remove the first query itself.

The `Cache Table Evidence` and `Synthetic Cache-Read Measurement` sections are
supporting evidence. They inspect sampled cache entries and compare query
counts under preload scenarios. They do not replace the warm HTTP lookup-group
capture for page-level decisions.

With the site candidates disabled, the effective preload list is Drupal core's
default list only. A future enhancement could add isolated scenarios for
core-only, site-only, and core-plus-site preload lists before re-enabling a
site-specific preload tag.

Do not add broad or entity-specific tags only because they are common:

- `rendered`
- `http_response`
- `node_view`
- `block_view`
- `node:*`
- `paragraph:*`
- `media:*`
- `file:*`

Those tags often appear because the global page layout includes navigation,
footer blocks, media, and page-specific content. Preloading them can add
overhead without removing a cachetag lookup.
