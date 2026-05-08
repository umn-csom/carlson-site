<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\NodeInterface;

/**
 * Samples representative paths for warm cachetags lookup capture.
 */
final class CachePreloadAuditRequestSampler {

  /**
   * Build the focused warm-request lookup sample.
   *
   * @param int $menu_depth
   *   Menu levels to include from anonymous-accessible navigation.
   * @param int $content_samples_per_bundle
   *   Published anonymous-accessible node examples per content type.
   * @param int $path_limit
   *   Maximum number of paths to sample. Zero means no limit.
   * @param array $seed_menus
   *   Menu machine names to inspect. Empty means auto-discover menu blocks.
   *
   * @return array
   *   Paths keyed by path with the sample source as the value.
   */
  public function sampleLookupPaths(
    int $menu_depth,
    int $content_samples_per_bundle,
    int $path_limit,
    array $seed_menus = []
  ): array {
    $paths = [];
    $this->addPath($paths, '/', 'fixed: front page');
    if ($this->pathLimitReached($paths, $path_limit)) {
      return $paths;
    }

    if ($menu_depth > 0
      && $this->addMenuSeedPaths(
        $paths,
        $seed_menus,
        $path_limit,
        $menu_depth
      )
    ) {
      return $paths;
    }

    if ($content_samples_per_bundle > 0) {
      $this->addContentTypeExamplePaths(
        $paths,
        $content_samples_per_bundle,
        $path_limit
      );
    }

    return $paths;
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
    int $path_limit,
    int $max_depth = 0
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
        if ($this->addMenuTreePaths(
          $paths,
          $tree,
          $menu_name,
          $path_limit,
          $max_depth
        )) {
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
    int $path_limit,
    int $max_depth = 0
  ): bool {
    $level = $tree;
    $depth = 1;
    while ($level && ($max_depth <= 0 || $depth <= $max_depth)) {
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
            'menu level ' . $depth . ': ' . $menu_name . ': '
              . $element->link->getTitle()
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
      $depth++;
    }

    return FALSE;
  }

  /**
   * Add representative anonymous-accessible content paths.
   *
   * @param array $paths
   *   Existing path samples, keyed by path.
   * @param int $samples_per_bundle
   *   Number of examples per content type.
   * @param int $path_limit
   *   Maximum number of paths to sample. Zero means no limit.
   *
   * @return bool
   *   TRUE when the path limit has been reached.
   */
  private function addContentTypeExamplePaths(
    array &$paths,
    int $samples_per_bundle,
    int $path_limit
  ): bool {
    $bundle_counts = \Drupal::database()->query(
      "SELECT type, COUNT(*) FROM {node_field_data}
       WHERE status = 1 GROUP BY type ORDER BY COUNT(*) DESC"
    )->fetchAllKeyed();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $alias_manager = \Drupal::service('path_alias.manager');
    $anonymous = new AnonymousUserSession();
    $front_path = (string) \Drupal::config('system.site')->get('page.front');

    foreach (array_keys($bundle_counts) as $bundle) {
      $query = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->condition('type', $bundle)
        ->sort('changed', 'DESC')
        ->range(0, max(25, $samples_per_bundle * 10));

      $added = 0;
      $node_ids = $query->execute();
      foreach ($node_storage->loadMultiple($node_ids) as $node) {
        if (!$node instanceof NodeInterface) {
          continue;
        }
        if ('/node/' . $node->id() === $front_path) {
          continue;
        }
        if (!$node->access('view', $anonymous)) {
          continue;
        }

        $path = $alias_manager->getAliasByPath('/node/' . $node->id());
        $this->addPath(
          $paths,
          $path,
          'content type example: ' . $bundle . ':' . $node->id()
        );
        $added++;

        if ($this->pathLimitReached($paths, $path_limit)) {
          return TRUE;
        }
        if ($added >= $samples_per_bundle) {
          break;
        }
      }
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

}
