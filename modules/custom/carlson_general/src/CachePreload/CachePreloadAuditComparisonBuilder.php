<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Builds request-level preload comparison evidence.
 */
final class CachePreloadAuditComparisonBuilder {

  /**
   * Parse comparison tags.
   *
   * @param string $compare_tags
   *   Comma-separated tags, "auto", or empty.
   * @param array $lookup_probe_tags
   *   Tags detected by warm lookup capture.
   *
   * @return array
   *   Tags to compare.
   */
  public function compareTagsOption(
    string $compare_tags,
    array $lookup_probe_tags
  ): array {
    $compare_tags = trim($compare_tags);
    if ($compare_tags === '') {
      return [];
    }
    if (strtolower($compare_tags) === 'auto') {
      return $lookup_probe_tags;
    }

    return $this->normalizedTags(explode(',', $compare_tags));
  }

  /**
   * Compare baseline requests against requests with one extra preload tag.
   *
   * @param array $compare_tags
   *   Tags to compare.
   * @param array $warm_lookup_capture
   *   Baseline warm lookup capture.
   * @param \Drupal\carlson_general\CachePreload\CachePreloadAuditLookupCapture $lookup_capture
   *   Lookup capture helper.
   * @param array $options
   *   Parsed options.
   * @param array $effective_preload_tags
   *   Core/default and site preload tags.
   *
   * @return array
   *   Comparison report.
   */
  public function build(
    array $compare_tags,
    array $warm_lookup_capture,
    CachePreloadAuditLookupCapture $lookup_capture,
    array $options,
    array $effective_preload_tags
  ): array {
    $report = [
      'enabled' => (bool) $compare_tags,
      'tags' => [],
      'unsupported' => '',
    ];
    if (!$compare_tags) {
      $report['unsupported'] =
        'Skipped; pass --compare-tags=auto or a tag list.';
      return $report;
    }
    if (empty($warm_lookup_capture['enabled'])) {
      $report['enabled'] = FALSE;
      $report['unsupported'] =
        'Skipped; baseline lookup capture is unavailable.';
      return $report;
    }

    foreach ($compare_tags as $tag) {
      $report['tags'][] = $this->compareTag(
        $tag,
        $warm_lookup_capture,
        $lookup_capture,
        $options,
        $effective_preload_tags
      );
    }

    return $report;
  }

  /**
   * Return tags whose request-level comparison supports adding them.
   *
   * @param array $preload_comparison
   *   Comparison report.
   *
   * @return array
   *   Tags to add.
   */
  public function addTags(array $preload_comparison): array {
    $tags = [];
    foreach (($preload_comparison['tags'] ?? []) as $row) {
      if (str_starts_with((string) $row['decision'], 'Add candidate;')) {
        $tags[] = $row['tag'];
      }
    }

    return $tags;
  }

  /**
   * Compare one tag.
   *
   * @param string $tag
   *   Candidate tag.
   * @param array $warm_lookup_capture
   *   Baseline warm lookup capture.
   * @param \Drupal\carlson_general\CachePreload\CachePreloadAuditLookupCapture $lookup_capture
   *   Lookup capture helper.
   * @param array $options
   *   Parsed options.
   * @param array $effective_preload_tags
   *   Core/default and site preload tags.
   *
   * @return array
   *   Comparison row.
   */
  private function compareTag(
    string $tag,
    array $warm_lookup_capture,
    CachePreloadAuditLookupCapture $lookup_capture,
    array $options,
    array $effective_preload_tags
  ): array {
    $baseline_rows = $this->baselineRowsForTag($warm_lookup_capture, $tag);
    $paths = [];
    foreach ($baseline_rows as $path => $row) {
      $paths[$path] = $row['source'] ?? 'compare candidate';
    }
    if (!$paths) {
      return [
        'tag' => $tag,
        'path_count' => 0,
        'baseline_lookup_groups' => 0,
        'candidate_lookup_groups' => 0,
        'baseline_single_tag_groups' => 0,
        'candidate_single_tag_groups' => 0,
        'query_delta' => 0,
        'decision' => 'Skipped; tag was not detected in baseline pages.',
        'paths' => [],
      ];
    }

    $candidate_capture = $lookup_capture->capture(
      $paths,
      $options['base-url'],
      (int) $options['lookup-warmups'],
      $options['lookup-mysql-user'],
      $options['lookup-mysql-pass'],
      $this->normalizedTags(array_merge($effective_preload_tags, [$tag])),
      [$tag]
    );
    $candidate_rows = [];
    foreach (($candidate_capture['paths'] ?? []) as $row) {
      $candidate_rows[$row['path']] = $row;
    }

    return $this->comparisonRow($tag, $baseline_rows, $candidate_rows);
  }

