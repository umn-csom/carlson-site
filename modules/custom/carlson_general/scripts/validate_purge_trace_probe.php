<?php

/**
 * @file
 * CSM-353: Trigger and inspect a purge-trace probe from the UI.
 *
 * Usage:
 * - Execute through /admin/reports/carlson-scripts
 * - Optionally run through Drush script execution
 */

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name) ?: 'Unavailable';

if (
  !class_exists('\Drupal') ||
  !\Drupal::hasService('state') ||
  !\Drupal::hasService('entity_type.manager') ||
  !\Drupal::hasService('cache_tags.invalidator') ||
  !\Drupal::hasService('carlson_general.purge_trace.runtime') ||
  !\Drupal::hasService('carlson_general.purge_trace.writer')
) {
  $error = 'Required Drupal services are not available.';
  script_log($error, 'error');
  return $error;
}

$state = \Drupal::state();
$runtime = \Drupal::service('carlson_general.purge_trace.runtime');
$writer = \Drupal::service('carlson_general.purge_trace.writer');

$status = $writer->getStatus();
if (empty($status['private_available'])) {
  $error = 'private:// is not available for purge trace capture.';
  script_log($error, 'error');
  return $error;
}

$previous_enabled = (bool) $state->get(
  \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ENABLED,
  FALSE,
);
$previous_capture_callers = (bool) $state->get(
  \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
  TRUE,
);
$previous_estimate_cache_object_impact = (bool) $state->get(
  \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
  FALSE,
);

$summary = NULL;
$trace_file = NULL;
$download_url = NULL;
$persisted = FALSE;
$probe_description = '';
$restore_note = 'No entity restore was needed.';

try {
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ENABLED,
    TRUE,
  );
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
    TRUE,
  );
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
    TRUE,
  );

  $node = carlson_general_find_purge_trace_probe_node(
    \Drupal::entityTypeManager(),
  );

  if ($node instanceof NodeInterface) {
    $probe_description = sprintf(
      'Real node save on %s node #%s.',
      $node->bundle(),
      $node->id(),
    );
    $original_title = $node->label();
    $probe_title = sprintf(
      '%s [purge trace probe %s]',
      $original_title,
      gmdate('H:i:s'),
    );

    $node->setNewRevision(FALSE);
    $node->setTitle($probe_title);
    $node->save();
    script_log(
      sprintf(
        'Saved %s node #%s with temporary title for purge trace probe.',
        $node->bundle(),
        $node->id(),
      ),
      'notice',
    );

    [$summary, $trace_file] = carlson_general_flush_purge_trace_probe(
      $runtime,
      $writer,
    );

    $state->set(
      \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ENABLED,
      FALSE,
    );

    $restored_node = \Drupal\node\Entity\Node::load($node->id());
    if ($restored_node instanceof NodeInterface) {
      $restored_node->setNewRevision(FALSE);
      $restored_node->setTitle($original_title);
      $restored_node->save();
      $restore_note = sprintf(
        'Restored %s node #%s title to its original value.',
        $restored_node->bundle(),
        $restored_node->id(),
      );
      script_log($restore_note, 'notice');
    }
    else {
      $restore_note = sprintf(
        'Could not reload node #%s to restore its title.',
        $node->id(),
      );
      script_log($restore_note, 'warning');
    }
  }
  else {
    $probe_description = 'Synthetic invalidation using node_list:news.';
    \Drupal::service('cache_tags.invalidator')->invalidateTags([
      'node:synthetic-probe',
      'node_list:news',
    ]);
    script_log(
      'No news/event node was available. Ran synthetic broad invalidation.',
      'warning',
    );

    [$summary, $trace_file] = carlson_general_flush_purge_trace_probe(
      $runtime,
      $writer,
    );
  }

  if ($summary !== NULL && $trace_file) {
    [$date, $filename] = explode('/', $trace_file, 2);
    $download_url = Url::fromRoute(
      'carlson_general.purge_trace_download',
      [
        'date' => $date,
        'filename' => $filename,
      ],
      ['absolute' => TRUE],
    )->toString();

    $contents = $writer->readFile($date, $filename);
    $lines = preg_split('/\R/', trim($contents));
    $last_line = end($lines);
    $decoded_last_line = $last_line ? json_decode($last_line, TRUE) : NULL;
    $persisted = is_array($decoded_last_line) &&
      ($decoded_last_line['trace_id'] ?? NULL) ===
      ($summary['trace_id'] ?? NULL);
  }
}
catch (\Throwable $throwable) {
  $message = 'Probe failed: ' . $throwable->getMessage();
  script_log($message, 'error');
  script_log($throwable->getTraceAsString(), 'debug');
  return $message;
}
finally {
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ENABLED,
    $previous_enabled,
  );
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
    $previous_capture_callers,
  );
  $state->set(
    \Drupal\carlson_general\Service\PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
    $previous_estimate_cache_object_impact,
  );
}

