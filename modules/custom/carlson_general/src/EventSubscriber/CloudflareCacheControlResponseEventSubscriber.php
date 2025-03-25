<?php

namespace Drupal\carlson_general\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Site\Settings;
use Drupal\Core\Config\ConfigFactory;

/**
 * Subscriber for adding Cloudflare cache control headers.
 */
class CloudflareCacheControlResponseEventSubscriber implements EventSubscriberInterface {

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Set http cache control headers.
   */
  public function setCloudflareCacheControlHeaders(ResponseEvent $event): void {
    $response = $event->getResponse();
    $status_code = $response->getStatusCode();
    if ($response->isCacheable()) {
      // Success responses (200s)
      if ($status_code >= 200 && $status_code < 300) {
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=900');
      }
      // Redirect responses (300s)
      elseif ($status_code >= 300 && $status_code < 400) {
        // Cache redirects for a shorter time
        // 301 (permanent) can be cached longer, others shorter
        $max_age = ($status_code === 301) ? 3600 : 60;
        $response->headers->set('Cloudflare-CDN-Cache-Control', "max-age=$max_age");
      }
      // Client errors (400s)
      elseif ($status_code >= 400 && $status_code < 500) {
        switch ($status_code) {
          case 403:
          case 404:
            // Common errors - cache briefly to prevent abuse
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=60');
            break;

          case 405:
            // Method not allowed - cache briefly for API endpoints
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=30');
            break;

          case 429:
            // Too many requests - cache briefly to reinforce rate limiting
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=60');
            break;

          default:
            // Other 4xx errors - don't cache
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        }
      }
      // Server errors (500s) - don't cache
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['setCloudflareCacheControlHeaders', -10];
    return $events;
  }

}
