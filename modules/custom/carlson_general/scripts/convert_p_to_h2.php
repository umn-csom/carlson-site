<?php

/**
 * @file
 * CSM-248: Convert P tags to H2 tags based on PopeTech Alert data in CSV.
 *
 * Usage:
 * - drush scr path/to/convert_p_to_h2.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

// --- Configuration ---
// Set to 0 to process all rows, or set to a number > 0 to limit processing for debugging
$debug_row_limit = 0;
// --- End Configuration ---

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\Entity\Node;

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

$datetime_suffix = date('Y-m-d_H-i-s');
$report_filename = "{$script_name}_{$datetime_suffix}_report.html";
$report_file_uri = "public://script_logs/{$report_filename}";
// --- End Script Initialization ---

script_log("Starting P to H2 conversion script ({$script_name}).", 'info');
script_log("Log file will be: {$log_file_uri}", 'info');
script_log("Report file will be: {$report_file_uri}", 'info');

ini_set('max_execution_time', 1800);
script_log("Set max_execution_time to 1800 seconds.", 'info');

// Ensure Drupal services are available
if (
    !class_exists('\Drupal')
    || !\Drupal::hasService('file_system')
    || !\Drupal::hasService('path_alias.manager')
    || !\Drupal::hasService('entity_type.manager')
    || !\Drupal::hasService('entity_field.manager')
) {
    $error_msg = "Required Drupal services not available. Ensure Drupal is bootstrapped.";
    script_log($error_msg, 'error');
    return "ERROR: {$error_msg} Log: {$log_file_uri}";
}

/** @var FileSystemInterface $file_system */
$file_system = \Drupal::service('file_system');
/** @var AliasManagerInterface $alias_manager */
$alias_manager = \Drupal::service('path_alias.manager');
/** @var EntityTypeManagerInterface $entity_type_manager */
$entity_type_manager = \Drupal::service('entity_type.manager');
/** @var EntityFieldManagerInterface $entity_field_manager */
$entity_field_manager = \Drupal::service('entity_field.manager');

// Helper function to normalize HTML for comparison (moved from original update hook)
$normalizeHtml = function ($html) {
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = str_replace(['&apos;', '&rsquo;'], "'", $html);
    $html = preg_replace('/[\s\n\r\t]+/', ' ', $html);
    $html = str_replace("'", '"', $html);
    $html = str_replace('""', '"', $html);
    return trim($html);
};

$csv_file_path = $file_system->realpath(__DIR__) . '/fix_possible_headings.csv';
script_log("Using CSV file: {$csv_file_path}", 'info');

if (!file_exists($csv_file_path)) {
    $error_msg = "Error: CSV file not found at {$csv_file_path}.";
    script_log($error_msg, 'error');
    return "Script failed: {$error_msg} Log: {$log_file_path}";
}

$csv_data = array_map('str_getcsv', file($csv_file_path));
$header = array_shift($csv_data);
$url_index = array_search('uri', $header);
$html_index = array_search('html', $header);

if ($url_index === false || $html_index === false) {
    $error_msg = "Error: CSV format incorrect. Must contain 'uri' and 'html' columns.";
    script_log($error_msg, 'error');
    return "Script failed: {$error_msg} Log: {$log_file_path}";
}

$processed_nodes_count = 0;
$updated_entities_count = 0;
$total_tags_converted = 0;
$successful_replacements_for_report = [];
$fields_checked_log = [];
$rows_processed_in_this_run = 0;

script_log("Processing " . count($csv_data) . " rows from CSV...", 'info');

