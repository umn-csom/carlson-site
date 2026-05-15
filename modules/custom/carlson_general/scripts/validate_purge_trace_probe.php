<?php

/**
 * @file
 * Compatibility launcher for the CSM-353 purge trace validation probe.
 *
 * The purge trace implementation lives in the carlson_purge_trace module.
 * This file only keeps the legacy Carlson scripts UI entry discoverable.
 */

use Drupal\Core\Url;

if (!class_exists('\Drupal') || !\Drupal::hasService('module_handler')) {
  return 'Drupal services are not available.';
}

if (!\Drupal::moduleHandler()->moduleExists('carlson_purge_trace')) {
  return 'The carlson_purge_trace module is not enabled.';
}

try {
  $probe_url = Url::fromRoute(
    'carlson_purge_trace.probe',
    [],
    ['absolute' => TRUE],
  )->toString();

  return implode(PHP_EOL, [
    'The purge trace probe now lives in the carlson_purge_trace module.',
    'Open the direct probe URL and submit the confirmation form to run it.',
    'Direct probe URL: ' . $probe_url,
  ]);
}
catch (\Throwable $throwable) {
  return 'Purge trace probe failed: ' . $throwable->getMessage();
}
