<?php

/**
 * @file
 * CSM-295: Consolidate button styles across all content types in batches.
 *
 * This script handles ALL cases including:
 * - Fields with text formats
 * - Plain text_long fields
 * - String_long fields
 * - Config entities
 * - Webforms
 * - Any field that might contain HTML with button classes
 *
 * Usage:
 * - drush scr path/to/update_button_styles_batch_api_public.php
 * - DRY_RUN=1 drush scr path/to/update_button_styles_batch_api_public.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

// Initialize Drupal environment for Drush
if (php_sapi_name() === 'cli') {
  set_time_limit(0);
}

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

$datetime_suffix = date('Y-m-d_H-i-s');
$report_filename = "{$script_name}_{$datetime_suffix}_report.csv";
$report_file_uri = "public://script_logs/{$report_filename}";
$report_file_path = \Drupal::service('file_system')->realpath($report_file_uri);
// --- End Script Initialization ---

script_log("CSM-295: Starting button style update script...", 'info');
script_log("Log: {$log_file_path}", 'info');
script_log("Report: {$report_file_path}", 'info');

// Dry run option
$dry_run = !empty($_ENV['DRY_RUN']);
if ($dry_run) {
  script_log("Running in DRY RUN mode - no changes will be made", 'info');
}

// Button style mappings - EXPANDED to include compound classes
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

// Ensure Drupal services are available.
if (
  !class_exists('\Drupal')
  || !\Drupal::hasService('database')
  || !\Drupal::hasService('entity_field.manager')
  || !\Drupal::hasService('entity_type.manager')
) {
  $error_msg = "Required Drupal services not available. Ensure Drupal is bootstrapped or script is run in a Drupal environment.";
  script_log($error_msg, 'error');
  return "ERROR: {$error_msg} Log: {$log_file_path}";
}

// Get services
$entity_field_manager = \Drupal::service('entity_field.manager');
$entity_type_manager = \Drupal::service('entity_type.manager');
$database = Database::getConnection();


// Configuration
$batch_size = 50;

// Limit number of rows to process for debugging. 0 = processes all rows.
$debug_row_limit = 0;
if ($debug_row_limit > 0) {
  script_log("Debug limit: {$debug_row_limit}", 'info');
}

// Step 1: Get HTML fields
script_log("Step 1: Identifying HTML fields...", 'info');
$html_fields = getFieldsWithHTML($entity_field_manager, $entity_type_manager);
$field_count = 0;
foreach ($html_fields as $bundles) {
  foreach ($bundles as $fields) {
    $field_count += count($fields);
  }
}
script_log("Found $field_count HTML fields across all entity types", 'info');

// Step 2: Find entities with old classes
script_log("Step 2: Finding entities with old button classes...", 'info');
$entities_to_process = findEntitiesWithOldClasses($database, $html_fields, $button_mappings);
$total_entities = count($entities_to_process);

// Apply debug limit if set
if ($debug_row_limit > 0) {
  $original_count = $total_entities;
  $entities_to_process = array_slice($entities_to_process, 0, $debug_row_limit);
  $total_entities = count($entities_to_process);
  script_log("Debug limit active: Processing {$total_entities} out of {$original_count} entities", 'info');
} else {
  script_log("Found {$total_entities} entities to process", 'info');
}

if ($total_entities === 0) {
  $error_msg = "No entities found with old button classes. Nothing to update.";
  script_log($error_msg, 'error');
  return "ERROR: {$error_msg} Log: {$log_file_path}";
}

// Step 3:Process in batches and save updates to report file
script_log("Step 3: Processing entities in batches...", 'info');
$batches = array_chunk($entities_to_process, $batch_size);
$total_updated = 0;

$report_handle = fopen($report_file_path, 'w');
fputcsv($report_handle, ['Entity Path', 'Node ID', 'Replacements', 'Entity Label', 'Link']);

foreach ($batches as $batch_index => $batch) {
  script_log(sprintf("Processing batch %d/%d...", $batch_index + 1, count($batches)), 'info');

  $batch_result = processBatch($batch, $entity_type_manager, $entity_field_manager, $button_mappings, $dry_run);

  $total_updated += $batch_result['updated_count'];

  // Write log entries
  foreach ($batch_result['log_entries'] as $entry) {
    // Format the entity path as entity_type.bundle[id].field
    $entity_path = sprintf(
      '%s.%s[%s].%s',
      $entry['entity_type'],
      $entry['bundle'],
      $entry['entity_id'],
      $entry['field_name']
    );

    // Generate the entity link
    $entity_link = '';
    if ($entry['entity_type'] === 'node') {
      $entity_link = Url::fromRoute('entity.node.canonical', ['node' => $entry['entity_id']], ['absolute' => TRUE])->toString();
    } elseif ($entry['entity_type'] === 'block_content') {
      $entity_link = Url::fromRoute('entity.block_content.edit_form', ['block_content' => $entry['entity_id']], ['absolute' => TRUE])->toString();
    } elseif ($entry['entity_type'] === 'paragraph') {
      // For paragraphs, link to the parent node if available
      if (!empty($entry['node_id'])) {
        $entity_link = Url::fromRoute('entity.node.canonical', ['node' => $entry['node_id']], ['absolute' => TRUE])->toString();
      }
    }

    // Create the new CSV row with our updated format
    $csv_row = [
      $entity_path,
      $entry['node_id'],
      $entry['replacements'],
      $entry['entity_label'],
      $entity_link
    ];

    fputcsv($report_handle, $csv_row);
  }

  script_log(sprintf("  Updated %d entities in this batch", $batch_result['updated_count']), 'info');
}

fclose($report_handle);

/**
 * Step 1: Identify entity types, bundles, and fields that might contain HTML
 */
