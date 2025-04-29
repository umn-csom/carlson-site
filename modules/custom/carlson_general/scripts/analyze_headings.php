<?php

/**
 * Script to detect and fix heading structure issues from a CSV list of URLs.
 * 
 * Usage: drush scr analyze_headings.php [path_to_csv] [--dry-run]
 */

// Replace these lines:
$csv_path = DRUPAL_ROOT . '/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/alerts_skipped_heading_level.csv';
echo "Using CSV file: {$csv_path}\n";

$fix_mode = !in_array('--dry-run', $_SERVER['argv']);
$mode_text = $fix_mode ? "FIX MODE" : "DRY RUN MODE";
echo "Running in {$mode_text}\n";

// Check if CSV file exists
if (!file_exists($csv_path)) {
    echo "Error: CSV file not found at {$csv_path}\n";
    exit(1);
}

// Read CSV file
echo "Reading URLs from {$csv_path}...\n";
$handle = fopen($csv_path, 'r');
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

echo "Found " . count($urls) . " URLs to process.\n\n";

// Track results
$processed = 0;
$successful = 0;
$failed = 0;
$fixed_count = 0;

// More detailed tracking of fixes
$detailed_fixes = [];

// Process each URL
foreach ($urls as $index => $item) {
    $url = $item['url'];
    $title = $item['title'];
    $html_snippet = $item['html']; // This comes from your CSV
    
    $current = $index + 1;
    $total = $processed + $failed + 1;
    echo "Processing [{$current}/{$total}]: {$title}\n";
    echo "URL: {$url}\n";
    
    // Show the problematic HTML
    if (!empty($html_snippet)) {
        echo "HTML with issue: " . htmlspecialchars($html_snippet) . "\n";
        
        // Extract heading levels from the HTML snippet
        if (preg_match('/<h([1-6])[^>]*>/', $html_snippet, $start_match) && 
            preg_match('/<h([1-6])[^>]*>/', $html_snippet, $end_match, 0, strpos($html_snippet, '</h'))) {
            $first_level = (int)$start_match[1];
            $second_level = (int)$end_match[1];
            
            if ($second_level > $first_level + 1) {
                echo "CONFIRMED ISSUE: Heading level skip from h{$first_level} to h{$second_level}\n";
            }
        }
    }
    
    // Convert URL to internal path
    $path_alias = parse_url($url, PHP_URL_PATH);
    $internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($path_alias);
    
    // Extract node ID from internal path
    if (preg_match('|^/node/(\d+)|', $internal_path, $matches)) {
        $node_id = $matches[1];
        echo "Found Node ID: {$node_id}\n";
        
        // Process the node
        try {
            $result = processNode($node_id, $fix_mode);
            if ($result['processed']) {
                $processed++;
                if ($result['fixed'] > 0) {
                    $fixed_count += $result['fixed'];
                    $successful++;
                    
                    // Store detailed fix information
                    if (!empty($result['details'])) {
                        $detailed_fixes[$url] = [
                            'title' => $title,
                            'node_id' => $node_id,
                            'fixed_count' => $result['fixed'],
                            'details' => $result['details']
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            echo "ERROR processing node: " . $e->getMessage() . "\n";
            $failed++;
        }
    } else {
        echo "Could not determine node ID from path {$internal_path}\n";
        $failed++;
    }
    
    echo str_repeat('-', 80) . "\n\n";
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Total URLs: " . count($urls) . "\n";
echo "Successfully processed: {$processed}\n";
echo "Failed to process: {$failed}\n";
echo "Pages with fixed issues: {$successful}\n";
echo "Total heading issues fixed: {$fixed_count}\n";

// Add this comprehensive report at the end, after the summary section:
if (!empty($detailed_fixes)) {
    echo "\n\n=== DETAILED FIX REPORT ===\n";
    echo "The following heading structure issues were fixed:\n\n";
    
    foreach ($detailed_fixes as $url => $fix_data) {
        echo "PAGE: {$fix_data['title']}\n";
        echo "URL: {$url}\n";
        echo "Node ID: {$fix_data['node_id']}\n";
        echo "Total fixes: {$fix_data['fixed_count']}\n";
        
        foreach ($fix_data['details'] as $i => $detail) {
            $item_number = $i + 1;
            echo "  {$item_number}. ";
            
            if ($detail['type'] == 'paragraph') {
                echo "Paragraph type '{$detail['paragraph_type']}', field '{$detail['field']}'\n";
            } else {
                echo "Node field '{$detail['field']}'\n";
            }
            
            echo "     Fixed {$detail['count']} heading structure issues\n";
            echo "     Example: '{$detail['example_before']}' → '{$detail['example_after']}'\n";
        }
        
        echo str_repeat('-', 80) . "\n";
    }
    
    // Generate a CSV report file
    $report_file = __DIR__ . '/heading_fixes_report_' . date('Y-m-d_H-i-s') . '.csv';
    $report_handle = fopen($report_file, 'w');
    
    // CSV headers
    fputcsv($report_handle, ['Page Title', 'URL', 'Node ID', 'Field Type', 'Field Name', 'Issue Count']);
    
    // CSV data
    foreach ($detailed_fixes as $url => $fix_data) {
        foreach ($fix_data['details'] as $detail) {
            fputcsv($report_handle, [
                $fix_data['title'],
                $url,
                $fix_data['node_id'],
                $detail['type'],
                $detail['field'],
                $detail['count']
            ]);
        }
    }
    
    fclose($report_handle);
    echo "\nDetailed report saved to: {$report_file}\n";
}

/**
 * Process a single node to detect and fix heading structure issues
 */
function processNode($node_id, $fix_mode) {
    $result = [
        'processed' => false,
        'fixed' => 0,
        'details' => [] // Add this to track details
    ];
    
    // Load the node
    $node = \Drupal\node\Entity\Node::load($node_id);
    if (!$node) {
        echo "Error: Node #{$node_id} not found.\n";
        return $result;
    }
    
    echo "Analyzing node #{$node_id}: {$node->label()}\n";
    
    // Track if any updates were made
    $updated = false;
    $issues_fixed = 0;
    
    // Check for paragraphs
    echo "EXAMINING PARAGRAPHS:\n";
    foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
        if (
            $field_definition->getType() == 'entity_reference_revisions' &&
            $field_definition->getSetting('target_type') == 'paragraph'
        ) {
            if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                echo "- Checking paragraph field: {$field_name}\n";
                
                $paragraphs = $node->get($field_name)->referencedEntities();
                foreach ($paragraphs as $index => $paragraph) {
                    echo "  Paragraph #{$index} (type: {$paragraph->getType()})\n";
                    
                    foreach ($paragraph->getFieldDefinitions() as $para_field_name => $para_field_def) {
                        if (in_array($para_field_def->getType(), ['text_long', 'text_with_summary', 'text'])) {
                            if ($paragraph->hasField($para_field_name) && !$paragraph->get($para_field_name)->isEmpty()) {
                                $para_item = $paragraph->get($para_field_name)->first();
                                $para_value = $para_item->value;
                                
                                echo "    Field: {$para_field_name}\n";
                                
                                // Check if paragraph field contains both h2 and h4 tags
                                if (preg_match('/<h2[^>]*>/i', $para_value) && preg_match('/<h4[^>]*>/i', $para_value)) {
                                    echo "    ✓ Field contains both h2 and h4 tags\n";
                                    
                                    // First, identify sections that start with h2 and end at the next h2 or end of content
                                    $sections = preg_split('/(<h2[^>]*>.*?<\/h2>)/si', $para_value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                                    $new_value = '';

                                    foreach ($sections as $index => $section) {
                                        // If this is an h2 heading section starter
                                        if (preg_match('/^<h2[^>]*>.*?<\/h2>$/si', $section)) {
                                            $new_value .= $section;
                                        } else {
                                            // For content after an h2, replace all h4 with h3
                                            $fixed_section = preg_replace_callback('/(<h4)([^>]*>)(.*?)(<\/h4>)/si', 
                                                function($matches) {
                                                    $tag_start = $matches[1]; // <h4
                                                    $attributes = $matches[2]; // any attributes including >
                                                    $content = $matches[3]; // content between tags
                                                    $tag_end = $matches[4]; // </h4>
                                                    
                                                    // Check if class attribute already exists
                                                    if (strpos($attributes, 'class="') !== false) {
                                                        // Append h4 class to existing classes
                                                        $attributes = preg_replace('/class="([^"]*)"/', 'class="$1 h4"', $attributes);
                                                    } else if (strpos($attributes, "class='") !== false) {
                                                        // Handle single quotes too
                                                        $attributes = preg_replace('/class=\'([^\']*)\'/', "class='$1 h4'", $attributes);
                                                    } else {
                                                        // Add class attribute before the closing >
                                                        $attributes = rtrim($attributes, '>') . ' class="h4">';
                                                    }
                                                    
                                                    return '<h3' . $attributes . $content . '</h3>';
                                                }, 
                                                $section
                                            );
                                            $new_value .= $fixed_section;
                                        }
                                    }

                                    // Count how many we fixed
                                    $fixed_count = substr_count($para_value, '<h4') - substr_count($new_value, '<h4');
                                    $issues_fixed += $fixed_count;

                                    if ($fix_mode && $fixed_count > 0) {
                                        $paragraph->set($para_field_name, [
                                            'value' => $new_value,
                                            'format' => $para_item->format
                                        ]);
                                        $paragraph->save();
                                        $updated = true;
                                        echo "    ✓ Fixed heading structure in paragraph {$para_field_name}\n";
                                        
                                        // Add detailed information about the fix
                                        $result['details'][] = [
                                            'type' => 'paragraph',
                                            'paragraph_type' => $paragraph->getType(),
                                            'field' => $para_field_name,
                                            'count' => $fixed_count,
                                            'example_before' => substr(strip_tags($para_value), 0, 50),
                                            'example_after' => substr(strip_tags($new_value), 0, 50)
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // First check body field and other primary text fields
    echo "EXAMINING NODE FIELDS:\n";
    foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
        if (
            $field_definition->getType() == 'text_with_summary' ||
            $field_definition->getType() == 'text_long' ||
            $field_definition->getType() == 'text'
        ) {
            if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                $item = $node->get($field_name)->first();
                $value = $item->value;
                
                echo "- Checking field: {$field_name}\n";
                
                // Check if field contains both h2 and h4 tags
                if (preg_match('/<h2[^>]*>/i', $value) && preg_match('/<h4[^>]*>/i', $value)) {
                    echo "  ✓ Field contains both h2 and h4 tags\n";
                    
                    // First, identify sections that start with h2 and end at the next h2 or end of content
                    $sections = preg_split('/(<h2[^>]*>.*?<\/h2>)/si', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    $new_value = '';

                    foreach ($sections as $index => $section) {
                        // If this is an h2 heading section starter
                        if (preg_match('/^<h2[^>]*>.*?<\/h2>$/si', $section)) {
                            $new_value .= $section;
                        } else {
                            // For content after an h2, replace all h4 with h3
                            $fixed_section = preg_replace_callback('/(<h4)([^>]*>)(.*?)(<\/h4>)/si', 
                                function($matches) {
                                    $tag_start = $matches[1]; // <h4
                                    $attributes = $matches[2]; // any attributes including >
                                    $content = $matches[3]; // content between tags
                                    $tag_end = $matches[4]; // </h4>
                                    
                                    // Check if class attribute already exists
                                    if (strpos($attributes, 'class="') !== false) {
                                        // Append h4 class to existing classes
                                        $attributes = preg_replace('/class="([^"]*)"/', 'class="$1 h4"', $attributes);
                                    } else if (strpos($attributes, "class='") !== false) {
                                        // Handle single quotes too
                                        $attributes = preg_replace('/class=\'([^\']*)\'/', "class='$1 h4'", $attributes);
                                    } else {
                                        // Add class attribute before the closing >
                                        $attributes = rtrim($attributes, '>') . ' class="h4">';
                                    }
                                    
                                    return '<h3' . $attributes . $content . '</h3>';
                                }, 
                                $section
                            );
                            $new_value .= $fixed_section;
                        }
                    }

                    // Count how many we fixed
                    $fixed_count = substr_count($value, '<h4') - substr_count($new_value, '<h4');
                    $issues_fixed += $fixed_count;

                    if ($fix_mode && $fixed_count > 0) {
                        $node->set($field_name, [
                            'value' => $new_value,
                            'format' => $item->format
                        ]);
                        $updated = true;
                        echo "  ✓ Fixed heading structure in {$field_name}\n";
                        
                        // Add detailed information about the fix
                        $result['details'][] = [
                            'type' => 'node_field',
                            'field' => $field_name,
                            'count' => $fixed_count,
                            'example_before' => substr(strip_tags($value), 0, 50),
                            'example_after' => substr(strip_tags($new_value), 0, 50)
                        ];
                    }
                }
            }
        }
    }
    
    // Save changes if needed
    if ($updated && $fix_mode) {
        $node->save();
        echo "Changes have been saved for node {$node_id}\n";
    }
    
    $result['processed'] = true;
    $result['fixed'] = $issues_fixed;
    
    return $result;
}
