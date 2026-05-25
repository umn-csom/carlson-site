<?php

namespace Drupal\carlson_general;

use Drupal\Core\Url;

/**
 * Builds the asynchronous responsive off-canvas menu pieces.
 *
 * Mental model:
 * - buildPlaceholder() is used during the initial page request. It attaches the
 *   responsive_menu assets, Carlson AJAX library, endpoint setting, a
 *   mobile-scoped endpoint preload hint, and a small DOM placeholder instead of
 *   rendering the expensive menu tree immediately.
 * - ResponsiveOffCanvasController calls buildMenu() later through AJAX. That
 *   method returns the full wrapper and menu tree markup that replaces the
 *   placeholder in the browser.
 * - buildMenuTree() mirrors responsive_menu's normal off-canvas tree building
 *   flow so existing menu-name, manipulator, and tree alter hooks still apply.
 * - addMenuCacheability() keeps the AJAX response tied to menu config and
 *   active-trail cache contexts so cached fragments vary with the page path.
 */
class ResponsiveOffCanvasAjax {

  /**
   * Checks whether the async off-canvas menu should be active.
   *
   * @return bool
   *   TRUE when the current request should use the async off-canvas menu.
   */
  public static function isEnabled(): bool {
    $config = \Drupal::config('responsive_menu.settings');
    if (
      !$config->get('allow_admin') &&
      function_exists('_current_theme_is_admin') &&
      \_current_theme_is_admin()
    ) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Builds the initial lightweight placeholder render array.
   *
   * @return array
   *   The placeholder render array.
   */
  public static function buildPlaceholder(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'off-canvas-wrapper',
          'off-canvas-wrapper--async',
        ],
        'data-carlson-responsive-off-canvas-placeholder' => '',
      ],
      '#cache' => [
        'contexts' => [
          'languages:language_interface',
          'url.path',
        ],
        'tags' => ['config:responsive_menu.settings'],
      ],
    ];

    static::attachResponsiveMenuAssets($build);
    $build['#attached']['library'][] =
      'carlson_general/responsive_off_canvas_ajax';

    $build['#attached']['drupalSettings']['carlsonGeneral']['responsiveOffCanvas'] = [
      'endpoint' => '/carlson-general/responsive-off-canvas',
      'prefetch' => TRUE,
    ];

    static::attachEndpointPreload($build);

    return $build;
  }

  /**
   * Adds a mobile-scoped preload hint for the AJAX off-canvas endpoint.
   *
   * @param array $build
   *   The render array receiving attachments.
   */
  protected static function attachEndpointPreload(array &$build): void {
    $endpoint = Url::fromRoute('carlson_general.responsive_off_canvas')
      ->toString();
    $href = $endpoint . '?current_path=' .
      rawurlencode(\Drupal::request()->getPathInfo());

    $link = [
      'rel' => 'preload',
      'href' => $href,
      'as' => 'fetch',
      'crossorigin' => 'anonymous',
    ];

    $media = static::mobileMediaQuery();
    if ($media !== '') {
      $link['media'] = $media;
    }

    $build['#attached']['html_head_link'][] = [$link, FALSE];
  }

  /**
   * Returns the inverse of the configured desktop responsive menu breakpoint.
   *
   * @return string
   *   A media query that matches mobile/off-canvas viewports.
   */
  protected static function mobileMediaQuery(): string {
    $breakpoint = \Drupal::config('responsive_menu.settings')
      ->get('horizontal_media_query');

    if (!is_string($breakpoint) || $breakpoint === '') {
      return '';
    }

    return 'not all and ' . $breakpoint;
  }

  /**
   * Builds the full off-canvas menu render array for the AJAX endpoint.
   *
   * @return array
   *   The full off-canvas menu render array.
   */
  public static function buildMenu(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['off-canvas-wrapper'],
      ],
      'off_canvas' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'off-canvas',
        ],
        'menu' => static::buildMenuTree(),
      ],
      '#cache' => [
        'keys' => [
          'carlson_general',
          'responsive_menu',
          'off_canvas_ajax',
        ],
        'contexts' => ['url.query_args:current_path'],
        'tags' => ['config:responsive_menu.settings'],
      ],
    ];

    static::addMenuCacheability($build);

    return $build;
  }

  /**
   * Builds the responsive_menu off-canvas menu tree.
   *
   * @return array
   *   The off-canvas menu tree render array.
   */
  protected static function buildMenuTree(): array {
    $off_canvas_menus = \Drupal::config('responsive_menu.settings')
      ->get('off_canvas_menus');

    \Drupal::moduleHandler()
      ->alter('responsive_menu_off_canvas_menu_names', $off_canvas_menus);

    $combined_tree = [];
    $menu_tree = \Drupal::menuTree();
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    \Drupal::moduleHandler()
      ->alter('responsive_menu_off_canvas_manipulators', $manipulators);

    foreach (explode(',', $off_canvas_menus) as $menu_name) {
      $menu_name = trim($menu_name);
      if ($menu_name === '') {
        continue;
      }

      $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
      $parameters->expandedParents = [];
      $tree_items = $menu_tree->load($menu_name, $parameters->onlyEnabledLinks());
      $tree_manipulated = $menu_tree->transform($tree_items, $manipulators);
      $combined_tree = array_merge($combined_tree, $tree_manipulated);
    }

    $menu = $menu_tree->build($combined_tree);
    $menu['#theme'] = 'responsive_menu_off_canvas';

    $parent_build = [];
    \Drupal::moduleHandler()
      ->alter('responsive_menu_off_canvas_tree', $menu, $parent_build);

    return $menu;
  }

  /**
   * Attaches responsive_menu libraries and settings without rendering the menu.
   *
   * @param array $build
   *   The render array receiving attachments.
   */
  protected static function attachResponsiveMenuAssets(array &$build): void {
    $config = \Drupal::config('responsive_menu.settings');

    if ($config->get('use_breakpoint')) {
      $css_file = \_get_breakpoint_css_filepath() .
        RESPONSIVE_MENU_BREAKPOINT_FILENAME;
      if (!file_exists($css_file)) {
        \responsive_menu_generate_breakpoint_css(
          $config->get('horizontal_media_query')
        );
      }
      $build['#attached']['library'][] =
        'responsive_menu/responsive_menu.breakpoint';
    }

    $build['#attached']['library'][] = 'responsive_menu/responsive_menu.mmenu';

    $polyfill_path = DRUPAL_ROOT . '/libraries/mmenu/dist/mmenu.polyfills.js';
    if ($config->get('use_polyfills') && file_exists($polyfill_path)) {
      $build['#attached']['library'][] =
        'responsive_menu/responsive_menu.polyfills';
    }

    $build['#attached']['library'][] = 'responsive_menu/responsive_menu.config';
    if ($config->get('include_css')) {
      $build['#attached']['library'][] =
        'responsive_menu/responsive_menu.styling';
    }
    $build['#attached']['drupalSettings']['responsive_menu'] = [
      'position' => $config->get('off_canvas_position'),
      'theme' => $config->get('off_canvas_theme'),
      'pagedim' => $config->get('pagedim'),
      'modifyViewport' => $config->get('modify_viewport'),
      'use_bootstrap' => $config->get('use_bootstrap'),
      'breakpoint' => $config->get('horizontal_media_query'),
      'drag' => $config->get('drag'),
    ];

    if ($config->get('off_canvas_position') === 'contextual') {
      $language = \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getDirection();
      $position = $language === 'rtl' ? 'right' : 'left';
      $build['#attached']['drupalSettings']['responsive_menu']['position'] =
        $position;
    }
    else {
      $build['#attached']['drupalSettings']['responsive_menu']['position'] =
        $config->get('off_canvas_position');
    }
  }

  /**
   * Adds menu cacheability that mirrors responsive_menu page-bottom output.
   *
   * @param array $build
   *   The menu render array.
   */
  protected static function addMenuCacheability(array &$build): void {
    $off_canvas_menus = \Drupal::config('responsive_menu.settings')
      ->get('off_canvas_menus');

    \Drupal::moduleHandler()
      ->alter('responsive_menu_off_canvas_menu_names', $off_canvas_menus);

    foreach (explode(',', $off_canvas_menus) as $menu_name) {
      $menu_name = trim($menu_name);
      if ($menu_name === '') {
        continue;
      }

      $build['#cache']['keys'][] = $menu_name;
      $build['#cache']['contexts'][] =
        'route.menu_active_trails:' . $menu_name;
      $build['#cache']['tags'][] = 'config:system.menu.' . $menu_name;
    }
  }

}
