<?php

/**
 * @file
 * CSM-247: Fix skipped heading levels from a CSV list of URLs.
 *
 * Usage:
 * - drush scr path/to/fix_possible_headings.php [--dry-run]
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

$datetime_suffix = date('Y-m-d_H-i-s');
$report_filename = "{$script_name}_{$datetime_suffix}_report.csv";
$report_file_uri = "public://script_logs/{$report_filename}";
// --- End Script Initialization ---

// Ensure Drupal services are available
if (
    !class_exists('\Drupal')
    || !\Drupal::hasService('path_alias.manager')
    || !\Drupal::hasService('entity_type.manager')
    || !\Drupal::hasService('file_system')
) {
    $error_msg = "Required Drupal services not available. Ensure Drupal is bootstrapped.";
    die("ERROR: {$error_msg}");
}

/** @var \Drupal\Core\Path\AliasManagerInterface $alias_manager */
$alias_manager = \Drupal::service('path_alias.manager');
/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
$entity_type_manager = \Drupal::service('entity_type.manager');
/** @var \Drupal\Core\File\FileSystemInterface $file_system */
$file_system = \Drupal::service('file_system');

script_log("Starting heading analysis script ({$script_name}).", 'info');
script_log("Log file will be: {$log_file_path}", 'info');

// CSV Path and Fix Mode
$csv_path = $file_system->realpath(__DIR__) . '/fix_possible_headings.csv';
script_log("Using CSV file: {$csv_path}", 'info');

$fix_mode = true; // Default to fix_mode when run from update hook.
if (php_sapi_name() === 'cli' && in_array('--dry-run', $_SERVER['argv'])) {
    $fix_mode = false;
}
$mode_text = $fix_mode ? "FIX MODE" : "DRY RUN MODE";
script_log("Running in {$mode_text}", 'info');

// Check if CSV file exists
if (!file_exists($csv_path)) {
    $error_msg = "CSV file not found at {$csv_path}";
    script_log($error_msg, 'error');
    return "Script failed: {$error_msg}. Log: {$log_file_path}";
}

// Read CSV file
script_log("Reading URLs from {$csv_path}...", 'info');
$handle = fopen($csv_path, 'r');
if (!$handle) {
    $error_msg = "Could not open CSV file at {$csv_path}";
    script_log($error_msg, 'error');
    return "Script failed: {$error_msg}. Log: {$log_file_path}";
}

$headers = fgetcsv($handle);
$urls = [];

while (($data = fgetcsv($handle)) !== FALSE) {
    if (isset($data[1]) && !empty($data[1])) {
        $urls[] = [
            'title' => $data[0] ?? 'Unknown',
            'url' => $data[1],
            'html' => $data[3] ?? ''
        ];
    }
}
fclose($handle);
script_log("Found " . count($urls) . " URLs to process.", 'info');

// Track results
$processed_nodes_count = 0;
$successful_fixes_nodes_count = 0;
$failed_to_process_nodes_count = 0;
$total_heading_issues_fixed = 0;
$detailed_fixes_report_data = []; // For CSV report

