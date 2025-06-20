<?php

/**
 * @file
 * CSM-213: Copy missing alt text to media thumbnail from media image field.
 *
 * Usage:
 * - drush scr path/to/alt_text_sync.php
 * - May also be included by an update hook.
 * - May be executed through the web interface at admin/reports/carlson-scripts
 */

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;

// --- Script Initialization ---
$start_time = microtime(true);
$script_name = basename(__FILE__, '.php');
require_once __DIR__ . '/_script_logger.php';
$log_file_path = init_log_file($script_name);

// Ensure Drupal services are available
if (
    !class_exists('\Drupal')
    || !\Drupal::hasService('database')
) {
    $error_msg = "Required Drupal services (database) not available. Ensure Drupal is bootstrapped.";
    script_log($error_msg, 'error');
    return "ERROR: {$error_msg}";
}

/** @var Connection $database */
$database = \Drupal::service('database');

$updated_thumbnails_count = 0;
$processed_media_count = 0;

try {
    script_log("CSM-213: Selecting main images with non-empty alt text.", 'info');
    $query = $database->select('media__image', 'main')
        ->fields('main', ['entity_id', 'image_alt'])
        ->condition('main.image_alt', '', '!=') // Ensure alt is not empty
        ->isNotNull('main.image_alt'); // Ensure alt is not NULL
    $query->join('media_field_data', 'field', 'main.entity_id = field.mid');
    $query->fields('field', ['thumbnail__target_id', 'thumbnail__alt', 'name']);

    $results = $query->execute();

    foreach ($results as $record) {
        $processed_media_count++;
        $media_id = $record->entity_id;
        $media_name = $record->name;
        $main_image_alt = $record->image_alt;
        $thumbnail_fid = $record->thumbnail__target_id;
        $current_thumbnail_alt = $record->thumbnail__alt;

        script_log("Processing Media ID: {$media_id} ('{$media_name}'). Thumbnail FID: {$thumbnail_fid}. Main Alt: '{$main_image_alt}'. Current Thumb Alt: '{$current_thumbnail_alt}'", 'debug');

        if (!empty($main_image_alt) && $main_image_alt !== $current_thumbnail_alt) {
            $database->update('media_field_data')
                ->fields(['thumbnail__alt' => $main_image_alt])
                ->condition('mid', $media_id)
                // It's safer to condition on mid, but if only thumbnail__target_id is available from old logic:
                // ->condition('thumbnail__target_id', $thumbnail_fid)
                ->execute();
            $updated_thumbnails_count++;
            script_log("Updated thumbnail for Media ID: {$media_id} ('{$media_name}'). New Alt: '{$main_image_alt}'. (Was: '{$current_thumbnail_alt}')", 'info');
        }
        elseif (empty($main_image_alt)) {
             script_log("Skipped Media ID: {$media_id} ('{$media_name}') - Main image alt text is empty.", 'debug');
        }
        else if ($main_image_alt === $current_thumbnail_alt) {
             script_log("Skipped Media ID: {$media_id} ('{$media_name}') - Thumbnail alt text already matches main image.", 'debug');
        }
    }
} catch (\Exception $e) {
    $error_message = "Exception during alt text synchronization: " . $e->getMessage();
    script_log($error_message, 'error');
    $summary_message = "Script failed: " . $error_message;
}

// --- Final Script Output & Return ---
$execution_time = microtime(true) - $start_time;
script_log("CSM-213: Alt text sync script finished.", 'info');
script_log(sprintf("Execution time: %.2f seconds.", $execution_time), 'info');

$summary_message = sprintf(
    "CSM-213: Alt text synchronization completed. \nProcessed %d media items. \nUpdated %d thumbnail alt texts.",
    $processed_media_count,
    $updated_thumbnails_count
);
$log_message = "Log: " . get_log_viewer_url($log_file_path);

script_log($summary_message, 'info');
script_log($log_message, 'info');

// Return value for update hooks or other includes
return $summary_message . PHP_EOL . $log_message;
