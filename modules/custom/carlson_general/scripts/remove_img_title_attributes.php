<?php

/**
 * @file
 * CSM-231: Remove title attributes from img tags in WYSIWYG fields.
 *
 * Usage:
 * - drush scr path/to/remove_img_title_attributes.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

$datetime_suffix = date('Y-m-d_H-i-s');
$report_filename = "{$script_name}_{$datetime_suffix}_report.html";
$report_file_uri = "public://script_logs/{$report_filename}";
// --- End Script Initialization ---

script_log("CSM-231: Starting removal of img title attributes script ({$script_name}).", 'info');
script_log("Log: {$log_file_path}", 'info');
script_log("Report: {$report_file_uri}", 'info');

// Set longer execution limits (especially if not CLI, though CLI might also need it)
ini_set('max_execution_time', 1800);  // 30 minutes
script_log('Setting max execution time to 30 minutes', 'info');

$entity_field_manager = \Drupal::service('entity_field.manager');
$entity_type_manager = \Drupal::service('entity_type.manager');
$db = \Drupal::database();
/** @var FileSystemInterface $file_system */
$file_system = \Drupal::service('file_system');

// === STEP 1: Find all text fields ===
$fields_to_process = [];

// APPROACH 1: Get configured fields
$field_configs = $entity_type_manager->getStorage('field_config')->loadByProperties([
    'field_type' => ['text', 'text_long', 'text_with_summary'],
]);

foreach ($field_configs as $field_config) {
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();
    $field_name = $field_config->getName();

    $fields_to_process[] = [
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'field_name' => $field_name,
    ];
}

// APPROACH 2: Get base fields for all entity types
$entity_types = $entity_type_manager->getDefinitions();
foreach ($entity_types as $entity_type_id => $entity_type) {
    if ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
        // Get all base fields for this entity type
        $base_fields = $entity_field_manager->getBaseFieldDefinitions($entity_type_id);

        foreach ($base_fields as $field_name => $field_definition) {
            $field_type = $field_definition->getType();

            // Only include text fields
            if (in_array($field_type, ['text', 'text_long', 'text_with_summary'])) {
                // For node body fields, we need one entry per bundle
                if ($entity_type_id == 'node' && $field_name == 'body') {
                    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
                    foreach (array_keys($bundles) as $bundle) {
                        $fields_to_process[] = [
                            'entity_type' => $entity_type_id,
                            'bundle' => $bundle,
                            'field_name' => $field_name,
                        ];
                    }
                } else {
                    $fields_to_process[] = [
                        'entity_type' => $entity_type_id,
                        'bundle' => $entity_type_id,
                        'field_name' => $field_name,
                    ];
                }
            }
        }
    }
}

// APPROACH 3: Directly scan database tables for field tables
$tables = $db->query("SHOW TABLES LIKE '%\\_\\_%'")->fetchCol();

foreach ($tables as $table) {
    // Only look for field data tables, not revision tables
    if (strpos($table, '__') !== FALSE && strpos($table, 'revision') === FALSE) {
        list($entity_type, $field_name) = explode('__', $table, 2);

        // Check if table has a value column (text fields should)
        $schema = $db->query("DESCRIBE `{$table}`")->fetchAllAssoc('Field');
        $value_column = $field_name . '_value';

        if (isset($schema[$value_column])) {
            // This appears to be a text field table
            $fields_to_process[] = [
                'entity_type' => $entity_type,
                'bundle' => 'all',  // We don't know the bundle but don't need it
                'field_name' => $field_name,
                'table' => $table,
                'value_column' => $value_column,
            ];
        }
    }
}

// Remove any duplicates (by entity_type and field_name)
$unique_fields = [];
foreach ($fields_to_process as $field) {
    $key = $field['entity_type'] . '|' . $field['field_name'];
    $unique_fields[$key] = $field;
}
$fields_to_process = array_values($unique_fields);

script_log(sprintf('Found %d text fields to process', count($fields_to_process)), 'info');

