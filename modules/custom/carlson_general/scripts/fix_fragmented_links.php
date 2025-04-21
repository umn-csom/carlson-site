<?php

/**
 * Drupal script to detect and fix fragmented links in tabbed_content paragraphs.
 * 
 * Usage: 
 * - Just run: drush scr fix_fragmented_links.php
 */

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\EntityTypeManagerInterface;

// Create a buffer for the return message
$output_buffer = [];

// Check if we're running in CLI mode
$is_cli = (php_sapi_name() === 'cli');

// Function to safely output messages
function output_message($message)
{
    global $output_buffer, $is_cli;

    $output_buffer[] = $message;

    if ($is_cli) {
        echo $message . "\n";
    } else {
        \Drupal::logger('carlson_general')->notice($message);
    }
}

output_message("RUNNING FRAGMENTED LINKS DETECTION AND REPAIR");

// Get all paragraph IDs of type tabbed_content
$tabbed_content_ids = \Drupal::entityQuery('paragraph')
    ->condition('type', 'tabbed_content')
    ->accessCheck(FALSE)
    ->execute();

if (empty($tabbed_content_ids)) {
    output_message("No tabbed_content paragraphs found.");
    return "No tabbed_content paragraphs found.";
}

output_message("Found " . count($tabbed_content_ids) . " tabbed_content paragraphs.");
$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

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
    $original = $content;

    // Medical Alley specific fix
    if (strpos($content, 'medicalalley.org/2020/04/university-of-minnesota-researchers') !== false) {
        // First check if this is a valid single link with saferedirecturl (don't break those)
        if (preg_match('/<a[^>]*target="_blank"[^>]*data-saferedirecturl="[^"]*"[^>]*>.*?<\/a>/', $content)) {
            // This is already a proper link with saferedirecturl attributes - don't modify it
            return [
                'content' => $content,
                'merged_count' => 0,
                'changed' => false
            ];
        }

        // Check for actual fragmented links
        if (
            strpos($content, '<wbr><a') !== false ||
            preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>/', $content)
        ) {

            // Get all attributes from the first link including target and data-saferedirecturl
            preg_match('/<a([^>]+)href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"]([^>]*)>/', $content, $attr_match);

            if (!empty($attr_match)) {
                $attrs = $attr_match[1] . ' ' . $attr_match[2];

                // Create a better replacement preserving all attributes
                $replacement = "<a{$attrs}>University of Minnesota COVID-19 Hospitalization Project</a>";

                // Pattern to match the entire chain of medicalalley.org links
                $pattern = '/<a[^>]+href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"][^>]*>[^<]*<\/a>(?:<wbr><a[^>]+href=[\'"]https:\/\/medicalalley\.org\/2020\/04\/university-of-minnesota-researchers[^"\']+[\'"][^>]*>[^<]*<\/a>)*/';

                $new_content = preg_replace($pattern, $replacement, $content);

                if ($new_content !== $content) {
                    return [
                        'content' => $new_content,
                        'merged_count' => 1,
                        'changed' => true
                    ];
                }
            }
        }
    }

    // General case for data-saferedirecturl links - Don't modify intact saferedirecturl links
    if (strpos($content, 'data-saferedirecturl') !== false && strpos($content, '<wbr>') !== false) {
        // Check if there are actual fragmented links or just proper links with saferedirecturl
        if (preg_match('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>[^<]*<\/a><wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>/', $content)) {
            preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*data-saferedirecturl=[\'"][^\'"]+[\'"][^>]*>([^<]*)<\/a>/', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];

                // Count fragments with this URL
                preg_match_all('/<a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>([^<]*)<\/a>/', $content, $fragments);

                if (count($fragments[1]) > 1) {
                    // Get attributes from first link - preserve all attributes
                    preg_match('/<a([^>]+)href=[\'"]' . preg_quote($url, '/') . '[\'"]([^>]*)>/', $content, $attr_match);
                    $attrs = $attr_match[1] . ' ' . $attr_match[2];

                    // Create replacement with combined text
                    $combined_text = implode('', $fragments[1]);
                    $replacement = "<a{$attrs}>{$combined_text}</a>";

                    // Pattern to match entire chain
                    $pattern = '/<a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>[^<]*<\/a>(?:<wbr><a[^>]+href=[\'"]' . preg_quote($url, '/') . '[\'"][^>]*>[^<]*<\/a>)*/';

                    $new_content = preg_replace($pattern, $replacement, $content);

                    if ($new_content !== $content) {
                        return [
                            'content' => $new_content,
                            'merged_count' => count($fragments[1]) - 1,
                            'changed' => true
                        ];
                    }
                }
            }
        }
    }

    // No specific fix found
    return [
        'content' => $content,
        'merged_count' => 0,
        'changed' => false
    ];
}

