# CSM-226 Cache Preload Phase 2

## Status

Phase 1 is already merged to `dev` and `master`. The current preload list is:

```php
$settings['cache_preload_tags'] = [
  'views_data',
  'config:core.extension',
];
```

Phase 2 logs cache tags across a wide local page sample, ranks the observed
tags by page frequency, and uses that data to determine whether the initial
list is enough or whether another tag has evidence strong enough to add to
`settings.site.php`.

## Static Evidence

The checked-in config has broad Views usage:

- 64 active View config files.
- 68 block configs using `views_block`.
- 37 block configs depending on menus.
- 11 block configs depending on `system.menu.main`.

That supports keeping `views_data` and `config:core.extension`, but it is not
enough by itself to add menu or block tags. Those need runtime/cache evidence.

## Audit Command

Run the Phase 2 audit in a local DDEV environment:

```bash
HOST_SITE="/Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu"
SCRIPT_DIR="docroot/sites/carlsonschool.umn.edu/modules/custom"
SCRIPT="$SCRIPT_DIR/carlson_general/scripts/audit_cache_preload_candidates.php"
OUT="$HOST_SITE/csm-226-cache-preload-audit.md"
CSV_CONTAINER="/var/www/html/docroot/sites/carlsonschool.umn.edu"
CSV_CONTAINER="$CSV_CONTAINER/csm-226-cache-tag-frequency.csv"

ddev drush @carlsonschool.ddev cr
ddev drush @carlsonschool.ddev scr "$SCRIPT" \
  -- --base-url=https://carlsonschool.ddev.site \
  --samples-per-bundle=10 \
  --path-limit=250 \
  --frequency-csv="$CSV_CONTAINER" \
  > "$OUT"
```

The Markdown report is redirected by the host shell. The CSV is written from
inside the DDEV container, so the `--frequency-csv` value must use the
container path under `/var/www/html/docroot`.

For a full local published-node inventory, use `--samples-per-bundle=0` and
`--path-limit=0`. That can be slow, but it is the closest local approximation
of a whole-site cache-tag frequency export.

If the HTTP sample reports zero `x-drupal-cache-tags` responses, enable local
cacheability debug headers before using the HTTP section for a decision:

```yaml
parameters:
  http.response.debug_cacheability_headers: true
```

The script still inspects local cache tables and runs a checksum-query
measurement without those headers, but the complete rendered-page frequency
table requires `x-drupal-cache-tags`.

## Decision Criteria

Keep the current tags when the audit shows Views metadata in representative
runtime/cache evidence:

- `views_data`
- `config:core.extension`

Add another tag only when both conditions are true:

- It appears across representative public requests or warmed cache entries.
- Adding it lowers the measured `cachetags` query count in the local checksum
  query comparison.

Do not add broad tags such as `rendered`, `http_response`, `node_view`, or
`block_view` only because they are frequent. Drupal core's preload guidance
warns that those tags are often grouped with request-specific entity IDs or
other page-specific tags, which means preloading them may add overhead without
eliminating a lookup.

`config:system.menu.main` is the strongest follow-up candidate from static
config, but it should stay out of `settings.site.php` unless the Phase 2 audit
shows a real query-count reduction.