// === STEP 2: Process all fields directly ===
$processed_entities = 0;
$updated_entities = 0;
$updated_by_type = [];
$updated_field_values = [];

// Process each field directly
foreach ($fields_to_process as $field_index => $field) {
    $entity_type = $field['entity_type'];
    $field_name = $field['field_name'];

    // Get table name for the field
    if (isset($field['table']) && isset($field['value_column'])) {
        $table_name = $field['table'];
        $value_column = $field['value_column'];
    } else {
        $table_name = $entity_type . '__' . $field_name;
        $value_column = $field_name . '_value';
    }
    $entity_id_column = 'entity_id';

    // Check if table exists before proceeding
    $table_exists = $db->schema()->tableExists($table_name);
    if (!$table_exists) {
        script_log(sprintf('Skipping [%d/%d] (%s:%s): Table %s does not exist', $field_index + 1, count($fields_to_process), $field_name, $entity_type, $table_name), 'notice');
        continue;
    }
    script_log(sprintf('Processing [%d/%d] (%s:%s)', $field_index + 1, count($fields_to_process), $entity_type, $field_name), 'info');

    // Load entity storage
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    try {
        // Query without batching - get all entities with title attributes
        $query = \Drupal::database()->select($table_name, 't')
            ->fields('t', [$entity_id_column])
            ->condition('t.' . $value_column, '%<img%title=%', 'LIKE');

        $result = $query->execute()->fetchCol();

        // Process all results at once
        if (!empty($result)) {
            $entities = $entity_storage->loadMultiple($result);

            foreach ($entities as $entity) {
                $field_items = $entity->get($field_name);
                $field_updated = false;
                $title_attrs_removed = 0;

                script_log(sprintf('Processing %s:%s %d', $entity_type, $field_name, $entity->id()), 'info');
                // Process all values in multi-value fields
                foreach ($field_items as $delta => $item) {
                    $field_value = $item->value;

                    // Only process if the field has content and contains img tags with title attributes
                    if (!empty($field_value) && strpos($field_value, '<img') !== false && strpos($field_value, 'title=') !== false) {
                        // Count how many title attributes before changes
                        $title_count_before = preg_match_all('/<img[^>]*title=["\'](.*?)["\'][^>]*>/i', $field_value, $matches);

                        // Use regex to find and remove title attributes from img tags
                        $pattern = '/<img\b([^>]*)\btitle\s*=\s*(["\'])(.*?)\2([^>]*)>/is';
                        $replacement = '<img$1$3>';
                        $new_value = preg_replace($pattern, $replacement, $field_value);

                        // Count how many title attributes after changes
                        $title_count_after = preg_match_all('/<img[^>]*title=["\'](.*?)["\'][^>]*>/i', $new_value, $matches);
                        $removed_count = $title_count_before - $title_count_after;

                        // If content was changed, update the entity
                        if ($new_value !== $field_value) {
                            $entity->get($field_name)->set($delta, ['value' => $new_value, 'format' => $item->format]);
                            $field_updated = true;
                            $title_attrs_removed += $removed_count;
                            $updated_field_values[] = [
                                'entity_type' => $entity_type,
                                'entity_id' => $entity->id(),
                                'field_name' => $field_name,
                                'delta' => $delta,
                                'old' => $field_value,
                                'new' => $new_value,
                                'removed' => $removed_count,
                                'link' => get_entity_link($entity),
                            ];
                        }
                    }
                }

                // Save entity if any field values were updated
                if ($field_updated) {
                    $entity->save();
                    $updated_entities++;

                    // Get entity label or ID
                    $entity_label = '';
                    if (method_exists($entity, 'label') && $entity->label()) {
                        $entity_label = $entity->label();
                    } else {
                        $entity_label = "ID: " . $entity->id();
                    }

                    // Find parent node for paragraphs
                    $parent_info = '';
                    if ($entity_type == 'paragraph') {
                        try {
                            // Get the parent entity
                            $parent_field = $entity->get('parent_field_name')->value;
                            $parent_type = $entity->get('parent_type')->value;
                            $parent_id = $entity->get('parent_id')->value;

                            if ($parent_type && $parent_id) {
                                // Try to load the parent entity
                                $parent_entity = \Drupal::entityTypeManager()
                                    ->getStorage($parent_type)
                                    ->load($parent_id);

                                if ($parent_entity) {
                                    $parent_label = method_exists($parent_entity, 'label') ? $parent_entity->label() : "ID: $parent_id";
                                    $parent_info = " (in $parent_type: \"$parent_label\", ID: $parent_id)";
                                } else {
                                    $parent_info = " (in $parent_type ID: $parent_id)";
                                }
                            }
                        } catch (\Exception $e) {
                            $parent_info = " (parent lookup failed: {$e->getMessage()})";
                        }
                    }

                    // Track by entity type for summary
                    if (!isset($updated_by_type[$entity_type])) {
                        $updated_by_type[$entity_type] = [];
                    }
                    $updated_by_type[$entity_type][] = [
                        'id' => $entity->id(),
                        'label' => $entity_label,
                        'attributes_removed' => $title_attrs_removed,
                        'parent_info' => $parent_info
                    ];

                    // Add detailed log entry with parent info for paragraphs
                    script_log(sprintf(
                        'Updated %s "%s" (ID: %s)%s: Removed %d title attributes from %s field',
                        $entity_type,
                        $entity_label,
                        $entity->id(),
                        $parent_info,
                        $title_attrs_removed,
                        $field_name
                    ));
                }

                $processed_entities++;
            }
        }
    } catch (\Exception $e) {
        script_log(sprintf('Error processing field %s for entity type %s: %s', $field_name, $entity_type, $e->getMessage()), 'error');
    }
}

