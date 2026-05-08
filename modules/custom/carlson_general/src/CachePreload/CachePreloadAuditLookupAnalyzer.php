<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Analyzes cachetags SQL lookup groups for preload evidence.
 */
final class CachePreloadAuditLookupAnalyzer {

  /**
   * Convert SQL queries into lookup groups.
   *
   * @param array $queries
   *   SQL query strings.
   * @param array $effective_preload_tags
   *   Tags already preloaded.
   *
   * @return array
   *   Lookup groups.
   */
  public function lookupGroups(
    array $queries,
    array $effective_preload_tags
  ): array {
    $groups = [];
    foreach ($queries as $query) {
      $tags = $this->queryTags($query);
      if (!$tags) {
        continue;
      }
      $stable_tags = array_values(array_filter(
        $tags,
        fn(string $tag): bool => $this->isStableReusableTag($tag)
      ));
      $groups[] = [
        'tag_count' => count($tags),
        'stable_tags' => $stable_tags,
        'preloaded_tags' => array_values(array_intersect(
          $effective_preload_tags,
          $tags
        )),
      ];
    }

    return $groups;
  }

  /**
   * Summarize lookup groups into preload candidate signals.
   *
   * @param array $groups
   *   Lookup groups.
   * @param array $effective_preload_tags
   *   Tags already preloaded.
   *
   * @return array
   *   Summary fields.
   */
  public function summarizeGroups(
    array $groups,
    array $effective_preload_tags
  ): array {
    $stable_counts = [];
    foreach ($groups as $group) {
      foreach (array_unique($group['stable_tags']) as $tag) {
        $stable_counts[$tag] = ($stable_counts[$tag] ?? 0) + 1;
      }
    }
    arsort($stable_counts);

    $repeated = array_filter(
      $stable_counts,
      static fn(int $count): bool => $count > 1
    );
    $preloaded = array_intersect_key(
      $repeated,
      array_flip($effective_preload_tags)
    );
    $candidates = array_diff_key(
      $repeated,
      array_flip($effective_preload_tags)
    );

    return [
      'query_count' => count($groups),
      'lookup_group_count' => count($groups),
      'largest_group_tag_count' => $this->largestGroupTagCount($groups),
      'candidate_tags' => $this->countRows($candidates),
      'repeated_preloaded_tags' => $this->countRows($preloaded),
      'repeated_stable_tags' => $this->countRows($repeated),
      'groups' => $groups,
      'recommendation' => $this->recommendation(
        $groups,
        $candidates,
        $preloaded
      ),
    ];
  }

  /**
   * Extract quoted cache tags from one SQL lookup.
   *
   * @param string $query
   *   SQL query.
   *
   * @return array
   *   Sorted unique cache tags.
   */
  private function queryTags(string $query): array {
    preg_match_all("/'((?:\\\\'|[^'])*)'/", $query, $matches);
    $tags = array_map('stripcslashes', $matches[1] ?? []);
    sort($tags);

    return array_values(array_unique($tags));
  }

  /**
   * Check whether a tag has a stable shape worth reviewing across groups.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return bool
   *   TRUE when the tag is stable and reusable enough to inspect.
   */
  private function isStableReusableTag(string $tag): bool {
    $exact = [
      'access_policies',
      'config:block_list',
      'config:core.extension',
      'config:system.site',
      'entity_bundles',
      'entity_field_info',
      'entity_types',
      'library_info',
      'local_task',
      'route_match',
      'router',
      'routes',
      'views_data',
    ];
    if (in_array($tag, $exact, TRUE)) {
      return TRUE;
    }

    $prefixes = [
      'config:system.menu.',
      'config:user.role.',
      'config:views.view.',
    ];
    foreach ($prefixes as $prefix) {
      if (str_starts_with($tag, $prefix)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Return group-count rows for a table cell.
   *
   * @param array $counts
   *   Counts keyed by tag.
   *
   * @return array
   *   Rows with tag and group count.
   */
  private function countRows(array $counts): array {
    $rows = [];
    foreach ($counts as $tag => $count) {
      $rows[] = ['tag' => $tag, 'group_count' => $count];
    }

    return $rows;
  }

  /**
   * Return the largest tag count found in any lookup group.
   *
   * @param array $groups
   *   Lookup groups.
   *
   * @return int
   *   Largest group tag count.
   */
  private function largestGroupTagCount(array $groups): int {
    $counts = array_column($groups, 'tag_count');
    return $counts ? max($counts) : 0;
  }

  /**
   * Explain what the captured groups mean for preload.
   *
   * @param array $groups
   *   Lookup groups.
   * @param array $candidates
   *   Repeated non-preloaded stable tags.
   * @param array $preloaded
   *   Repeated already-preloaded stable tags.
   *
   * @return string
   *   Recommendation.
   */
  private function recommendation(
    array $groups,
    array $candidates,
    array $preloaded
  ): string {
    if (!$groups) {
      return 'No cachetags lookup captured for this warm request.';
    }
    if (count($groups) === 1) {
      return 'No new tag from this page; only one lookup group was captured.';
    }
    if ($candidates) {
      return 'Test repeated stable tags from this request before adding them.';
    }
    if ($preloaded) {
      return 'Repeated stable tags are already in the effective preload list.';
    }

    return 'No stable tag repeated across lookup groups.';
  }

}
