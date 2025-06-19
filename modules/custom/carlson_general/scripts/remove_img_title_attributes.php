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

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);
// --- End Script Initialization ---

script_log("CSM-231: Starting removal of img title attributes script ({$script_name}).", 'info');
script_log("Log: {$log_file_path}", 'info');

// Set longer execution limits (especially if not CLI, though CLI might also need it)
ini_set('max_execution_time', 1800);  // 30 minutes
ini_set('memory_limit', '512M'); // Increase memory limit if processing many entities
script_log("Set max_execution_time to 1800 seconds and memory_limit to 512M.", 'info');

// Ensure Drupal services are available.
if (
    !class_exists('\Drupal')
    || !\Drupal::hasService('entity_field.manager')
    || !\Drupal::hasService('entity_type.manager')
    || !\Drupal::hasService('entity_type.bundle.info')
) {
    $error_msg = "Required Drupal services not available. Ensure Drupal is bootstrapped or script is run in a Drupal environment.";
    script_log($error_msg, 'error');
    return "ERROR: {$error_msg} Log: {$log_file_path}";
}

/** @var EntityFieldManagerInterface $entity_field_manager */
$entity_field_manager = \Drupal::service('entity_field.manager');
/** @var EntityTypeManagerInterface $entity_type_manager */
$entity_type_manager = \Drupal::service('entity_type.manager');
/** @var EntityTypeBundleInfoInterface $bundle_info_service */
$bundle_info_service = \Drupal::service('entity_type.bundle.info');

$fields_to_process = [];
$processed_entities_count = 0;
$updated_entities_count = 0;
$total_title_attributes_removed = 0;
$updated_entity_details = []; // To store info for the log summary

// === STEP 1: Find all text fields ===
script_log("Step 1: Finding all relevant text fields...", 'info');

// APPROACH 1: Get configured fields (non-base fields)
$field_configs = $entity_type_manager->getStorage('field_config')->loadByProperties([
    'field_type' => ['text', 'text_long', 'text_with_summary'],
]);
foreach ($field_configs as $field_config) {
    $fields_to_process[] = [
        'entity_type' => $field_config->getTargetEntityTypeId(),
        'bundle' => $field_config->getTargetBundle(), // Specific bundle
        'field_name' => $field_config->getName(),
    ];
}
script_log("Found " . count($fields_to_process) . " configured text fields.", 'debug');

// APPROACH 2: Get base fields for all content entity types
$all_entity_types = $entity_type_manager->getDefinitions();
foreach ($all_entity_types as $entity_type_id => $entity_type_definition) {
    if ($entity_type_definition->entityClassImplements(ContentEntityInterface::class)) {
        $base_fields = $entity_field_manager->getBaseFieldDefinitions($entity_type_id);
        foreach ($base_fields as $field_name => $field_definition) {
            if (in_array($field_definition->getType(), ['text', 'text_long', 'text_with_summary'])) {
                $fields_to_process[] = [
                    'entity_type' => $entity_type_id,
                    'bundle' => '*', // Indicate all bundles for this base field
                    'field_name' => $field_name,
                ];
            }
        }
    }
}
script_log("After adding base fields, total potential field definitions: " . count($fields_to_process) . ".", 'debug');

$unique_fields_map = [];
foreach ($fields_to_process as $field) {
    $key = $field['entity_type'] . '|' . $field['field_name'];
    if (!isset($unique_fields_map[$key]) || $field['bundle'] !== '*') {
        $unique_fields_map[$key] = $field;
    }
}
$fields_to_process = array_values($unique_fields_map);
script_log("Found " . count($fields_to_process) . " unique text field definitions to process (entity_type|field_name).", 'info');

script_log("Step 2: Processing entities for each field definition...", 'info');