foreach ($csv_data as $row_index => $row) {
    // Row limit check
    if ($debug_row_limit > 0 && $updated_entities_count >= $debug_row_limit) {
        script_log("Reached limit of " . $debug_row_limit . " updated entities. Stopping processing.", 'info');
        break;
    }

    if (empty($row[$url_index]) || empty($row[$html_index])) {
        script_log("Skipping CSV row " . ($row_index + 1) . " due to empty URL or HTML.", 'debug');
        continue;
    }

    $url = trim($row[$url_index]);
    $html_to_find_raw = trim($row[$html_index]);
    $html_to_find_normalized = $normalizeHtml($html_to_find_raw);

    script_log("Processing URL: {$url} (CSV row " . ($row_index + 1) . ") Normalized HTML to find: '" . substr($html_to_find_normalized, 0, 100) . "...'", 'debug');

    $url_parts = parse_url($url);
    $path_alias = isset($url_parts['path']) ? $url_parts['path'] : $url;
    if ($path_alias && $path_alias[0] !== '/') {
        $path_alias = '/' . $path_alias;
    }
    $internal_path = $alias_manager->getPathByAlias($path_alias);

    try {
        if (preg_match('|^/node/(\d+)|S', $internal_path, $matches)) {
            $node_id = $matches[1];
            $node = $entity_type_manager->getStorage('node')->load($node_id);

            if (!$node) {
                script_log("Node ID {$node_id} not found for URL {$url}. Skipping.", 'warning');
                continue;
            }
            $processed_nodes_count++;
            $node_title = $node->getTitle();
            script_log("Processing Node '{$node_title}' (ID: {$node_id}) from URL {$url}", 'debug');

            $node_updated_in_this_iteration = false;

            // Process node fields
            foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
                if (in_array($field_definition->getType(), ['text_long', 'text_with_summary', 'string', 'text', 'text_plain', 'text_formatted']) && $node->hasField($field_name)) {
                    $fields_checked_log['node:' . $field_name] = $field_definition->getType();
                    $field = $node->get($field_name);
                    foreach ($field as $delta => $item) {
                        $original_field_value = $item->value;
                        if (strpos($normalizeHtml($original_field_value), $html_to_find_normalized) !== false) {
                            script_log("Potential match in Node ID {$node_id}, Field {$field_name}", 'debug');
                            list($updated_value, $replacements_made, $field_replaced_pairs) = convertHtmlTagsInValue($original_field_value, $html_to_find_normalized, $normalizeHtml);
                            if ($replacements_made > 0) {
                                $item->value = $updated_value;
                                $node_updated_in_this_iteration = true;
                                $total_tags_converted += $replacements_made;
                                $successful_replacements_for_report[] = createReportEntry($url, 'node', $node_id, $field_name, $replacements_made, $field_replaced_pairs);
                                script_log("Replaced {$replacements_made} p tags in Node ID {$node_id}, Field {$field_name}", 'info');
                            }
                        }
                    }
                }
            }

            // Process paragraph fields
            $paragraph_field_types = ['entity_reference_revisions', 'paragraphs'];
            foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
                if (in_array($field_definition->getType(), $paragraph_field_types) && $node->hasField($field_name)) {
                    $paragraphs = $node->get($field_name)->referencedEntities();
                    foreach ($paragraphs as $paragraph) {
                        $paragraph_updated_this_iteration = false;
                        foreach ($paragraph->getFieldDefinitions() as $p_field_name => $p_field_definition) {
                            if (in_array($p_field_definition->getType(), ['text_long', 'text_with_summary', 'string', 'text', 'text_plain', 'text_formatted']) && $paragraph->hasField($p_field_name)) {
                                $fields_checked_log['paragraph:'. $paragraph->bundle() . ':' . $p_field_name] = $p_field_definition->getType();
                                $p_field = $paragraph->get($p_field_name);
                                foreach ($p_field as $p_delta => $p_item) {
                                    $original_p_value = $p_item->value;
                                    if (strpos($normalizeHtml($original_p_value), $html_to_find_normalized) !== false) {
                                        script_log("Potential match in Paragraph ID {$paragraph->id()}, Field {$p_field_name}", 'debug');
                                        list($updated_p_value, $replacements_made_p, $p_field_replaced_pairs) = convertHtmlTagsInValue($original_p_value, $html_to_find_normalized, $normalizeHtml);
                                        if ($replacements_made_p > 0) {
                                            $p_item->value = $updated_p_value;
                                            $paragraph_updated_this_iteration = true;
                                            $total_tags_converted += $replacements_made_p;
                                            $successful_replacements_for_report[] = createReportEntry($url, 'paragraph', $paragraph->id(), $p_field_name, $replacements_made_p, $p_field_replaced_pairs, $paragraph->bundle());
                                            script_log("Replaced {$replacements_made_p} p tags in Paragraph ID {$paragraph->id()}, Field {$p_field_name}", 'info');
                                        }
                                    }
                                }
                            }
                            // Note: No recursive paragraph processing here based on original script structure, assuming direct text fields in referenced paragraphs.
                        }
                        if ($paragraph_updated_this_iteration) {
                            $paragraph->save();
                            $updated_entities_count++; // Count paragraphs as updated entities too
                            $node_updated_in_this_iteration = true; // If a paragraph is saved, the node is effectively updated
                        }
                    }
                }
            }

            if ($node_updated_in_this_iteration) {
                $node->save();
                if (!$paragraph_updated_this_iteration) { // Avoid double counting if only node fields updated
                    $updated_entities_count++;
                }
            }

        } else {
            script_log("URL {$url} (resolved to {$internal_path}) is not a node path. Skipping.", 'warning');
        }
    } catch (\Exception $e) {
        script_log("ERROR processing URL {$url}: " . $e->getMessage(), 'error');
    }

    $rows_processed_in_this_run++;
}

