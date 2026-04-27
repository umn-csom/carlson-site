<?php

namespace Drupal\carlson_purge_trace\Controller;

use Drupal\carlson_purge_trace\Service\PurgeTraceRuntime;
use Drupal\carlson_purge_trace\Service\PurgeTraceWriter;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs a purge trace validation probe from the UI.
 */
class PurgeTraceProbeController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $purgeTraceEntityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The purge trace runtime.
   *
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceRuntime
   */
  protected PurgeTraceRuntime $runtime;

  /**
   * The purge trace writer.
   *
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceWriter
   */
  protected PurgeTraceWriter $writer;

  /**
   * Constructs the probe controller.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    PurgeTraceRuntime $runtime,
    PurgeTraceWriter $writer,
  ) {
    $this->purgeTraceEntityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->runtime = $runtime;
    $this->writer = $writer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('cache_tags.invalidator'),
      $container->get('carlson_purge_trace.runtime'),
      $container->get('carlson_purge_trace.writer'),
    );
  }

  /**
   * Runs the validation probe.
   */
  public function run(): array {
    $previous_enabled = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_ENABLED,
      PurgeTraceRuntime::DEFAULT_ENABLED,
    );
    $previous_capture_callers = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
      PurgeTraceRuntime::DEFAULT_CAPTURE_CALLERS,
    );
    $previous_estimate_cache_object_impact = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
      PurgeTraceRuntime::DEFAULT_ESTIMATE_CACHE_OBJECT_IMPACT,
    );

    $summary = NULL;
    $trace_file = NULL;
    $download_url = NULL;
    $persisted = FALSE;
    $probe_description = '';
    $restore_note = 'No entity restore was needed.';

    try {
      $node = $this->findProbeNode();

      if ($node instanceof NodeInterface) {
        $probe_description = sprintf(
          'Real node save on %s node #%s.',
          $node->bundle(),
          $node->id(),
        );
        $original_title = $node->label();
        $suffix = sprintf(' [purge trace probe %s]', gmdate('H:i:s'));
        $base_title = mb_substr($original_title, 0, 255 - strlen($suffix));
        $node_saved = FALSE;

        try {
          $node->setNewRevision(FALSE);
          $node->setTitle($base_title . $suffix);
          $node->save();
          $node_saved = TRUE;

          [$summary, $trace_file] = $this->flushTrace();
        }
        finally {
          if ($node_saved) {
            $this->state->set(PurgeTraceRuntime::STATE_ENABLED, FALSE);
            $restore_note = $this->restoreProbeNode(
              (string) $node->id(),
              $original_title,
            );
          }
        }
      }
      else {
        $probe_description = 'Synthetic invalidation using node_list:news.';
        $this->cacheTagsInvalidator->invalidateTags([
          'node:synthetic-probe',
          'node_list:news',
        ]);

        [$summary, $trace_file] = $this->flushTrace();
      }

      if ($summary !== NULL && $trace_file) {
        [$date, $filename] = explode('/', $trace_file, 2);
        $download_url = Url::fromRoute(
          'carlson_purge_trace.download',
          [
            'date' => $date,
            'filename' => $filename,
          ],
          ['absolute' => TRUE],
        )->toString();

        $contents = $this->writer->readFile($date, $filename);
        $lines = preg_split('/\R/', trim($contents));
        $last_line = end($lines);
        $decoded_last_line = $last_line ? Json::decode($last_line) : NULL;
        $persisted = is_array($decoded_last_line) &&
          ($decoded_last_line['trace_id'] ?? NULL) ===
          ($summary['trace_id'] ?? NULL);
      }
    }
    catch (\Throwable $throwable) {
      return $this->buildOutput([
        'CSM-353 Purge Trace Probe',
        str_repeat('=', 80),
        'Probe failed: ' . $throwable->getMessage(),
      ]);
    }
    finally {
      $this->state->set(PurgeTraceRuntime::STATE_ENABLED, $previous_enabled);
      $this->state->set(
        PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
        $previous_capture_callers,
      );
      $this->state->set(
        PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
        $previous_estimate_cache_object_impact,
      );
    }

    $output = [
      'CSM-353 Purge Trace Probe',
      str_repeat('=', 80),
      'Probe: ' . $probe_description,
      'Capture enabled: ' . ($previous_enabled ? 'yes' : 'no'),
      'Caller stack samples: ' . ($previous_capture_callers ? 'yes' : 'no'),
      'Cache object impact estimation: '
        . ($previous_estimate_cache_object_impact ? 'yes' : 'no'),
      'Trace file: ' . ($trace_file ?: 'none'),
      'Download: ' . ($download_url ?: 'unavailable'),
      'Persisted last line matches current trace: '
        . ($persisted ? 'yes' : 'no'),
      'Restore: ' . $restore_note,
    ];

    if ($summary === NULL || !$trace_file) {
      $output[] = 'Result: ' . ($previous_enabled
        ? 'Probe ran, but no purge trace summary was written.'
        : 'Probe ran with purge trace capture disabled; no trace was expected.');
      return $this->buildOutput($output);
    }

    $output[] = 'Blast radius: ' . $summary['blast_radius'];
    $output[] = 'Estimated queue items: ' . $summary['estimated_queue_items'];
    $output[] = 'Estimated Acquia BAN batches: '
      . $summary['estimated_acquia_ban_batches'];
    $output[] = 'Broad tags: ' . implode(', ', $summary['broad_tags']);
    $output[] = 'Cache object impact in trace: '
      . (!empty($summary['cache_object_impact']['enabled'])
        ? 'enabled'
        : 'disabled');

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
    if ($cache_impact_summary !== []) {
      $output[] = 'Current cache object matches: '
        . implode(', ', $cache_impact_summary);
    }

    $output[] = 'Contexts: ' . implode(', ', $summary['contexts']);
    $output[] = '';
    $output[] = 'Trace JSON';
    $output[] = str_repeat('-', 80);
    $output[] = Json::encode($summary);

    return $this->buildOutput($output);
  }

  /**
   * Finds a candidate node for the probe.
   */
  protected function findProbeNode(): ?NodeInterface {
    $storage = $this->purgeTraceEntityTypeManager->getStorage('node');

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
   * @return array{0: array<string, mixed>|null, 1: string|null}
   *   The summary array and relative file path.
   */
  protected function flushTrace(): array {
    $summary = $this->runtime->buildSummary();
    $trace_file = NULL;

    if ($summary !== NULL) {
      $trace_file = $this->writer->write($summary);
    }

    $this->runtime->markFlushed();
    return [$summary, $trace_file];
  }

  /**
   * Restores the probe node title after a successful probe save.
   */
  protected function restoreProbeNode(string $node_id, string $original_title): string {
    try {
      $restored_node = $this->purgeTraceEntityTypeManager
        ->getStorage('node')
        ->load($node_id);

      if (!$restored_node instanceof NodeInterface) {
        return sprintf(
          'Could not reload node #%s to restore its title.',
          $node_id,
        );
      }

      $restored_node->setNewRevision(FALSE);
      $restored_node->setTitle($original_title);
      $restored_node->save();
      return sprintf(
        'Restored %s node #%s title to its original value.',
        $restored_node->bundle(),
        $restored_node->id(),
      );
    }
    catch (\Throwable $throwable) {
      return sprintf(
        'Could not restore node #%s title: %s',
        $node_id,
        $throwable->getMessage(),
      );
    }
  }

  /**
   * Builds a preformatted response.
   *
   * @param string[] $lines
   *   Output lines.
   */
  protected function buildOutput(array $lines): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<pre style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ output }}</pre>',
      '#context' => [
        'output' => implode(PHP_EOL, $lines),
      ],
    ];
  }

}
