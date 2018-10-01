<?php

namespace Drupal\carlson_slick;

use Drupal\slick\SlickSkinInterface;

/**
 * Implements SlickSkinInterface as registered via hook_slick_skins_info().
 */
class SlickCarlsonSkin implements SlickSkinInterface {

  /**
   * {@inheritdoc}
   */
  public function skins() {
    $path  = base_path() . drupal_get_path('module', 'carlson_slick');
    $skins = [
      'carlson_slick' => [
        'name' => t('Carlson Slick Skin'),
        'description' => t('A Carlson slick skin.'),
        'group' => 'main',
        'provider' => 'slick_extras',
        'css' => [
          'theme' => [
            $path . '/css/slick.carlson.css' => [],
          ],
        ],
      ],
    ];

    return $skins;
  }

}
