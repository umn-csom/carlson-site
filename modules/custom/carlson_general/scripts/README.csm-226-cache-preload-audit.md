# CSM-226 Cache Preload Audit Script

## Purpose

`audit_cache_preload_candidates.php` supports CSM-226 Phase 2. It gathers
cache-tag evidence from a broad local page sample so reviewers can see which
tags are commonly present across rendered pages.

The script does not automatically decide which tags belong in
`$settings['cache_preload_tags']`. It creates evidence for that decision.

## Current Context

Phase 1 is already merged to `dev` and `master`. The current preload list is:

```php
$settings['cache_preload_tags'] = [
  'views_data',
  'config:core.extension',
];
```

Phase 2 checks whether those initial tags are enough or whether another tag
has evidence strong enough to add to `settings.site.php`.

## Mental Model

1. Drupal render arrays carry cacheability metadata.
2. When local cacheability debug headers are enabled, Drupal exposes the final
   page cache tags in the `x-drupal-cache-tags` response header.
3. The script renders representative local pages through HTTP.
4. It reads each response's `x-drupal-cache-tags` header.
5. It deduplicates tags per page, then counts how many sampled pages include
   each tag.
6. It writes a frequency table so high-coverage tags can be reviewed.
7. It separately checks cache-table evidence and measures cachetag checksum
   queries for candidate preload scenarios.

High page frequency is only a signal. A tag should be added to the static
preload list only when it also reduces cachetag checksum queries in the local
measurement.

## Config Evidence

The script checks configuration evidence during each run. This is static
evidence because it comes from Drupal config entities, not from rendered HTTP
responses.

The check loads active Drupal config through the Entity API:

- Loads all View config entities and skips disabled Views.
- Counts active Views and display plugin types.
- Loads all Block config entities and skips disabled Blocks.
- Counts enabled Block placements whose plugin ID starts with `views_block:`.
- Counts enabled Block config dependencies that start with `views.view.`.
- Counts enabled Block config dependencies that start with `system.menu.`.

This data appears in the Markdown report's `Config Evidence` section. It
supports keeping `views_data` and `config:core.extension`, but it is not enough
by itself to add menu or block tags. Those need runtime/cache evidence.

A raw `config/sync` scan can show higher counts because it includes disabled
block config files. The audit report intentionally uses active config so the
summary reflects what Drupal can render locally.

## Page Sample Sources

The script builds the sample from these sources:

- Fixed important pages: `/`, `/news`, and `/academics`.
- Enabled Views page displays with concrete paths.
- Recent published nodes from each content bundle.

Views page paths containing dynamic placeholders such as `%` or `{...}` are
skipped because they cannot be requested safely without route parameters.

The default sample options are:

- `--samples-per-bundle=10`
- `--path-limit=250`
- `--cache-read-limit=250`

Use `--samples-per-bundle=0 --path-limit=0` for a full local published-node
inventory. That can be slow.

## Run Locally

Run from the DDEV project root:

```bash
HOST_SITE="/Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu"
SCRIPT_DIR="docroot/sites/carlsonschool.umn.edu/modules/custom"
SCRIPT="$SCRIPT_DIR/carlson_general/scripts/audit_cache_preload_candidates.php"
OUT="$HOST_SITE/csm-226-cache-preload-audit.md"
CSV_CONTAINER="/var/www/html/docroot/sites/carlsonschool.umn.edu"
CSV_CONTAINER="$CSV_CONTAINER/csm-226-cache-tag-frequency.csv"

ddev drush @carlsonschool.ddev scr "$SCRIPT" -- \
  --base-url=https://carlsonschool.ddev.site \
  --samples-per-bundle=10 \
  --path-limit=250 \
  --frequency-csv="$CSV_CONTAINER" \
  > "$OUT"
```

The Markdown report is redirected by the host shell. The CSV is written by PHP
inside the DDEV container, so use the container path under
`/var/www/html/docroot/sites/carlsonschool.umn.edu`.

Run `ddev drush @carlsonschool.ddev cr` first when you want a fresh cache
sample after local config or code changes.

## CSV Columns

- `cache_tag`: The cache tag observed in one or more rendered responses.
- `page_count`: Number of sampled pages whose `x-drupal-cache-tags` header
  included the tag.
- `sample_paths`: Up to 8 example paths where the tag appeared.

`page_count` is page coverage. It is not a count of repeated occurrences inside
one rendered page.

## Reading Findings

Good follow-up candidates are usually stable, low-cardinality config tags that
appear across representative pages and reduce checksum queries when tested.

Keep the current tags when the audit shows Views metadata in representative
runtime/cache evidence:

- `views_data`
- `config:core.extension`

Add another tag only when both conditions are true:

- It appears across representative public requests or warmed cache entries.
- Adding it lowers the measured `cachetags` query count in the local checksum
  query comparison.

Do not add broad or entity-specific tags only because they are frequent:

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

`config:system.menu.main` is the strongest follow-up candidate from static
config, but it should stay out of `settings.site.php` unless the audit shows a
real query-count reduction.

## Header Requirement

The rendered-page frequency table depends on local cacheability debug headers.
If the report shows zero responses with `x-drupal-cache-tags`, enable this in
local services configuration:

```yaml
parameters:
  http.response.debug_cacheability_headers: true
```

The cache-table evidence and checksum-query measurement can still run without
headers, but the CSV frequency table requires the header.
