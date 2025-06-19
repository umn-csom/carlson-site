<?php

/**
 * Drupal script to detect and fix fragmented links in tabbed_content paragraphs.
 *
 * Usage:
 * - drush scr modules/custom/carlson_general/scripts/fix_fragmented_links.php
 * - May also be included by an update hook.
 */

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);
$drupal_root = \Drupal::root();
// --- End Script Initialization ---

script_log("Starting fragmented links detection and repair script ({$script_name}).", 'info');
script_log("Log file will be: {$log_file_path}", 'info');

// Ensure Drupal services are available
if (
    !class_exists('\Drupal')
    || !\Drupal::hasService('entity_type.manager')
    || !\Drupal::hasService('file_system')
) {
    $error_msg = "Required Drupal services (entity_type.manager, file_system) not available. Ensure Drupal is bootstrapped.";
    script_log($error_msg, 'error');
    return "ERROR: {$error_msg}";
}

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
$entity_type_manager = \Drupal::service('entity_type.manager');
/** @var \Drupal\Core\File\FileSystemInterface $file_system */
$file_system = \Drupal::service('file_system');


// Get all paragraph IDs of type tabbed_content
try {
    $tabbed_content_ids = $entity_type_manager->getStorage('paragraph')
        ->getQuery()
        ->condition('type', 'tabbed_content')
        ->accessCheck(FALSE)
        ->execute();
} catch (\Exception $e) {
    $error_message = "Script error. Error querying tabbed_content paragraphs: " . $e->getMessage();
    script_log($error_message, 'error');
    return $error_message . PHP_EOL . "Detailed log: {$log_file_path}";
}


if (empty($tabbed_content_ids)) {
    $execution_time = microtime(true) - $start_time;
    $final_summary = "Script complete. No tabbed_content paragraphs found.";
    script_log($final_summary, 'info');
    script_log(sprintf("Total execution time: %.2f seconds.", $execution_time), 'info');
    return $final_summary . PHP_EOL . "Detailed log: {$log_file_path}";
}

script_log("Found " . count($tabbed_content_ids) . " tabbed_content paragraphs.", 'info');
$paragraph_storage = $entity_type_manager->getStorage('paragraph');

// Counter for paragraphs with possible fragmented URLs
$fragmented_count = 0;
$fixed_count = 0;
$total_tabs = 0;
$all_fragmented_links = [];

// Tracking arrays
$fragmented_links_by_node = [];
$updated_nodes = [];

/**
 * Function to extract and analyze fragmented links with their HTML
 */
function extractFragmentedLinks($content, $location)
{
    $links = [];
    script_log("Extracting fragmented links from: {$location}", 'debug');

    // Look for common fragmented link patterns
    if (strpos($content, '<wbr><a') !== false) {
        // Pattern 1: Link followed by <wbr> then another link with same URL
        preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a><wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $links[] = [
                'url' => $match[1],
                'type' => 'wbr-separated',
                'location' => $location,
                'html' => $match[0]
            ];
        }

        // Pattern 2: Multiple fragments with <wbr> separators
        preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a>(?:<wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>)+/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $links[] = [
                'url' => $match[1],
                'type' => 'multi-fragment',
                'location' => $location,
                'html' => $match[0]
            ];
        }
    }

    // Pattern 3: Adjacent links with same URL
    preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $links[] = [
            'url' => $match[1],
            'type' => 'adjacent',
            'location' => $location,
            'html' => $match[0]
        ];
    }

    return $links;
}

/**
 * Validates if a link is actually fragmented rather than a properly formatted link
 * with Google's safe redirect attributes.
 */
function isActuallyFragmentedLink($content)
{
    // Skip fixing for proper links with saferedirecturl that aren't actually fragmented
    if (strpos($content, 'data-saferedirecturl') !== false) {
        // Count the number of <a> tags vs. <wbr> tags
        $a_count = substr_count($content, '<a ');
        $wbr_count = substr_count($content, '<wbr>');

        // If there's only one <a> tag, it's not fragmented
        if ($a_count == 1) {
            return false;
        }

        // If it's a proper formatted Google redirected link without <wbr>
        if (preg_match('/<a[^>]*target="_blank"[^>]*data-saferedirecturl="[^"]*"[^>]*>[^<]*<\/a>$/', $content) && $wbr_count == 0) {
            return false;
        }
    }

    return true;
}

/**
 * Special handler for specific problematic links like medicalalley.org
 */