  /**
   * Build one aggregate comparison row.
   *
   * @param string $tag
   *   Candidate tag.
   * @param array $baseline_rows
   *   Baseline rows keyed by path.
   * @param array $candidate_rows
   *   Candidate rows keyed by path.
   *
   * @return array
   *   Comparison row.
   */
  private function comparisonRow(
    string $tag,
    array $baseline_rows,
    array $candidate_rows
  ): array {
    $baseline_lookup_groups = 0;
    $candidate_lookup_groups = 0;
    $baseline_single_tag_groups = 0;
    $candidate_single_tag_groups = 0;
    $path_rows = [];
    foreach ($baseline_rows as $path => $baseline_row) {
      $candidate_row = $candidate_rows[$path] ?? [];
      $baseline_count = (int) ($baseline_row['lookup_group_count'] ?? 0);
      $candidate_count = (int) ($candidate_row['lookup_group_count'] ?? 0);
      $baseline_single = $this->singleTagGroupCount($baseline_row, $tag);
      $candidate_single = $this->singleTagGroupCount($candidate_row, $tag);
      $baseline_lookup_groups += $baseline_count;
      $candidate_lookup_groups += $candidate_count;
      $baseline_single_tag_groups += $baseline_single;
      $candidate_single_tag_groups += $candidate_single;
      $path_rows[] = [
        'path' => $path,
        'baseline_lookup_groups' => $baseline_count,
        'candidate_lookup_groups' => $candidate_count,
        'baseline_single_tag_groups' => $baseline_single,
        'candidate_single_tag_groups' => $candidate_single,
      ];
    }

    return [
      'tag' => $tag,
      'path_count' => count($baseline_rows),
      'baseline_lookup_groups' => $baseline_lookup_groups,
      'candidate_lookup_groups' => $candidate_lookup_groups,
      'baseline_single_tag_groups' => $baseline_single_tag_groups,
      'candidate_single_tag_groups' => $candidate_single_tag_groups,
      'query_delta' => $baseline_lookup_groups - $candidate_lookup_groups,
      'decision' => $this->comparisonDecision(
        $baseline_lookup_groups,
        $candidate_lookup_groups,
        $baseline_single_tag_groups,
        $candidate_single_tag_groups
      ),
      'paths' => $path_rows,
    ];
  }

  /**
   * Return baseline rows where a tag appeared as a candidate.
   *
   * @param array $warm_lookup_capture
   *   Warm lookup capture report.
   * @param string $tag
   *   Candidate tag.
   *
   * @return array
   *   Baseline rows keyed by path.
   */
  private function baselineRowsForTag(
    array $warm_lookup_capture,
    string $tag
  ): array {
    $rows = [];
    foreach (($warm_lookup_capture['paths'] ?? []) as $row) {
      foreach (($row['candidate_tags'] ?? []) as $tag_row) {
        if (($tag_row['tag'] ?? '') === $tag) {
          $rows[$row['path']] = $row;
          break;
        }
      }
    }

    return $rows;
  }

  /**
   * Count single-tag lookup groups for one tag.
   *
   * @param array $row
   *   Captured path row.
   * @param string $tag
   *   Cache tag.
   *
   * @return int
   *   Single-tag lookup group count.
   */
  private function singleTagGroupCount(array $row, string $tag): int {
    $count = 0;
    foreach (($row['groups'] ?? []) as $group) {
      if (($group['tag_count'] ?? 0) === 1
        && (($group['tags'][0] ?? '') === $tag)
      ) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Return the comparison decision.
   *
   * @param int $baseline_lookup_groups
   *   Baseline lookup count.
   * @param int $candidate_lookup_groups
   *   Candidate lookup count.
   * @param int $baseline_single_tag_groups
   *   Baseline single-tag lookup count for the candidate.
   * @param int $candidate_single_tag_groups
   *   Candidate single-tag lookup count for the candidate.
   *
   * @return string
   *   Decision text.
   */
  private function comparisonDecision(
    int $baseline_lookup_groups,
    int $candidate_lookup_groups,
    int $baseline_single_tag_groups,
    int $candidate_single_tag_groups
  ): string {
    if ($candidate_lookup_groups < $baseline_lookup_groups
      && $candidate_single_tag_groups < $baseline_single_tag_groups
    ) {
      return 'Add candidate; lookup count decreased and single-tag lookup disappeared.';
    }
    if ($candidate_single_tag_groups < $baseline_single_tag_groups) {
      return 'Review manually; single-tag lookup changed but total count did not drop.';
    }

    return 'Do not add; request preload did not reduce the lookup evidence.';
  }

  /**
   * Normalize tag arrays.
   *
   * @param mixed $tags
   *   Tags to normalize.
   *
   * @return array
   *   Unique non-empty tags.
   */
  private function normalizedTags(mixed $tags): array {
    $tags = array_filter(array_map('strval', (array) $tags));
    return array_values(array_unique($tags));
  }

}
