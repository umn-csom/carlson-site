<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Reviews observed cache tags as preload candidates.
 */
final class CachePreloadAuditCandidateReviewer {

  /**
   * Review observed tags and assign candidate classifications.
   *
   * @param array $tag_frequency
   *   Observed tag frequency data.
   * @param array $current_preload_tags
   *   Cache preload tags currently configured for the site.
   * @param array $measurements
   *   Checksum query measurements keyed by scenario.
   * @param int $coverage_threshold
   *   Minimum coverage percentage for automatic candidate consideration.
   * @param int $limit
   *   Maximum reviewed rows to return.
   *
   * @return array
   *   Candidate review rows and summary metadata.
   */
  public function review(
    array $tag_frequency,
    array $current_preload_tags,
    array $measurements = [],
    int $coverage_threshold = 50,
    int $limit = 75
  ): array {
    $tag_classifier = new CachePreloadAuditTagClassifier();
    $pages = (int) ($tag_frequency['pages_with_headers'] ?? 0);
    $rows = [];

    foreach (($tag_frequency['tags'] ?? []) as $tag => $info) {
      $page_count = (int) ($info['page_count'] ?? 0);
      $coverage = $pages > 0 ? ($page_count / $pages) * 100 : 0;
      $classification = $tag_classifier->classify(
        $tag,
        $coverage,
        $coverage_threshold,
        $current_preload_tags
      );
      $measurement = $this->measurementForTag($tag, $measurements);

      $rows[] = [
        'tag' => $tag,
        'page_count' => $page_count,
        'pages_with_headers' => $pages,
        'coverage' => round($coverage, 1),
        'classification' => $classification['classification'],
        'reason' => $classification['reason'],
        'measurement' => $this->measurementText($measurement, $measurements),
        'recommendation' => $this->recommendation(
          $classification['classification'],
          $measurement,
          $measurements
        ),
        'probe' => $classification['classification'] === 'candidate',
      ];
    }

    usort($rows, function (array $left, array $right) use (
      $tag_classifier
    ): int {
      return $tag_classifier->classificationPriority($left['classification'])
        <=> $tag_classifier->classificationPriority($right['classification'])
        ?: $tag_classifier->tagPriority($left['tag'])
        <=> $tag_classifier->tagPriority($right['tag'])
        ?: $right['page_count'] <=> $left['page_count'];
    });

    return [
      'pages_with_headers' => $pages,
      'coverage_threshold' => $coverage_threshold,
      'probe_rows' => array_values(array_filter(
        $rows,
        static fn(array $row): bool => !empty($row['probe'])
      )),
      'rows' => array_slice($rows, 0, max(0, $limit)),
    ];
  }

  /**
   * Return tags that should receive one-off checksum measurements.
   *
   * @param array $review
   *   Candidate review data.
   * @param int $limit
   *   Maximum probe tags to return.
   *
   * @return array
   *   Candidate cache tags.
   */
  public function probeTags(array $review, int $limit = 5): array {
    $tags = [];
    foreach (($review['probe_rows'] ?? $review['rows'] ?? []) as $row) {
      if (!empty($row['probe'])) {
        $tags[] = $row['tag'];
      }
      if (count($tags) >= $limit) {
        break;
      }
    }

    return $tags;
  }

  /**
   * Return the measurement row for a dynamically probed tag.
   *
   * @param string $tag
   *   Cache tag.
   * @param array $measurements
   *   Checksum query measurements keyed by scenario.
   *
   * @return array|null
   *   Measurement row, or NULL when not measured.
   */
  private function measurementForTag(string $tag, array $measurements): ?array {
    if ($tag === 'config:system.menu.main'
      && isset($measurements['current_plus_menu_probe'])
    ) {
      return $measurements['current_plus_menu_probe'];
    }

    foreach ($measurements as $measurement) {
      if (($measurement['candidate_tag'] ?? '') === $tag) {
        return $measurement;
      }
    }

    return NULL;
  }

  /**
   * Summarize one dynamic measurement.
   *
   * @param array|null $measurement
   *   Measurement row.
   * @param array $measurements
   *   Checksum query measurements keyed by scenario.
   *
   * @return string
   *   Human-readable measurement summary.
   */
  private function measurementText(
    ?array $measurement,
    array $measurements
  ): string {
    if (isset($measurements['unsupported'])) {
      return $measurements['unsupported'];
    }
    if ($measurement === NULL) {
      return 'Not measured in this run.';
    }

    return 'Current ' . $this->currentQueryCount($measurements)
      . ', with tag ' . $measurement['cachetag_queries']
      . ' cachetag queries.';
  }

  /**
   * Return the recommendation text for a reviewed tag.
   *
   * @param string $classification
   *   Candidate classification.
   * @param array|null $measurement
   *   Measurement row.
   * @param array $measurements
   *   Checksum query measurements keyed by scenario.
   *
   * @return string
   *   Recommendation text.
   */
  private function recommendation(
    string $classification,
    ?array $measurement,
    array $measurements
  ): string {
    if ($classification === 'current_preload') {
      return 'Keep; already part of the configured preload list.';
    }
    if ($classification !== 'candidate') {
      return 'Do not add from frequency evidence.';
    }
    if ($measurement === NULL || isset($measurements['unsupported'])) {
      return 'Worth testing; add only if checksum queries decrease.';
    }

    $current_queries = $this->currentQueryCount($measurements);
    if ($measurement['cachetag_queries'] < $current_queries) {
      return 'Consider adding; checksum queries decreased in this run.';
    }

    return 'Do not add yet; no checksum query decrease was measured.';
  }

  /**
   * Return the current preload scenario query count.
   *
   * @param array $measurements
   *   Checksum query measurements keyed by scenario.
   *
   * @return int
   *   Cachetag query count.
   */
  private function currentQueryCount(array $measurements): int {
    return (int) (
      $measurements['current_views_preload']['cachetag_queries']
      ?? PHP_INT_MAX
    );
  }

}
