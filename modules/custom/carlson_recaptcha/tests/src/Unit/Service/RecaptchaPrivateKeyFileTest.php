<?php

namespace Drupal\Tests\carlson_recaptcha\Unit\Service;

use Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the private reCAPTCHA key file writer.
 *
 * @coversDefaultClass \Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile
 * @group carlson_recaptcha
 */
class RecaptchaPrivateKeyFileTest extends UnitTestCase {

  /**
   * Tests writing keys creates the private directory and JSON file.
   *
   * @covers ::write
   */
  public function testWritePreparesDirectoryAndWritesJson(): void {
    $directory_path = sys_get_temp_dir()
      . DIRECTORY_SEPARATOR
      . uniqid('recaptcha_private_key_file_', TRUE);
    mkdir($directory_path);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())
      ->method('prepareDirectory')
      ->with(
        'private://recaptcha',
        FileSystemInterface::CREATE_DIRECTORY
          | FileSystemInterface::MODIFY_PERMISSIONS,
      )
      ->willReturn(TRUE);
    $file_system->method('realpath')
      ->with('private://recaptcha')
      ->willReturn($directory_path);
    $file_system->expects($this->once())
      ->method('chmod')
      ->with(
        $directory_path . DIRECTORY_SEPARATOR . 'recaptcha.dev-test.json',
        0600,
      );

    $keys = [
      'v2' => [
        'site_key' => 'v2-site-key',
        'secret_key' => 'v2-secret-key',
      ],
      'v3' => [
        'site_key' => 'v3-site-key',
        'secret_key' => 'v3-secret-key',
      ],
    ];

    $key_file = new RecaptchaPrivateKeyFile($file_system);
    $this->assertTrue($key_file->write($keys));

    $target_path = $directory_path
      . DIRECTORY_SEPARATOR
      . 'recaptcha.dev-test.json';
    $this->assertFileExists($target_path);
    $this->assertSame(
      json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
      file_get_contents($target_path),
    );

    unlink($target_path);
    rmdir($directory_path);
  }

}
