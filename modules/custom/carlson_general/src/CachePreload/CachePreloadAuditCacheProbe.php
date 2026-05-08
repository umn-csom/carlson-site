<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Database\Database;

/**
 * Probes cache tables and cache-tag checksum query behavior.
 *
 * Lookup capture identifies repeated stable tags. This class checks whether
 * adding a candidate tag to the preload list actually reduces cachetags
 * database queries during sampled cache reads.
 */
final class CachePreloadAuditCacheProbe {

  /**
   * Summarize candidate tag frequency in cache tables.
   *
   * @param array $candidate_tags
   *   Candidate tags to search for.
   *
   * @return array
   *   Cache table evidence summary.
   */
  public function cacheEvidence(array $candidate_tags): array {
    $connection = \Drupal::database();
    $database = $connection->getConnectionOptions()['database'] ?? '';
    $tables = $connection->query(
      "SELECT TABLE_NAME FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = :database
         AND COLUMN_NAME IN ('cid', 'tags')
         AND TABLE_NAME LIKE '%cache%'
       GROUP BY TABLE_NAME
       HAVING COUNT(DISTINCT COLUMN_NAME) = 2
       ORDER BY TABLE_NAME",
      [':database' => $database]
    )->fetchCol();

    $evidence = ['tables' => $tables, 'tags' => []];
    foreach ($candidate_tags as $tag) {
      $evidence['tags'][$tag] = ['total' => 0, 'tables' => []];
    }

    foreach ($tables as $table) {
      $quoted_table = $this->quoteTable($table);
      foreach ($candidate_tags as $tag) {
        $count = (int) $connection->query(
          "SELECT COUNT(*) FROM {$quoted_table}
           WHERE tags IS NOT NULL
             AND tags <> ''
             AND CONCAT(' ', tags, ' ') LIKE :needle",
          [':needle' => '% ' . $connection->escapeLike($tag) . ' %']
        )->fetchField();
        if ($count > 0) {
          $evidence['tags'][$tag]['total'] += $count;
          $evidence['tags'][$tag]['tables'][$table] = $count;
        }
      }
    }

    return $evidence;
  }

  /**
   * Select cache entries for a local checksum-query measurement.
   *
   * @param array $tables
   *   Cache tables to inspect.
   * @param array $candidate_tags
   *   Candidate tags to search for.
   * @param int $limit
   *   Maximum cache entries to read.
   *
   * @return array
   *   Cache reads to perform.
   */
  public function cacheReads(
    array $tables,
    array $candidate_tags,
    int $limit
  ): array {
    $connection = \Drupal::database();
    $reads = [];
    $per_table = max(10, (int) ceil($limit / max(1, count($tables))));

    foreach ($tables as $table) {
      $bin = $this->binFromTable($table);
      if ($bin === '') {
        continue;
      }
      $conditions = [];
      $args = [];
      foreach ($candidate_tags as $index => $tag) {
        $placeholder = ":tag_{$index}";
        $conditions[] = "CONCAT(' ', tags, ' ') LIKE {$placeholder}";
        $args[$placeholder] = '% ' . $connection->escapeLike($tag) . ' %';
      }
      $quoted_table = $this->quoteTable($table);
      $query = "SELECT cid, tags FROM {$quoted_table}
        WHERE tags IS NOT NULL
          AND tags <> ''
          AND (" . implode(' OR ', $conditions) . ")
        ORDER BY cid
        LIMIT " . (int) $per_table;

      foreach ($connection->query($query, $args) as $row) {
        $reads[] = [
          'bin' => $bin,
          'cid' => $row->cid,
          'tags' => $this->splitTags($row->tags),
        ];
        if (count($reads) >= $limit) {
          return $reads;
        }
      }
    }

    return $reads;
  }

