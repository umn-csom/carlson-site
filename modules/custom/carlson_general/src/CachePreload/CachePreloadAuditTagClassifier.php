<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Classifies observed cache tags for preload review.
 *
 * The goal is conservative. A frequent tag is only a candidate when it is also
 * stable and reusable enough to be sensible in a static preload list.
 */
final class CachePreloadAuditTagClassifier {

  /**
   * Tags that are too broad for static preload selection.
   */
  private const BROAD_TAGS = [
    'block_content_view',
    'block_view',
    'http_response',
    'media_view',
    'node_list',
    'node_view',
    'paragraph_view',
    'rendered',
    'taxonomy_term_view',
  ];

  /**
   * Classify one observed cache tag.
   *
   * @param string $tag
   *   Cache tag.
   * @param float $coverage
   *   Observed coverage percentage.
   * @param int $coverage_threshold
   *   Minimum coverage percentage for candidate consideration.
   * @param array $current_preload_tags
   *   Cache preload tags currently configured for the site.
   *
   * @return array
   *   Classification and reason.
   */
  public function classify(
    string $tag,
    float $coverage,
    int $coverage_threshold,
    array $current_preload_tags
  ): array {
    if (in_array($tag, $current_preload_tags, TRUE)) {
      return [
        'classification' => 'current_preload',
        'reason' => 'Already configured in cache_preload_tags.',
      ];
    }

    if ($coverage < $coverage_threshold) {
      return [
        'classification' => 'excluded_low_frequency',
        'reason' => 'Below the configured coverage threshold.',
      ];
    }

    if ($this->isEntitySpecific($tag)) {
      return [
        'classification' => 'excluded_entity_specific',
        'reason' => 'Entity-specific tags are too granular to preload.',
      ];
    }

    if ($this->isBroadTag($tag)) {
      return [
        'classification' => 'excluded_broad',
        'reason' => 'Broad render/list tags are invalidated too widely.',
      ];
    }

    if (str_starts_with($tag, 'config:block.block.')) {
      return [
        'classification' => 'excluded_granular_config',
        'reason' => 'Individual block config tags would bloat the list.',
      ];
    }

    if ($this->isStableConfigCandidate($tag)) {
      return [
        'classification' => 'candidate',
        'reason' => 'High-coverage stable config-style tag.',
      ];
    }

    return [
      'classification' => 'excluded_runtime_specific',
      'reason' => 'Runtime/module-specific tag, not a stable config target.',
    ];
  }

  /**
   * Return display priority for a classification.
   *
   * @param string $classification
   *   Candidate classification.
   *
   * @return int
   *   Lower values are shown first.
   */
  public function classificationPriority(string $classification): int {
    $priorities = [
      'current_preload' => 0,
      'candidate' => 1,
      'excluded_broad' => 2,
      'excluded_granular_config' => 3,
      'excluded_entity_specific' => 4,
      'excluded_runtime_specific' => 5,
      'excluded_low_frequency' => 6,
    ];

    return $priorities[$classification] ?? 99;
  }

  /**
   * Return display priority for a cache tag.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return int
   *   Lower values are shown first.
   */
  public function tagPriority(string $tag): int {
    if ($tag === 'config:block_list') {
      return 0;
    }
    if ($tag === 'config:system.menu.main') {
      return 1;
    }
    if (str_starts_with($tag, 'config:system.menu.')) {
      return 2;
    }
    if ($tag === 'config:system.site') {
      return 3;
    }
    if (str_starts_with($tag, 'config:user.role.')) {
      return 4;
    }
    if (str_starts_with($tag, 'config:views.view.')) {
      return 5;
    }

    return 99;
  }

  /**
   * Check whether a tag points at one entity item.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return bool
   *   TRUE when the tag is entity-specific.
   */
  private function isEntitySpecific(string $tag): bool {
    return preg_match(
      '/^(block_content|file|media|node|paragraph|taxonomy_term|user):\d+$/',
      $tag
    ) === 1;
  }

  /**
   * Check whether a tag is too broad for preload selection.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return bool
   *   TRUE when the tag is too broad.
   */
  private function isBroadTag(string $tag): bool {
    if (in_array($tag, self::BROAD_TAGS, TRUE)) {
      return TRUE;
    }

    return preg_match('/^[a-z0-9_]+_(list|view)(:|$)/', $tag) === 1;
  }

  /**
   * Check whether a tag is a stable config-like candidate.
   *
   * @param string $tag
   *   Cache tag.
   *
   * @return bool
   *   TRUE when the tag is worth a checksum probe.
   */
  private function isStableConfigCandidate(string $tag): bool {
    if (in_array($tag, ['config:block_list', 'views_data'], TRUE)) {
      return TRUE;
    }

    // Prefer low-cardinality config tags. Entity instance tags and broad
    // rendered/list tags are excluded earlier in the classification flow.
    $prefixes = [
      'config:core.extension',
      'config:system.menu.',
      'config:system.site',
      'config:system.theme',
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

}
