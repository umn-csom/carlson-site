# CSM-226 Cache Preload Audit Script

## Purpose

`audit_cache_preload_candidates.php` supports CSM-226 Phase 2. It captures
warm-request `cachetags` lookup groups for representative anonymous pages and
creates a Markdown report with preload recommendations.

The script does not modify `$settings['cache_preload_tags']`. It only produces
evidence for that decision.

## Current Context

The current site setting includes two Phase 1 preload tags:

```php
$settings['cache_preload_tags'] = [
  'views_data',
  'config:core.extension',
];
```

Phase 2 checks whether warm-request lookup evidence supports the current
site-added tags or any new tags. Cache-table presence and page-level cache-tag
headers are not enough by themselves.

## Mental Model

1. Drupal cache items carry cacheability metadata, including cache tags.
2. A rendered page is built from multiple cached items/components.
3. During a warm request, Drupal validates cached items by checking current
   checksum/invalidation values for their cache tags.
4. One `cachetags` SQL query validates one lookup group: the tag set needed by
   one cache validation operation.
5. A useful preload tag is stable and appears across multiple lookup groups in
   the same warm request.
6. Preloading registers that tag so Drupal can load its checksum value into
   request-local memory during checksum validation. Later validation operations
   in the same request can then reuse that value without another lookup for
   that tag.

The decision rule is:

```text
warm lookup groups showing repeated stable tags
+ measured cachetags query reduction
= candidate worth adding to cache_preload_tags
```

The script no longer uses `x-drupal-cache-tags` response headers for candidate
selection. Headers show final page-level cacheability metadata, but they do not
prove that a tag was reused across multiple cache validation groups during one
request.

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

Useful adjustments:

- Use `--lookup-paths=/,/some-page` to audit a specific page list.
- Use `--lookup-menu-depth=1` to focus only on top-level menu pages.
- Use `--lookup-content-samples-per-bundle=0` to skip content-type examples.
- Use `--lookup-path-limit=60` to cap auto-discovered lookup pages.
- Use `--cache-read-limit=50` for a faster supporting cache-read measurement.
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
  > "$OUT"
```

Run `ddev drush @carlsonschool.ddev cr` first when you want a fresh cache
sample after local config or code changes.

The warm lookup capture uses the local MariaDB general log through DDEV's
default root database account. It temporarily enables `general_log`, requests
the selected page anonymously, reads only queries against `cachetags`, and
turns `general_log` off again. If the local database user cannot toggle the
general log, the report marks lookup capture as unavailable.

Lookup capture does not follow redirects. A redirect and its destination are
separate HTTP requests, and combining both would make one URL look like it has
more lookup groups than it really does. The auto content-type sample also skips
the configured front page node because `/` is already sampled directly.

## Test Existing Preload Tags

To check whether existing site-added tags such as `views_data` or
`config:core.extension` are useful, run a focused local comparison:

1. Choose representative pages that are likely to exercise the tag. For Views
   tags, use Views-heavy pages, high-traffic landing pages, or pages that embed
   several Views blocks.
2. Temporarily set the local site preload list to empty:

   ```php
   $settings['cache_preload_tags'] = [];
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

5. Restore the original preload list and rebuild cache.
6. Read `Repeated non-preloaded stable tags` in the report.

If `views_data` or `config:core.extension` appear there, they are candidates
for measurement. If they do not appear, the script did not prove they are useful
for the tested pages.

## Reading Findings

The `Final Preload Recommendation` section is the report's decision summary.
It lists tags to add only when both conditions are true:

- The tag repeats across warm lookup groups.
- Adding the tag lowers the measured `cachetags` query count.

If the list is `none`, the audit did not produce enough evidence to change
`settings.site.php`.

The `Warm Request Lookup Groups` section is the main preload evidence:

- `Lookup groups`: number of `cachetags` validation queries captured.
- `Largest group`: number of tags in the largest validation query.
- `Repeated non-preloaded stable tags`: tags that appeared in more than one
  lookup group and are not already in the effective preload list.
- `Repeated preloaded tags`: tags that repeated but are already covered by
  core defaults or site settings.

If a page has only one lookup group, there is usually no new single tag to
preload for that page. Adding one tag would not remove the lookup because the
same query still has to validate the other tags in that group.

The `Cache Table Evidence` and `Synthetic Cache-Read Measurement` sections are
supporting evidence. They inspect sampled cache entries and compare query
counts under preload scenarios. They do not replace the warm HTTP lookup-group
capture for page-level decisions.

The current measurement compares the effective preload list against no preload.
When testing existing site-added tags, use the temporary-disable workflow above
to see whether those tags become repeated non-preloaded stable tags. A future
enhancement could add isolated scenarios for core-only, site-only, and
core-plus-site preload lists.

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
