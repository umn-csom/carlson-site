<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Site\Settings;

/**
 * Collects CSM-226 cache preload audit evidence.
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
    $current_preload_tags = Settings::get('cache_preload_tags', []);
    $candidate_tags = $this->candidateTags($current_preload_tags);
    $paths = $request_sampler->samplePaths(
      (int) $options['samples-per-bundle'],
      (int) $options['path-limit']
    );
    $cache_evidence = $cache_probe->cacheEvidence($candidate_tags);
    $cache_reads = $cache_probe->cacheReads(
      $cache_evidence['tables'],
      $candidate_tags,
      (int) $options['cache-read-limit']
    );
    $http_results = $options['skip-http']
      ? []
      : $request_sampler->httpResults(
        $paths,
        $options['base-url'],
        $candidate_tags
      );

    return [
      'options' => $options,
      'current_preload_tags' => $current_preload_tags,
      'candidate_tags' => $candidate_tags,
      'paths' => $paths,
      'http_results' => $http_results,
      'tag_frequency' => $request_sampler->tagFrequency($http_results),
      'cache_evidence' => $cache_evidence,
      'cache_reads' => $cache_reads,
      'measurements' => $cache_probe->measurements(
        $cache_reads,
        $current_preload_tags
      ),
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
      'samples-per-bundle' => 10,
      'path-limit' => 250,
      'cache-read-limit' => 250,
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

    $integer_options = ['samples-per-bundle', 'path-limit', 'cache-read-limit'];
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

}
