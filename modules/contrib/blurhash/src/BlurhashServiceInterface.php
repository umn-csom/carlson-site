<?php

namespace Drupal\blurhash;

use Drupal\file\Entity\File;

/**
 * Service description.
 */
interface BlurhashServiceInterface {

  /**
   * Encode a blurhash.
   *
   * @return string
   *   A blurhash encoded string.
   */
  public function encode(): string;

  /**
   * Decode a blurhash.
   *
   * @param string $blurhash
   *   A blurhash encoded string.
   * @param int $width
   *   The image width integer.
   * @param int $height
   *   The image height integer.
   *
   * @return array
   *   An array of pixels.
   */
  public function decode(string $blurhash, int $width, int $height): array;

  /**
   * Create blurhash from image URI.
   *
   * @param string $uri
   *   An URI to an image.
   *
   * @return self
   *   The current blurhash service.
   */
  public static function fromUri($uri): self;

  /**
   * Create blurhash from image file.
   *
   * @param \Drupal\file\Entity\File $file
   *   A image file entity.
   *
   * @return self
   *   The current blurhash service.
   */
  public static function fromFile(File $file): self;

}
