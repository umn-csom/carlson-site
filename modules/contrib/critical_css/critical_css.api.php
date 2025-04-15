<?php

/**
 * @file
 * Critical CSS hooks.
 */

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Alter the possible paths to search critical CSS files.
 *
 * @param array $file_paths
 *   The critical CSS files array.
 * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
 *   The current entity used, or NULL if not on an entity route.
 */
function hook_critical_css_file_paths_suggestion_alter(array &$file_paths, ?ContentEntityInterface $entity) {

}
