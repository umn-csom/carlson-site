<?php

/**
 * @file
 * Views hooks.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\user_expire\Hook\UserExpireViewsHooks;

/**
 * @file
 * Views integration for the User Expire module.
 */

/**
 * Implements hook_views_data().
 *
 * {@inheritdoc}
 */
#[LegacyHook]
function user_expire_views_data() {
  return \Drupal::service(UserExpireViewsHooks::class)->viewsData();
}
