<?php

namespace Drupal\Tests\carlson_general\Unit\EventSubscriber;

use Drupal\carlson_general\EventSubscriber\CloudflareCacheControlResponseEventSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests CDN cache control header behavior.
 *
 * @coversDefaultClass \Drupal\carlson_general\EventSubscriber\CloudflareCacheControlResponseEventSubscriber
 * @group carlson_general
 */
class CloudflareCacheControlResponseEventSubscriberTest extends UnitTestCase {

  /**
   * Tests uncacheable responses explicitly bypass CDN caching.
   *
   * @covers ::setCloudflareCacheControlHeaders
   */
  public function testUncacheableResponseForcesNoStore(): void {
    $subscriber = new CloudflareCacheControlResponseEventSubscriber();
    $response = new Response();
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-cache');
    $response->setStatusCode(200);

    $subscriber->setCloudflareCacheControlHeaders(
      $this->createResponseEvent($response),
    );

    $this->assertSame(
      'no-store',
      $response->headers->get('Cloudflare-CDN-Cache-Control'),
    );
    $this->assertSame(
      'no-store',
      $response->headers->get('CDN-Cache-Control'),
    );
  }

  /**
   * Tests Drupal uncacheable responses explicitly bypass CDN caching.
   *
   * @covers ::setCloudflareCacheControlHeaders
   */
  public function testDrupalUncacheableResponseForcesNoStore(): void {
    $subscriber = new CloudflareCacheControlResponseEventSubscriber();
    $response = new Response();
    $response->setPublic();
    $response->setMaxAge(900);
    $response->headers->set('X-Drupal-Cache', 'UNCACHEABLE');
    $response->setStatusCode(200);

    $subscriber->setCloudflareCacheControlHeaders(
      $this->createResponseEvent($response),
    );

    $this->assertSame(
      'no-store',
      $response->headers->get('Cloudflare-CDN-Cache-Control'),
    );
    $this->assertSame(
      'no-store',
      $response->headers->get('CDN-Cache-Control'),
    );
  }

  /**
   * Tests cacheable success responses get an edge TTL.
   *
   * @covers ::setCloudflareCacheControlHeaders
   */
  public function testCacheableSuccessResponseGetsEdgeTtl(): void {
    $subscriber = new CloudflareCacheControlResponseEventSubscriber();
    $response = new Response();
    $response->setPublic();
    $response->setMaxAge(900);
    $response->setStatusCode(200);

    $subscriber->setCloudflareCacheControlHeaders(
      $this->createResponseEvent($response),
    );

    $this->assertSame(
      'max-age=900',
      $response->headers->get('Cloudflare-CDN-Cache-Control'),
    );
    $this->assertSame(
      'max-age=900',
      $response->headers->get('CDN-Cache-Control'),
    );
  }

  /**
   * Tests cacheable temporary redirects get a five minute edge TTL.
   *
   * @covers ::setCloudflareCacheControlHeaders
   */
  public function testCacheableTemporaryRedirectGetsFiveMinuteEdgeTtl(): void {
    $subscriber = new CloudflareCacheControlResponseEventSubscriber();
    $response = new Response('', 302);
    $response->setPublic();
    $response->setMaxAge(900);

    $subscriber->setCloudflareCacheControlHeaders(
      $this->createResponseEvent($response),
    );

    $this->assertSame(
      'max-age=300',
      $response->headers->get('Cloudflare-CDN-Cache-Control'),
    );
    $this->assertSame(
      'max-age=300',
      $response->headers->get('CDN-Cache-Control'),
    );
  }

  /**
   * Tests cacheable not found responses get a five minute edge TTL.
   *
   * @covers ::setCloudflareCacheControlHeaders
   */
  public function testCacheableNotFoundResponseGetsFiveMinuteEdgeTtl(): void {
    $subscriber = new CloudflareCacheControlResponseEventSubscriber();
    $response = new Response('', 404);
    $response->setPublic();
    $response->setMaxAge(900);

    $subscriber->setCloudflareCacheControlHeaders(
      $this->createResponseEvent($response),
    );

    $this->assertSame(
      'max-age=300',
      $response->headers->get('Cloudflare-CDN-Cache-Control'),
    );
    $this->assertSame(
      'max-age=300',
      $response->headers->get('CDN-Cache-Control'),
    );
  }

  /**
   * Creates a response event for testing.
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

}
