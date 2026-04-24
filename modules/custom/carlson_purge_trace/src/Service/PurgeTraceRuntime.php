<?php

namespace Drupal\carlson_purge_trace\Service;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Collects purge trace data for the current request or command.
 */
class PurgeTraceRuntime {

  /**
   * Default purge trace capture state.
   */
  public const DEFAULT_ENABLED = FALSE;

  /**
   * Default caller capture state.
   */
  public const DEFAULT_CAPTURE_CALLERS = FALSE;

  /**
   * Default cache-object impact estimation state.
   */
  public const DEFAULT_ESTIMATE_CACHE_OBJECT_IMPACT = FALSE;

  /**
   * Default retention period in days.
   */
  public const DEFAULT_RETENTION_DAYS = 3;

  /**
   * State key for the enable toggle.
   */
  public const STATE_ENABLED = 'carlson_purge_trace.enabled';

  /**
   * State key for retention.
   */
  public const STATE_RETENTION_DAYS = 'carlson_purge_trace.retention_days';

  /**
   * State key for caller capture.
   */
  public const STATE_CAPTURE_CALLERS = 'carlson_purge_trace.capture_callers';

  /**
   * State key for cache-object impact estimation.
   */
  public const STATE_ESTIMATE_CACHE_OBJECT_IMPACT =
    'carlson_purge_trace.estimate_cache_object_impact';

  /**
   * State key for the last cleanup timestamp.
   */
  public const STATE_LAST_CLEANUP = 'carlson_purge_trace.last_cleanup';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected CurrentRouteMatch $currentRouteMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The collected causes.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $causes = [];

  /**
   * The collected context flags.
   *
   * @var array<string, bool>
   */
  protected array $contexts = [];

  /**
   * The collected invalidation calls.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $invalidationCalls = [];

  /**
   * The aggregate unique tags keyed by value.
   *
   * @var array<string, bool>
   */
  protected array $uniqueTags = [];

  /**
   * The command name, if applicable.
   *
   * @var string|null
   */
  protected ?string $commandName = NULL;

  /**
   * Whether the trace was flushed already.
   *
   * @var bool
   */
  protected bool $flushed = FALSE;

  /**
   * The request trace ID.
   *
   * @var string
   */
  protected string $traceId;

  /**
   * The time at which collection began.
   *
   * @var float
   */
  protected float $startedAt;

  /**
   * Constructs the runtime collector.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    CurrentRouteMatch $current_route_match,
    RequestStack $request_stack,
    StateInterface $state,
  ) {
    $this->currentUser = $current_user;
    $this->currentRouteMatch = $current_route_match;
    $this->requestStack = $request_stack;
    $this->state = $state;
    $this->traceId = uniqid('purge-trace-', TRUE);
    $this->startedAt = microtime(TRUE);
  }

  /**
   * Returns whether tracing is enabled.
   */
  public function isEnabled(): bool {
    return (bool) $this->state->get(
      self::STATE_ENABLED,
      self::DEFAULT_ENABLED,
    );
  }

  /**
   * Returns whether caller capture is enabled.
   */
  public function shouldCaptureCallers(): bool {
    return (bool) $this->state->get(
      self::STATE_CAPTURE_CALLERS,
      self::DEFAULT_CAPTURE_CALLERS,
    );
  }

  /**
   * Returns whether cache-object impact estimation is enabled.
   */
  public function shouldEstimateCacheObjectImpact(): bool {
    return (bool) $this->state->get(
      self::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
      self::DEFAULT_ESTIMATE_CACHE_OBJECT_IMPACT,
    );
  }

  /**
   * Returns the retention period in days.
   */
  public function getRetentionDays(): int {
    return max(1, (int) $this->state->get(
      self::STATE_RETENTION_DAYS,
      self::DEFAULT_RETENTION_DAYS,
    ));
  }

  /**
   * Records an entity mutation that may have triggered invalidation.
   */
  public function recordEntityOperation(
    string $operation,
    EntityInterface $entity,
  ): void {
    if (!$this->isEnabled()) {
      return;
    }

    $id = $entity->id();
    $bundle = $entity->bundle();
    $entity_type = $entity->getEntityTypeId();
    $key = implode(':', [$operation, $entity_type, (string) $bundle, (string) $id]);
    $label = NULL;

    try {
      $label = $entity->label();
    }
    catch (\Throwable) {
      $label = NULL;
    }

    $this->causes[$key] = [
      'type' => 'entity',
      'operation' => $operation,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'id' => $id,
      'label' => $label,
    ];

    if ($entity_type === 'node') {
      $this->contexts['node_change'] = TRUE;
    }

    if (in_array($entity_type, ['block', 'block_content'], TRUE)) {
      $this->contexts['block_change'] = TRUE;
    }

    if (str_starts_with($entity_type, 'feeds_')) {
      $this->contexts['feeds_change'] = TRUE;
    }
  }

  /**
   * Records a config mutation.
   */
  public function recordConfigOperation(string $operation, string $name): void {
    if (!$this->isEnabled()) {
      return;
    }

    $this->causes[$operation . ':' . $name] = [
      'type' => 'config',
      'operation' => $operation,
      'name' => $name,
    ];
    $this->contexts['config_change'] = TRUE;
  }

  /**
   * Records an invalidation call.
   *
   * @param string[] $tags
   *   The invalidated tags.
   * @param array<string, mixed> $caller
   *   Caller metadata.
   */
  public function recordInvalidation(array $tags, array $caller = []): void {
    if (!$this->isEnabled()) {
      return;
    }

    $tags = array_values(array_unique(array_filter($tags, 'is_string')));
    if ($tags === []) {
      return;
    }

    foreach ($tags as $tag) {
      $this->uniqueTags[$tag] = TRUE;
    }

    $this->invalidationCalls[] = [
      'offset_ms' => (int) round((microtime(TRUE) - $this->startedAt) * 1000),
      'tag_count' => count($tags),
      'tags' => $tags,
      'caller' => $caller,
    ];
  }

