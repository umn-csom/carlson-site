<?php

namespace Drupal\user_expire\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for user_expire.
 */
class UserExpireViewsHooks {
  use StringTranslationTrait;
  /**
   * @file
   * Views integration for the User Expire module.
   */

  /**
   * Implements hook_views_data().
   *
   * {@inheritdoc}
   */
  #[Hook('views_data')]
  public function viewsData() {
    $data['user_expire']['table']['group'] = $this->t('User');
    $data['user_expire']['table']['join'] = [
      'users_field_data' => [
        'left_field' => 'uid',
        'field' => 'uid',
      ],
    ];
    $data['user_expire']['expiration'] = [
      'title' => $this->t('Expiration date'),
      'help' => $this->t('The date on which this account will be disabled.'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ];
    return $data;
  }

}
