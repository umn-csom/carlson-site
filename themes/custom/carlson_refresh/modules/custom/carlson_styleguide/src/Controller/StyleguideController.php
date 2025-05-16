<?php

namespace Drupal\carlson_styleguide\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for styleguide pages.
 */
class StyleguideController extends ControllerBase {

  /**
   * Renders the styleguide index page.
   *
   * @return array
   *   A render array for the styleguide index page.
   */
  public function index() {
    $build = [
      '#theme' => 'styleguide_index',
      '#styleguide_pages' => [
        [
          'title' => $this->t('Typography'),
          'description' => $this->t('Typography styles including headings, text, lists, and more.'),
          'url' => Url::fromRoute('carlson_styleguide.typography'),
        ],
        [
          'title' => $this->t('Buttons'),
          'description' => $this->t('Button styles and variations.'),
          'url' => Url::fromRoute('carlson_styleguide.buttons'),
        ],
      ],
      '#attached' => [
        'library' => [
          'carlson_refresh/global-styling',
          'carlson_refresh/global-scripts',
        ],
      ],
    ];

    return $build;
  }

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

  /**
   * Renders the typography styleguide page.
   *
   * @return array
   *   A render array for the typography styleguide page.
   */
  public function typography() {
    $build = [
      '#theme' => 'test_typography',
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
