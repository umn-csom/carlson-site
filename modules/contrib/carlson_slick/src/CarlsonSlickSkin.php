<?php

namespace Drupal\carlson_slick\src;

use Drupal\slick\SlickSkinInterface;

/**
 * Implements SlickSkinInterface as registered via hook_slick_skins_info().
 */
class CarlsonSlickSkin implements SlickSkinInterface {

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
        'provider' => 'carlson_slick',
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