/**
 * Function to fix fragmented links in HTML content
 */
function fixFragmentedLinks($content)
{
    $original = $content;

    // First pass: Remove all <wbr> tags
    $content = str_replace('<wbr>', '', $content);

    // Second pass: Simple adjacent link merger
    $pattern = '/(<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>)([^<]*)<\/a>\s*<a[^>]+href=[\'"]\\2[\'"][^>]*>([^<]*)<\/a>/';
    $replacement = '$1$3$4</a>';

    $new_content = preg_replace($pattern, $replacement, $content);
    if ($new_content !== $content) {
        return [
            'content' => $new_content,
            'merged_count' => 1,
            'changed' => true
        ];
    }

    // Third pass: DOM-based approach for more complex cases
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
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
        'changed' => ($original !== $content)
    ];
}

/**
 * Fix all fragmented links in content with multiple passes
 */
function fixAllFragmentedLinks($content, $max_passes = 3)
{
    global $is_cli;
    $original = $content;
    $pass_count = 0;
    $total_fixes = 0;
    $changes_made = true;

    // Continue fixing until no more changes or max passes reached
    while ($changes_made && $pass_count < $max_passes) {
        $pass_count++;
        $changes_made = false;

        // First try specific fixes
        $result = fixSpecificProblematicLinks($content);

        if ($result['changed']) {
            $content = $result['content'];
            $total_fixes += $result['merged_count'];
            $changes_made = true;
        } else {
            // Then try general fixes
            $result = fixFragmentedLinks($content);

            if ($result['changed']) {
                $content = $result['content'];
                $total_fixes += $result['merged_count'];
                $changes_made = true;
            }
        }

        // Debug info about each pass
        if ($changes_made) {
            if ($is_cli) {
                output_message("   Pass {$pass_count}: Fixed {$result['merged_count']} links");
            }
        }
    }

    return [
        'content' => $content,
        'merged_count' => $total_fixes,
        'changed' => ($original !== $content),
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

    // Use DOM to modify HTML properly
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
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
    $paragraph = $paragraph_storage->load($paragraph_id);

    // Skip if no tab items
    if (!$paragraph->hasField('field_tab_accordion_item') || $paragraph->get('field_tab_accordion_item')->isEmpty()) {
        continue;
    }

    // Get parent node info
    $host_entity = $paragraph->getParentEntity();
    if (!$host_entity || $host_entity->getEntityTypeId() !== 'node') {
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

                $location = "Tab: \"{$heading}\"";
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

                        output_message("Fixing fragmented links in Node {$node_id} - {$node_title}, {$location}");

                        // Use multi-pass approach instead of individual fixes
                        $result = fixAllFragmentedLinks($content);

                        if ($result['changed']) {
                            // Clean Google safe redirect attributes
                            $result['content'] = removeDataSaferedirecturl($result['content']);

                            $tab_item->set('field_main_content', [
                                'value' => $result['content'],
                                'format' => $tab_item->get('field_main_content')->format
                            ]);
                            $tab_item->save();

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

                        $location = "Tab: \"{$heading}\", Subitem: \"{$subheading}\"";
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

                                output_message("Fixing fragmented links in Node {$node_id} - {$node_title}, {$location}");

                                // Try fixing all fragmented links with multiple passes
                                $result = fixAllFragmentedLinks($subcontent);

                                if ($result['changed']) {
                                    // Clean Google safe redirect attributes
                                    $result['content'] = removeDataSaferedirecturl($result['content']);

                                    $subitem->set('field_main_content', [
                                        'value' => $result['content'],
                                        'format' => $subitem->get('field_main_content')->format
                                    ]);
                                    $subitem->save();

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
foreach ($fragmented_links_by_node as $node) {
    $total_fragmented_links += count($node['links']);
}

// 1. SUMMARY REPORT
output_message("\nSUMMARY REPORT:");
output_message(str_repeat('=', 80));
output_message("Total tabbed content paragraphs: " . count($tabbed_content_ids));
output_message("Total tabs: {$total_tabs}");
output_message("Items with potential fragmented links: {$fragmented_count}");
output_message("Total nodes with fragmented links: " . count($fragmented_links_by_node));
output_message("Total fragmented links found: {$total_fragmented_links}");
output_message("Total nodes successfully updated: " . count($updated_nodes));
output_message("Total links fixed: {$fixed_count}");

if (count($updated_nodes) < count($fragmented_links_by_node)) {
    output_message("Nodes with issues not fixed: " . (count($fragmented_links_by_node) - count($updated_nodes)));
}

if (count($updated_nodes) > 0 && count($fragmented_links_by_node) > 0) {
    output_message("Success rate: " . round((count($updated_nodes) / count($fragmented_links_by_node)) * 100) . "%");
}
output_message(str_repeat('=', 80));

// 2. UPDATED NODES REPORT - Only show in CLI mode
if (!empty($updated_nodes) && $is_cli) {
    output_message("\nNODES UPDATED:");
    output_message(str_repeat('=', 80));

    foreach ($updated_nodes as $node_id => $data) {
        output_message("Node ID: {$node_id} - {$data['title']}");
        output_message("Total fixes: {$data['fixes']}");

        if (isset($data['passes'])) {
            output_message("Passes needed: ");
            foreach ($data['passes'] as $location => $passes) {
                output_message("   - {$location}: {$passes} passes");
            }
        }

        output_message(str_repeat('-', 80));

        foreach ($data['links'] as $i => $link) {
            output_message(($i + 1) . ". Fixed URL: " . $link['url']);
            output_message("   Location: " . $link['location']);
            output_message("   Original HTML: " . htmlspecialchars($link['html']));
            output_message(str_repeat('-', 40));
        }
    }
}

// 3. DETAILED FRAGMENTED LINKS REPORT - Only show in CLI mode
if (!empty($fragmented_links_by_node) && $is_cli) {
    output_message("\nFRAGMENTED LINKS DETAIL:");
    output_message(str_repeat('=', 80));

    foreach ($fragmented_links_by_node as $node_id => $data) {
        output_message("Node ID: {$node_id} - {$data['title']}");
        output_message(str_repeat('-', 80));

        foreach ($data['links'] as $i => $link) {
            output_message(($i + 1) . ". URL: " . $link['url']);
            output_message("   Type: " . $link['type']);
            output_message("   Location: " . $link['location']);
            output_message("   HTML: " . htmlspecialchars($link['html']));
            output_message(str_repeat('-', 40));
        }
    }
}

// Save parent nodes to register the changes
if (!empty($updated_nodes)) {
    output_message("\nWaiting 5 seconds before saving parent nodes to ensure all paragraph changes are registered...");

    if ($is_cli) {
        // Only sleep in CLI mode
        sleep(5);
    }

    // Clear static entity cache to ensure fresh data
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache();

    output_message("Saving parent nodes to register changes...");
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($updated_nodes as $node_id => $data) {
        output_message("Saving node {$node_id}...");
        $node = $node_storage->load($node_id);
        if ($node) {
            // Simply saving the node will create a new revision with the paragraph changes
            $node->save();
            output_message("✓ Node {$node_id} saved successfully.");

            // Add a small delay between node saves
            if (count($updated_nodes) > 1 && $is_cli) {
                sleep(1);
            }
        }
    }

    // Clear entity caches again after all nodes have been saved
    \Drupal::entityTypeManager()->getStorage('node')->resetCache(array_keys($updated_nodes));
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache();

    // Add a longer delay before validation to ensure database operations complete
    output_message("\nWaiting 10 seconds before validating fixes to ensure all changes are properly registered...");

    if ($is_cli) {
        // Only sleep in CLI mode
        sleep(10);
    }
}

// Validation step - verify links were actually fixed - only run in CLI mode
if (!empty($updated_nodes) && $is_cli) {
    output_message("\nVALIDATING FIXES:");
    output_message(str_repeat('=', 80));

    $remaining_issues = [];
    $validated_nodes = 0;
    $fully_fixed_nodes = 0;

    foreach ($updated_nodes as $node_id => $data) {
        output_message("Validating node {$node_id}...");

        // Load the node again to get fresh content
        $node = $node_storage->load($node_id);
        if (!$node) continue;

        $validated_nodes++;
        $node_has_remaining_issues = false;

        // Get all paragraphs from this node
        $field_names = $node->getFieldDefinitions();
        foreach ($field_names as $field_name => $field_definition) {
            if ($field_definition->getType() == 'entity_reference_revisions') {
                if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                    $paragraphs = $node->get($field_name)->referencedEntities();

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
                                            $remaining_issues[$node_id][] = "Tab: \"{$heading}\" still has fragmented links";
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
                                                    $remaining_issues[$node_id][] = "Tab: \"{$heading}\", Subitem: \"{$subheading}\" still has fragmented links";
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
            output_message("✓ Node {$node_id} - All fragmented links fixed successfully");
            $fully_fixed_nodes++;
        } else {
            output_message("✗ Node {$node_id} - Some fragmented links remain");
        }
    }

    output_message("\nVALIDATION RESULTS:");
    output_message(str_repeat('-', 80));
    output_message("Nodes validated: {$validated_nodes}");
    output_message("Nodes fully fixed: {$fully_fixed_nodes}");

    if ($fully_fixed_nodes < $validated_nodes) {
        output_message("Nodes with remaining issues: " . ($validated_nodes - $fully_fixed_nodes));

        foreach ($remaining_issues as $node_id => $issues) {
            output_message("Node {$node_id} remaining issues:");
            foreach ($issues as $issue) {
                output_message("  - {$issue}");
            }
        }

        output_message("Some links could not be automatically fixed. Manual review may be required.");
    } else {
        output_message("All links were successfully fixed! No manual action required.");
    }

    output_message(str_repeat('=', 80));
}

// Debug function to examine specific node with issues - only in CLI mode
if (!empty($remaining_issues) && $is_cli) {
    output_message("\nDEBUGGING REMAINING ISSUES:");
    output_message(str_repeat('=', 80));

    // Ask user which node to examine
    $problem_node_id = 106581; // Hardcode the problematic node ID

    output_message("Examining node {$problem_node_id}...");
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
                                        output_message("Found tab: \"{$heading}\"");

                                        if ($tab->hasField('field_main_content') && !$tab->get('field_main_content')->isEmpty()) {
                                            $content = $tab->get('field_main_content')->value;

                                            // Extract and show problematic links
                                            output_message("Analyzing content for fragmented links...");

                                            // Look for <wbr><a pattern
                                            if (strpos($content, '<wbr><a') !== false) {
                                                output_message("Found <wbr><a pattern!");
                                                preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a><wbr><a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/',
                                                    $content,
                                                    $matches,
                                                    PREG_SET_ORDER
                                                );

                                                foreach ($matches as $i => $match) {
                                                    output_message("Fragmented link #{$i}:");
                                                    output_message("  URL: {$match[1]}");
                                                    output_message("  HTML: " . htmlspecialchars($match[0]));
                                                }
                                            }

                                            // Look for adjacent links with same URL
                                            if (preg_match_all(
                                                '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/a>\s*<a[^>]+href=[\'"]\\1[\'"][^>]*>([^<]*)<\/a>/',
                                                $content,
                                                $matches,
                                                PREG_SET_ORDER
                                            )) {

                                                output_message("Found adjacent links with same URL pattern!");
                                                foreach ($matches as $i => $match) {
                                                    output_message("Adjacent link #{$i}:");
                                                    output_message("  URL: {$match[1]}");
                                                    output_message("  HTML: " . htmlspecialchars($match[0]));
                                                }
                                            }

                                            // If no specific pattern matched, show a section of the content
                                            if (empty($matches)) {
                                                output_message("No specific pattern matched. Showing content snippet around potential issues:");

                                                // Look for links with the URL in the text
                                                if (preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>\s*\\1\s*<\/a>/',
                                                    $content,
                                                    $matches,
                                                    PREG_SET_ORDER
                                                )) {

                                                    output_message("Found links with URL as text:");
                                                    foreach ($matches as $i => $match) {
                                                        output_message("Link #{$i}: " . htmlspecialchars($match[0]));
                                                    }
                                                }

                                                // Extract all links
                                                preg_match_all(
                                                    '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>.*?<\/a>/',
                                                    $content,
                                                    $all_links,
                                                    PREG_SET_ORDER
                                                );

                                                output_message("\nAll links in the content:");
                                                foreach ($all_links as $i => $link) {
                                                    output_message("Link #{$i}: " . htmlspecialchars($link[0]));
                                                    output_message("  URL: {$link[1]}");
                                                }
                                            }
                                        }

                                        // Also check tab subitems
                                        if ($tab->hasField('field_tab_accordion_subitem') && !$tab->get('field_tab_accordion_subitem')->isEmpty()) {
                                            $subitems = $tab->get('field_tab_accordion_subitem')->referencedEntities();
                                            output_message("Tab has " . count($subitems) . " subitems, checking those as well...");

                                            foreach ($subitems as $j => $subitem) {
                                                $subheading = $subitem->hasField('field_heading') ? $subitem->get('field_heading')->value : 'No heading';
                                                output_message("Subitem #{$j}: \"{$subheading}\"");

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

output_message("\nLink detection and repair complete!");

// Return a summary for the update hook
return "Fixed {$fixed_count} fragmented links in " . count($updated_nodes) . " nodes.";