/**
 * Converts p tags matching the normalized $html_to_find to h2 tags in $value.
 */
function convertHtmlTagsInValue($value, $html_to_find_normalized, callable $normalizeHtml) {
    $replacements = 0;
    $current_pos = 0;
    $output_value = $value;
    $replaced_html_pairs = []; // Collect pairs of [original_p_tag, new_h2_tag]

    while (($normalized_pos = strpos($normalizeHtml($output_value), $html_to_find_normalized, $current_pos)) !== false) {
        // Find the actual unnormalized HTML snippet in $output_value that corresponds to $normalized_pos
        // This is tricky. We make an assumption that the length difference is not too extreme.
        // A more robust solution would involve DOM parsing or more complex position mapping.

        // Crude way to find the original <p> tag around the match
        // Search backwards from an estimated position in original string for <p
        $estimated_original_start = $normalized_pos; // Simplified assumption for now
        $search_start_offset = max(0, $estimated_original_start - 100); // search window
        $temp_search_area = substr($output_value, $search_start_offset);

        if (preg_match('/(<p[^>]*>.*?<\/p>)/is', $temp_search_area, $match, PREG_OFFSET_CAPTURE)) {
            $matched_p_tag_html = $match[0][0];
            $matched_p_tag_offset_in_search = $match[0][1];
            $actual_p_tag_start_in_output = $search_start_offset + $matched_p_tag_offset_in_search;

            // Check if the normalized version of this found P tag matches what we are looking for
            if ($normalizeHtml($matched_p_tag_html) == $html_to_find_normalized) {
                $actual_p_tag_html = $matched_p_tag_html; // This is the actual P tag we're replacing
                $new_h2_tag = preg_replace('/^<p\\b([^>]*)>(.*?)<\/p>$/is', '<h2$1>$2</h2>', $actual_p_tag_html);
                // Replace &apos; with straight quote in the converted HTML
                $new_h2_tag = str_replace('&apos;', "'", $new_h2_tag);
                $output_value = substr_replace($output_value, $new_h2_tag, $actual_p_tag_start_in_output, strlen($actual_p_tag_html));
                $replacements++;
                $replaced_html_pairs[] = ['original' => $actual_p_tag_html, 'new' => $new_h2_tag];
                $current_pos = $normalized_pos + strlen($normalizeHtml($new_h2_tag)); // Adjusted based on previous fix
                continue;
            }
        }
        // If exact match logic fails or assumptions are wrong, move past the normalized position to avoid infinite loops.
        // This ensures $current_pos is always advanced based on the normalized search string.
        $current_pos = $normalized_pos + strlen($html_to_find_normalized);
    }
    return [$output_value, $replacements, $replaced_html_pairs];
}

/**
 * Creates an entry for the Report.
 */
function createReportEntry($url, $entity_type, $entity_id, $field_name, $replacements_made_in_field, $replaced_pairs_array, $paragraph_bundle = 'N/A') {
    // This function will now return a structure that holds all replacements for a given field.
    return [
        'url' => $url,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'paragraph_bundle' => $paragraph_bundle,
        'field_name' => $field_name,
        'replacements_count_in_field' => $replacements_made_in_field, // Total for this specific field
        'replaced_pairs' => $replaced_pairs_array, // Array of ['original' => '...', 'new' => '...']
    ];
}

// Generate Report
$report_html = "<h1>P to H2 Conversion Report - {$datetime_suffix}</h1>";
$report_html .= "<p>Processed " . count($csv_data) . " CSV rows. Found {$total_tags_converted} p tags converted in {$updated_entities_count} entities (nodes/paragraphs).</p>";

