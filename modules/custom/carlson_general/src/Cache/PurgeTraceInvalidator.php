<?php

namespace Drupal\carlson_general\Cache;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\carlson_general\Service\PurgeTraceRuntime;

/**
 * Observes all cache tag invalidations for purge trace capture.
 */
class PurgeTraceInvalidator implements CacheTagsInvalidatorInterface {

  /**
   * The request runtime collector.
   *
   * @var \Drupal\carlson_general\Service\PurgeTraceRuntime
   */
  protected PurgeTraceRuntime $runtime;

  /**
   * Constructs the invalidator.
   */
  public function __construct(PurgeTraceRuntime $runtime) {
    $this->runtime = $runtime;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if (!$this->runtime->isEnabled()) {
      return;
    }

    $this->runtime->recordInvalidation($tags, $this->buildCallerSummary());
  }

  /**
   * Builds a compact caller summary from the current stack.
   *
   * @return array<string, mixed>
   *   The caller summary.
   */
  protected function buildCallerSummary(): array {
    if (!$this->runtime->shouldCaptureCallers()) {
      return [];
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
    $frames = [];

    foreach ($trace as $frame) {
      $class = $frame['class'] ?? '';
      $function = $frame['function'] ?? 'unknown';

      if (
        $class === __CLASS__ ||
        $class === 'Drupal\\Core\\Cache\\CacheTagsInvalidator'
      ) {
        continue;
      }

      if ($function === 'invalidateTags') {
        continue;
      }

      $signature = $class ? $class . '::' . $function : $function;
      $location = !empty($frame['file']) && !empty($frame['line'])
        ? basename($frame['file']) . ':' . $frame['line']
        : NULL;

      $frames[] = $location ? $signature . ' @ ' . $location : $signature;
    }

    return [
      'origin' => $frames[0] ?? NULL,
      'frames' => array_slice($frames, 0, 5),
    ];
  }

}
