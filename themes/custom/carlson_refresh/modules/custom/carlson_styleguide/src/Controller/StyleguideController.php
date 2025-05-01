<?php

namespace Drupal\carlson_styleguide\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for styleguide pages.
 */
class StyleguideController extends ControllerBase {

  /**
   * Renders the button styleguide page.
   *
   * @return array
   *   A render array for the button styleguide page.
   */
  public function buttons() {
    $build = [
      '#theme' => 'test_buttons',
      '#attached' => [
        'library' => [
          'carlson_refresh/global-styling',
          'carlson_refresh/global-scripts',
        ],
      ],
    ];

    return $build;
  }

}