function fixSpecificProblematicLinks($content)
{
    $original_content_for_fix_specific = $content; // Renamed to avoid conflict
    $merged_here = 0; // Renamed
    $changed_here = false; // Renamed
    script_log("Attempting fixSpecificProblematicLinks", 'debug');

    // Medical Alley specific fix
    if (strpos($original_content_for_fix_specific, 'medicalalley.org/2020/04/university-of-minnesota-researchers') !== false) {
        script_log("Found 'medicalalley.org' link pattern.", 'debug');
        // First check if this is a valid single link with saferedirecturl (don't break those)
        if (preg_match('/<a[^>]*target="_blank"[^>]*data-saferedirecturl="[^"]*"[^>]*>.*?<\/a>/', $original_content_for_fix_specific)) {
            script_log("Skipping Medical Alley fix: Already a proper saferedirecturl link.", 'debug');
            return [
                'content' => $original_content_for_fix_specific,
                'merged_count' => 0,
                'changed' => false
            ];
        }

        // Check for actual fragmented links
        if (
            strpos($original_content_for_fix_specific, '<wbr><a') !== false ||
            preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>/', $original_content_for_fix_specific)
        ) {
            script_log("Medical Alley link appears fragmented. Attempting fix.", 'debug');
            // Get all attributes from the first link including target and data-saferedirecturl
            preg_match('/<a([^>]+)href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"]([^>]*)>/', $original_content_for_fix_specific, $attr_match);

            if (!empty($attr_match)) {
                $attrs = $attr_match[1] . ' ' . $attr_match[2];

                // Create a better replacement preserving all attributes
                $replacement = "<a{$attrs}>University of Minnesota COVID-19 Hospitalization Project</a>";
                script_log("Replacing with: " . htmlspecialchars($replacement), 'debug');

                // Pattern to match the entire chain of medicalalley.org links
                $pattern = '/<a[^>]+href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"][^>]*>[^<]*<\/a>(?:<wbr><a[^>]+href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"][^>]*>[^<]*<\/a>)*/';

                $new_content_specific = preg_replace($pattern, $replacement, $original_content_for_fix_specific); // Renamed var

                if ($new_content_specific !== $original_content_for_fix_specific) {
                    script_log("Medical Alley link fixed.", 'info');
                    return [
                        'content' => $new_content_specific,
                        'merged_count' => 1, // Simplified: counts as one major fix
                        'changed' => true
                    ];
                }
            }
        }
    }

    // General case for data-saferedirecturl links - Don't modify intact saferedirecturl links
    if (strpos($original_content_for_fix_specific, 'data-saferedirecturl') !== false && strpos($original_content_for_fix_specific, '<wbr>') !== false) {
        script_log("Found 'data-saferedirecturl' with '<wbr>'. Checking for fragmentation.", 'debug');
        // Check if there are actual fragmented links or just proper links with saferedirecturl
        if (preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a><wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>/', $original_content_for_fix_specific)) {
            script_log("Fragmented 'data-saferedirecturl' link found. Attempting fix.", 'debug');
            preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*data-saferedirecturl=[\'"][^\'"]+[\'"][^>]*>([^<]*)<\/a>/', $original_content_for_fix_specific, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                script_log("Processing URL for saferedirecturl fix: {$url}", 'debug');

                // Count fragments with this URL
                preg_match_all('/<a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>([^<]*)<\/a>/', $original_content_for_fix_specific, $fragments);

                if (count($fragments[1]) > 1) {
                    script_log("Found " . count($fragments[1]) . " fragments for URL: {$url}", 'debug');
                    // Get attributes from first link - preserve all attributes
                    preg_match('/<a([^>]+)href=[\'"]' . preg_quote($url, '/') . '[\'"]([^>]*)>/', $original_content_for_fix_specific, $attr_match);
                    $attrs = $attr_match[1] . ' ' . $attr_match[2];

                    // Create replacement with combined text
                    $combined_text = implode('', $fragments[1]);
                    $replacement = "<a{$attrs}>{$combined_text}</a>";
                    script_log("Replacing with: " . htmlspecialchars($replacement), 'debug');

                    // Pattern to match entire chain
                    $pattern = '/<a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>[^<]*<\/a>(?:<wbr><a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>[^<]*<\/a>)*/';

                    $new_content_specific = preg_replace($pattern, $replacement, $original_content_for_fix_specific); // Renamed var

                    if ($new_content_specific !== $original_content_for_fix_specific) {
                        script_log("Fixed fragmented saferedirecturl link for URL: {$url}", 'info');
                        return [
                            'content' => $new_content_specific,
                            'merged_count' => count($fragments[1]) - 1,
                            'changed' => true
                        ];
                    }
                }
            }
        }
    }

    // No specific fix found
    script_log("No specific problematic links fixed in this pass.", 'debug');
    return [
        'content' => $original_content_for_fix_specific,
        'merged_count' => 0,
        'changed' => false
    ];
}

/**
 * Function to fix fragmented links in HTML content
 */
