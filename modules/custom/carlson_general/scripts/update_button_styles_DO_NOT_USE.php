<?php

/**
 * @file
 * CSM-295: Consolidate button styles across all content types.
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
 * - drush scr path/to/update_button_styles_DO_NOT_USE.php
 * - DRY_RUN=1 drush scr path/to/update_button_styles_DO_NOT_USE.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

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

$updated_count = 0;
$tables_processed = [];
$report_entries = [];
$all_fields = [];

// Helper function to find HTML content in arrays
function findHtmlInArray($data, $path = [], &$results = []) {
  if (is_array($data)) {
    foreach ($data as $key => $value) {
      $current_path = array_merge($path, [$key]);
      if (is_string($value) && preg_match('/<[^>]+>/', $value)) {
        $path_string = implode('.', $current_path);
        $results[$path_string] = $value;
      } elseif (is_array($value)) {
        findHtmlInArray($value, $current_path, $results);
      }
    }
  }
  return $results;
}

// Helper function to update array at path
function updateArrayAtPath(&$array, $path, $value) {
  $keys = explode('.', $path);
  $current = &$array;

  foreach ($keys as $key) {
    if (!isset($current[$key])) {
      return;
    }
    $current = &$current[$key];
  }

  $current = $value;
}

// Function to update button classes in content
function updateButtonClasses($content, $button_mappings) {
  $updated_content = $content;
  $changes_made = FALSE;
  $replacements = [];

  // First pass: Handle compound classes (most specific to least specific)
  foreach ($button_mappings as $old_class => $new_class) {
    if (strpos($old_class, ' ') !== FALSE) { // Compound class
      // Use a more flexible pattern for compound classes
      $escaped_class = preg_quote($old_class, '/');
      $patterns = [
        // Exact match within quotes
        '/class="([^"]*)\b' . $escaped_class . '\b([^"]*)"/i',
        '/class=\'([^\']*)\b' . $escaped_class . '\b([^\']*)\'/i',
        // With extra spaces
        '/class\s*=\s*"([^"]*)\b' . $escaped_class . '\b([^"]*)"/i',
        '/class\s*=\s*\'([^\']*)\b' . $escaped_class . '\b([^\']*)\'/i',
      ];

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $updated_content)) {
          if ($new_class === '') {
            // Remove the class
            $updated_content = preg_replace(
              '/\b' . $escaped_class . '\b\s*/i',
              '',
              $updated_content
            );
          } else {
            // Replace with new class
            $updated_content = preg_replace(
              '/\b' . $escaped_class . '\b/i',
              $new_class,
              $updated_content
            );
          }
          $changes_made = TRUE;
          $replacements[] = "$old_class → " . ($new_class ?: '(removed)');
        }
      }
    }
  }

  // Second pass: Handle single classes
  foreach ($button_mappings as $old_class => $new_class) {
    if (strpos($old_class, ' ') === FALSE) { // Single class
      $escaped_class = preg_quote($old_class, '/');
      $patterns = [
        // Class within quotes
        '/class="([^"]*)\b' . $escaped_class . '\b([^"]*)"/i',
        '/class=\'([^\']*)\b' . $escaped_class . '\b([^\']*)\'/i',
        // With extra spaces
        '/class\s*=\s*"([^"]*)\b' . $escaped_class . '\b([^"]*)"/i',
        '/class\s*=\s*\'([^\']*)\b' . $escaped_class . '\b([^\']*)\'/i',
      ];

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $updated_content)) {
          if ($new_class === '') {
            // Remove the class
            $updated_content = preg_replace(
              '/\s*\b' . $escaped_class . '\b\s*/i',
              ' ',
              $updated_content
            );
          } else {
            // Replace with new class (avoid duplicating existing btn class)
            $callback = function($matches) use ($old_class, $new_class, $escaped_class) {
              $full_match = $matches[0];
              $before = $matches[1];
              $after = $matches[2];

              // Check if we already have 'btn' class when adding 'btn btn-primary'
              if (strpos($new_class, 'btn ') === 0 && preg_match('/\bbtn\b/', $before . $after)) {
                // Just add the modifier without 'btn'
                $new_class_parts = explode(' ', $new_class);
                array_shift($new_class_parts); // Remove 'btn'
                $replacement_class = implode(' ', $new_class_parts);
              } else {
                $replacement_class = $new_class;
              }

              return str_replace($old_class, $replacement_class, $full_match);
            };

            $updated_content = preg_replace_callback(
              '/class="([^"]*)\b' . $escaped_class . '\b([^"]*)"/i',
              $callback,
              $updated_content
            );
            $updated_content = preg_replace_callback(
              '/class=\'([^\']*)\b' . $escaped_class . '\b([^\']*)\'/i',
              $callback,
              $updated_content
            );
          }
          $changes_made = TRUE;
          $replacements[] = "$old_class → " . ($new_class ?: '(removed)');
        }
      }
    }
  }

  // Clean up any resulting issues
  if ($changes_made) {
    // Remove empty class attributes
    $updated_content = preg_replace('/class\s*=\s*["\']\s*["\']/i', '', $updated_content);
    // Clean up extra spaces
    $updated_content = preg_replace('/\s{2,}/', ' ', $updated_content);
    // Clean up spaces inside class attributes
    $updated_content = preg_replace('/class\s*=\s*["\']\s+/', 'class="', $updated_content);
    $updated_content = preg_replace('/\s+["\']/i', '"', $updated_content);
  }

  return [
    'content' => $updated_content,
    'changed' => $changes_made,
    'replacements' => $replacements,
  ];
}