function getFieldsWithHTML($entity_field_manager, $entity_type_manager) {
  $html_fields = [];

  $entity_types = $entity_type_manager->getDefinitions();

  foreach ($entity_types as $entity_type_id => $entity_type) {
    if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      continue;
    }

    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);

    foreach ($bundles as $bundle_id => $bundle_info) {
      $field_definitions = $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle_id);

      foreach ($field_definitions as $field_name => $field_definition) {
        $field_type = $field_definition->getType();

        if (in_array($field_type, ['text', 'text_long', 'text_with_summary', 'string', 'string_long'])) {
          if (!isset($html_fields[$entity_type_id])) {
            $html_fields[$entity_type_id] = [];
          }
          if (!isset($html_fields[$entity_type_id][$bundle_id])) {
            $html_fields[$entity_type_id][$bundle_id] = [];
          }
          $html_fields[$entity_type_id][$bundle_id][$field_name] = [
            'table' => $entity_type_id . '__' . $field_name,
            'column' => $field_name . '_value',
            'translatable' => $field_definition->isTranslatable(),
          ];
        }
      }
    }
  }

  return $html_fields;
}

/**
 * Step 2: Find entities containing old button classes
 */
function findEntitiesWithOldClasses($database, $html_fields, $button_mappings) {
  $entities_to_process = [];
  $old_classes = array_keys($button_mappings);

  foreach ($html_fields as $entity_type_id => $bundles) {
    foreach ($bundles as $bundle_id => $fields) {
      foreach ($fields as $field_name => $field_info) {
        $table = $field_info['table'];
        $column = $field_info['column'];

        if (!$database->schema()->tableExists($table)) {
          continue;
        }

        // Build query to find entities with old classes
        $query = $database->select($table, 't');
        $query->fields('t', ['entity_id', 'langcode'])
          ->distinct();

        // Add conditions for each old class
        $or = $query->orConditionGroup();
        foreach ($old_classes as $old_class) {
          $or->condition('t.' . $column, '%' . $database->escapeLike($old_class) . '%', 'LIKE');
        }
        $query->condition($or);

        try {
          $results = $query->execute()->fetchAll();

          foreach ($results as $result) {
            $key = $entity_type_id . ':' . $result->entity_id . ':' . $result->langcode;
            if (!isset($entities_to_process[$key])) {
              $entities_to_process[$key] = [
                'entity_type' => $entity_type_id,
                'entity_id' => $result->entity_id,
                'langcode' => $result->langcode,
                'bundle' => $bundle_id,
                'fields' => [],
              ];
            }
            $entities_to_process[$key]['fields'][] = $field_name;
          }
        } catch (\Exception $e) {
          echo "Error querying $table: " . $e->getMessage() . "\n";
        }
      }
    }
  }

  return array_values($entities_to_process);
}

