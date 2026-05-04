<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\NodeInterface;

/**
 * Samples representative paths and runtime cache-tag headers.
 *
 * The path sample intentionally starts with likely public navigation pages,
 * then broadens to Views page displays and recent published content. The
 * report's Source column comes from this class.
 */
final class CachePreloadAuditRequestSampler {

  /**
   * Build a representative page sample.
   *
   * @param int $samples_per_bundle
   *   Number of node paths to sample per bundle.
   * @param int $path_limit
   *   Maximum number of paths to sample.
   * @param array $seed_menus
   *   Menu machine names to use for important page seed discovery. Leave empty
   *   to discover menus from active front-end menu blocks.
   *
   * @return array
   *   Paths keyed by path with the sample source as the value.
   */
  public function samplePaths(
    int $samples_per_bundle,
    int $path_limit,
    array $seed_menus = []
  ): array {
    $paths = [];
    $this->addPath($paths, '/', 'fixed: front page');
    if ($this->pathLimitReached($paths, $path_limit)) {
      return $paths;
    }
    if ($this->addMenuSeedPaths($paths, $seed_menus, $path_limit)) {
      return $paths;
    }

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
   * Add internal menu links as important seed paths.
   *
   * @param array $paths
   *   Existing path samples, keyed by path.
   * @param array $menu_names
   *   Menu machine names to inspect.
   * @param int $path_limit
   *   Maximum number of paths to sample.
   *
   * @return bool
   *   TRUE when the path limit has been reached.
   */
  private function addMenuSeedPaths(
    array &$paths,
    array $menu_names,
    int $path_limit
  ): bool {
    $menu_tree = \Drupal::menuTree();
    $account_switcher = \Drupal::service('account_switcher');
    $menu_names = $menu_names ?: $this->menuSeedNamesFromBlocks();

    // Drush usually runs as an administrative user. Switch to anonymous before
    // applying menu access checks so menu seeds match public navigation.
    $account_switcher->switchTo(new AnonymousUserSession());
    try {
      foreach ($menu_names as $menu_name) {
        $parameters = new MenuTreeParameters();
        $parameters->onlyEnabledLinks();
        $tree = $menu_tree->load($menu_name, $parameters);
        $tree = $menu_tree->transform(
          $tree,
          $this->anonymousMenuTreeManipulators()
        );
        if ($this->addMenuTreePaths($paths, $tree, $menu_name, $path_limit)) {
          return TRUE;
        }
      }
    }
    finally {
      $account_switcher->switchBack();
    }

    return FALSE;
  }

  /**
   * Discover menu names from active menu blocks in the default front-end theme.
   *
   * @return array
   *   Menu machine names keyed in block display order.
   */
  private function menuSeedNamesFromBlocks(): array {
    $theme = (string) \Drupal::config('system.theme')->get('default');
    $region_order = $this->themeRegionOrder($theme);
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'theme' => $theme,
        'status' => TRUE,
      ]);

    uasort($blocks, static function ($left, $right) use ($region_order): int {
      return [
        $region_order[$left->getRegion()] ?? PHP_INT_MAX,
        $left->getWeight(),
        $left->id(),
      ] <=> [
        $region_order[$right->getRegion()] ?? PHP_INT_MAX,
        $right->getWeight(),
        $right->id(),
      ];
    });

    $menu_names = [];
    foreach ($blocks as $block) {
      // Menu blocks expose the source menu as the plugin derivative ID.
      if (preg_match(
        '/^(?:system_menu_block|menu_block):(.+)$/',
        $block->getPluginId(),
        $matches
      )) {
        $menu_names[$matches[1]] = $matches[1];
      }
    }

    return array_values($menu_names ?: ['main']);
  }

  /**
   * Return theme region positions keyed by region name.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Region positions keyed by region name.
   */
  private function themeRegionOrder(string $theme): array {
    if (!function_exists('system_region_list')) {
      return [];
    }

    return array_flip(array_keys(system_region_list($theme)));
  }

  /**
   * Return the menu transforms used before sampling anonymous menu links.
   *
   * @return array
   *   Menu tree manipulator definitions.
   */
  private function anonymousMenuTreeManipulators(): array {
    return [
      // Match Drupal's rendered menu behavior: resolve node access first,
      // then prune inaccessible menu links and sort in display order.
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
  }

  /**
   * Add internal links from a loaded menu tree.
   *
   * @param array $paths
   *   Existing path samples, keyed by path.
   * @param array $tree
   *   Loaded menu tree elements.
   * @param string $menu_name
   *   Menu machine name.
   * @param int $path_limit
   *   Maximum number of paths to sample.
   *
   * @return bool
   *   TRUE when the path limit has been reached.
   */
  private function addMenuTreePaths(
    array &$paths,
    array $tree,
    string $menu_name,
    int $path_limit
  ): bool {
    $level = $tree;
    while ($level) {
      $next_level = [];
      foreach ($level as $element) {
        // checkAccess() keeps inaccessible top-level elements for cacheability
        // metadata, but rendered menus skip them. The sampler should too.
        if ($element->access instanceof AccessResultInterface
          && !$element->access->isAllowed()
        ) {
          continue;
        }
        $path = $this->menuLinkPath($element->link);
        if ($path !== NULL) {
          $this->addPath(
            $paths,
            $path,
            'menu: ' . $menu_name . ': ' . $element->link->getTitle()
          );
          if ($this->pathLimitReached($paths, $path_limit)) {
            return TRUE;
          }
        }
        foreach ($element->subtree as $child) {
          $next_level[] = $child;
        }
      }
      $level = $next_level;
    }

    return FALSE;
  }

  /**
   * Return the local path for an internal menu link.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   Menu link plugin.
   *
   * @return string|null
   *   Local path, or NULL when the link is external or not renderable.
   */
  private function menuLinkPath(MenuLinkInterface $link): ?string {
    try {
      $url = $link->getUrlObject();
      if ($url->isExternal()) {
        return NULL;
      }
      if ($url->isRouted()
        && in_array($url->getRouteName(), ['<nolink>', '<button>'], TRUE)
      ) {
        return NULL;
      }
      $path = parse_url($url->toString(), PHP_URL_PATH);
    }
    catch (\Throwable $exception) {
      return NULL;
    }

    if (!$path || str_starts_with($path, '/admin')) {
      return NULL;
    }
    if (str_contains($path, '%') || str_contains($path, '{')) {
      return NULL;
    }

    return $path;
  }

  /**
   * Check whether the configured path limit has been reached.
   *
   * @param array $paths
   *   Current path samples.
   * @param int $path_limit
   *   Maximum number of paths to sample. Zero means no limit.
   *
   * @return bool
   *   TRUE when the limit is non-zero and has been reached.
   */
  private function pathLimitReached(array $paths, int $path_limit): bool {
    return $path_limit > 0 && count($paths) >= $path_limit;
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