script_log("Phase 1: Discovering ALL fields that might contain HTML...", 'info');

// Get all entity types
$entity_types = $entity_type_manager->getDefinitions();

foreach ($entity_types as $entity_type_id => $entity_type) {
  // Skip if entity type doesn't support fields
  if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
    continue;
  }

  try {
    // Get field storage definitions
    $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);

    foreach ($field_storage_definitions as $field_name => $field_storage_definition) {
      $field_type = $field_storage_definition->getType();

      // Include ALL text and string fields that might contain HTML
      if (in_array($field_type, [
        'text_long',
        'text_with_summary',
        'string_long',
        'text',
        'string',
      ])) {
        // Get all bundles using this field
        $field_map = $entity_field_manager->getFieldMap();
        $bundles = $field_map[$entity_type_id][$field_name]['bundles'] ?? [];

        $all_fields[$entity_type_id][$field_name] = [
          'field_type' => $field_type,
          'bundles' => $bundles,
          'table_name' => $entity_type_id . '__' . $field_name,
          'revision_table_name' => $entity_type_id . '_revision__' . $field_name,
          'value_column' => $field_name . '_value',
          'format_column' => $field_name . '_format',
        ];
      }
    }
  } catch (\Exception $e) {
    // Skip problematic entity types
  }
}

// Add special/custom fields
$special_tables = [
  'webform' => ['webform_id', 'elements'],
  'config' => ['name', 'data'],
  'block_custom' => ['bid', 'body'],
  'menu_link_content__link' => ['entity_id', 'link_title'],
  'menu_link_content__link' => ['entity_id', 'link_options'],
];

foreach ($special_tables as $table => $fields) {
  if ($database->schema()->tableExists($table)) {
    $all_fields['_special'][$table] = [
      'field_type' => 'special',
      'table_name' => $table,
      'id_column' => $fields[0],
      'value_column' => $fields[1],
    ];
  }
}

script_log("Found " . count($all_fields) . " entity types with potential HTML fields", 'info');

// Phase 2: Process all discovered fields
script_log("Phase 2: Processing all fields...", 'info');

