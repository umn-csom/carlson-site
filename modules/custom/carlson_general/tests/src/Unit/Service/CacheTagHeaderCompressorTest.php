<?php

namespace Drupal\Tests\carlson_general\Unit\Service;

use Drupal\carlson_general\Service\CacheTagHeaderCompressor;
use Drupal\Tests\UnitTestCase;

/**
 * Tests cache tag header prefix compression.
 *
 * @group carlson_general
 * @coversDefaultClass \Drupal\carlson_general\Service\CacheTagHeaderCompressor
 */
class CacheTagHeaderCompressorTest extends UnitTestCase {

  /**
   * The compressor under test.
   *
   * @var \Drupal\carlson_general\Service\CacheTagHeaderCompressor
   */
  protected CacheTagHeaderCompressor $compressor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->compressor = new CacheTagHeaderCompressor();
  }

  /**
   * Tests block_view tags compress repeated prefixes.
   *
   * @covers ::compress
   */
  public function testCompressesBlockViewPrefixes(): void {
    $input = implode(' ', [
      'block_view:local_tasks_block',
      'block_view:menu_block',
      'block_view:menu_block:footer-second',
    ]);

    $this->assertSame(
      'block_view:local_tasks_block bv:menu_block bv:mb:footer-second',
      $this->compressor->compress($input),
    );
  }

  /**
   * Tests config block tags compress repeated prefixes.
   *
   * @covers ::compress
   */
  public function testCompressesConfigBlockPrefixes(): void {
    $input = implode(' ', [
      'config:block.block.carlson_refresh_2023mainnav',
      'config:block.block.carlson_refresh_2023mainnav_2',
    ]);

    $this->assertSame(
      'config:block.block.carlson_refresh_2023mainnav c:b.b.carlson_refresh_2023mainnav_2',
      $this->compressor->compress($input),
    );
  }

  /**
   * Tests the first token in a header is always left full.
   *
   * @covers ::compress
   */
  public function testFirstTokenRemainsFull(): void {
    $this->assertSame(
      'http_response',
      $this->compressor->compress('http_response'),
    );
  }

  /**
   * Tests unrelated tokens do not abbreviate each other.
   *
   * @covers ::compress
   */
  public function testUnrelatedTokensRemainFull(): void {
    $input = 'node:1 node:2';
    $this->assertSame('node:1 n:2', $this->compressor->compress($input));
  }

}