// === REPORT GENERATION ===
$total_title_attributes_removed_in_report = 0;
foreach ($updated_field_values as $update) {
    $total_title_attributes_removed_in_report += $update['removed'];
}

$report_html = "<h1>Image Title Attribute Removal Report - {$datetime_suffix}</h1>";
$report_html .= "<p>Found {$total_title_attributes_removed_in_report} title attributes removed in {$updated_entities} entities.</p>";

if (!empty($updated_field_values)) {
    // Group by entity for better organization
    $by_entity = [];
    foreach ($updated_field_values as $update) {
        $entity_key = $update['entity_type'] . '-' . $update['entity_id'];
        if (!isset($by_entity[$entity_key])) {
            $by_entity[$entity_key] = [
                'link' => $update['link'],
                'updates' => []
            ];
        }
        $by_entity[$entity_key]['updates'][] = $update;
    }

    $report_html .= "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; margin-bottom:20px; border-collapse:collapse;'>\n";
    $report_html .= "<thead>\n";
    $report_html .= "<tr style='background:#f0f0f0;'>\n";
    $report_html .= "<th style='width:5%;'>#</th>\n";
    $report_html .= "<th style='width:15%;'>Field Info</th>\n";
    $report_html .= "<th style='width:35%;'>Original HTML</th>\n";
    $report_html .= "<th style='width:35%;'>Updated HTML</th>\n";
    $report_html .= "<th style='width:10%;'>Removed</th>\n";
    $report_html .= "</tr>\n";
    $report_html .= "</thead>\n";
    $report_html .= "<tbody>\n";

    $entity_count = 0;
    foreach ($by_entity as $entity_key => $entity_data) {
        $entity_count++;

        // Entity URL row
        $report_html .= "<tr style='background:#e6e6e6;'>\n";
        $report_html .= "<td colspan='5'><strong>{$entity_count}. Entity:</strong> ";

        $link_url = $entity_data['link'];

        $report_html .= "<a href='" . htmlspecialchars($link_url) . "' target='_blank'>" . htmlspecialchars($link_url) . "</a></td>\n";
        $report_html .= "</tr>\n";

        foreach ($entity_data['updates'] as $update) {
            $report_html .= "<tr>\n";
            $report_html .= "<td style='text-align:center;'>{$entity_count}.{$update['delta']}</td>\n";
            $report_html .= "<td>";
            $report_html .= '<code>' . $update['entity_type'] . '[' . $update['entity_id'] . '].' . $update['field_name'] . '</code>';
            $report_html .= "</td>\n";
            $highlighted_old = htmlspecialchars($update['old']);
            $highlighted_old = preg_replace('/(title=&quot;[^&]*?&quot;)/', '<strong>$1</strong>', $highlighted_old);
            $highlighted_old = preg_replace('/(alt=&quot;[^&]*?&quot;)/', '<strong>$1</strong>', $highlighted_old);
            $report_html .= "<td><pre class='color-error'>" . $highlighted_old . "</pre></td>\n";
            $highlighted_new = htmlspecialchars($update['new']);
            $highlighted_new = preg_replace('/(title=&quot;[^&]*?&quot;)/', '<strong>$1</strong>', $highlighted_new);
            $highlighted_new = preg_replace('/(alt=&quot;[^&]*?&quot;)/', '<strong>$1</strong>', $highlighted_new);
            $report_html .= "<td><pre class='color-success'>" . $highlighted_new . "</pre></td>\n";
            $report_html .= "<td style='text-align:center;'>{$update['removed']}</td>\n";
            $report_html .= "</tr>\n";
        }
    }

    $report_html .= "</tbody>\n";
    $report_html .= "</table>\n";
} else {
    $report_html .= "<p>No image title attributes were removed.</p>";
}