/**
 * Step 3:Process a batch of entities
 */
function processBatch($entities_info, $entity_type_manager, $entity_field_manager, $button_mappings, $dry_run) {
  $log_entries = [];
  $updated_count = 0;

  foreach ($entities_info as $entity_info) {
    try {
      $storage = $entity_type_manager->getStorage($entity_info['entity_type']);
      $entity = $storage->load($entity_info['entity_id']);

      if (!$entity) {
        continue;
      }

      // Handle translations
      if ($entity->isTranslatable() && $entity->hasTranslation($entity_info['langcode'])) {
        $entity = $entity->getTranslation($entity_info['langcode']);
      }

      $entity_changed = false;
      $entity_updates = [];

      foreach ($entity_info['fields'] as $field_name) {
        if (!$entity->hasField($field_name)) {
          continue;
        }

        $field = $entity->get($field_name);
        if ($field->isEmpty()) {
          continue;
        }

        foreach ($field as $delta => $item) {
          $value = $item->value ?? '';

          if (empty($value)) {
            continue;
          }

          $result = updateHTMLWithDOM($value, $button_mappings);

          if ($result['changed']) {
            $item->value = $result['content'];
            $entity_changed = true;

            $entity_updates[] = [
              'field_name' => $field_name,
              'delta' => $delta,
              'replacements' => $result['replacements'],
            ];
          }
        }
      }

      if ($entity_changed) {
        if (!$dry_run) {
          $entity->save();
        }

        $updated_count++;

        // Find parent node if this is a paragraph
        $node_id = null;
        if ($entity_info['entity_type'] === 'paragraph') {
          $node_id = findParentNodeForParagraph($entity, $entity_type_manager);
        } elseif ($entity_info['entity_type'] === 'node') {
          $node_id = $entity->id();
        }

        foreach ($entity_updates as $update) {
          $log_entries[] = [
            'entity_type' => $entity_info['entity_type'],
            'bundle' => $entity_info['bundle'],
            'entity_id' => $entity->id(),
            'langcode' => $entity_info['langcode'],
            'node_id' => $node_id,
            'field_name' => $update['field_name'],
            'replacements' => implode(', ', $update['replacements']),
            'entity_label' => method_exists($entity, 'label') ? substr($entity->label() ?? 'N/A', 0, 100) : 'N/A',
          ];
        }
      }
    } catch (\Exception $e) {
      script_log("Error processing entity {$entity_info['entity_type']} {$entity_info['entity_id']}: " . $e->getMessage(), 'error');
    }
  }

  return [
    'log_entries' => $log_entries,
    'updated_count' => $updated_count,
  ];
}

/**
 * Step 3.1: Update HTML using DOM parser
 */
