<?php

/**
 * @file
 * CSM-295: Update button classes in menu link attributes.
 *
 * Usage:
 * - drush scr path/to/update_menu_link_button_styles.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\Database\Database;

// Initialize Drupal environment for Drush
if (PHP_SAPI === 'cli') {
  set_time_limit(0);
}

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

// Get button style mappings
$button_mappings = [
  // Original single class mappings
  'button-link-primary' => 'btn btn-primary',
  'button-link-secondary' => 'btn btn-primary',
  'btn-link-primary' => 'btn btn-primary',
  'btn-link-secondary' => 'btn btn-primary',
  'maroon-solid-button' => 'btn btn-primary',
  'btn-primary--maroon' => 'btn btn-primary',
  'maroon-underline-button' => 'btn btn-primary',
  'gold-solid-button' => 'btn btn-secondary',
  'btn-primary--gold' => 'btn btn-secondary',
  'btn-primary--yellow' => 'btn btn-secondary',
  'btn-gold' => 'btn btn-secondary',
  'gold-underline-button' => 'btn btn-secondary',
  'maroon-outline-button' => 'btn btn-outline-primary',
  'gold-outline-button' => 'btn btn-outline-secondary',
  'btn-cta' => '',
  'program-features__btn' => 'btn btn-primary',

  // Compound class mappings
  'btn btn-link-primary' => 'btn btn-primary',
  'btn btn-link-secondary' => 'btn btn-primary',
  'btn-sm btn-link-primary' => 'btn btn-sm btn-primary',
  'btn-sm btn-link-secondary' => 'btn btn-sm btn-primary',
  'btn-lg btn-link-primary' => 'btn btn-lg btn-primary',
  'btn-lg btn-link-secondary' => 'btn btn-lg btn-primary',
];

script_log("CSM-295: Starting menu link button class update...", 'info');

// Get database connection
$database = Database::getConnection();

// Find menu links with button classes
$button_classes = array_keys($button_mappings);
$query = $database->select('menu_link_content_data', 'm')
  ->fields('m', ['id', 'title', 'menu_name', 'link__options'])
  ->condition('link__options', '%' . $database->escapeLike('class') . '%', 'LIKE');

$or_condition = $query->orConditionGroup();
foreach ($button_classes as $class) {
  $or_condition->condition('link__options', '%' . $database->escapeLike($class) . '%', 'LIKE');
}
$query->condition($or_condition);
$results = $query->execute()->fetchAll();

$total_links = count($results);
$updated_count = 0;
$log_entries = [];

script_log("Found {$total_links} menu links with potential button classes to update.", 'info');

// Get entity type manager to load/save entities properly
$entity_type_manager = \Drupal::entityTypeManager();
$menu_link_storage = $entity_type_manager->getStorage('menu_link_content');

foreach ($results as $link) {
  $id = $link->id;
  $title = $link->title;
  $menu_name = $link->menu_name;
  $options = unserialize($link->link__options);

  $original_options = $options;
  $updated = false;

  // Check if there are classes in attributes
  if (isset($options['attributes']['class']) && is_array($options['attributes']['class'])) {
    $classes = $options['attributes']['class'];
    $new_classes = [];

    foreach ($classes as $idx => $class) {
      // Check single classes
      if (isset($button_mappings[$class])) {
        $updated = true;
        $mapping = $button_mappings[$class];

        if (empty($mapping)) {
          // Remove the class
          unset($classes[$idx]);
          script_log("Menu link #{$id} \"{$title}\" ({$menu_name}): Removed class \"{$class}\"", 'info');
        }
        else {
          // Replace with new class(es)
          $new_class_parts = explode(' ', $mapping);
          foreach ($new_class_parts as $new_class) {
            if (!in_array($new_class, $new_classes)) {
              $new_classes[] = $new_class;
            }
          }
          unset($classes[$idx]);
          script_log("Menu link #{$id} \"{$title}\" ({$menu_name}): Replaced \"{$class}\" with \"{$mapping}\"", 'info');
        }
      }
      else {
        // Keep original class if no mapping
        $new_classes[] = $class;
      }
    }

    // Check compound classes (e.g., "btn btn-link-primary")
    $combined_class = implode(' ', $classes);
    foreach ($button_mappings as $old_compound => $new_compound) {
      if (strpos($old_compound, ' ') !== false && strpos($combined_class, $old_compound) !== false) {
        $updated = true;
        $compound_parts = explode(' ', $old_compound);

        // Remove all parts of the compound class
        foreach ($compound_parts as $part) {
          $key = array_search($part, $new_classes);
          if ($key !== false) {
            unset($new_classes[$key]);
          }
        }

        // Add new compound class parts
        if (!empty($new_compound)) {
          $new_parts = explode(' ', $new_compound);
          foreach ($new_parts as $new_part) {
            if (!in_array($new_part, $new_classes)) {
              $new_classes[] = $new_part;
            }
          }
        }

        script_log("Menu link #{$id} \"{$title}\" ({$menu_name}): Replaced compound \"{$old_compound}\" with \"{$new_compound}\"", 'info');
      }
    }

    // Update the options with new classes
    if ($updated) {
      $options['attributes']['class'] = array_values($new_classes);

      // Try both approaches: direct DB update and entity save
      // 1. Direct DB update
      $database->update('menu_link_content_data')
        ->fields([
          'link__options' => serialize($options),
        ])
        ->condition('id', $id)
        ->execute();

      // 2. Load and save the entity properly to ensure caches are cleared
      try {
        $menu_link_entity = $menu_link_storage->load($id);
        if ($menu_link_entity) {
          // Update the link options in the entity
          $menu_link_entity->link->first()->options = $options;
          $menu_link_entity->save();
        }
      }
      catch (\Exception $e) {
        script_log("Error updating menu link entity #{$id}: " . $e->getMessage(), 'error');
      }

      $updated_count++;
    }
  }
}

// For good measure, clear all relevant caches
\Drupal::service('cache_tags.invalidator')->invalidateTags(['menu', 'rendered']);

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("CSM-295: Menu link button class update complete.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

// Log final results
script_log("Total links processed: {$total_links}", 'info');
script_log("Total links updated: {$updated_count}", 'info');

// Return a summary message that will be shown in the update hook
$final_summary_message = sprintf(
  "CSM-295: Menu link button class update complete. Total links processed: %d. Total links updated: %d.",
  $total_links,
  $updated_count
);
$log_message = "Log: " . get_log_viewer_url($log_file_path);

script_log($final_summary_message, 'info');
script_log($log_message, 'info');

// Return a summary message that will be shown in the update hook
return $final_summary_message . PHP_EOL . $log_message;
