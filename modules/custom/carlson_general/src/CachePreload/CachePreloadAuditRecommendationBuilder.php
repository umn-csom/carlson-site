<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Builds final preload recommendations from warm lookup evidence.
 */
final class CachePreloadAuditRecommendationBuilder {

  /**
   * Return non-preloaded tags that should be measured.
   *
   * @param array $warm_lookup_capture
   *   Warm lookup capture report.
   *
   * @return array
   *   Cache tags.
   */
  public function candidateTags(array $warm_lookup_capture): array {
    return array_keys($this->aggregateTags(
      $warm_lookup_capture,
      'candidate_tags'
    ));
  }

  /**
   * Build final recommendation rows.
   *
   * @param array $warm_lookup_capture
   *   Warm lookup capture report.
   * @param array $measurements
   *   Synthetic cache-read measurements keyed by scenario.
   *
   * @return array
   *   Recommendation summary.
   */
  public function build(
    array $warm_lookup_capture,
    array $measurements
  ): array {
    $candidates = $this->aggregateTags(
      $warm_lookup_capture,
      'candidate_tags'
    );
    $already_preloaded = $this->aggregateTags(
      $warm_lookup_capture,
      'repeated_preloaded_tags'
    );

    $rows = [];
    $add_tags = [];
    foreach ($candidates as $tag => $info) {
      $measurement = $this->measurementForTag($tag, $measurements);
      $decision = $this->decision($measurement, $measurements, $info);
      if ($decision['status'] === 'add') {
        $add_tags[] = $tag;
      }

      $rows[] = [
        'tag' => $tag,
        'paths' => $info['paths'],
        'path_count' => count($info['paths']),
        'group_count' => $info['group_count'],
        'avoidable_group_count' => $info['avoidable_group_count'] ?? 0,
        'repeated_group_count' => $info['repeated_group_count'] ?? 0,
        'measurement' => $this->measurementText($measurement, $measurements),
        'decision' => $decision['label'],
      ];
    }

    return [
      'add_tags' => $add_tags,
      'rows' => $rows,
      'already_preloaded' => $already_preloaded,
      'decision_rule' => 'Review stable tags that appear in later single-tag '
        . 'warm lookup groups; add globally only after representative-page '
        . 'verification.',
    ];
  }

  /**
   * Aggregate tag rows across captured paths.
   *
   * @param array $warm_lookup_capture
   *   Warm lookup capture report.
   * @param string $key
   *   Capture row key to aggregate.
   *
   * @return array
   *   Tag data keyed by tag.
   */
  private function aggregateTags(
    array $warm_lookup_capture,
    string $key
  ): array {
    $tags = [];
    foreach (($warm_lookup_capture['paths'] ?? []) as $path_row) {
      foreach (($path_row[$key] ?? []) as $tag_row) {
        $tag = $tag_row['tag'];
        $tags[$tag]['group_count'] = ($tags[$tag]['group_count'] ?? 0)
          + (int) $tag_row['group_count'];
        $tags[$tag]['avoidable_group_count'] =
          ($tags[$tag]['avoidable_group_count'] ?? 0)
          + (int) ($tag_row['avoidable_group_count'] ?? 0);
        $tags[$tag]['repeated_group_count'] =
          ($tags[$tag]['repeated_group_count'] ?? 0)
          + (int) ($tag_row['repeated_group_count'] ?? 0);
        $tags[$tag]['paths'][$path_row['path']] = $path_row['path'];
      }
    }

    uasort($tags, static function (array $left, array $right): int {
      return $right['group_count'] <=> $left['group_count']
        ?: count($right['paths']) <=> count($left['paths']);
    });

    foreach ($tags as &$tag) {
      $tag['paths'] = array_values($tag['paths']);
    }

    return $tags;
  }

  /**
   * Return the measurement row for a candidate tag.
   *
   * @param string $tag
   *   Cache tag.
   * @param array $measurements
   *   Measurements keyed by scenario.
   *
   * @return array|null
   *   Measurement row, or NULL when not measured.
   */
  private function measurementForTag(string $tag, array $measurements): ?array {
    foreach ($measurements as $measurement) {
      if (($measurement['candidate_tag'] ?? '') === $tag) {
        return $measurement;
      }
    }

    return NULL;
  }

  /**
   * Return decision details for a measured candidate.
   *
   * @param array|null $measurement
   *   Measurement row.
   * @param array $measurements
   *   Measurements keyed by scenario.
   * @param array $candidate_info
   *   Aggregated candidate evidence.
   *
   * @return array
   *   Decision status and label.
   */
  private function decision(
    ?array $measurement,
    array $measurements,
    array $candidate_info
  ): array {
    if (!empty($candidate_info['avoidable_group_count'])) {
      return [
        'status' => 'test',
        'label' => 'Candidate; warm request has a later single-tag lookup.',
      ];
    }
    if (isset($measurements['unsupported'])) {
      return [
        'status' => 'test',
        'label' => 'Test manually; checksum preload is unsupported here.',
      ];
    }
    if ($measurement === NULL) {
      return [
        'status' => 'test',
        'label' => 'Candidate; measurement was not run.',
      ];
    }

    $current_queries = $this->currentQueryCount($measurements);
    if ($measurement['cachetag_queries'] < $current_queries) {
      return [
        'status' => 'add',
        'label' => 'Add; warm lookup candidate and query count decreased.',
      ];
    }

    return [
      'status' => 'reject',
      'label' => 'Do not add yet; query count did not decrease.',
    ];
  }

  /**
   * Render measurement text.
   *
   * @param array|null $measurement
   *   Measurement row.
   * @param array $measurements
   *   Measurements keyed by scenario.
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
      return 'Not measured.';
    }

    return 'Effective current ' . $this->currentQueryCount($measurements)
      . ', with tag ' . $measurement['cachetag_queries'] . '.';
  }

  /**
   * Return the effective current query count.
   *
   * @param array $measurements
   *   Measurements keyed by scenario.
   *
   * @return int
   *   Cachetags query count.
   */
  private function currentQueryCount(array $measurements): int {
    return (int) (
      $measurements['effective_current_preload']['cachetag_queries']
      ?? PHP_INT_MAX
    );
  }

}