$total_fields = 0;
foreach ($all_fields as $entity_type_id => $fields) {
  foreach ($fields as $field_name => $field_info) {
    $total_fields++;

    if ($entity_type_id === '_special') {
      // Handle special tables
      $table = $field_info['table_name'];
      if (!$database->schema()->tableExists($table)) {
        continue;
      }

      script_log("Processing special table: $table", 'info');

      try {
        $query = $database->select($table, 't')->fields('t');
        $results = $query->execute();
        $table_updates = 0;

        foreach ($results as $row) {
          if ($table === 'config') {
            $data = unserialize($row->data);
            $updated = FALSE;

            // Recursively search for HTML content in config data
            $html_locations = findHtmlInArray($data);
            foreach ($html_locations as $path => $content) {
              if (is_string($content) && !empty($content)) {
                $result = updateButtonClasses($content, $button_mappings);
                if ($result['changed']) {
                  // Update the data array at the path
                  updateArrayAtPath($data, $path, $result['content']);
                  $updated = TRUE;
                  $updated_count++;
                }
              }
            }

            if ($updated && !$dry_run) {
              $database->update($table)
                ->fields(['data' => serialize($data)])
                ->condition('collection', $row->collection)
                ->condition('name', $row->name)
                ->execute();
              $table_updates++;
            }
          } else {
            $content = $row->{$field_info['value_column']} ?? '';
            if (!empty($content) && preg_match('/<[^>]+>/', $content)) {
              $result = updateButtonClasses($content, $button_mappings);
              if ($result['changed']) {
                if (!$dry_run) {
                  $database->update($table)
                    ->fields([$field_info['value_column'] => $result['content']])
                    ->condition($field_info['id_column'], $row->{$field_info['id_column']})
                    ->execute();
                }
                $updated_count++;
                $table_updates++;
              }
            }
          }
        }

        if ($table_updates > 0) {
          script_log("Updated $table_updates records", 'info');
        }

      } catch (\Exception $e) {
        script_log($e->getMessage(), 'error');
      }

      continue;
    }

    // Regular fields
    $tables = [
      $field_info['table_name'],
      $field_info['revision_table_name'],
    ];

    foreach ($tables as $table) {
      if (!$database->schema()->tableExists($table)) {
        continue;
      }

      if (in_array($table, $tables_processed)) {
        continue;
      }

      $tables_processed[] = $table;
      $value_column = $field_info['value_column'];

      script_log("Processing $table ($entity_type_id.$field_name)...", 'info');

      try {
        // Build query
        $query = $database->select($table, 't')
          ->fields('t', ['entity_id', $value_column]);

        // Check if we have a format column
        $has_format_column = $database->schema()->fieldExists($table, $field_info['format_column']);
        if ($has_format_column) {
          $query->fields('t', [$field_info['format_column']]);
        }

        // Only get non-empty values
        $query->condition($value_column, '', '<>');

        $results = $query->execute();
        $table_updates = 0;

        foreach ($results as $row) {
          $content = $row->{$value_column};

          // Skip if no HTML content
          if (!preg_match('/<[^>]+>/', $content)) {
            continue;
          }

          // Check if content has any of our old button classes
          $has_old_classes = FALSE;
          foreach (array_keys($button_mappings) as $old_class) {
            if (stripos($content, $old_class) !== FALSE) {
              $has_old_classes = TRUE;
              break;
            }
          }

          if (!$has_old_classes) {
            continue;
          }

          // Update button classes
          $result = updateButtonClasses($content, $button_mappings);

          if ($result['changed']) {
            if (!$dry_run) {
              $database->update($table)
                ->fields([$value_column => $result['content']])
                ->condition('entity_id', $row->entity_id)
                ->execute();
            }

            $updated_count++;
            $table_updates++;

            $report_entries[] = [
              'table' => $table,
              'entity_type' => $entity_type_id,
              'field_name' => $field_name,
              'entity_id' => $row->entity_id,
              'format' => ($has_format_column && isset($row->{$field_info['format_column']}))
                ? $row->{$field_info['format_column']}
                : 'plain',
              'replacements' => implode(', ', $result['replacements']),
              'preview' => substr(strip_tags($content), 0, 50) . '...',
            ];
          }
        }

        if ($table_updates > 0) {
          script_log("Updated $table_updates records", 'info');
        }

      } catch (\Exception $e) {
        script_log($e->getMessage(), 'error');
      }
    }
  }
}

// Phase 3: Direct database search for any tables we might have missed
script_log("Phase 3: Final sweep for missed button classes...", 'info');

$all_tables = $database->query("SHOW TABLES")->fetchCol();
$search_patterns = [
  '%body%',
  '%text%',
  '%content%',
  '%description%',
  '%summary%',
  '%field_%',
];