function fixFragmentedLinks($content)
{
    $original_content_for_fix_general = $content; // Renamed
    $merged_here_general = 0; // Renamed
    $changed_here_general = false; // Renamed
    script_log("Attempting fixFragmentedLinks (general)", 'debug');

    // First pass: Remove all <wbr> tags
    $content_no_wbr = str_replace('<wbr>', '', $original_content_for_fix_general);
    if ($content_no_wbr !== $original_content_for_fix_general) {
        script_log("Removed <wbr> tags.", 'debug');
        $changed_here_general = true;
    }

    // Second pass: Simple adjacent link merger
    $pattern = '/(<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>)([^<]*)<\/a>\s*<a[^>]+href=[\'"]\\2[\'"][^>]*>([^<]*)<\/a>/';
    $replacement = '$1$3$4</a>';

    $new_content_adj = preg_replace($pattern, $replacement, $content_no_wbr);
    if ($new_content_adj !== $content_no_wbr) {
        script_log("Merged adjacent links.", 'debug');
        $merged_here_general++;
        $changed_here_general = true;
        return [ // Return early if this simple fix worked
            'content' => $new_content_adj,
            'merged_count' => $merged_here_general,
            'changed' => true
        ];
    }

    // Third pass: DOM-based approach for more complex cases
    script_log("Attempting DOM-based link merging.", 'debug');
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    // Ensure content is UTF-8 for DOM parsing
    if (!mb_check_encoding($new_content_adj, 'UTF-8')) {
        $new_content_adj = mb_convert_encoding($new_content_adj, 'UTF-8');
    }
    // Wrap content in a div with UTF-8 meta tag to help DOM parser
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $new_content_adj . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new \DOMXPath($dom);
    $links = $xpath->query('//a');

    // Group links by href
    $link_groups = [];
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (!empty($href)) {
            if (!isset($link_groups[$href])) {
                $link_groups[$href] = [];
            }
            $link_groups[$href][] = $link;
        }
    }

    // Find and merge adjacent links with same href
    $merged_count = 0;
    foreach ($link_groups as $href => $group) {
        if (count($group) > 1) {
            $adjacentGroups = [];
            $currentGroup = [$group[0]];

            // Group adjacent links
            for ($i = 1; $i < count($group); $i++) {
                $prevLink = $group[$i - 1];
                $currLink = $group[$i];

                // Check if links are adjacent
                $isAdjacent = false;
                $node = $prevLink;

                while ($node = $node->nextSibling) {
                    if ($node === $currLink) {
                        $isAdjacent = true;
                        break;
                    }

                    // Skip whitespace nodes
                    if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                        continue;
                    }

                    break;
                }

                if ($isAdjacent) {
                    $currentGroup[] = $currLink;
                } else {
                    if (count($currentGroup) > 1) {
                        $adjacentGroups[] = $currentGroup;
                    }
                    $currentGroup = [$currLink];
                }
            }

            if (count($currentGroup) > 1) {
                $adjacentGroups[] = $currentGroup;
            }

            // Merge each group of adjacent links
            foreach ($adjacentGroups as $links) {
                $newLink = $dom->createElement('a');

                // Copy attributes from first link
                foreach ($links[0]->attributes as $attr) {
                    $newLink->setAttribute($attr->name, $attr->value);
                }

                // Combine text content
                $combinedText = '';
                foreach ($links as $link) {
                    $combinedText .= $link->textContent;
                }

                $textNode = $dom->createTextNode($combinedText);
                $newLink->appendChild($textNode);

                // Replace first link with combined link
                $links[0]->parentNode->replaceChild($newLink, $links[0]);

                // Remove remaining links
                for ($i = 1; $i < count($links); $i++) {
                    if ($links[$i]->parentNode) {
                        $links[$i]->parentNode->removeChild($links[$i]);
                    }
                }

                $merged_count++;
            }
        }
    }

    if ($merged_count > 0) {
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $fixed_content = $dom->saveHTML($body);
            $fixed_content = preg_replace(['/<body[^>]*>/', '/<\/body>/'], '', $fixed_content);

            return [
                'content' => $fixed_content,
                'merged_count' => $merged_count,
                'changed' => true
            ];
        }
    }

    return [
        'content' => $content,
        'merged_count' => 0,
        'changed' => ($original_content_for_fix_general !== $content)
    ];
}

/**
 * Fix all fragmented links in content with multiple passes
 */
function fixAllFragmentedLinks($content, $max_passes = 3)
{
    $original_content_for_fix_all = $content;
    $pass_count = 0;
    $total_fixes_in_all = 0;
    $changes_made_in_pass = true;

    script_log("Starting fixAllFragmentedLinks (max passes: {$max_passes})", 'info');

    // Continue fixing until no more changes or max passes reached
    while ($changes_made_in_pass && $pass_count < $max_passes) {
        $pass_count++;
        $changes_made_in_pass = false;
        script_log("--- fixAllFragmentedLinks Pass {$pass_count} ---", 'debug');

        // First try specific fixes
        $result_specific = fixSpecificProblematicLinks($content);

        if ($result_specific['changed']) {
            $content = $result_specific['content'];
            $total_fixes_in_all += $result_specific['merged_count'];
            $changes_made_in_pass = true;
            script_log("   Pass {$pass_count} (Specific): Fixed {$result_specific['merged_count']} links", 'info');
        } else {
            // Then try general fixes
            $result_general = fixFragmentedLinks($content);

            if ($result_general['changed']) {
                $content = $result_general['content'];
                $total_fixes_in_all += $result_general['merged_count'];
                $changes_made_in_pass = true;
                script_log("   Pass {$pass_count} (General): Fixed {$result_general['merged_count']} links", 'info');
            }
        }

        if (!$changes_made_in_pass) {
            script_log("   Pass {$pass_count}: No changes made in this pass.", 'debug');
        }
    }
    script_log("Finished fixAllFragmentedLinks after {$pass_count} passes. Total links fixed in this call: {$total_fixes_in_all}", 'info');

    return [
        'content' => $content,
        'merged_count' => $total_fixes_in_all,
        'changed' => ($original_content_for_fix_all !== $content),
        'passes' => $pass_count
    ];
}

