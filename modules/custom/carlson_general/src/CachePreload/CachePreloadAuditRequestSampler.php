<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\node\NodeInterface;

/**
 * Samples representative paths and runtime cache-tag headers.
 */
final class CachePreloadAuditRequestSampler {

  /**
   * Build a representative page sample.
   *
   * @param int $samples_per_bundle
   *   Number of node paths to sample per bundle.
   * @param int $path_limit
   *   Maximum number of paths to sample.
   *
   * @return array
   *   Paths keyed by path with the sample source as the value.
   */
  public function samplePaths(
    int $samples_per_bundle,
    int $path_limit
  ): array {
    $paths = [];
    $this->addPath($paths, '/', 'fixed: front page');
    $this->addPath($paths, '/news', 'fixed: news landing');
    $this->addPath($paths, '/academics', 'fixed: academics landing');

    $entity_type_manager = \Drupal::entityTypeManager();
    $alias_manager = \Drupal::service('path_alias.manager');
    $view_storage = $entity_type_manager->getStorage('view');

    foreach ($view_storage->loadMultiple() as $view) {
      if (method_exists($view, 'status') && !$view->status()) {
        continue;
      }
      foreach (($view->get('display') ?? []) as $display_id => $display) {
        if (($display['display_plugin'] ?? '') !== 'page') {
          continue;
        }
        $path = $display['display_options']['path'] ?? '';
        if ($path && !str_contains($path, '%') && !str_contains($path, '{')) {
          $this->addPath(
            $paths,
            '/' . $path,
            'view: ' . $view->id() . ':' . $display_id
          );
        }
        if ($path_limit > 0 && count($paths) >= $path_limit) {
          return $paths;
        }
      }
    }

    $bundle_counts = \Drupal::database()->query(
      "SELECT type, COUNT(*) FROM {node_field_data}
       WHERE status = 1 GROUP BY type ORDER BY COUNT(*) DESC"
    )->fetchAllKeyed();
    $node_storage = $entity_type_manager->getStorage('node');

    foreach (array_keys($bundle_counts) as $bundle) {
      $query = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->condition('type', $bundle)
        ->sort('changed', 'DESC');

      if ($samples_per_bundle > 0) {
        $query->range(0, $samples_per_bundle);
      }

      $node_ids = $query->execute();

      foreach ($node_storage->loadMultiple($node_ids) as $node) {
        if (!$node instanceof NodeInterface) {
          continue;
        }
        $path = $alias_manager->getAliasByPath('/node/' . $node->id());
        $this->addPath($paths, $path, 'node ' . $bundle . ':' . $node->id());
        if ($path_limit > 0 && count($paths) >= $path_limit) {
          return $paths;
        }
      }
    }

    return $paths;
  }

  /**
   * Fetch pages and inspect cacheability debug headers when enabled.
   *
   * @param array $paths
   *   Paths keyed by path with the sample source as the value.
   * @param string $base_url
   *   Base URL to fetch.
   * @param array $candidate_tags
   *   Candidate cache tags to check in response headers.
   *
   * @return array
   *   HTTP sample results.
   */
  public function httpResults(
    array $paths,
    string $base_url,
    array $candidate_tags
  ): array {
    $client = \Drupal::httpClient();
    $results = [];

    foreach ($paths as $path => $source) {
      $row = [
        'path' => $path,
        'source' => $source,
        'status' => 0,
        'tags' => [],
        'view_markers' => [],
      ];
      try {
        $response = $client->request('GET', $base_url . $path, [
          'allow_redirects' => TRUE,
          'http_errors' => FALSE,
          'timeout' => 20,
          'verify' => FALSE,
          'headers' => ['User-Agent' => 'CSM-226 cache preload audit'],
        ]);
        $row['status'] = $response->getStatusCode();
        $row['tags'] = $this->splitTags(
          $response->getHeaderLine('x-drupal-cache-tags')
        );
        $row['view_markers'] = $this->viewMarkers($row['tags']);
        foreach ($candidate_tags as $tag) {
          $row['candidate_hits'][$tag] = in_array($tag, $row['tags'], TRUE);
        }
      }
      catch (\Throwable $exception) {
        $row['error'] = $exception->getMessage();
      }
      $results[] = $row;
    }

    return $results;
  }

  /**
   * Count every observed cache tag by rendered page frequency.
   *
   * @param array $http_results
   *   HTTP sample results.
   *
   * @return array
   *   Cache tag frequency data.
   */
  public function tagFrequency(array $http_results): array {
    $frequency = [
      'sampled_pages' => count($http_results),
      'pages_with_headers' => 0,
      'unique_tags' => 0,
      'tags' => [],
    ];

    foreach ($http_results as $row) {
      if (empty($row['tags'])) {
        continue;
      }
      $frequency['pages_with_headers']++;
      foreach (array_unique($row['tags']) as $tag) {
        if (!isset($frequency['tags'][$tag])) {
          $frequency['tags'][$tag] = [
            'page_count' => 0,
            'sample_paths' => [],
          ];
        }
        $frequency['tags'][$tag]['page_count']++;
        if (count($frequency['tags'][$tag]['sample_paths']) < 8) {
          $frequency['tags'][$tag]['sample_paths'][] = $row['path'];
        }
      }
    }

    uasort($frequency['tags'], static function (
      array $left,
      array $right
    ): int {
      return $right['page_count'] <=> $left['page_count'];
    });
    $frequency['unique_tags'] = count($frequency['tags']);

    return $frequency;
  }

  /**
   * Add a normalized path to the sample list.
   *
   * @param array $paths
   *   Existing path samples, keyed by path.
   * @param string $path
   *   Path to add.
   * @param string $source
   *   Description of why the path was sampled.
   */
  private function addPath(array &$paths, string $path, string $source): void {
    $path = '/' . ltrim($path, '/');
    $path = $path === '//' ? '/' : $path;
    $paths[$path] = $paths[$path] ?? $source;
  }

  /**
   * Split cache tag headers.
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
   * Return cache tags that indicate rendered page usage of Views.
   *
   * @param array $tags
   *   Cache tags from a response.
   *
   * @return array
   *   View-related cache tags.
   */
  private function viewMarkers(array $tags): array {
    return array_values(array_filter($tags, static function ($tag): bool {
      return str_starts_with($tag, 'config:views.view.')
        || str_starts_with($tag, 'block_view:views_block:');
    }));
  }

}
