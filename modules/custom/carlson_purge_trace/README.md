# Purge Trace

This document describes the temporary purge-trace capture added for
`CSM-353`.

The implementation lives in the standalone `carlson_purge_trace` module and
writes one summarized JSON record per traced request or command into
`private://purge-trace`.

## Purpose

The purge trace is meant to answer:

- what changed in Drupal
- which cache tags were invalidated
- whether broad tags such as `node_list:*` were involved
- what the likely purge blast radius was

It is a temporary diagnostic tool, not permanent observability
infrastructure.

## Where Files Are Written

- Base URI: `private://purge-trace`
- Local path: `private/sites/carlsonschool.umn.edu/purge-trace`
- File pattern: `YYYY-MM-DD/purge-trace-YYYY-MM-DD-HH.ndjson`

Each line in an `ndjson` file is one standalone JSON trace record.
The admin report at `/admin/reports/purge-trace` can download individual
hourly files or stream all current files into one combined NDJSON export.

## Rotation And Retention

- Files are grouped by UTC date and hour.
- New traces within the same hour append to the same file.
- Retention is age-based, not size-based.
- Cleanup runs at most once per hour when a new trace is written while
  capture is enabled.
- Directories older than the configured retention window are deleted.

The retention setting is stored in state:

- `carlson_purge_trace.retention_days`

Other runtime toggles are also stored in state:

- `carlson_purge_trace.enabled`
- `carlson_purge_trace.capture_callers`
- `carlson_purge_trace.estimate_cache_object_impact`

Default state:

- Purge trace capture: disabled
- Caller stack samples: disabled
- Cache object impact estimation: disabled
- Retention days: 7

When purge trace capture is disabled, no trace summaries are written, cleanup
does not run, and the caller-stack and cache-impact options are not used.

The validation probe at `/admin/reports/purge-trace/probe` respects these
settings. If capture is disabled, it still runs the probe save/restore but
reports that no purge trace file was expected.

## How Capture Works

The purge trace is built from three layers:

1. A runtime collector records request or command context.
2. A cache-tags observer records every `invalidateTags()` call.
3. An event subscriber flushes one summary record at terminate time.

The most important implication is that the trace sees invalidations before
they are processed by Purge queue processors.

## JSON Schema

### Top-Level Fields

- `trace_id`
  Unique id for the trace record.

- `recorded_at`
  UTC timestamp for when the summary was written.

- `duration_ms`
  Milliseconds between trace start and final flush.

- `execution_context`
  `http` or `console`.

- `command`
  Console command name when available, otherwise `null`.

- `request`
  Nested request metadata.

- `user`
  Nested user metadata.

- `contexts`
  High-level flags inferred during execution, such as `node_change`,
  `block_change`, `config_change`, `cron`, `batch`, or `feeds_route`.

- `causes`
  Best-effort list of entity or config mutations seen during the trace.

- `invalidation_call_count`
  Number of separate `invalidateTags()` calls observed.

- `estimated_queue_items`
  Count of unique invalidated tags in the trace. This is an estimate of
  downstream queue items, not a page count.

- `estimated_acquia_ban_batches`
  Estimated number of Acquia BAN batches, assuming 15 tags per batch.

- `blast_radius`
  Heuristic classification: `low`, `medium`, or `high`.

- `broad_tags`
  Subset of `unique_tags` considered risky because they imply wide
  invalidation scope.

- `cache_object_impact`
  Approximate current Drupal cache objects whose cache-tag metadata matches
  the broad tags in this trace.

- `tag_families`
  Counts grouped by tag family prefix, such as `node`, `node_list`, or
  `config`.

- `unique_tags`
  De-duplicated list of all invalidated tags seen in the trace.

- `invalidation_calls`
  Raw per-call invalidation records.

### `request`

- `route_name`
  Drupal route name, when available.

- `uri`
  Request URI, when available.

- `method`
  HTTP method, when available.

### `user`

- `uid`
  Current user id.

- `is_authenticated`
  Whether the current user is logged in.

### `causes`

Each item in `causes` is one of these shapes.

Entity cause:

- `type`
  Always `entity`.

- `operation`
  `insert`, `update`, or `delete`.

- `entity_type`
  Drupal entity type id, such as `node` or `block_content`.

- `bundle`
  Bundle name, such as `news` or `page`.

- `id`
  Entity id.

- `label`
  Best-effort entity label, when available.

Config cause:

- `type`
  Always `config`.

- `operation`
  `save` or `delete`.

- `name`
  Config object name.

### `invalidation_calls`

Each item in `invalidation_calls` includes:

- `offset_ms`
  Milliseconds since trace start when that invalidation call happened.

- `tag_count`
  Number of tags invalidated in that specific call.

- `tags`
  Exact tags passed to that specific invalidation call.

- `caller`
  Compact stack summary for the invalidation origin.

### `caller`

- `origin`
  Best-effort top caller frame after filtering out the trace service itself.

- `frames`
  Up to five filtered stack frames for debugging the invalidation path.

### `cache_object_impact`

- `enabled`
  Whether cache-object estimation was turned on for this trace.

- `inspected_tags`
  The broad tags used for cache-object matching.

- `sample_limit`
  Maximum number of sample cache IDs returned per cache bin.

- `available_bins`
  Cache bins that were available for inspection on the current environment.

- `union`
  Summary of current cache objects matching any inspected broad tag.

- `by_tag`
  Per-tag breakdown of current cache objects matching each inspected broad tag.

Each cache-bin summary under `union` or `by_tag` includes:

- `count`
  Number of current cache rows in that bin whose `tags` column contains the
  inspected tag or tags.

- `sample_cids`
  Up to five recent cache IDs from that bin for debugging. These are cache
  object IDs, not guaranteed page URLs or entity IDs.

## Broad Tag Heuristic

The trace currently treats tags like these as broad:

- `*_list`
- `*_list:*`
- `config:*`
- `entity_types`
- `local_task`
- `http_response`
- `rendered`
- `breakpoints`
- `theme_registry`
- `library_info`

These tags are not guaranteed to be dangerous in every case, but they are
used as the main signal for likely wide invalidation scope.

## Important Caveats

- `estimated_queue_items` is a tag count, not an exact Purge queue count.
- `estimated_acquia_ban_batches` is a rough estimate.
- `blast_radius` is heuristic, not a measured Varnish page-eviction total.
- `cache_object_impact` is a current Drupal cache-object snapshot, not a
  guaranteed future Varnish page-eviction count.
- `cache_object_impact` is disabled by default because it adds extra database
  queries for traces with broad tags.
- Local `drush php:eval` testing can produce less useful request metadata,
  such as `uid: 0` or missing route information, while still capturing the
  real invalidated tags.