/**
 * Removes data-saferedirecturl attributes and adds text-break class to links
 * that previously contained <wbr> or are long URLs
 */
function removeDataSaferedirecturl($content)
{
    // If no links or no attributes to process, return as is
    if (strpos($content, '<a ') === false) {
        return $content;
    }
    script_log("Attempting removeDataSaferedirecturl and add text-break class.", 'debug');

    // Use DOM to modify HTML properly
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    // Ensure content is UTF-8 for DOM parsing
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8');
    }
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new \DOMXPath($dom);
    $links = $xpath->query('//a');

    // Process each link
    foreach ($links as $link) {
        // Remove data-saferedirecturl attribute if it exists
        if ($link->hasAttribute('data-saferedirecturl')) {
            $link->removeAttribute('data-saferedirecturl');
        }

        // Add text-break class for long URLs or links that likely had <wbr>
        $href = $link->getAttribute('href');
        $text = $link->textContent;

        // Add the class if it's a longer URL or link text
        if (strlen($href) > 30 || strlen($text) > 30) {
            if ($link->hasAttribute('class')) {
                $classes = $link->getAttribute('class');
                if (strpos($classes, 'text-break') === false) {
                    $link->setAttribute('class', $classes . ' text-break');
                }
            } else {
                $link->setAttribute('class', 'text-break');
            }
        }
    }

    // Extract body content
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        $cleaned_content = $dom->saveHTML($body);
        $cleaned_content = preg_replace(['/<body[^>]*>/', '/<\/body>/'], '', $cleaned_content);
        return $cleaned_content;
    }

    // Fallback to regex for data-saferedirecturl removal only
    return preg_replace('/\s+data-saferedirecturl=[\'"][^\'"]*[\'"]/', '', $content);
}

