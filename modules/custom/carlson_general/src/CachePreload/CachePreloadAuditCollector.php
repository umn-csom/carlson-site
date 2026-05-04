<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Site\Settings;

/**
 * Collects CSM-226 cache preload audit evidence.
 *
 * This is the orchestration layer for the Drush script. It keeps the audit
 * workflow in one place while delegating page sampling, cache probing,
 * candidate review, and report rendering to focused helper classes.
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
    $request_sampler = new CachePreloadAuditRequestSampler();
    $candidate_reviewer = new CachePreloadAuditCandidateReviewer();
    $current_preload_tags = Settings::get('cache_preload_tags', []);
    $candidate_tags = $this->candidateTags($current_preload_tags);
    $paths = $request_sampler->samplePaths(
      (int) $options['samples-per-bundle'],
      (int) $options['path-limit'],
      $this->seedMenus($options['seed-menus'])
    );
    $http_results = $options['skip-http']
      ? []
      : $request_sampler->httpResults(
        $paths,
        $options['base-url'],
        $candidate_tags
    );
    $tag_frequency = $request_sampler->tagFrequency($http_results);

    // First pass: classify high-frequency tags before measurement so we know
    // which stable candidates deserve one-off checksum probes.
    $candidate_review = $candidate_reviewer->review(
      $tag_frequency,
      $current_preload_tags,
      [],
      (int) $options['candidate-coverage-threshold'],
      (int) $options['candidate-review-limit']
    );
    $candidate_probe_tags = $candidate_reviewer->probeTags(
      $candidate_review,
      (int) $options['candidate-probe-limit']
    );

    // Cache evidence and reads include the fixed probe list plus any dynamic
    // candidates selected from the observed response-header frequency table.
    $measured_candidate_tags = array_values(array_unique(array_merge(
      $candidate_tags,
      $candidate_probe_tags
    )));
    $cache_evidence = $cache_probe->cacheEvidence($measured_candidate_tags);
    $cache_reads = $cache_probe->cacheReads(
      $cache_evidence['tables'],
      $measured_candidate_tags,
      (int) $options['cache-read-limit']
    );
    $measurements = $cache_probe->measurements(
      $cache_reads,
      $current_preload_tags,
      $candidate_probe_tags
    );

    // Second pass: attach measurement results to candidate rows so the report
    // can say whether a frequent tag actually reduced cachetags queries.
    $candidate_review = $candidate_reviewer->review(
      $tag_frequency,
      $current_preload_tags,
      $measurements,
      (int) $options['candidate-coverage-threshold'],
      (int) $options['candidate-review-limit']
    );

    return [
      'options' => $options,
      'current_preload_tags' => $current_preload_tags,
      'candidate_tags' => $measured_candidate_tags,
      'paths' => $paths,
      'http_results' => $http_results,
      'tag_frequency' => $tag_frequency,
      'candidate_review' => $candidate_review,
      'candidate_probe_tags' => $candidate_probe_tags,
      'cache_evidence' => $cache_evidence,
      'cache_reads' => $cache_reads,
      'measurements' => $measurements,
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
      'samples-per-bundle' => 10,
      'path-limit' => 250,
      'cache-read-limit' => 250,
      'candidate-coverage-threshold' => 50,
      'candidate-review-limit' => 75,
      'candidate-probe-limit' => 5,
      'frequency-csv' => '',
      'skip-http' => FALSE,
    ];

    foreach (array_slice($argv, 1) as $arg) {
      if ($arg === '--skip-http') {
        $options['skip-http'] = TRUE;
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
      'samples-per-bundle',
      'path-limit',
      'cache-read-limit',
      'candidate-coverage-threshold',
      'candidate-review-limit',
      'candidate-probe-limit',
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