// Process each URL
foreach ($urls as $index => $item) {
    $url = $item['url'];
    $title = $item['title'];
    $html_snippet_from_csv = $item['html'];

    $current_item_num = $index + 1;
    script_log("Processing [{$current_item_num}/" . count($urls) . "]: Page title '{$title}' URL: {$url}", 'info');

    if (!empty($html_snippet_from_csv)) {
        script_log("Problematic HTML from CSV: " . htmlspecialchars($html_snippet_from_csv), 'debug');
        if (preg_match('/<h([1-6])[^>]*>/', $html_snippet_from_csv, $start_match) &&
            preg_match('/<h([1-6])[^>]*>/', $html_snippet_from_csv, $end_match, 0, strpos($html_snippet_from_csv, '</h'))) {
            $first_level = (int)$start_match[1];
            $second_level = (int)$end_match[1];
            if ($second_level > $first_level + 1) {
                script_log("CONFIRMED CSV ISSUE: Heading level skip from h{$first_level} to h{$second_level}", 'debug');
            }
        }
    }

    $internal_path = '';
    try {
        $path_alias = parse_url($url, PHP_URL_PATH);
        $internal_path = $alias_manager->getPathByAlias($path_alias);
    } catch (\Exception $e) {
        script_log("Error converting URL to internal path for {$url}: " . $e->getMessage(), 'warning');
        $failed_to_process_nodes_count++;
        continue;
    }

    if (preg_match('|^/node/(\d+)|S', $internal_path, $matches)) {
        $node_id = $matches[1];
        script_log("Found Node ID: {$node_id} for path {$internal_path}", 'debug');

        try {
            $node_process_result = processNode($node_id, $fix_mode, $entity_type_manager);
            if ($node_process_result['processed']) {
                $processed_nodes_count++;
                if ($node_process_result['fixed_count'] > 0) {
                    $total_heading_issues_fixed += $node_process_result['fixed_count'];
                    $successful_fixes_nodes_count++;
                    if (!empty($node_process_result['details'])) {
                        foreach($node_process_result['details'] as $detail_item) {
                             $detailed_fixes_report_data[] = [
                                'page_title' => $title,
                                'url' => $url,
                                'node_id' => $node_id,
                                'item_type' => $detail_item['type'], // 'node_field' or 'paragraph'
                                'field_name' => $detail_item['field'],
                                'paragraph_type' => $detail_item['type'] == 'paragraph' ? $detail_item['paragraph_type'] : 'N/A',
                                'issues_fixed_in_field' => $detail_item['count'],
                                'example_before' => $detail_item['example_before'],
                                'example_after' => $detail_item['example_after'],
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            script_log("ERROR processing node {$node_id}: " . $e->getMessage(), 'error');
            $failed_to_process_nodes_count++;
        }
    } else {
        script_log("Could not determine node ID from path {$internal_path} (original URL: {$url})", 'warning');
        $failed_to_process_nodes_count++;
    }
    script_log(str_repeat('-', 80), 'debug');
}

// Summary logging
script_log(str_repeat('=', 80), 'info');
script_log("--- SCRIPT SUMMARY ---", 'info');
script_log(str_repeat('=', 80), 'info');
script_log("Total URLs from CSV: " . count($urls), 'info');
script_log("Nodes processed: {$processed_nodes_count}", 'info');
script_log("Nodes where heading issues were fixed: {$successful_fixes_nodes_count}", 'info');
script_log("Total heading structure issues fixed across all content: {$total_heading_issues_fixed}", 'info');
script_log("Nodes/URLs that failed to process (e.g., not found, path error): {$failed_to_process_nodes_count}", 'info');

// Generate a CSV report file for detailed fixes
if (!empty($detailed_fixes_report_data)) {
    try {
        $report_dir_path = $file_system->realpath(dirname($report_file_uri));
        if (!$file_system->prepareDirectory($report_dir_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            script_log("Failed to create or prepare directory for detailed report: {$report_dir_path}", 'error');
        } else {
            $report_handle = fopen($file_system->realpath($report_file_uri) ?: $report_dir_path . '/' . $report_base_filename, 'w');
            if ($report_handle) {
                fputcsv($report_handle, ['Page Title', 'URL', 'Node ID', 'Item Type', 'Field Name', 'Paragraph Type', 'Issues Fixed', 'Example Before', 'Example After']);
                foreach ($detailed_fixes_report_data as $report_row) {
                    fputcsv($report_handle, $report_row);
                }
                fclose($report_handle);
                script_log("Detailed fixes report saved to: " . $file_system->realpath($report_file_uri), 'info');
            } else {
                script_log("Failed to open detailed report file for writing: " . $file_system->realpath($report_file_uri), 'error');
            }
        }
    } catch (\Exception $e) {
        script_log("Error creating detailed CSV report: " . $e->getMessage(), 'error');
    }
} else {
    script_log("No detailed fixes to report in CSV.", 'info');
}

/**
 * Process a single node to detect and fix heading structure issues.
 */
function processNode($node_id, $fix_mode, EntityTypeManagerInterface $entity_type_manager) {
    $result = [
        'processed' => false,
        'fixed_count' => 0, // Changed from 'fixed' to 'fixed_count' for clarity
        'details' => []
    ];

    $node = Node::load($node_id);
    if (!$node) {
        script_log("Error: Node #{$node_id} not found.", 'warning');
        return $result;
    }

    script_log("Analyzing node #{$node_id}: {$node->label()} ({$node->bundle()})", 'debug');
    $updated_in_node = false;
    $issues_fixed_in_node = 0;

    // Check text fields directly on the node
    script_log("EXAMINING NODE FIELDS for node {$node_id}:", 'debug');
    foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
        if (in_array($field_definition->getType(), ['text_with_summary', 'text_long', 'text'])) {
            if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                $item = $node->get($field_name)->first();
                $value = $item->value;
                $format = $item->format;
                script_log("- Checking node field: {$field_name} on node {$node_id}", 'debug');

                if (preg_match('/<h2[^>]*>/i', $value) && preg_match('/<h4[^>]*>/i', $value)) {
                    script_log("  ✓ Field {$field_name} (Node {$node_id}) contains both h2 and h4 tags. Potential fix needed.", 'debug');
                    $new_value = fix_heading_structure_in_value($value, $issues_in_field);
                    if ($new_value !== $value && $issues_in_field > 0) {
                        $issues_fixed_in_node += $issues_in_field;
                        $result['details'][] = [
                            'type' => 'node_field',
                            'field' => $field_name,
                            'count' => $issues_in_field,
                            'example_before' => htmlspecialchars(substr(strip_tags($value), 0, 70)),
                            'example_after' => htmlspecialchars(substr(strip_tags($new_value), 0, 70))
                        ];
                        if ($fix_mode) {
                            $node->get($field_name)->setValue(['value' => $new_value, 'format' => $format]);
                            $updated_in_node = true;
                            script_log("    ✓ Fixed {$issues_in_field} heading structure issues in node field {$field_name} for Node {$node_id}", 'info');
                        } else {
                            script_log("    DRY RUN: Would fix {$issues_in_field} heading issues in node field {$field_name} for Node {$node_id}", 'info');
                        }
                    }
                }
            }
        }
    }

    // Check for paragraphs
    script_log("EXAMINING PARAGRAPHS for node {$node_id}:", 'debug');
    foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
        if ($field_definition->getType() == 'entity_reference_revisions' &&
            $field_definition->getSetting('target_type') == 'paragraph') {
            if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                script_log("- Checking paragraph field: {$field_name} on node {$node_id}", 'debug');
                $paragraphs = $node->get($field_name)->referencedEntities();
                foreach ($paragraphs as $para_index => $paragraph) {
                    script_log("  Paragraph #{$para_index} (type: {$paragraph->bundle()}, ID: {$paragraph->id()}) in field {$field_name}", 'debug');
                    $paragraph_updated = false;
                    foreach ($paragraph->getFieldDefinitions() as $para_field_name => $para_field_def) {
                        if (in_array($para_field_def->getType(), ['text_long', 'text_with_summary', 'text'])) {
                            if ($paragraph->hasField($para_field_name) && !$paragraph->get($para_field_name)->isEmpty()) {
                                $para_item = $paragraph->get($para_field_name)->first();
                                $para_value = $para_item->value;
                                $para_format = $para_item->format;
                                script_log("    Field: {$para_field_name} in Paragraph {$paragraph->id()}", 'debug');

                                if (preg_match('/<h2[^>]*>/i', $para_value) && preg_match('/<h4[^>]*>/i', $para_value)) {
                                    script_log("    ✓ Paragraph field {$para_field_name} (Para ID {$paragraph->id()}) contains both h2 and h4 tags.", 'debug');
                                    $new_para_value = fix_heading_structure_in_value($para_value, $issues_in_para_field);
                                    if ($new_para_value !== $para_value && $issues_in_para_field > 0) {
                                        $issues_fixed_in_node += $issues_in_para_field;
                                        $result['details'][] = [
                                            'type' => 'paragraph',
                                            'paragraph_type' => $paragraph->bundle(),
                                            'field' => $para_field_name,
                                            'count' => $issues_in_para_field,
                                            'example_before' => htmlspecialchars(substr(strip_tags($para_value), 0, 70)),
                                            'example_after' => htmlspecialchars(substr(strip_tags($new_para_value), 0, 70))
                                        ];
                                        if ($fix_mode) {
                                            $paragraph->get($para_field_name)->setValue(['value' => $new_para_value, 'format' => $para_format]);
                                            $paragraph_updated = true;
                                            script_log("      ✓ Fixed {$issues_in_para_field} heading issues in paragraph field {$para_field_name} (Para ID {$paragraph->id()})", 'info');
                                        } else {
                                            script_log("      DRY RUN: Would fix {$issues_in_para_field} heading issues in paragraph field {$para_field_name} (Para ID {$paragraph->id()})", 'info');
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($paragraph_updated && $fix_mode) { // Save paragraph if it was changed
                        $paragraph->save();
                        $updated_in_node = true; // Mark that the main node needs saving if any paragraph was changed and saved.
                    }
                }
            }
        }
    }

    if ($updated_in_node && $fix_mode) {
        $node->save();
        script_log("Changes have been saved for node {$node_id}", 'info');
    }

    $result['processed'] = true;
    $result['fixed_count'] = $issues_fixed_in_node;
    return $result;
}

/**
 * Helper function to fix heading structure within an HTML string.
 * Replaces H4s that appear after an H2 (and before another H2 or end of content) with H3s,
 * adding a class 'h4' to the new H3 for styling continuity.
 * @param string $html_content The HTML content to fix.
 * @param int &$fixed_count Reference to count how many replacements were made.
 * @return string The modified HTML content.
 */
function fix_heading_structure_in_value($html_content, &$fixed_count) {
    $fixed_count = 0;
    $sections = preg_split('/(<h2[^>]*>.*?<\/h2>)/si', $html_content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $new_overall_value = '';

    foreach ($sections as $section) {
        if (preg_match('/^<h2[^>]*>.*?<\/h2>$/si', $section)) {
            $new_overall_value .= $section; // This is an H2 section starter, add as is.
        } else {
            // This is content after an H2. Replace H4s within this section.
            $fixed_section_content = preg_replace_callback(
                '/(<h4)([^>]*>)(.*?)(<\/h4>)/si',
                function ($matches) use (&$fixed_count) {
                    $fixed_count++;
                    $attributes_part = $matches[2]; // This is the part like ' class="some-class" data-attr="val">'
                    $attributes_inside_tag = rtrim($attributes_part, '>'); // Remove the trailing >

                    // Correct regex to find class attribute and its value
                    if (preg_match('/class=(["\'])(.*?)\1/', $attributes_inside_tag, $class_matches)) {
                        $quote_char = $class_matches[1];
                        $existing_classes = $class_matches[2];
                        $new_classes = trim($existing_classes . ' h4');
                        // Replace the old class attribute with the new one
                        $attributes_inside_tag = preg_replace('/class=(["\'])(.*?)\1/', 'class=' . $quote_char . $new_classes . $quote_char, $attributes_inside_tag, 1);
                    } else {
                        // No existing class attribute, add one
                        $attributes_inside_tag .= ' class="h4"';
                    }
                    return '<h3' . $attributes_inside_tag . '>' . $matches[3] . '</h3>';
                },
                $section
            );
            $new_overall_value .= $fixed_section_content;
        }
    }
    return $new_overall_value;
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

$final_summary_message = sprintf(
    "Heading analysis script completed. URLs processed: %d. Nodes with fixes: %d. Total issues fixed: %d.",
    count($urls),
    $successful_fixes_nodes_count,
    $total_heading_issues_fixed
);
$log_message = "Log: {$log_file_path}";
$report_message = "Report: {$report_file_path}";

script_log($final_summary_message, 'info');
script_log($log_message, 'info');
script_log($report_message, 'info');

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message . PHP_EOL . $report_message;