  /**
   * Records an execution context.
   */
  public function addContext(string $context): void {
    if (!$this->isEnabled()) {
      return;
    }

    $this->contexts[$context] = TRUE;
  }

  /**
   * Stores the active console command.
   */
  public function setConsoleCommand(?string $command_name): void {
    if (!$this->isEnabled()) {
      return;
    }

    $this->commandName = $command_name ?: 'unknown';
    $this->contexts['console'] = TRUE;
  }

  /**
   * Returns whether a trace has already been written.
   */
  public function isFlushed(): bool {
    return $this->flushed;
  }

  /**
   * Marks the runtime as flushed.
   */
  public function markFlushed(): void {
    $this->flushed = TRUE;
  }

  /**
   * Builds the current request summary.
   *
   * @return array<string, mixed>|null
   *   The trace payload or NULL if nothing should be written.
   */
  public function buildSummary(): ?array {
    if (!$this->isEnabled() || $this->invalidationCalls === []) {
      return NULL;
    }

    $request = $this->requestStack->getCurrentRequest();
    $route_name = $this->currentRouteMatch->getRouteName();
    $request_uri = $request ? $request->getRequestUri() : NULL;
    $method = $request ? $request->getMethod() : NULL;
    $contexts = array_keys($this->contexts);

    if ($route_name === 'system.cron') {
      $contexts[] = 'cron';
    }

    if ($route_name && str_contains($route_name, 'batch')) {
      $contexts[] = 'batch';
    }

    if (
      ($route_name && str_contains($route_name, 'feeds')) ||
      ($request_uri && str_contains($request_uri, 'feeds'))
    ) {
      $contexts[] = 'feeds_route';
    }

    if ($this->commandName && str_contains($this->commandName, 'cron')) {
      $contexts[] = 'cron_command';
    }

    $contexts = array_values(array_unique($contexts));
    sort($contexts);

    $unique_tags = array_keys($this->uniqueTags);
    sort($unique_tags);
    $broad_tags = array_values(array_filter(
      $unique_tags,
      [$this, 'isBroadTag']
    ));
    $estimate_cache_object_impact = $this->shouldEstimateCacheObjectImpact();
    $cache_object_impact = [
      'enabled' => $estimate_cache_object_impact,
      'inspected_tags' => $broad_tags,
      'sample_limit' => 0,
      'available_bins' => [],
      'union' => [],
      'by_tag' => [],
    ];
    try {
      if (
        $estimate_cache_object_impact &&
        $broad_tags !== [] &&
        \Drupal::hasService('carlson_purge_trace.cache_impact_inspector')
      ) {
        $cache_object_impact = \Drupal::service(
          'carlson_purge_trace.cache_impact_inspector',
        )->summarize($broad_tags);
        $cache_object_impact['enabled'] = TRUE;
      }
    }
    catch (\Throwable) {
      $cache_object_impact['error'] = 'Cache impact inspection failed.';
    }

    return [
      'trace_id' => $this->traceId,
      'recorded_at' => gmdate(DATE_ATOM),
      'duration_ms' => (int) round((microtime(TRUE) - $this->startedAt) * 1000),
      'execution_context' => $this->commandName ? 'console' : 'http',
      'command' => $this->commandName,
      'request' => [
        'route_name' => $route_name,
        'uri' => $request_uri,
        'method' => $method,
      ],
      'user' => [
        'uid' => $this->currentUser->id(),
        'is_authenticated' => $this->currentUser->isAuthenticated(),
      ],
      'contexts' => $contexts,
      'causes' => array_values($this->causes),
      'invalidation_call_count' => count($this->invalidationCalls),
      'estimated_queue_items' => count($unique_tags),
      'estimated_acquia_ban_batches' => (int) ceil(count($unique_tags) / 15),
      'blast_radius' => $this->classifyBlastRadius(
        count($unique_tags),
        count($broad_tags),
        count($this->invalidationCalls),
      ),
      'broad_tags' => $broad_tags,
      'cache_object_impact' => $cache_object_impact,
      'tag_families' => $this->buildTagFamilySummary($unique_tags),
      'unique_tags' => $unique_tags,
      'invalidation_calls' => $this->invalidationCalls,
    ];
  }

  /**
   * Returns whether a tag has a broad blast radius.
   */
  protected function isBroadTag(string $tag): bool {
    return (bool) preg_match(
      '/(^.+_list(?::|$)|^config:|^entity_types$|^local_task$|^http_response$|'
      . '^rendered$|^breakpoints$|^theme_registry$|^library_info$)/',
      $tag,
    );
  }

  /**
   * Builds a family-level summary for the supplied tags.
   *
   * @param string[] $tags
   *   The unique tags.
   *
   * @return array<string, int>
   *   The family counts.
   */
  protected function buildTagFamilySummary(array $tags): array {
    $families = [];

    foreach ($tags as $tag) {
      $family = str_contains($tag, ':')
        ? strstr($tag, ':', TRUE)
        : $tag;
      $families[$family] = ($families[$family] ?? 0) + 1;
    }

    ksort($families);
    return $families;
  }

  /**
   * Classifies the likely blast radius.
   */
  protected function classifyBlastRadius(
    int $unique_tag_count,
    int $broad_tag_count,
    int $call_count,
  ): string {
    if ($broad_tag_count > 0 || $unique_tag_count >= 25 || $call_count >= 10) {
      return 'high';
    }

    if ($unique_tag_count >= 10 || $call_count >= 5) {
      return 'medium';
    }

    return 'low';
  }

}
