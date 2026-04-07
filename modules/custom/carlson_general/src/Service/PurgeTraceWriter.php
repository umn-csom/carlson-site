<?php

namespace Drupal\carlson_general\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * Writes purge trace summaries into hourly private log files.
 */
class PurgeTraceWriter {

  /**
   * The base directory URI.
   */
  protected const BASE_URI = 'private://purge-trace';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the writer.
   */
  public function __construct(
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
    StateInterface $state,
    StreamWrapperManagerInterface $stream_wrapper_manager,
  ) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('carlson_purge_trace');
    $this->state = $state;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * Writes a summary line and returns the relative file path.
   *
   * @param array<string, mixed> $summary
   *   The trace summary.
   *
   * @return string|null
   *   The relative file path or NULL on failure.
   */
  public function write(array $summary): ?string {
    if (!$this->isPrivateAvailable()) {
      return NULL;
    }

    $date_directory = gmdate('Y-m-d');
    $hour = gmdate('H');
    $directory_uri = self::BASE_URI . '/' . $date_directory;

    if (!$this->prepareDirectory($directory_uri)) {
      return NULL;
    }

    $directory_path = $this->fileSystem->realpath($directory_uri);
    if (!$directory_path) {
      $this->logger->error('Could not resolve purge trace directory @dir.', [
        '@dir' => $directory_uri,
      ]);
      return NULL;
    }

    $file_path = $directory_path . '/' . $hour . '.ndjson';
    $payload = json_encode($summary, JSON_UNESCAPED_SLASHES);

    if ($payload === FALSE) {
      $this->logger->error('Could not encode purge trace payload.');
      return NULL;
    }

    $written = @file_put_contents(
      $file_path,
      $payload . PHP_EOL,
      FILE_APPEND | LOCK_EX,
    );

    if ($written === FALSE) {
      $this->logger->error('Could not append purge trace file @file.', [
        '@file' => $file_path,
      ]);
      return NULL;
    }

    $this->cleanupIfNeeded();
    return $date_directory . '/' . $hour . '.ndjson';
  }

  /**
   * Returns status information for the admin UI.
   *
   * @return array<string, mixed>
   *   The status array.
   */
  public function getStatus(): array {
    $available = $this->isPrivateAvailable();
    $base_path = $available ? $this->fileSystem->realpath(self::BASE_URI) : FALSE;

    return [
      'private_available' => $available,
      'base_uri' => self::BASE_URI,
      'base_path' => $base_path ?: NULL,
    ];
  }

  /**
   * Lists the available trace files.
   *
   * @return array<int, array<string, mixed>>
   *   The trace file metadata.
   */
  public function listFiles(): array {
    if (!$this->isPrivateAvailable()) {
      return [];
    }

    $base_path = $this->fileSystem->realpath(self::BASE_URI);
    if (!$base_path || !is_dir($base_path)) {
      return [];
    }

    $files = [];
    foreach (new \DirectoryIterator($base_path) as $date_directory) {
      if ($date_directory->isDot() || !$date_directory->isDir()) {
        continue;
      }

      foreach (new \DirectoryIterator($date_directory->getPathname()) as $file) {
        if ($file->isDot() || !$file->isFile()) {
          continue;
        }

        if ($file->getExtension() !== 'ndjson') {
          continue;
        }

        $files[] = [
          'date' => $date_directory->getFilename(),
          'filename' => $file->getFilename(),
          'relative_path' => $date_directory->getFilename() . '/' . $file->getFilename(),
          'path' => $file->getPathname(),
          'size' => $file->getSize(),
          'modified' => $file->getMTime(),
        ];
      }
    }

    usort($files, static function (array $a, array $b): int {
      return $b['modified'] <=> $a['modified'];
    });

    return $files;
  }

  /**
   * Reads a trace file.
   *
   * @param string $date
   *   The date directory.
   * @param string $filename
   *   The file name.
   *
   * @return string
   *   The file contents.
   */
  public function readFile(string $date, string $filename): string {
    return (string) file_get_contents($this->resolveFilePath($date, $filename));
  }

  /**
   * Resolves a file path for download or display.
   *
   * @param string $date
   *   The date directory.
   * @param string $filename
   *   The file name.
   *
   * @return string
   *   The absolute path.
   */
  public function resolveFilePath(string $date, string $filename): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      throw new \InvalidArgumentException('Invalid date directory.');
    }

    if (!preg_match('/^\d{2}\.ndjson$/', $filename)) {
      throw new \InvalidArgumentException('Invalid trace filename.');
    }

    $base_path = $this->fileSystem->realpath(self::BASE_URI);
    if (!$base_path) {
      throw new \RuntimeException('Private purge trace directory is unavailable.');
    }

    $path = $base_path . '/' . $date . '/' . $filename;
    if (!is_file($path)) {
      throw new \RuntimeException('Trace file not found.');
    }

    return $path;
  }

  /**
   * Returns whether private:// is available.
   */
  public function isPrivateAvailable(): bool {
    return (bool) $this->streamWrapperManager->getViaScheme('private');
  }

  /**
   * Prepares a directory for writing.
   */
  protected function prepareDirectory(string $directory_uri): bool {
    $prepared_directory = $directory_uri;
    if ($this->fileSystem->prepareDirectory(
      $prepared_directory,
      FileSystemInterface::CREATE_DIRECTORY
      | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      return TRUE;
    }

    $this->logger->error('Could not prepare purge trace directory @dir.', [
      '@dir' => $directory_uri,
    ]);
    return FALSE;
  }

  /**
   * Runs age-based cleanup at most once per hour.
   */
  protected function cleanupIfNeeded(): void {
    $last_cleanup = (int) $this->state->get(PurgeTraceRuntime::STATE_LAST_CLEANUP, 0);
    $now = time();

    if (($now - $last_cleanup) < 3600) {
      return;
    }

    $this->state->set(PurgeTraceRuntime::STATE_LAST_CLEANUP, $now);
    $this->deleteExpiredDirectories();
  }

  /**
   * Deletes directories older than the retention period.
   */
  protected function deleteExpiredDirectories(): void {
    $retention_days = max(
      1,
      (int) $this->state->get(PurgeTraceRuntime::STATE_RETENTION_DAYS, 3),
    );
    $base_path = $this->fileSystem->realpath(self::BASE_URI);

    if (!$base_path || !is_dir($base_path)) {
      return;
    }

    $cutoff = strtotime('-' . $retention_days . ' days', strtotime('today UTC'));
    foreach (new \DirectoryIterator($base_path) as $date_directory) {
      if ($date_directory->isDot() || !$date_directory->isDir()) {
        continue;
      }

      $timestamp = strtotime($date_directory->getFilename() . ' UTC');
      if ($timestamp === FALSE || $timestamp >= $cutoff) {
        continue;
      }

      $this->fileSystem->deleteRecursive($date_directory->getPathname());
    }
  }

}
