<?php

namespace Drupal\carlson_purge_trace\Service;

use Drupal\Core\Database\Connection;

/**
 * Estimates current Drupal cache-object impact for broad invalidation tags.
 */
class PurgeTraceCacheImpactInspector {

  /**
   * The cache bins to inspect.
   */
  protected const TARGET_BINS = [
    'cache_page',
    'cache_dynamic_page_cache',
    'cache_render',
  ];

  /**
   * Maximum number of sample cache IDs to return per bin.
   */
  protected const SAMPLE_LIMIT = 5;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs the inspector.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Builds a bounded summary of current cache objects carrying supplied tags.
   *
   * @param string[] $tags
   *   The broad invalidation tags to inspect.
   *
   * @return array<string, mixed>
   *   Cache-object impact details.
   */
  public function summarize(array $tags): array {
    $tags = array_values(array_unique(array_filter($tags, 'is_string')));
    $available_bins = array_values(array_filter(
      self::TARGET_BINS,
      fn(string $table): bool => $this->database->schema()->tableExists($table),
    ));

    $summary = [
      'inspected_tags' => $tags,
      'sample_limit' => self::SAMPLE_LIMIT,
      'available_bins' => $available_bins,
      'union' => [],
      'by_tag' => [],
    ];

    foreach ($available_bins as $table) {
      $summary['union'][$table] = $this->summarizeBin($table, $tags);
    }

    foreach ($tags as $tag) {
      $summary['by_tag'][$tag] = [];
      foreach ($available_bins as $table) {
        $summary['by_tag'][$tag][$table] = $this->summarizeBin($table, [$tag]);
      }
    }

    return $summary;
  }

  /**
   * Summarizes current matching objects in one cache bin.
   *
   * @param string $table
   *   The cache bin table.
   * @param string[] $tags
   *   The tags to match.
   *
   * @return array<string, mixed>
   *   Count and sample CIDs.
   */
  protected function summarizeBin(string $table, array $tags): array {
    if ($tags === []) {
      return [
        'count' => 0,
        'sample_cids' => [],
      ];
    }

    [$where, $arguments] = $this->buildTagWhereClause($tags);
    $count = (int) $this->database->query(
      "SELECT COUNT(*) FROM {{$table}} WHERE tags IS NOT NULL AND ($where)",
      $arguments,
    )->fetchField();

    $sample_cids = $this->database->query(
      "SELECT cid FROM {{$table}} WHERE tags IS NOT NULL AND ($where) ORDER BY created DESC LIMIT "
      . self::SAMPLE_LIMIT,
      $arguments,
    )->fetchCol();

    return [
      'count' => $count,
      'sample_cids' => array_values($sample_cids),
    ];
  }

  /**
   * Builds a tag-matching WHERE clause for cache-tag columns.
   *
   * @param string[] $tags
   *   The tags to match as whole values.
   *
   * @return array{0: string, 1: array<string, string>}
   *   The SQL fragment and arguments.
   */
  protected function buildTagWhereClause(array $tags): array {
    $conditions = [];
    $arguments = [];

    foreach ($tags as $index => $tag) {
      $placeholder = ':tag_' . $index;
      $conditions[] = "CONCAT(' ', tags, ' ') LIKE $placeholder";
      $arguments[$placeholder] = '% '
        . $this->database->escapeLike($tag)
        . ' %';
    }

    return [
      implode(' OR ', $conditions),
      $arguments,
    ];
  }

}
