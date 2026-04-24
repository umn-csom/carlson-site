<?php

/**
 * @file
 * Compatibility launcher for the CSM-353 purge trace validation probe.
 *
 * The purge trace implementation lives in the carlson_purge_trace module.
 * This file only keeps the legacy Carlson scripts UI entry discoverable.
 */

use Drupal\carlson_purge_trace\Controller\PurgeTraceProbeController;
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

  $controller = \Drupal::classResolver(PurgeTraceProbeController::class);
  $build = $controller->run();

  $output = $build['#context']['output'] ?? NULL;
  if (!is_string($output)) {
    $output = (string) \Drupal::service('renderer')->renderPlain($build);
  }

  return implode(PHP_EOL, [
    'The purge trace probe now lives in the carlson_purge_trace module.',
    'Direct probe URL: ' . $probe_url,
    '',
    $output,
  ]);
}
catch (\Throwable $throwable) {
  return 'Purge trace probe failed: ' . $throwable->getMessage();
}