if (!empty($successful_replacements_for_report)) {
    // Group by URL for better organization (similar to old script)
    $by_url = [];
    foreach ($successful_replacements_for_report as $report_item) {
        $url_key = $report_item['url'];
        if (!isset($by_url[$url_key])) {
            $by_url[$url_key] = [];
        }
        $by_url[$url_key][] = $report_item;
    }

    // Start single table
    $report_html .= "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; margin-bottom:20px; border-collapse:collapse;'>\n";
    $report_html .= "<thead>\n";
    $report_html .= "<tr style='background:#f0f0f0;'>\n";
    $report_html .= "<th style='width:8%;'>#</th>\n";
    $report_html .= "<th style='width:22%;'>Field Info</th>\n";
    $report_html .= "<th style='width:25%;'>Original HTML</th>\n";
    $report_html .= "<th style='width:25%;'>Converted HTML</th>\n";
    $report_html .= "<th style='width:20%;'>Rendered Content</th>\n";
    $report_html .= "</tr>\n";
    $report_html .= "</thead>\n";
    $report_html .= "<tbody>\n";

    foreach ($by_url as $url_val => $replacements_in_url) {
        // Get the current domain from the request
        $current_domain = \Drupal::request()->getHost();
        $url_parts = parse_url($url_val);
        $path = $url_parts['path'] ?? '';
        $full_url = 'https://' . $current_domain . $path;

        // URL row
        $report_html .= "<tr style='background:#e6e6e6;'>\n";
        $report_html .= "<td colspan='5'><a href='" . htmlspecialchars($full_url) . "' target='_blank'>" .
                       htmlspecialchars($full_url) . "</a></td>\n";
        $report_html .= "</tr>\n";

        static $url_count = 0;
        $url_count++;
        $instance_count = 0; // Reset instance counter for each URL

        foreach ($replacements_in_url as $item_index => $report_item) {
            foreach($report_item['replaced_pairs'] as $pair_index => $pair) {
                $instance_count++; // Increment instance counter
                $report_html .= "<tr>\n";

                // Replacement number column with decimal system (URL.instance)
                $report_html .= "<td style='text-align:center;'><strong>{$url_count}.{$instance_count}</strong></td>\n";

                // Field info column
                $report_html .= "<td>";
                // Format: entity_type.bundle[id].field
                $report_html .= '<code class="nowrap">' . $report_item['entity_type'] . "</code><wbr/><code class='nowrap'>";
                if ($report_item['entity_type'] == 'paragraph') {
                    $report_html .= '.' . $report_item['paragraph_bundle'];
                }
                $report_html .= '[' . $report_item['entity_id'] . ']</code><wbr/>';
                $report_html .= '<code class="nowrap">.' . $report_item['field_name'] . '</code>';
                $report_html .= "</td>\n";

                // Original HTML column
                $report_html .= "<td>\n";
                $report_html .= "<pre class='color-error'>" .
                               htmlspecialchars($pair['original']) . "</pre>\n";
                $report_html .= "</td>\n";

                // Converted HTML column
                $report_html .= "<td>\n";
                $report_html .= "<pre class='color-success'>" .
                               htmlspecialchars($pair['new']) . "</pre>\n";
                $report_html .= "</td>\n";

                // Rendered content column
                $report_html .= "<td>\n";
                $report_html .= "<code>" . htmlspecialchars(strip_tags($pair['new'])) . "</code>\n";
                $report_html .= "</td>\n";

                $report_html .= "</tr>\n";
            }
        }
    }

    $report_html .= "</tbody>\n";
    $report_html .= "</table>\n";

} else {
    $report_html .= "<p>No p tags were converted or no replacements made it to the report.</p>";
}

if ($file_system->saveData($report_html, $report_file_uri, FileSystemInterface::EXISTS_REPLACE)) {
    $report_file_path = $file_system->realpath($report_file_uri);
    script_log("Report saved to: {$report_file_path}", 'info');
} else {
    script_log("Failed to save Report to: {$report_file_uri}", 'error');
    $report_file_path = "ERROR creating report.";
}

// Final Summary
script_log(str_repeat('=', 80), 'info');
script_log("--- SCRIPT SUMMARY ---", 'info');
script_log(str_repeat('=', 80), 'info');
script_log("Total CSV rows processed: " . count($csv_data), 'info');
script_log("Nodes checked: {$processed_nodes_count}", 'info');
script_log("Total entities updated (nodes/paragraphs saved): {$updated_entities_count}", 'info');
script_log("Total p tags converted to h2: {$total_tags_converted}", 'info');
script_log("Fields checked for matches (format: entity_type:field_name or paragraph:bundle:field_name):", 'info');
foreach ($fields_checked_log as $field_key => $type) {
    script_log("- {$field_key} (type: {$type})", 'info');
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("P to H2 conversion script finished.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

$final_summary_message = sprintf(
    "Script completed. CSV rows: %d. Nodes checked: %d. Entities updated: %d. Tags converted: %d.",
    count($csv_data),
    $processed_nodes_count,
    $updated_entities_count,
    $total_tags_converted
);
$log_message = "Log: {$log_file_path}";
$report_message = "Report: {$report_file_path}";

script_log($final_summary_message, 'info');
script_log($log_message, 'info');
script_log($report_message, 'info');

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message . PHP_EOL . $report_message;