foreach ($fields_to_process as $field_index => $field_info) {
    $entity_type_id = $field_info['entity_type'];
    $field_name = $field_info['field_name'];
    $bundle = $field_info['bundle'];

    script_log("Processing field definition " . ($field_index + 1) . "/" . count($fields_to_process) . ": {$entity_type_id}->{$field_name} (Bundle: {$bundle})", 'debug');

    try {
        $entity_storage = $entity_type_manager->getStorage($entity_type_id);
        $query = $entity_storage->getQuery()
            ->accessCheck(FALSE)
            ->condition($field_name . '.value', '%<img%title=%', 'LIKE');

        if ($bundle !== '*') {
            $entity_type_definition = $entity_type_manager->getDefinition($entity_type_id);
            $bundle_key = $entity_type_definition->getKey('bundle');
            if ($bundle_key) {
                 $query->condition($bundle_key, $bundle);
            }
        }

        $entity_ids = $query->execute();

        if (empty($entity_ids)) {
            script_log("No entities found with img title attributes in {$entity_type_id}->{$field_name} (Bundle: {$bundle}).", 'debug');
            continue;
        }

        script_log("Found " . count($entity_ids) . " entities for {$entity_type_id}->{$field_name} (Bundle: {$bundle}) with potential titles.", 'info');

        // Process in chunks to manage memory for large sets of entities
        $chunk_size = 50;
        $entity_id_chunks = array_chunk($entity_ids, $chunk_size);

        foreach ($entity_id_chunks as $chunk_of_ids) {
            $entities = $entity_storage->loadMultiple($chunk_of_ids);
            foreach ($entities as $entity) {
                $processed_entities_count++;
                if (!$entity->hasField($field_name)) {
                    script_log("Entity ID {$entity->id()} of type {$entity_type_id} unexpectedly missing field {$field_name}. Skipping.", 'warning');
                    continue;
                }

                $field_items = $entity->get($field_name);
                $field_updated_for_this_entity = false;
                $title_attrs_removed_this_entity = 0;

                foreach ($field_items as $delta => $item) {
                    $field_value = $item->value;
                    if (
                        !empty($field_value)
                        && strpos($field_value, '<img') !== false
                        && strpos($field_value, 'title=') !== false
                    ) {
                        $original_value = $field_value;
                        $new_value = preg_replace_callback(
                            '/(<img\s)([^>]*?)(\s?title\s*=\s*(["\'])(?:(?!\4).)*\4)([^>]*>)/i',
                            function ($matches) {
                                return $matches[1] . trim($matches[2] . ' ' . $matches[5]);
                            },
                            $field_value
                        );

                        $removed_now = substr_count(strtolower($original_value), ' title=') - substr_count(strtolower($new_value), ' title=');

                        if ($new_value !== $original_value && $removed_now > 0) {
                            $item->value = $new_value;
                            $field_updated_for_this_entity = true;
                            $title_attrs_removed_this_entity += $removed_now;
                            script_log("Removed {$removed_now} title attributes from {$entity_type_id} ID {$entity->id()}, field {$field_name}[{$delta}].", 'debug');
                        }
                    }
                }

                if ($field_updated_for_this_entity) {
                    try {
                        $entity->save();
                        $updated_entities_count++;
                        $total_title_attributes_removed += $title_attrs_removed_this_entity;

                        $entity_label_str = (method_exists($entity, 'label') && $entity->label()) ? $entity->label() : "ID: " . $entity->id();
                        $updated_entity_details[] = "Updated {$entity_type_id} '{$entity_label_str}' (ID: {$entity->id()}), Field: {$field_name}. Removed {$title_attrs_removed_this_entity} title(s).";
                        script_log("Successfully saved {$entity_type_id} ID {$entity->id()} after removing {$title_attrs_removed_this_entity} title attributes from field {$field_name}.", 'info');
                    } catch (\Exception $e) {
                        script_log("Error saving {$entity_type_id} ID {$entity->id()}: " . $e->getMessage(), 'error');
                    }
                }
            }
            // Release memory by unsetting entities in the chunk if possible, and clearing static cache
            unset($entities);
            $entity_storage->resetCache($chunk_of_ids);
        }
    } catch (\Exception $e) {
        script_log("Error processing field definition {$entity_type_id}->{$field_name}: " . $e->getMessage(), 'error');
    }
}

script_log("Step 3: Finalizing and creating summary...", 'info');

$summary_lines = [
    "=== Title Attribute Removal Summary ===",
    "Total unique field definitions checked: " . count($fields_to_process),
    "Total entities whose fields were checked: {$processed_entities_count}",
    "Total entities updated (saved): {$updated_entities_count}",
    "Total title attributes removed: {$total_title_attributes_removed}",
    "",
    "Details of updated entities:"
];

if (!empty($updated_entity_details)) {
    foreach($updated_entity_details as $detail_line) {
        $summary_lines[] = "- " . $detail_line;
    }
} else {
    $summary_lines[] = "- No entities had title attributes removed.";
}

foreach($summary_lines as $s_line) {
    script_log($s_line, 'info');
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("Image title attribute removal script finished.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

$final_summary_message = sprintf(
    "CSM-231: Removal of img title attributes complete. Entities checked: %d. Entities updated: %d. Total title attributes removed: %d.",
    $processed_entities_count,
    $updated_entities_count,
    $total_title_attributes_removed
);
$log_message = "Log: " . get_log_viewer_url($log_file_path);

script_log($final_summary_message, 'info');
script_log($log_message, 'info');

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message;
