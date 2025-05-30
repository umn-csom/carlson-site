<?php

/**
 * Standalone Drupal script to synchronize alt text from main media images to their thumbnails.
 *
 * Usage:
 * - drush scr modules/custom/carlson_general/scripts/alt_text_sync.php
 * - May also be included by an update hook.
 */

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;

// --- Script Initialization ---
$is_cli = (php_sapi_name() === 'cli');
$log_messages = [];
$start_time = microtime(true);
$script_filename = basename(__FILE__, '.php');
$datetime_suffix = date('Y-m-d_H-i-s');
$log_filename = "{$script_filename}_{$datetime_suffix}.txt";
$log_directory = 'public://script_logs';
$log_file_uri = "{$log_directory}/{$log_filename}";

/**
 * Helper function to write messages to the log array.
 */
function script_log($message, $type = 'notice') {
    global $log_messages, $is_cli;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$type}] {$message}";
    $log_messages[] = $log_entry;
    if ($is_cli && in_array($type, ['error', 'warning', 'info'])) {
        // Echo more types to console for this script as it has more direct user interaction steps.
        echo $log_entry . PHP_EOL;
    }
}

/**
 * Writes all accumulated log messages to the specified log file.
 */
function write_log_file(FileSystemInterface $file_system, $log_uri, array $messages) {
    try {
        $log_dir_path = $file_system->realpath(dirname($log_uri));
        if (!$file_system->prepareDirectory($log_dir_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            \Drupal::logger('carlson_general')->error("Failed to create or prepare log directory: @dir", ['@dir' => $log_dir_path]);
            echo "ERROR: Failed to create or prepare log directory: {$log_dir_path}" . PHP_EOL;
            return false;
        }
        $file_system->saveData(implode(PHP_EOL, $messages), $log_uri, FileSystemInterface::EXISTS_REPLACE);
        return $log_uri;
    } catch (\Exception $e) {
        \Drupal::logger('carlson_general')->error("Failed to write log file @log_uri: @error", ['@log_uri' => $log_uri, '@error' => $e->getMessage()]);
        echo "ERROR: Failed to write log file {$log_uri}: " . $e->getMessage() . PHP_EOL;
        return false;
    }
}
// --- End Script Initialization ---

script_log("Starting alt text synchronization script.", 'info');
script_log("Log file will be: {$log_file_uri}", 'info');

// Ensure Drupal services are available
if (!\Drupal::hasService('database') || !\Drupal::hasService('file_system')) {
    $error_msg = "Required Drupal services (database, file_system) not available. Ensure Drupal is bootstrapped.";
    script_log($error_msg, 'error');
    if ($is_cli) {
        echo "ERROR: {$error_msg}\\n";
    }
    // Attempt to write any existing logs before exiting if file_system is somewhat available.
    if (isset($file_system) && $file_system instanceof FileSystemInterface) {
         write_log_file($file_system, $log_file_uri, $log_messages);
    }
    return "ERROR: {$error_msg}";
}

/** @var \\Drupal\\Core\\Database\\Connection $database */
$database = \Drupal::service('database');
/** @var \\Drupal\\Core\\File\\FileSystemInterface $file_system */
$file_system = \Drupal::service('file_system');

$updated_thumbnails_count = 0;
$processed_media_count = 0;

try {
    script_log("Selecting main images with non-empty alt text.", 'info');
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

    $summary_message = sprintf("Alt text synchronization completed. Processed %d media items. Updated %d thumbnail alt texts.",
        $processed_media_count,
        $updated_thumbnails_count
    );
    script_log($summary_message, 'info');

} catch (\\Exception $e) {
    $error_message = "Exception during alt text synchronization: " . $e->getMessage();
    script_log($error_message, 'error');
    $summary_message = "Script failed: " . $error_message;
}

$execution_time = microtime(true) - $start_time;
script_log(sprintf("Total script execution time: %.2f seconds.", $execution_time), 'info');
script_log("Alt text sync script finished.", 'info');

// Write the log file
$log_file_written_uri = write_log_file($file_system, $log_file_uri, $log_messages);
$log_path_for_output = $log_file_written_uri ? $file_system->realpath($log_file_written_uri) : "ERROR creating log file.";

if ($is_cli) {
    echo "{$summary_message}\\n";
    echo "Detailed log: {$log_path_for_output}\\n";
}

return "{$summary_message} Detailed log: {$log_path_for_output}";
