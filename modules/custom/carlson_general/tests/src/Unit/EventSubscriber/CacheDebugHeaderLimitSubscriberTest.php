<?php

namespace Drupal\Tests\carlson_general\Unit\EventSubscriber;

use Drupal\carlson_general\EventSubscriber\CacheDebugHeaderLimitSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests cache debug header limiting behavior.
 *
 * @group carlson_general
 */
class CacheDebugHeaderLimitSubscriberTest extends UnitTestCase {

  /**
   * Original AH_SITE_ENVIRONMENT value.
   *
   * @var string|null
   */
  protected ?string $originalEnvironment;

  /**
   * Whether AH_SITE_ENVIRONMENT was set before the test.
   *
   * @var bool
   */
  protected bool $hadOriginalEnvironment;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hadOriginalEnvironment = array_key_exists(
      'AH_SITE_ENVIRONMENT',
      $_ENV,
    );
    $this->originalEnvironment = $_ENV['AH_SITE_ENVIRONMENT'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->hadOriginalEnvironment) {
      $_ENV['AH_SITE_ENVIRONMENT'] = $this->originalEnvironment;
    }
    else {
      unset($_ENV['AH_SITE_ENVIRONMENT']);
    }
    parent::tearDown();
  }

  /**
   * Tests long DEV cache tag headers are compressed and annotated.
   *
   * @covers ::limitDebugHeaders
   */
  public function testLongDebugHeaderIsCompressedOnDev(): void {
    $_ENV['AH_SITE_ENVIRONMENT'] = 'dev';
    $subscriber = new CacheDebugHeaderLimitSubscriber();
    $response = new Response();
    $response->headers->set(
      'X-Drupal-Cache-Tags',
      $this->createCacheTagHeader(2000),
    );

    $subscriber->limitDebugHeaders($this->createResponseEvent($response));

    $this->assertLessThanOrEqual(
      6000,
      strlen((string) $response->headers->get('X-Drupal-Cache-Tags')),
    );
    $this->assertStringContainsString(
      'compressed',
      (string) $response->headers->get('X-Carlson-Cache-Tags-Compressed'),
    );
    $this->assertStringStartsWith(
      'node:1',
      (string) $response->headers->get('X-Drupal-Cache-Tags'),
    );
    $this->assertStringContainsString(
      ' n:2 ',
      ' ' . $response->headers->get('X-Drupal-Cache-Tags') . ' ',
    );
  }

  /**
   * Tests long local cache tag headers are compressed and annotated.
   *
   * @covers ::limitDebugHeaders
   */
  public function testLongDebugHeaderIsCompressedOnLocal(): void {
    unset($_ENV['AH_SITE_ENVIRONMENT']);
    $subscriber = new CacheDebugHeaderLimitSubscriber();
    $response = new Response();
    $response->headers->set(
      'X-Drupal-Cache-Tags',
      $this->createCacheTagHeader(2000),
    );

    $subscriber->limitDebugHeaders($this->createResponseEvent($response));

    $this->assertLessThanOrEqual(
      16000,
      strlen((string) $response->headers->get('X-Drupal-Cache-Tags')),
    );
    $this->assertStringContainsString(
      'compressed; original-count=2000;',
      (string) $response->headers->get('X-Carlson-Cache-Tags-Compressed'),
    );
    $this->assertStringContainsString(
      'limit=16000',
      (string) $response->headers->get('X-Carlson-Cache-Tags-Compressed'),
    );
  }

  /**
   * Tests local keeps more compressed tags than Acquia DEV.
   *
   * @covers ::limitDebugHeaders
   */
  public function testLocalAllowsLargerCompressedHeaderThanDev(): void {
    $value = $this->createCacheTagHeader(2000);

    $_ENV['AH_SITE_ENVIRONMENT'] = 'dev';
    $dev_subscriber = new CacheDebugHeaderLimitSubscriber();
    $dev_response = new Response();
    $dev_response->headers->set('X-Drupal-Cache-Tags', $value);
    $dev_subscriber->limitDebugHeaders(
      $this->createResponseEvent($dev_response),
    );

    unset($_ENV['AH_SITE_ENVIRONMENT']);
    $local_subscriber = new CacheDebugHeaderLimitSubscriber();
    $local_response = new Response();
    $local_response->headers->set('X-Drupal-Cache-Tags', $value);
    $local_subscriber->limitDebugHeaders(
      $this->createResponseEvent($local_response),
    );

    $this->assertGreaterThan(
      strlen((string) $dev_response->headers->get('X-Drupal-Cache-Tags')),
      strlen((string) $local_response->headers->get('X-Drupal-Cache-Tags')),
    );
  }

  /**
   * Tests production cache tag headers are left untouched.
   *
   * @covers ::limitDebugHeaders
   */
  public function testProdDebugHeaderIsNotTrimmed(): void {
    $_ENV['AH_SITE_ENVIRONMENT'] = 'prod';
    $subscriber = new CacheDebugHeaderLimitSubscriber();
    $value = $this->createCacheTagHeader(2000);
    $response = new Response();
    $response->headers->set('X-Drupal-Cache-Tags', $value);

    $subscriber->limitDebugHeaders($this->createResponseEvent($response));

    $this->assertSame($value, $response->headers->get('X-Drupal-Cache-Tags'));
    $this->assertFalse(
      $response->headers->has('X-Carlson-Cache-Tags-Compressed'),
    );
  }

  /**
   * Tests short DEV cache tag headers are left untouched.
   *
   * @covers ::limitDebugHeaders
   */
  public function testShortDebugHeaderIsNotTrimmedOnDev(): void {
    $_ENV['AH_SITE_ENVIRONMENT'] = 'dev';
    $subscriber = new CacheDebugHeaderLimitSubscriber();
    $response = new Response();
    $response->headers->set('X-Drupal-Cache-Tags', 'http_response node:1');

    $subscriber->limitDebugHeaders($this->createResponseEvent($response));

    $this->assertSame(
      'http_response node:1',
      $response->headers->get('X-Drupal-Cache-Tags'),
    );
    $this->assertFalse(
      $response->headers->has('X-Carlson-Cache-Tags-Compressed'),
    );
  }

  /**
   * Creates a response event for testing.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response to put into the event.
   *
   * @return \Symfony\Component\HttpKernel\Event\ResponseEvent
   *   The response event.
   */
  protected function createResponseEvent(Response $response): ResponseEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/test');

    return new ResponseEvent(
      $kernel,
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
  }

  /**
   * Creates a large cache tag header for testing.
   *
   * @param int $count
   *   The number of node tags to include.
   *
   * @return string
   *   The cache tag header value.
   */
  protected function createCacheTagHeader(int $count): string {
    $tags = [];
    foreach (range(1, $count) as $index) {
      $tags[] = 'node:' . $index;
    }

    return implode(' ', $tags);
  }

}
