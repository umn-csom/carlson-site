<?php

namespace Drupal\blurhash;

use Drupal\file\Entity\File;
use kornrunner\Blurhash;

/**
 * Blurhash service.
 */
final class BlurhashService implements BlurhashServiceInterface {

  /**
   * The image object.
   *
   * @var \GdImage
   */
  private \GdImage $image;

  /**
   * The constructor.
   */
  public function __construct(\GdImage $image) {
    $this->image = $image;
  }

  /**
   * {@inheritdoc}
   */
  public function encode(): string {
    $width = imagesx($this->image);
    $height = imagesy($this->image);

    $pixels = [];
    for ($y = 0; $y < $height; ++$y) {
      $row = [];
      for ($x = 0; $x < $width; ++$x) {
        $index = imagecolorat($this->image, $x, $y);
        $colors = imagecolorsforindex($this->image, $index);

        $row[] = [$colors['red'], $colors['green'], $colors['blue']];
      }
      $pixels[] = $row;
    }

    // @phpstan-ignore-next-line
    return Blurhash::encode($pixels, 4, 3, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function decode(string $blurhash, int $width, int $height): array {
    // @phpstan-ignore-next-line
    return Blurhash::decode($blurhash, $width, $height, 1.0, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public static function fromUri($uri): self {
    if ($string = file_get_contents($uri)) {
      $image = imagecreatefromstring($string);
    }
    return new self($image);
  }

  /**
   * {@inheritdoc}
   */
  public static function fromFile(File $file): self {
    throw new \Exception('Not implemented');
  }

}