if ($file_system->saveData($report_html, $report_file_uri, FileSystemInterface::EXISTS_REPLACE)) {
    $report_file_path = $file_system->realpath($report_file_uri);
    script_log("Report saved to: {$report_file_path}", 'info');
} else {
    script_log("Failed to save Report to: {$report_file_uri}", 'error');
    $report_file_path = "ERROR creating report.";
}

function get_entity_link($entity) {
    if (method_exists($entity, 'getParentEntity') && $entity->getParentEntity()) {
        return get_entity_link($entity->getParentEntity());
    } else if (method_exists($entity, 'getParent') && $entity->getParent()) {
        return get_entity_link($entity->getParent());
    }

    $entity_type_id = $entity->getEntityTypeId();
    try {
        if ($entity->hasLinkTemplate('canonical')) {
            return $entity->toUrl('canonical', ['absolute' => true])->toString();
        }
    } catch (\Exception $e) {
        // Fallback if URL generation fails.
    }
    return '';
}

// === STEP 3: Create summary and log file ===
// Create a summary of entities updated by type
$summary = [];
$total_removed = 0;
foreach ($updated_by_type as $type => $entities) {
    $type_total = count($entities);
    $entity_list = [];
    $removed_attrs = 0;
    foreach ($entities as $entity_data) {
        $entity_list[] = $entity_data['label'] . ' (ID: ' . $entity_data['id'] . ')' .
            (!empty($entity_data['parent_info']) ? $entity_data['parent_info'] : '');
        $removed_attrs += $entity_data['attributes_removed'];
    }
    $total_removed += $removed_attrs;
    script_log("$type ($type_total): " . implode(', ', $entity_list), 'info');
}

$log_message = "Log: " . get_log_viewer_url($log_file_path);
$report_message = "Report: " . get_log_viewer_url($report_file_path);

// Add summary to log file
script_log("=== SUMMARY ===", 'info');
script_log("Total entities processed: {$processed_entities}", 'info');
script_log("Total entities updated: {$updated_entities}", 'info');
script_log("Total title attributes removed: {$total_removed}", 'info');
script_log("Updated entities by type:\n" . implode("\n", $summary), 'info');
script_log($report_message, 'info');

$execution_time = microtime(true) - $start_time;
script_log("Completed image title removal in @time seconds", 'info', [
    '@time' => round($execution_time, 2)
]);

$final_summary_message = "CSM-231: Removed title attributes from images in WYSIWYG fields. \nEntities processed: {$processed_entities}, \nEntities updated: {$updated_entities}, \nTitle attributes removed: {$total_removed}.";

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message . PHP_EOL . $report_message;
