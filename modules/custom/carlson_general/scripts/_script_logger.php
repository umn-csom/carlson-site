<?php

/**
 * @file
 * Helper functions for logging script messages.
 *
 * @package carlson_general
 * @author James Wilson <ames@bluespark.com>
 */

use Drupal\Core\File\FileSystemInterface;

function init_log_file($script_name) {
  global $log_file_uri;
  $datetime_suffix = date('Y-m-d_H-i-s');
  $log_filename = "{$script_name}_{$datetime_suffix}.txt";
  $log_file_uri = "public://script_logs/{$log_filename}";

  try {
    /** @var FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $log_dir_path = $file_system->realpath(dirname($log_file_uri));
    if (
      !$file_system->prepareDirectory($log_dir_path,FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)
    ) {
      $message = "Failed to create or prepare log directory: {$log_dir_path}";
      \Drupal::logger('carlson_general')->error($message);
      if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ERROR: $message" . PHP_EOL);
      }
      else {
        \Drupal::messenger()->addError($message);
      }
      return FALSE;
    }
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [INFO] Log file created: {$log_file_uri}" . PHP_EOL;
    append_to_log_file($log_entry, 'info');
    return $file_system->realpath($log_file_uri);
  } catch (\Exception $e) {
    $message = "Failed to write log file {$log_file_uri}: " . $e->getMessage();
    \Drupal::logger('carlson_general')->error($message);
    if (php_sapi_name() === 'cli') {
      fwrite(STDERR, "ERROR: $message" . PHP_EOL);
    } else {
      \Drupal::messenger()->addError($message);
    }
    return FALSE;
  }
}

/**
 * Write messages to the log array and show in CLI.
 *
 * @param string $message
 * @param string $type the PSR-3 log level
 * Supported types: debug, info, notice, warning, error, critical, alert, emergency.
 * @see https://www.php-fig.org/psr/psr-3/
 */
function script_log($message, $type = 'notice') {
  global $log_messages;
  $timestamp = date('Y-m-d H:i:s');
  $log_entry = "[{$timestamp}] [{$type}] {$message}";
  $log_messages[] = $log_entry;

  // Add all messages to system log or dblog.
  \Drupal::logger('carlson_general')->log($type, $message);

  // Add all messages to log file.
  append_to_log_file($message, $type);

  // Show all messages in CLI (drush updb).
  if (php_sapi_name() === 'cli') {
    $stream_resource = match ($type) {
      'error', 'critical', 'alert', 'emergency' => STDERR,
      default => STDOUT,
    };
    fwrite($stream_resource, $log_entry . PHP_EOL);
  }
  // Show warnings or errors on screen (update.php).
  else {
    $messenger_type = match ($type) {
      'error', 'critical', 'alert', 'emergency' => 'error',
      'warning' => 'warning',
      default => null,
    };
    if ($messenger_type) {
      \Drupal::messenger()->addMessage($message, $messenger_type);
    }
  }
}

/**
 * Append a message to the log file.
 */
function append_to_log_file($message, $type = 'notice') {
  global $log_file_uri;
  $timestamp = date('Y-m-d H:i:s');
  $log_entry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;

  // Open the file in append mode.
  $handle = fopen($log_file_uri, 'a');
  if ($handle) {
    fwrite($handle, $log_entry);
    fclose($handle);
    return true;
  } else {
    // Handle error (log, throw, etc.)
    return false;
  }
}
