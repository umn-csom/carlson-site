<?php

/**
 * @file
 * CSM-226: Audit cache-tag preload candidates.
 *
 * The script warms representative local pages, captures warm-request
 * cachetags lookup groups for selected paths, and reports whether a stable tag
 * appears in a later lookup group that preload could avoid.
 *
 * Usage:
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/
 *   audit_cache_preload_candidates.php
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/
 *   audit_cache_preload_candidates.php
 *   -- --base-url=https://carlsonschool.ddev.site --seed-menus=auto
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/
 *   audit_cache_preload_candidates.php
 *   -- --lookup-paths=auto --lookup-menu-depth=2
 *
 * See README.csm-226-cache-preload-audit.md in this directory for the full
 * mental model, output format, and decision criteria.
 */

use Drupal\carlson_general\CachePreload\CachePreloadAuditCollector;
use Drupal\carlson_general\CachePreload\CachePreloadAuditReporter;

if (!class_exists('\Drupal') || !\Drupal::hasService('entity_type.manager')) {
  fwrite(STDERR, "Drupal is not bootstrapped.\n");
  return;
}

$collector = new CachePreloadAuditCollector();
$reporter = new CachePreloadAuditReporter();
$arguments = $argv ?? [];
if (isset($extra) && is_array($extra)) {
  $arguments = array_merge([$arguments[0] ?? __FILE__], $extra);
}
$report = $collector->collect($arguments);

print $reporter->render($report);
