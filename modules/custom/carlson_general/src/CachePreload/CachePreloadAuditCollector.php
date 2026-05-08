<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Site\Settings;

/**
 * Collects CSM-226 cache preload audit evidence.
 *
 * This is the orchestration layer for the Drush script. It keeps the audit
 * workflow in one place while delegating page sampling, lookup capture, cache
 * probing, and report rendering to focused helper classes.
 */
final class CachePreloadAuditCollector {

  /**
   * Collect all report data.
   *
   * @param array $argv
   *   CLI arguments passed to the Drush script.
   *
   * @return array
   *   The collected audit data.
   */
  public function collect(array $argv): array {
    $options = $this->options($argv);
    $cache_probe = new CachePreloadAuditCacheProbe();
    $comparison_builder = new CachePreloadAuditComparisonBuilder();
    $lookup_capture = new CachePreloadAuditLookupCapture();
    $request_sampler = new CachePreloadAuditRequestSampler();
    $recommendation_builder = new CachePreloadAuditRecommendationBuilder();
    $site_preload_tags = $this->normalizedTags(
      Settings::get('cache_preload_tags', [])
    );
    $core_preload_tags = $this->corePreloadTags();
    $effective_preload_tags = $this->normalizedTags(array_merge(
      $core_preload_tags,
      $site_preload_tags
    ));
    $candidate_tags = $this->candidateTags($effective_preload_tags);
    $lookup_paths = $this->lookupPaths(
      $options['lookup-paths'],
      $request_sampler,
      $this->seedMenus($options['seed-menus']),
      (int) $options['lookup-menu-depth'],
      (int) $options['lookup-content-samples-per-bundle'],
      (int) $options['lookup-path-limit']
    );
    $warm_lookup_capture = $options['skip-lookup-capture']
      ? [
        'enabled' => FALSE,
        'unsupported' => 'Skipped by option.',
        'paths' => [],
      ]
      : $lookup_capture->capture(
        $lookup_paths,
        $options['base-url'],
        (int) $options['lookup-warmups'],
        $options['lookup-mysql-user'],
        $options['lookup-mysql-pass'],
        $effective_preload_tags
    );
    $lookup_probe_tags = $recommendation_builder->candidateTags(
      $warm_lookup_capture
    );

    // Cache evidence and reads include the fixed probe list plus any dynamic
    // candidates selected from warm lookup-group evidence.
    $measured_candidate_tags = array_values(array_unique(array_merge(
      $candidate_tags,
      $lookup_probe_tags
    )));
    $cache_evidence = $cache_probe->cacheEvidence($measured_candidate_tags);
    $cache_reads = $cache_probe->cacheReads(
      $cache_evidence['tables'],
      $measured_candidate_tags,
      (int) $options['cache-read-limit']
    );
    $measurements = $cache_probe->measurements(
      $cache_reads,
      $effective_preload_tags,
      $lookup_probe_tags
    );
    $preload_recommendations = $recommendation_builder->build(
      $warm_lookup_capture,
      $measurements
    );
    $compare_tags = $comparison_builder->compareTagsOption(
      $options['compare-tags'],
      $lookup_probe_tags
    );
    $preload_comparison = $comparison_builder->build(
      $compare_tags,
      $warm_lookup_capture,
      $lookup_capture,
      $options,
      $effective_preload_tags
    );
    $comparison_add_tags = $comparison_builder->addTags($preload_comparison);
    if ($comparison_add_tags) {
      $preload_recommendations['add_tags'] = array_values(array_unique(
        array_merge($preload_recommendations['add_tags'], $comparison_add_tags)
      ));
      $preload_recommendations['decision_rule'] = 'Add tags only when '
        . 'request-level comparison shows fewer warm cachetags lookups.';
    }

    return [
      'options' => $options,
      'core_preload_tags' => $core_preload_tags,
      'site_preload_tags' => $site_preload_tags,
      'effective_preload_tags' => $effective_preload_tags,
      'current_preload_tags' => $site_preload_tags,
      'candidate_tags' => $measured_candidate_tags,
      'lookup_paths' => $lookup_paths,
      'lookup_probe_tags' => $lookup_probe_tags,
      'cache_evidence' => $cache_evidence,
      'cache_reads' => $cache_reads,
      'measurements' => $measurements,
      'warm_lookup_capture' => $warm_lookup_capture,
      'preload_recommendations' => $preload_recommendations,
      'preload_comparison' => $preload_comparison,
    ];
  }