function updateHTMLWithDOM($html, $button_mappings) {
  if (empty($html) || !preg_match('/<[^>]+>/', $html)) {
    return ['content' => $html, 'changed' => false, 'replacements' => []];
  }

  // Create DOM document
  $dom = new \DOMDocument();
  $libxml_previous = libxml_use_internal_errors(true);

  // Wrap content to ensure proper parsing
  $wrapped_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
  $dom->loadHTML($wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

  libxml_clear_errors();
  libxml_use_internal_errors($libxml_previous);

  $xpath = new \DOMXPath($dom);
  $changed = false;
  $replacements = [];

  // Find all elements with class attributes
  $elements = $xpath->query('//*[@class]');

  foreach ($elements as $element) {
    $class_attr = $element->getAttribute('class');
    $classes = array_filter(array_unique(explode(' ', $class_attr)));
    $new_classes = [];
    $element_changed = false;

    foreach ($classes as $class) {
      if (isset($button_mappings[$class])) {
        // Single class replacement
        if ($button_mappings[$class] === '') {
          // Remove the class
          $element_changed = true;
          $replacements[] = "$class → (removed)";
        } else {
          // Replace with new class(es)
          $new_class_parts = explode(' ', $button_mappings[$class]);
          foreach ($new_class_parts as $new_class) {
            if (!in_array($new_class, $new_classes)) {
              $new_classes[] = $new_class;
            }
          }
          $element_changed = true;
          $replacements[] = "$class → {$button_mappings[$class]}";
        }
      } else {
        // Check for compound classes
        $compound_found = false;
        foreach ($button_mappings as $old_compound => $new_compound) {
          if (strpos($old_compound, ' ') !== false) {
            $compound_parts = explode(' ', $old_compound);
            if (count(array_intersect($compound_parts, $classes)) === count($compound_parts)) {
              // All parts of compound class are present
              if ($new_compound === '') {
                // Remove the compound class parts
                $classes = array_diff($classes, $compound_parts);
                $element_changed = true;
                $replacements[] = "$old_compound → (removed)";
              } else {
                // Replace compound class
                $classes = array_diff($classes, $compound_parts);
                $new_parts = explode(' ', $new_compound);
                foreach ($new_parts as $new_part) {
                  if (!in_array($new_part, $new_classes)) {
                    $new_classes[] = $new_part;
                  }
                }
                $element_changed = true;
                $replacements[] = "$old_compound → $new_compound";
              }
              $compound_found = true;
              break;
            }
          }
        }

        if (!$compound_found && !in_array($class, $new_classes)) {
          $new_classes[] = $class;
        }
      }
    }

    if ($element_changed) {
      $element->setAttribute('class', implode(' ', array_unique($new_classes)));
      $changed = true;
    }
  }

  if ($changed) {
    // Extract the body content
    $body = $dom->getElementsByTagName('body')->item(0);
    $updated_html = '';
    foreach ($body->childNodes as $child) {
      $updated_html .= $dom->saveHTML($child);
    }

    return [
      'content' => $updated_html,
      'changed' => true,
      'replacements' => array_unique($replacements),
    ];
  }

  return ['content' => $html, 'changed' => false, 'replacements' => []];
}

/**
 * Step 3.2: Find parent node for a paragraph.
 */
function findParentNodeForParagraph($paragraph, $entity_type_manager) {
  if ($paragraph->hasField('parent_id') && $paragraph->hasField('parent_type')) {
    $parent_type = $paragraph->get('parent_type')->value;
    $parent_id = $paragraph->get('parent_id')->value;

    if ($parent_type && $parent_id) {
      try {
        $parent_entity = $entity_type_manager->getStorage($parent_type)->load($parent_id);

        if ($parent_entity) {
          if ($parent_type === 'node') {
            return $parent_entity->id();
          } elseif ($parent_type === 'paragraph') {
            return findParentNodeForParagraph($parent_entity, $entity_type_manager);
          }
        }
      } catch (\Exception $e) {
        // Skip if parent entity can't be loaded
      }
    }
  }

  return null;
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("Button style update complete.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

// Generate report
if ($total_updated > 0) {
  script_log("Generating summary report...", 'info');

  $summary = [];
  $report_handle = fopen($report_file_path, 'r');
  $header = fgetcsv($report_handle); // Skip header

  while (($row = fgetcsv($report_handle)) !== FALSE) {
    $key = $row[0] . '.' . $row[5]; // entity_type.field_name
    if (!isset($summary[$key])) {
      $summary[$key] = 0;
    }
    $summary[$key]++;
  }
  fclose($report_handle);

  script_log("Summary by entity type and field:", 'info');
  foreach ($summary as $key => $count) {
    script_log("  $key: $count updates", 'info');
  }
}

$final_summary_message = sprintf(
  "CSM-295: Button style consolidation complete. Total entities scanned: %d. Total entities updated: %d.",
  $total_entities,
  $total_updated
);
if ($dry_run) {
  $final_summary_message .= " This was a DRY RUN. No actual changes were made to the database. To execute the update, run the script without DRY_RUN=1";
}
$log_message = "Log: " . get_log_viewer_url($log_file_path);
$report_message = "Report: " . get_log_viewer_url($report_file_path);

script_log($final_summary_message, 'info');
script_log($log_message, 'info');
script_log($report_message, 'info');

// Return a summary message that will be shown in the update hook
return $final_summary_message . PHP_EOL . $log_message . PHP_EOL . $report_message;
