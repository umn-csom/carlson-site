<?php

namespace Drupal\carlson_general\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber for adding Cloudflare cache control headers.
 */
class CloudflareCacheControlResponseEventSubscriber implements EventSubscriberInterface {

  /**
   * Set http cache control headers.
   */
  public function setCloudflareCacheControlHeaders(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    $status_code = $response->getStatusCode();

    if ($this->shouldBypassCdnCache($response)) {
      $this->setCdnCacheControl($response, 'no-store');
      return;
    }

    // Success responses (200s).
    if ($status_code >= 200 && $status_code < 300) {
      $this->setCdnCacheControl($response, 'max-age=900');
    }
    // Redirect responses (300s).
    elseif ($status_code >= 300 && $status_code < 400) {
      // Cache redirects for a shorter time.
      // 301 (permanent) can be cached longer, others shorter.
      $max_age = ($status_code === 301) ? 3600 : 60;
      $this->setCdnCacheControl($response, "max-age=$max_age");
    }
    // Client errors (400s).
    elseif ($status_code >= 400 && $status_code < 500) {
      switch ($status_code) {
        case 403:
        case 404:
          // Common errors - cache briefly to prevent abuse.
          $this->setCdnCacheControl($response, 'max-age=60');
          break;

        case 405:
          // Method not allowed - cache briefly for API endpoints.
          $this->setCdnCacheControl($response, 'max-age=30');
          break;

        case 429:
          // Too many requests - cache briefly to reinforce rate limiting.
          $this->setCdnCacheControl($response, 'max-age=60');
          break;

        default:
          // Other 4xx errors - don't cache.
          $this->setCdnCacheControl($response, 'no-store');
      }
    }
    // Server errors (500s) - don't cache.
    else {
      $this->setCdnCacheControl($response, 'no-store');
    }
  }

  /**
   * Determines whether CDN cache should be bypassed for a response.
   */
  protected function shouldBypassCdnCache(Response $response): bool {
    if (!$response->isCacheable()) {
      return TRUE;
    }

    $headers = $response->headers;
    return $headers->hasCacheControlDirective('private')
      || $headers->hasCacheControlDirective('no-cache')
      || $headers->hasCacheControlDirective('no-store');
  }

  /**
   * Sets CDN cache control headers for Cloudflare and compatible proxies.
   */
  protected function setCdnCacheControl(
    Response $response,
    string $value,
  ): void {
    $response->headers->set('Cloudflare-CDN-Cache-Control', $value);
    $response->headers->set('CDN-Cache-Control', $value);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['setCloudflareCacheControlHeaders', -10];
    return $events;
  }

}
