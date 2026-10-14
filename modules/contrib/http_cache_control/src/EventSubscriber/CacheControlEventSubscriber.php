<?php

namespace Drupal\http_cache_control\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber for adding http cache control headers.
 */
class CacheControlEventSubscriber implements EventSubscriberInterface {

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
  public function setHeaderCacheControl(ResponseEvent $event) {
    $config = $this->configFactory->get('http_cache_control.settings');
    $response = $event->getResponse();

    if ($variation = $config->get('cache.http.vary')) {
      $vary = $response->getVary();

      foreach (array_map('trim', explode(',', $variation)) as $header) {
        $vary[] = $header;
      }
      if (!Settings::get('omit_vary_cookie')) {
        $vary[] = 'Cookie';
      }

      $response->setVary(implode(',', $vary));
    }

    if (!$response->isCacheable()) {
      return;
    }

    // Set the s-maxage directive.
    switch ($response->getStatusCode()) {
      case 404:
        $sMaxAge = (int) $config->get('cache.http.404_max_age');
        break;

      case 302:
        $sMaxAge = (int) $config->get('cache.http.302_max_age');
        break;

      case 301:
        $sMaxAge = (int) $config->get('cache.http.301_max_age');
        break;

      default:
        $sMaxAge = (int) $config->get('cache.http.s_maxage');
        break;
    }

    if ($sMaxAge > 0 && $sMaxAge !== $response->getMaxAge() && !$response->headers->hasCacheControlDirective('s-maxage')) {
      $response->setSharedMaxAge($sMaxAge);
    }

    // Add stale revalidation directives to non-error responses.
    if ($response->getStatusCode() < 400) {
      // Add must-revalidate directive.
      if ($value = $config->get('cache.http.mustrevalidate')) {
        $response->headers->addCacheControlDirective('must-revalidate', $value);
      }
      // Add no-cache directive.
      if ($value = $config->get('cache.http.nocache')) {
        $response->headers->addCacheControlDirective('no-cache', $value);
      }
      // Add no-store directive.
      if ($value = $config->get('cache.http.nostore')) {
        $response->headers->addCacheControlDirective('no-store', $value);
      }
      // Add stale-if-error directive.
      if ($seconds = $config->get('cache.http.stale_if_error')) {
        $response->headers->addCacheControlDirective('stale-if-error', $seconds);
      }
      // Add stale-while-revalidate directive.
      if ($seconds = $config->get('cache.http.stale_while_revalidate')) {
        $response->headers->addCacheControlDirective('stale-while-revalidate', $seconds);
      }

      // Set the Surrogate-Control header.
      $maxage = $config->get('cache.surrogate.maxage');
      $nostore = $config->get('cache.surrogate.nostore');

      if (!empty($maxage) || $nostore) {
        $value = $nostore ? ['no-store'] : [];
        if (!empty($maxage)) {
          $value[] = 'max-age=' . $maxage;
        }
        $response->headers->set('Surrogate-Control', implode(', ', $value));
      }
    }

    // Set targeted cache headers (RFC 9213 style), e.g. CDN-Cache-Control.
    $targetedItems = $config->get('cache.targeted.items') ?: [];
    if (is_array($targetedItems) && $targetedItems) {
      foreach ($targetedItems as $item) {
        $headerName = self::getTargetedCacheControlHeaderName($item);
        if (!$headerName) {
          continue;
        }

        $value = self::buildTargetedCacheControlHeaderValue($item);
        if ($value === '') {
          continue;
        }

        $response->headers->set($headerName, $value);
      }
    }
  }

  /**
   * Builds the full header name like "CDN-Cache-Control" from a config item.
   */
  protected static function getTargetedCacheControlHeaderName(array $item): ?string {
    $target = trim((string) ($item['target'] ?? ''));

    // Back-compat / UI convenience: allow reading custom_prefix if present.
    if ($target === '' && !empty($item['custom_prefix'])) {
      $target = trim((string) $item['custom_prefix']);
    }

    // If someone saved the full header name, strip suffix.
    $target = preg_replace('/-Cache-Control$/i', '', $target);

    // Safety: prevent header injection / invalid field-names.
    // Field-name tokens are effectively restricted to visible ASCII; for our
    // purposes, keep it conservative (letters/digits/hyphen).
    if ($target === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/', $target)) {
      return NULL;
    }

    return $target . '-Cache-Control';
  }

  /**
   * Turns a config item into a Cache-Control-style directive string.
   */
  protected static function buildTargetedCacheControlHeaderValue(array $item): string {
    $directives = [];

    // Visibility.
    $visibility = (string) ($item['visibility'] ?? '');
    if ($visibility === 'public' || $visibility === 'private') {
      $directives[] = $visibility;
    }

    // max-age / no-cache.
    $maxAge = $item['max_age'] ?? NULL;
    $noCache = !empty($item['no_cache']);

    // Support the older "max_age" sentinel approach if it exists.
    if (is_string($maxAge) && strtolower($maxAge) === 'no-cache') {
      $noCache = TRUE;
      $maxAge = 0;
    }

    if ($noCache) {
      $directives[] = 'no-cache';
    }

    if (is_numeric($maxAge)) {
      $maxAge = (int) $maxAge;
      if ($maxAge > 0) {
        $directives[] = 'max-age=' . $maxAge;
      }
    }

    // Boolean directives.
    if (!empty($item['must_revalidate'])) {
      $directives[] = 'must-revalidate';
    }
    if (!empty($item['no_store'])) {
      $directives[] = 'no-store';
    }
    if (!empty($item['no_transform'])) {
      $directives[] = 'no-transform';
    }
    if (!empty($item['proxy_revalidate'])) {
      $directives[] = 'proxy-revalidate';
    }

    return implode(', ', $directives);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Response: set header content for security policy.
    $events[KernelEvents::RESPONSE][] = ['setHeaderCacheControl', -10];
    return $events;
  }

}