if ($summary === NULL || !$trace_file) {
  $message = 'Probe ran, but no purge trace summary was written.';
  script_log($message, 'error');
  return $message;
}

$summary_json = json_encode(
  $summary,
  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
);
if ($summary_json === FALSE) {
  $summary_json = 'Could not encode summary JSON.';
}

$cache_impact_summary = [];
if (!empty($summary['cache_object_impact']['union'])) {
  foreach ($summary['cache_object_impact']['union'] as $bin => $bin_summary) {
    $cache_impact_summary[] = sprintf(
      '%s=%s',
      $bin,
      $bin_summary['count'] ?? 0,
    );
  }
}

script_log('Trace file: ' . $trace_file, 'info');
if ($download_url) {
  script_log('Download trace file: ' . $download_url, 'info');
}
script_log(
  'Persisted last line matches current trace: ' . ($persisted ? 'yes' : 'no'),
  'info',
);

$output = [];
$output[] = 'CSM-353 Purge Trace Probe';
$output[] = str_repeat('=', 80);
$output[] = 'Probe: ' . $probe_description;
$output[] = 'Trace file: ' . $trace_file;
$output[] = 'Download: ' . ($download_url ?: 'Unavailable');
$output[] = 'Persisted last line matches current trace: '
  . ($persisted ? 'yes' : 'no');
$output[] = 'Blast radius: ' . $summary['blast_radius'];
$output[] = 'Estimated queue items: ' . $summary['estimated_queue_items'];
$output[] = 'Estimated Acquia BAN batches: '
  . $summary['estimated_acquia_ban_batches'];
$output[] = 'Broad tags: ' . implode(', ', $summary['broad_tags']);
$output[] = 'Cache object impact estimation: '
  . (!empty($summary['cache_object_impact']['enabled']) ? 'enabled' : 'disabled');
if ($cache_impact_summary !== []) {
  $output[] = 'Current cache object matches: '
    . implode(', ', $cache_impact_summary);
}
$output[] = 'Contexts: ' . implode(', ', $summary['contexts']);
$output[] = 'Restore: ' . $restore_note;
$output[] = 'Script log: ' . $log_file_path;
$output[] = '';
$output[] = 'Trace JSON';
$output[] = str_repeat('-', 80);
$output[] = $summary_json;

return implode(PHP_EOL, $output);

/**
 * Finds a candidate node for the probe.
 *
 * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
 *   The entity type manager.
 *
 * @return \Drupal\node\NodeInterface|null
 *   A news or event node to save, or NULL if none are available.
 */
function carlson_general_find_purge_trace_probe_node(
  EntityTypeManagerInterface $entity_type_manager,
): ?NodeInterface {
  $storage = $entity_type_manager->getStorage('node');

  foreach (['news', 'event'] as $bundle) {
    $node_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->range(0, 1)
      ->sort('nid', 'ASC')
      ->execute();

    if (!$node_ids) {
      continue;
    }

    $node = $storage->load(reset($node_ids));
    if ($node instanceof NodeInterface) {
      return $node;
    }
  }

  return NULL;
}

/**
 * Flushes the current purge trace immediately.
 *
 * @param object $runtime
 *   The purge trace runtime service.
 * @param object $writer
 *   The purge trace writer service.
 *
 * @return array{0: array<string, mixed>|null, 1: string|null}
 *   The summary array and relative file path.
 */
function carlson_general_flush_purge_trace_probe(
  object $runtime,
  object $writer,
): array {
  $summary = $runtime->buildSummary();
  $trace_file = NULL;

  if ($summary !== NULL) {
    $trace_file = $writer->write($summary);
  }

  $runtime->markFlushed();
  return [$summary, $trace_file];
}