  /**
   * Compare checksum-query counts for baseline and preload scenarios.
   *
   * @param array $cache_reads
   *   Cache reads to perform for each scenario.
   * @param array $effective_preload_tags
   *   Cache preload tags from core defaults plus site settings.
   * @param array $candidate_probe_tags
   *   Candidate tags to measure individually.
   *
   * @return array
   *   Measurement results keyed by scenario.
   */
  public function measurements(
    array $cache_reads,
    array $effective_preload_tags,
    array $candidate_probe_tags = []
  ): array {
    $checksum = \Drupal::service('cache_tags.invalidator.checksum');
    if (!method_exists($checksum, 'registerCacheTagsForPreload')) {
      return ['unsupported' => 'Checksum service does not support preloading.'];
    }

    // Fixed scenarios preserve the original Phase 2 comparison while dynamic
    // scenarios let the report test lookup-derived candidates.
    $scenarios = [
      'baseline_no_preload' => [],
      'effective_current_preload' => $effective_preload_tags,
      'effective_plus_menu_probe' => array_values(array_unique(array_merge(
        $effective_preload_tags,
        ['config:system.menu.main']
      ))),
      'effective_plus_block_probe' => array_values(array_unique(array_merge(
        $effective_preload_tags,
        ['config:block_list', 'block_view']
      ))),
    ];

    $results = [];
    foreach ($scenarios as $label => $preload_tags) {
      $results[$label] = $this->measure($label, $preload_tags, $cache_reads);
    }
    foreach ($candidate_probe_tags as $tag) {
      $label = 'effective_plus_candidate_' . $this->scenarioSlug($tag);
      $results[$label] = $this->measure(
        $label,
        array_values(array_unique(array_merge(
          $effective_preload_tags,
          [$tag]
        ))),
        $cache_reads
      );
      $results[$label]['candidate_tag'] = $tag;
    }

    return $results;
  }

  /**
   * Run one cache-read measurement.
   *
   * @param string $label
   *   Scenario label.
   * @param array $preload_tags
   *   Tags to register for checksum preloading.
   * @param array $cache_reads
   *   Cache reads to perform.
   *
   * @return array
   *   Measurement result.
   */
  private function measure(
    string $label,
    array $preload_tags,
    array $cache_reads
  ): array {
    $checksum = \Drupal::service('cache_tags.invalidator.checksum');
    if (method_exists($checksum, 'reset')) {
      $checksum->reset();
    }
    $checksum->registerCacheTagsForPreload($preload_tags);

    // Log only the cache reads below. The metric is how often those reads need
    // to query the cachetags checksum table under this preload scenario.
    $log_key = 'csm226_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $label);
    Database::startLog($log_key);

    $hits = 0;
    $errors = 0;
    foreach ($cache_reads as $read) {
      try {
        $hits += \Drupal::cache($read['bin'])->get($read['cid']) ? 1 : 0;
      }
      catch (\Throwable $exception) {
        $errors++;
      }
    }

    $cachetag_queries = 0;
    foreach (Database::getLog($log_key) as $entry) {
      $query = (string) ($entry['query'] ?? '');
      $cachetag_queries += stripos($query, 'cachetags') === FALSE ? 0 : 1;
    }

    return [
      'preload_tags' => $preload_tags,
      'reads' => count($cache_reads),
      'hits' => $hits,
      'errors' => $errors,
      'cachetag_queries' => $cachetag_queries,
    ];
  }

  /**
   * Convert a cache tag to a scenario-safe slug.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return string
   *   Scenario slug.
   */
  private function scenarioSlug(string $tag): string {
    return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $tag), '_');
  }

  /**
   * Split cache tag table values.
   *
   * @param string $tags
   *   Space- or comma-separated cache tags.
   *
   * @return array
   *   Sorted unique cache tags.
   */
  private function splitTags(string $tags): array {
    if (trim($tags) === '') {
      return [];
    }
    $tags = preg_split('/[\s,]+/', trim($tags));
    sort($tags);
    return array_values(array_unique($tags));
  }

  /**
   * Convert a cache table name to a cache bin name.
   *
   * @param string $table
   *   Cache table name.
   *
   * @return string
   *   Cache bin name, or an empty string when not recognized.
   */
  private function binFromTable(string $table): string {
    $position = strpos($table, 'cache_');
    return $position === FALSE ? '' : substr($table, $position + 6);
  }

  /**
   * Quote a cache table name returned by information_schema.
   *
   * @param string $table
   *   Cache table name.
   *
   * @return string
   *   Quoted cache table name.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the table name contains unexpected characters.
   */
  private function quoteTable(string $table): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
      throw new \InvalidArgumentException("Unexpected table name: {$table}");
    }
    return "`{$table}`";
  }

}
