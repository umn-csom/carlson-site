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
    // $config = $this->configFactory->get('http_cache_control.settings');
    $response = $event->getResponse();
    $status_code = $response->getStatusCode();
    if ($response->isCacheable()) {
      if ($status_code === 301) {
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=86400');
        return;
      }
      if ($status_code < 400) {
        // Set Cloudflare cache control directive for non-error responses.
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'max-age=900');
      }
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