foreach ($tabbed_content_ids as $paragraph_id) {
    $paragraph = NULL; // Initialize
    try {
        $paragraph = $paragraph_storage->load($paragraph_id);
    } catch (\Exception $e) {
        script_log("Error loading paragraph ID {$paragraph_id}: " . $e->getMessage(), 'error');
        continue;
    }

    if (!$paragraph) {
        script_log("Skipping paragraph ID {$paragraph_id}: Failed to load.", 'warning');
        continue;
    }

    $host_entity = NULL; // Initialize
    try {
        $host_entity = $paragraph->getParentEntity();
    } catch (\Exception $e) {
        script_log("Error getting parent entity for paragraph ID {$paragraph_id}: " . $e->getMessage(), 'warning');
        continue; // Skip if parent cannot be determined
    }

    if (!$host_entity || $host_entity->getEntityTypeId() !== 'node') {
        $parent_type = $host_entity ? $host_entity->getEntityTypeId() : 'none';
        script_log("Skipping paragraph ID {$paragraph_id}: Parent entity is not a node (type: {$parent_type}).", 'debug');
        continue;
    }

    $node_id = $host_entity->id();
    $node_title = $host_entity->label();

    // Process tab items
    $tab_items = $paragraph->get('field_tab_accordion_item')->referencedEntities();
    foreach ($tab_items as $tab_item) {
        // Add this line to count each tab
        $total_tabs++;

        // Check main content for fragmented links
        if ($tab_item->hasField('field_main_content') && !$tab_item->get('field_main_content')->isEmpty()) {
            $content = $tab_item->get('field_main_content')->value;

            if (
                preg_match('/<a[^>]+><\/a>\s*<a[^>]+>|<\/a>\s*<a[^>]+href=[\'"][^\'"]+[\'"]/i', $content)
                || strpos($content, '<wbr>') !== false
            ) {

                $heading = $tab_item->hasField('field_heading') && !$tab_item->get('field_heading')->isEmpty()
                    ? $tab_item->get('field_heading')->value
                    : 'No heading';

                $location = "Node ID {$node_id} ('{$node_title}'), Tab: \"{$heading}\"";
                script_log("Checking content in {$location}", 'debug');
                $links = extractFragmentedLinks($content, $location);

                // Add this check before attempting to fix:
                if (!empty($links) && isActuallyFragmentedLink($content)) {
                    if (!empty($links)) {
                        // Add this line to count items with fragmented links
                        $fragmented_count++;

                        // Initialize array for this node if not exists
                        if (!isset($fragmented_links_by_node[$node_id])) {
                            $fragmented_links_by_node[$node_id] = [
                                'title' => $node_title,
                                'links' => []
                            ];
                        }

                        // Add links to this node's collection
                        $fragmented_links_by_node[$node_id]['links'] = array_merge(
                            $fragmented_links_by_node[$node_id]['links'],
                            $links
                        );

                        script_log("Attempting to fix fragmented links in {$location}", 'info');

                        // Use multi-pass approach instead of individual fixes
                        $result = fixAllFragmentedLinks($content);

                        if ($result['changed']) {
                            // Clean Google safe redirect attributes
                            $result['content'] = removeDataSaferedirecturl($result['content']);

                            $tab_item->set('field_main_content', [
                                'value' => $result['content'],
                                'format' => $tab_item->get('field_main_content')->format
                            ]);
                            try {
                                $tab_item->save();
                                script_log("Saved tab item '{$heading}' in paragraph ID {$paragraph_id} (Node {$node_id})", 'info');
                            } catch (\Exception $e) {
                                script_log("Error saving tab item '{$heading}' (Node {$node_id}): " . $e->getMessage(), 'error');
                            }

                            $fixed_count += $result['merged_count'];

                            // Track which nodes and links were updated
                            if (!isset($updated_nodes[$node_id])) {
                                $updated_nodes[$node_id] = [
                                    'title' => $node_title,
                                    'fixes' => 0,
                                    'links' => [],
                                    'passes' => []
                                ];
                            }

                            $updated_nodes[$node_id]['fixes'] += $result['merged_count'];
                            $updated_nodes[$node_id]['passes'][$location] = $result['passes'];

                            // Record the fixed links
                            foreach ($links as $link) {
                                $updated_nodes[$node_id]['links'][] = $link;
                            }
                        }
                    }
                }
            }
        }

        // Check subitems
        if ($tab_item->hasField('field_tab_accordion_subitem') && !$tab_item->get('field_tab_accordion_subitem')->isEmpty()) {
            $subitems = $tab_item->get('field_tab_accordion_subitem')->referencedEntities();

            foreach ($subitems as $subitem) {
                if ($subitem->hasField('field_main_content') && !$subitem->get('field_main_content')->isEmpty()) {
                    $subcontent = $subitem->get('field_main_content')->value;

                    if (
                        preg_match('/<a[^>]+><\/a>\s*<a[^>]+>|<\/a>\s*<a[^>]+href=[\'"][^\'"]+[\'"]/i', $subcontent)
                        || strpos($subcontent, '<wbr>') !== false
                    ) {

                        $heading = $tab_item->hasField('field_heading') && !$tab_item->get('field_heading')->isEmpty()
                            ? $tab_item->get('field_heading')->value
                            : 'No heading';

                        $subheading = $subitem->hasField('field_heading') && !$subitem->get('field_heading')->isEmpty()
                            ? $subitem->get('field_heading')->value
                            : 'No heading';

                        $location = "Node ID {$node_id} ('{$node_title}'), Tab: \"{$heading}\", Subitem: \"{$subheading}\"";
                        script_log("Checking content in {$location}", 'debug');
                        $links = extractFragmentedLinks($subcontent, $location);

                        // Add this check before attempting to fix:
                        if (!empty($links) && isActuallyFragmentedLink($subcontent)) {
                            if (!empty($links)) {
                                // Add this line to count items with fragmented links
                                $fragmented_count++;

                                if (!isset($fragmented_links_by_node[$node_id])) {
                                    $fragmented_links_by_node[$node_id] = [
                                        'title' => $node_title,
                                        'links' => []
                                    ];
                                }

                                $fragmented_links_by_node[$node_id]['links'] = array_merge(
                                    $fragmented_links_by_node[$node_id]['links'],
                                    $links
                                );

                                script_log("Attempting to fix fragmented links in {$location}", 'info');

                                // Try fixing all fragmented links with multiple passes
                                $result = fixAllFragmentedLinks($subcontent);

                                if ($result['changed']) {
                                    // Clean Google safe redirect attributes
                                    $result['content'] = removeDataSaferedirecturl($result['content']);

                                    $subitem->set('field_main_content', [
                                        'value' => $result['content'],
                                        'format' => $subitem->get('field_main_content')->format
                                    ]);
                                    try {
                                        $subitem->save();
                                        script_log("Saved subitem '{$subheading}' under tab '{$heading}' in paragraph ID {$paragraph_id} (Node {$node_id})", 'info');
                                    } catch (\Exception $e) {
                                        script_log("Error saving subitem '{$subheading}' (Node {$node_id}): " . $e->getMessage(), 'error');
                                    }

                                    $fixed_count += $result['merged_count'];

                                    // Track which nodes and links were updated
                                    if (!isset($updated_nodes[$node_id])) {
                                        $updated_nodes[$node_id] = [
                                            'title' => $node_title,
                                            'fixes' => 0,
                                            'links' => []
                                        ];
                                    }

                                    $updated_nodes[$node_id]['fixes'] += $result['merged_count'];

                                    // Record the fixed links
                                    foreach ($links as $link) {
                                        $updated_nodes[$node_id]['links'][] = $link;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Calculate total number of fragmented links first (needed for all reports)
$total_fragmented_links = 0;
foreach ($fragmented_links_by_node as $node_data) { // Iterate over $node_data
    $total_fragmented_links += count($node_data['links']);
}

// 1. SUMMARY REPORT (for log file)
script_log("\n--- SUMMARY REPORT ---", 'info');
script_log(str_repeat('=', 80), 'info');
script_log("Total tabbed content paragraphs processed: " . count($tabbed_content_ids), 'info');
script_log("Total tabs checked: {$total_tabs}", 'info');
script_log("Items with potential fragmented links initially identified: {$fragmented_count}", 'info');
script_log("Total nodes with fragmented links found: " . count($fragmented_links_by_node), 'info');
script_log("Total individual fragmented links found: {$total_fragmented_links}", 'info');
script_log("Total nodes successfully updated (i.e., had fixes applied): " . count($updated_nodes), 'info');
script_log("Total link merge operations performed: {$fixed_count}", 'info');


if (count($updated_nodes) < count($fragmented_links_by_node) && count($fragmented_links_by_node) > 0) {
    script_log("Nodes with issues potentially not fixed: " . (count($fragmented_links_by_node) - count($updated_nodes)), 'warning');
}

if (count($updated_nodes) > 0 && count($fragmented_links_by_node) > 0) {
    script_log("Node update success rate (based on nodes with links): " . round((count($updated_nodes) / count($fragmented_links_by_node)) * 100) . "%", 'info');
}
script_log(str_repeat('=', 80), 'info');

// 2. UPDATED NODES REPORT (for log file)
if (!empty($updated_nodes)) {
    script_log("\n--- NODES UPDATED ---", 'info');
    script_log(str_repeat('=', 80), 'info');

    foreach ($updated_nodes as $node_id => $data) {
        script_log("Node ID: {$node_id} - {$data['title']}", 'info');
        script_log("Total fixes in this node: {$data['fixes']}", 'info');

        if (isset($data['passes'])) {
            script_log("Passes needed: ", 'info');
            foreach ($data['passes'] as $location => $passes) {
                script_log("   - {$location}: {$passes} passes", 'info');
            }
        }
        script_log(str_repeat('-', 80), 'info');

        foreach ($data['links'] as $i => $link) {
            script_log(($i + 1) . ". Fixed URL: " . $link['url'], 'info');
            script_log("   Location: " . $link['location'], 'info');
            script_log("   Original HTML: " . htmlspecialchars($link['html']), 'info');
            script_log(str_repeat('-', 40), 'info');
        }
    }
} else {
    script_log("No nodes were updated.", 'info');
}

// 3. DETAILED FRAGMENTED LINKS REPORT (Initial state, for log file)
if (!empty($fragmented_links_by_node)) {
    script_log("\n--- INITIAL FRAGMENTED LINKS DETAIL (Before Fixes) ---", 'info');
    script_log(str_repeat('=', 80), 'info');

    foreach ($fragmented_links_by_node as $node_id => $data) {
        script_log("Node ID: {$node_id} - {$data['title']}", 'info');
        script_log(str_repeat('-', 80), 'info');

        foreach ($data['links'] as $i => $link) {
            script_log(($i + 1) . ". URL: " . $link['url'], 'info');
            script_log("   Type: " . $link['type'], 'info');
            script_log("   Location: " . $link['location'], 'info');
            script_log("   HTML: " . htmlspecialchars($link['html']), 'info');
            script_log(str_repeat('-', 40), 'info');
        }
    }
} else {
    script_log("No fragmented links were initially found.", 'info');
}


// Save parent nodes to register the changes
if (!empty($updated_nodes)) {
    script_log("\nSaving parent nodes to register changes...", 'info');
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $entity_type_manager->getStorage('node');

    // Clear static entity cache before saving to ensure fresh data if paragraphs were saved.
    $paragraph_storage->resetCache();

    foreach (array_keys($updated_nodes) as $node_id_to_save) { // Iterate over keys of $updated_nodes
        script_log("Saving node {$node_id_to_save}...", 'info');
        $node = NULL;
        try {
            $node = $node_storage->load($node_id_to_save);
            if ($node) {
                // Simply saving the node will create a new revision with the paragraph changes
                $node->save();
                script_log("✓ Node {$node_id_to_save} saved successfully.", 'info');
            } else {
                script_log("✗ Node {$node_id_to_save} could not be loaded for saving.", 'warning');
            }
        } catch (\Exception $e) {
            script_log("✗ Error saving node {$node_id_to_save}: " . $e->getMessage(), 'error');
        }
    }

    // Clear entity caches again after all nodes have been saved
    if (!empty(array_keys($updated_nodes))) {
        $node_storage->resetCache(array_keys($updated_nodes));
    }
    $paragraph_storage->resetCache();
    script_log("Parent node saving process completed.", 'info');
}


// Validation step - verify links were actually fixed - only run in CLI mode for now
// This section can be extensive and might be better as a separate diagnostic script
// For now, logging the attempt.
if (php_sapi_name() === 'cli' && !empty($updated_nodes)) {
    script_log("\n--- VALIDATING FIXES (CLI ONLY) ---", 'info');
    script_log(str_repeat('=', 80), 'info');

    $remaining_issues = [];
    $validated_nodes = 0;
    $fully_fixed_nodes = 0;
    /** @var \Drupal\node\NodeStorageInterface $node_storage_val */
    $node_storage_val = $entity_type_manager->getStorage('node');


    foreach (array_keys($updated_nodes) as $node_id_val) { // Iterate over keys of $updated_nodes
        script_log("Validating node {$node_id_val}...", 'info');
        $node_val = NULL; // Initialize

        // Load the node again to get fresh content
        try {
            $node_val = $node_storage_val->load($node_id_val);
        } catch (\Exception $e) {
            script_log("Error loading node {$node_id_val} for validation: " . $e->getMessage(), 'error');
            continue;
        }

        if (!$node_val) {
            script_log("Could not load node {$node_id_val} for validation.", 'warning');
            continue;
        }

        $validated_nodes++;
        $node_has_remaining_issues = false;

        // Get all paragraphs from this node
        $field_names = $node_val->getFieldDefinitions();
        foreach ($field_names as $field_name => $field_definition) {
            if ($field_definition->getType() == 'entity_reference_revisions') {
                if ($node_val->hasField($field_name) && !$node_val->get($field_name)->isEmpty()) {
                    $paragraphs = $node_val->get($field_name)->referencedEntities();

                    foreach ($paragraphs as $paragraph) {
                        if ($paragraph->getType() == 'tabbed_content') {
                            // Re-check all tabs
                            if ($paragraph->hasField('field_tab_accordion_item') && !$paragraph->get('field_tab_accordion_item')->isEmpty()) {
                                $tabs = $paragraph->get('field_tab_accordion_item')->referencedEntities();

                                foreach ($tabs as $tab) {
                                    // Check main content
                                    if ($tab->hasField('field_main_content') && !$tab->get('field_main_content')->isEmpty()) {
                                        $content = $tab->get('field_main_content')->value;

                                        // Detect remaining fragmented links
                                        if (
                                            strpos($content, '<wbr><a') !== false ||
                                            preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>/', $content)
                                        ) {
                                            $heading = $tab->hasField('field_heading') ? $tab->get('field_heading')->value : 'No heading';
                                            $remaining_issues[$node_id_val][] = "Tab: \"{$heading}\" still has fragmented links";
                                            $node_has_remaining_issues = true;
                                        }
                                    }

                                    // Check subitems
                                    if ($tab->hasField('field_tab_accordion_subitem') && !$tab->get('field_tab_accordion_subitem')->isEmpty()) {
                                        $subitems = $tab->get('field_tab_accordion_subitem')->referencedEntities();

                                        foreach ($subitems as $subitem) {
                                            if ($subitem->hasField('field_main_content') && !$subitem->get('field_main_content')->isEmpty()) {
                                                $content = $subitem->get('field_main_content')->value;

                                                // Detect remaining fragmented links
                                                if (
                                                    strpos($content, '<wbr><a') !== false ||
                                                    preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>/', $content)
                                                ) {
                                                    $heading = $tab->hasField('field_heading') ? $tab->get('field_heading')->value : 'No heading';
                                                    $subheading = $subitem->hasField('field_heading') ? $subitem->get('field_heading')->value : 'No heading';
                                                    $remaining_issues[$node_id_val][] = "Tab: \"{$heading}\", Subitem: \"{$subheading}\" still has fragmented links";
                                                    $node_has_remaining_issues = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!$node_has_remaining_issues) {
            script_log("✓ Node {$node_id_val} - All fragmented links fixed successfully");
            $fully_fixed_nodes++;
        } else {
            script_log("✗ Node {$node_id_val} - Some fragmented links remain");
        }
    }

    script_log("\nVALIDATION RESULTS:");
    script_log(str_repeat('-', 80));
    script_log("Nodes validated: {$validated_nodes}");
    script_log("Nodes fully fixed: {$fully_fixed_nodes}");

    if ($fully_fixed_nodes < $validated_nodes) {
        script_log("Nodes with remaining issues: " . ($validated_nodes - $fully_fixed_nodes));

        foreach ($remaining_issues as $node_id => $issues) {
            script_log("Node {$node_id} remaining issues:");
            foreach ($issues as $issue) {
                script_log("  - {$issue}");
            }
        }

        script_log("Some links could not be automatically fixed. Manual review may be required.");
    } else {
        script_log("All links were successfully fixed! No manual action required.");
    }

    script_log(str_repeat('=', 80));
}

// Debug function to examine specific node with issues - only in CLI mode
if (!empty($remaining_issues) && php_sapi_name() === 'cli') {
    script_log("\nDEBUGGING REMAINING ISSUES:");
    script_log(str_repeat('=', 80));

    // Ask user which node to examine
    $problem_node_id = 106581; // Hardcode the problematic node ID
    script_log("Attempting to debug remaining issues for pre-defined problem node ID: {$problem_node_id}", 'debug');


    script_log("Examining node {$problem_node_id}...");
    $node = $node_storage->load($problem_node_id);

    if ($node) {
        // Get all paragraphs from this node
        $field_names = $node->getFieldDefinitions();
        foreach ($field_names as $field_name => $field_definition) {
            if ($field_definition->getType() == 'entity_reference_revisions') {
                if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                    $paragraphs = $node->get($field_name)->referencedEntities();

                    foreach ($paragraphs as $paragraph) {
                        if ($paragraph->getType() == 'tabbed_content') {
                            if ($paragraph->hasField('field_tab_accordion_item') && !$paragraph->get('field_tab_accordion_item')->isEmpty()) {
                                $tabs = $paragraph->get('field_tab_accordion_item')->referencedEntities();

                                foreach ($tabs as $tab) {
                                    $heading = $tab->hasField('field_heading') ? $tab->get('field_heading')->value : 'No heading';

                                    // Focus on the problematic tab
                                    if ($heading == 'Media Coverage') {
                                        script_log("Found tab: \"{$heading}\"");

                                        if ($tab->hasField('field_main_content') && !$tab->get('field_main_content')->isEmpty()) {
                                            $content = $tab->get('field_main_content')->value;

                                            // Extract and show problematic links
                                            script_log("Analyzing content for fragmented links...");

                                            // Look for <wbr><a pattern
                                            if (strpos($content, '<wbr><a') !== false) {
                                                script_log("Found <wbr><a pattern!");
                                                preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a><wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/',
                                                    $content,
                                                    $matches,
                                                    PREG_SET_ORDER
                                                );

                                                foreach ($matches as $i => $match) {
                                                    script_log("Fragmented link #{$i}:");
                                                    script_log("  URL: {$match[1]}");
                                                    script_log("  HTML: " . htmlspecialchars($match[0]));
                                                }
                                            }

                                            // Look for adjacent links with same URL
                                            if (preg_match_all(
                                                '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/',
                                                $content,
                                                $matches,
                                                PREG_SET_ORDER
                                            )) {

                                                script_log("Found adjacent links with same URL pattern!");
                                                foreach ($matches as $i => $match) {
                                                    script_log("Adjacent link #{$i}:");
                                                    script_log("  URL: {$match[1]}");
                                                    script_log("  HTML: " . htmlspecialchars($match[0]));
                                                }
                                            }

                                            // If no specific pattern matched, show a section of the content
                                            if (empty($matches)) {
                                                script_log("No specific pattern matched. Showing content snippet around potential issues:");

                                                // Look for links with the URL in the text
                                                if (preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>\s*\\1\s*<\/a>/',
                                                    $content,
                                                    $matches,
                                                    PREG_SET_ORDER
                                                )) {

                                                    script_log("Found links with URL as text:");
                                                    foreach ($matches as $i => $match) {
                                                        script_log("Link #{$i}: " . htmlspecialchars($match[0]));
                                                    }
                                                }

                                                // Extract all links
                                                preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>.*?<\/a>/',
                                                    $content,
                                                    $all_links,
                                                    PREG_SET_ORDER
                                                );

                                                script_log("\nAll links in the content:");
                                                foreach ($all_links as $i => $link) {
                                                    script_log("Link #{$i}: " . htmlspecialchars($link[0]));
                                                    script_log("  URL: {$link[1]}");
                                                }
                                            }
                                        }

                                        // Also check tab subitems
                                        if ($tab->hasField('field_tab_accordion_subitem') && !$tab->get('field_tab_accordion_subitem')->isEmpty()) {
                                            $subitems = $tab->get('field_tab_accordion_subitem')->referencedEntities();
                                            script_log("Tab has " . count($subitems) . " subitems, checking those as well...");

                                            foreach ($subitems as $j => $subitem) {
                                                $subheading = $subitem->hasField('field_heading') ? $subitem->get('field_heading')->value : 'No heading';
                                                script_log("Subitem #{$j}: \"{$subheading}\"");

                                                if ($subitem->hasField('field_main_content') && !$subitem->get('field_main_content')->isEmpty()) {
                                                    // Similar analysis for subitem content
                                                    // ...
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// --- Final Script Output & Return ---
script_log("Link detection and repair script finished.", 'info');
$execution_time = microtime(true) - $start_time;
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');
$final_summary_message = sprintf(
    "Processed %d tabbed content paragraphs. Fixed %d fragmented links in %d nodes.",
    count($tabbed_content_ids),
    $fixed_count,
    count($updated_nodes)
);
script_log($final_summary_message, 'info');

$log_message = "Detailed log: {$log_file_path}";

// Return value for update hooks or other includes
return $final_summary_message . PHP_EOL . $log_message;