foreach ($all_tables as $table) {
  if (in_array($table, $tables_processed)) {
    continue;
  }

  // Check if table name suggests it might contain content
  $check_table = FALSE;
  foreach ($search_patterns as $pattern) {
    if (fnmatch($pattern, $table)) {
      $check_table = TRUE;
      break;
    }
  }

  if (!$check_table) {
    continue;
  }

  // Get columns
  try {
    $columns = $database->query("SHOW COLUMNS FROM {$table}")->fetchCol();

    foreach ($columns as $column) {
      // Look for columns that might contain HTML
      if (preg_match('/(value|text|body|content|description|summary|options)/i', $column)) {
        // Quick check for content
        $check_query = $database->select($table, 't')
          ->fields('t')
          ->condition($column, '', '<>');
        $check_query->range(0, 1);

        $sample = $check_query->execute()->fetch();
        if ($sample && isset($sample->{$column})) {
          $sample_content = $sample->{$column};

          // Check for HTML and old button classes
          if (preg_match('/<[^>]+>/', $sample_content)) {
            foreach (array_keys($button_mappings) as $old_class) {
              if (stripos($sample_content, $old_class) !== FALSE) {
                script_log("Found old button classes in $table.$column", 'info');

                // Process all rows
                $process_query = $database->select($table, 't')
                  ->fields('t')
                  ->condition($column, '', '<>');

                $results = $process_query->execute();
                $table_updates = 0;

                foreach ($results as $row) {
                  $content = $row->{$column};
                  $result = updateButtonClasses($content, $button_mappings);

                  if ($result['changed']) {
                    if (!$dry_run) {
                      $update_query = $database->update($table)
                        ->fields([$column => $result['content']]);

                      // Try to find a suitable ID column
                      $id_columns = ['entity_id', 'id', 'nid', 'tid', 'uid', 'bid', 'vid'];
                      $id_column_found = FALSE;

                      foreach ($id_columns as $id_col) {
                        if (isset($row->{$id_col})) {
                          $update_query->condition($id_col, $row->{$id_col});
                          $id_column_found = TRUE;
                          break;
                        }
                      }

                      if ($id_column_found) {
                        $update_query->execute();
                      } else {
                        script_log("No ID column found for $table", 'warning');
                      }
                    }

                    $updated_count++;
                    $table_updates++;
                  }
                }

                if ($table_updates > 0) {
                  script_log("Updated $table_updates records", 'info');
                }

                $tables_processed[] = $table;
                break 2; // Move to next table
              }
            }
          }
        }
      }
    }
  } catch (\Exception $e) {
    // Skip problematic tables
  }
}

// Write detailed report.
$report_handle = fopen($report_file_path, 'w');
fputcsv($report_handle, ['Table', 'Entity Type', 'Field Name', 'Entity ID', 'Format', 'Replacements', 'Content Preview']);
foreach ($report_entries as $entry) {
  fputcsv($report_handle, $entry);
}
fclose($report_handle);

// Summarize report in log.
if (!empty($report_entries)) {
  script_log("Summary by entity type and field:", 'info');
  $summary = [];
  foreach ($report_entries as $entry) {
    $key = $entry['entity_type'] . '.' . $entry['field_name'];
    if (!isset($summary[$key])) {
      $summary[$key] = 0;
    }
    $summary[$key]++;
  }

  foreach ($summary as $key => $count) {
    script_log("  $key: $count updates", 'info');
  }
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("Button style update complete.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

$final_summary_message = sprintf(
  "Total fields updated: %d. Tables processed: %d.",
  $updated_count,
  count($tables_processed)
);
if ($dry_run) {
  $final_summary_message .= " This was a DRY RUN. No actual changes were made to the database.";
  $final_summary_message .= " To execute the update, run the script without DRY_RUN=1";
}
$log_message = "Log: " . get_log_viewer_url($log_file_path);
$report_message = "Report: " . get_log_viewer_url($report_file_path);

script_log($final_summary_message, 'info');
script_log($log_message, 'info');
script_log($report_message, 'info');

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message . PHP_EOL . $report_message;

