<?php

/**
 * @file
 * CSM-226: Audit cache-tag preload candidates.
 *
 * The script renders representative local pages, reads Drupal's
 * x-drupal-cache-tags response header, and ranks observed tags by the number
 * of sampled pages that include each tag.
 *
 * Usage:
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/
 *   audit_cache_preload_candidates.php
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/
 *   audit_cache_preload_candidates.php
 *   -- --base-url=https://carlsonschool.ddev.site --seed-menus=main
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

if (!empty($report['options']['frequency-csv'])) {
  $reporter->writeFrequencyCsv($report, $report['options']['frequency-csv']);
}

print $reporter->render($report);
