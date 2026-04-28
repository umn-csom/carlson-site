<?php

namespace Drupal\carlson_general\Service;

use Drupal\Core\File\FileSystemInterface;

/**
 * Reads and writes environment-specific private reCAPTCHA key files.
 */
class RecaptchaPrivateKeyFile {

  /**
   * Directory URI for private reCAPTCHA key files.
   */
  protected const DIRECTORY_URI = 'private://recaptcha';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs the key file service.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * Returns whether private storage is available.
   *
   * @return bool
   *   TRUE when private storage has a resolved filesystem path.
   */
  public function isPrivateAvailable(): bool {
    return (bool) $this->fileSystem->realpath('private://');
  }

  /**
   * Reads the existing active environment key file.
   *
   * @return array<string, mixed>
   *   Existing key data, or an empty array.
   */
  public function read(): array {
    $target_path = $this->getTargetPath();
    if (!$target_path || !is_readable($target_path)) {
      return [];
    }

    $contents = file_get_contents($target_path);
    if ($contents === FALSE) {
      return [];
    }

    $keys = json_decode($contents, TRUE);
    return is_array($keys) ? $keys : [];
  }

  /**
   * Writes keys to the active environment private JSON file.
   *
   * @param array<string, mixed> $keys
   *   The key data to write.
   *
   * @return bool
   *   TRUE if the key file was written.
   */
  public function write(array $keys): bool {
    $options = FileSystemInterface::CREATE_DIRECTORY |
      FileSystemInterface::MODIFY_PERMISSIONS;
    $directory_uri = self::DIRECTORY_URI;

    if (!$this->fileSystem->prepareDirectory($directory_uri, $options)) {
      return FALSE;
    }

    $directory_path = $this->fileSystem->realpath($directory_uri);
    if (!$directory_path) {
      return FALSE;
    }

    $payload = json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === FALSE) {
      return FALSE;
    }

    $file_path = $directory_path
      . DIRECTORY_SEPARATOR
      . $this->getTargetFileName();
    $written = file_put_contents($file_path, $payload . PHP_EOL, LOCK_EX);
    if ($written === FALSE) {
      return FALSE;
    }

    $this->fileSystem->chmod($file_path, 0600);
    return TRUE;
  }

  /**
   * Determines whether an existing key value is configured.
   *
   * @param array<string, mixed> $keys
   *   Existing key data.
   * @param string $version
   *   The key version.
   * @param string $key
   *   The key name.
   *
   * @return bool
   *   TRUE if the key value exists.
   */
  public function hasValue(array $keys, string $version, string $key): bool {
    return !empty($keys[$version][$key]) &&
      is_string($keys[$version][$key]);
  }

  /**
   * Gets the active key file URI.
   *
   * @return string
   *   The private key file URI.
   */
  public function getTargetUri(): string {
    return self::DIRECTORY_URI . '/' . $this->getTargetFileName();
  }

  /**
   * Gets the resolved active key file path.
   *
   * @return string|null
   *   The resolved file path, or NULL if the directory does not exist.
   */
  public function getTargetPath(): ?string {
    $directory_path = $this->fileSystem->realpath(self::DIRECTORY_URI);
    if (!$directory_path) {
      return NULL;
    }

    return $directory_path . DIRECTORY_SEPARATOR . $this->getTargetFileName();
  }

  /**
   * Gets the active key file name.
   *
   * @return string
   *   The key file name.
   */
  protected function getTargetFileName(): string {
    return 'recaptcha.' . $this->getKeyEnvironment() . '.json';
  }

  /**
   * Gets the active key environment suffix.
   *
   * @return string
   *   The key environment suffix.
   */
  protected function getKeyEnvironment(): string {
    return $this->getCurrentEnvironment() === 'prod' ? 'prod' : 'dev-test';
  }

  /**
   * Gets the current platform environment name.
   *
   * @return string
   *   The platform environment.
   */
  public function getCurrentEnvironment(): string {
    return $_ENV['AH_SITE_ENVIRONMENT'] ?? 'local';
  }

}