  /**
   * Parse CLI options.
   *
   * @param array $argv
   *   CLI arguments passed to the Drush script.
   *
   * @return array
   *   Parsed options.
   */
  private function options(array $argv): array {
    $options = [
      'base-url' => 'https://carlsonschool.ddev.site',
      'seed-menus' => 'auto',
      'cache-read-limit' => 250,
      'lookup-paths' => 'auto',
      'lookup-menu-depth' => 2,
      'lookup-content-samples-per-bundle' => 1,
      'lookup-path-limit' => 0,
      'lookup-warmups' => 1,
      'lookup-mysql-user' => 'root',
      'lookup-mysql-pass' => 'root',
      'compare-tags' => '',
      'skip-lookup-capture' => FALSE,
    ];

    foreach (array_slice($argv, 1) as $arg) {
      if ($arg === '--skip-lookup-capture') {
        $options['skip-lookup-capture'] = TRUE;
        continue;
      }
      if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$name, $value] = explode('=', substr($arg, 2), 2);
        if (array_key_exists($name, $options)) {
          $options[$name] = $value;
        }
      }
    }

    $integer_options = [
      'cache-read-limit',
      'lookup-menu-depth',
      'lookup-content-samples-per-bundle',
      'lookup-path-limit',
      'lookup-warmups',
    ];
    foreach ($integer_options as $name) {
      $options[$name] = max(0, (int) $options[$name]);
    }
    $options['base-url'] = rtrim((string) $options['base-url'], '/');

    return $options;
  }

  /**
   * Return the non-core tags worth probing.
   *
   * @param array $current_preload_tags
   *   Cache preload tags currently configured for the site.
   *
   * @return array
   *   Candidate tags to include in the audit.
   */
  private function candidateTags(array $current_preload_tags): array {
    $tags = array_merge($current_preload_tags, [
      'config:system.menu.main',
      'config:system.menu.footer',
      'config:block_list',
      'block_view',
      'node_list',
      'node_view',
      'rendered',
      'http_response',
      'config:system.site',
      'config:user.role.anonymous',
    ]);

    return array_values(array_unique(array_filter($tags)));
  }

  /**
   * Return the Drupal core/default preload tags for this codebase.
   *
   * @return array
   *   Core preload tags registered before site settings are merged.
   */
  private function corePreloadTags(): array {
    return [
      'route_match',
      'access_policies',
      'routes',
      'router',
      'entity_types',
      'entity_field_info',
      'entity_bundles',
      'local_task',
      'library_info',
    ];
  }

  /**
   * Parse comma-separated warm lookup paths.
   *
   * @param string $lookup_paths
   *   Comma-separated paths to capture.
   *
   * @return array
   *   Paths keyed by normalized path with source labels as values.
   */
  private function lookupPaths(
    string $lookup_paths,
    CachePreloadAuditRequestSampler $request_sampler,
    array $seed_menus,
    int $menu_depth,
    int $content_samples_per_bundle,
    int $path_limit
  ): array {
    if (strtolower(trim($lookup_paths)) === 'auto') {
      return $request_sampler->sampleLookupPaths(
        $menu_depth,
        $content_samples_per_bundle,
        $path_limit,
        $seed_menus
      );
    }

    $paths = [];
    $requested_paths = array_filter(
      array_map('trim', explode(',', $lookup_paths))
    );
    foreach ($requested_paths as $path) {
      $path = '/' . ltrim($path, '/');
      $paths[$path] = 'lookup-path option';
    }

    return $paths;
  }

  /**
   * Normalize preload tag arrays from settings and defaults.
   *
   * @param mixed $tags
   *   Tags to normalize.
   *
   * @return array
   *   Sorted unique non-empty tags.
   */
  private function normalizedTags(mixed $tags): array {
    $tags = array_filter(array_map('strval', (array) $tags));
    return array_values(array_unique($tags));
  }

  /**
   * Parse comma-separated seed menus.
   *
   * @param string $seed_menus
   *   Comma-separated menu machine names, or "auto" for active menu blocks.
   *
   * @return array
   *   Menu machine names to use for seed path discovery. Empty means auto.
   */
  private function seedMenus(string $seed_menus): array {
    if (strtolower(trim($seed_menus)) === 'auto') {
      return [];
    }

    $menus = array_filter(array_map('trim', explode(',', $seed_menus)));
    return array_values($menus);
  }

}
