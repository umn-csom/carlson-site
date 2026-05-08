<?php

namespace Drupal\carlson_general\EventSubscriber;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsChecksumPreloadInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Allows the CSM-226 audit script to test request-local preload candidates.
 *
 * The subscriber is inert unless the Drush audit script stores a short-lived
 * token in state and sends that token in a request header. It lets the audit
 * compare baseline page requests against page requests with one extra preload
 * tag without editing settings.php between runs.
 */
final class CachePreloadAuditRequestSubscriber implements EventSubscriberInterface {

  /**
   * Header carrying the audit token.
   */
  public const TOKEN_HEADER = 'X-CSM-226-Preload-Audit-Token';

  /**
   * Header carrying comma-separated tags to preload for this request.
   */
  public const TAGS_HEADER = 'X-CSM-226-Preload-Audit-Tags';

  /**
   * State key for the short-lived audit token.
   */
  public const TOKEN_STATE_KEY = 'csm226_cache_preload_audit_token';

  /**
   * Constructs a cache preload audit request subscriber.
   *
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum
   *   Cache tags checksum service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   */
  public function __construct(
    private readonly CacheTagsChecksumInterface $checksum,
    private readonly StateInterface $state
  ) {
  }

  /**
   * Registers audit-request preload tags.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()
      || !$this->checksum instanceof CacheTagsChecksumPreloadInterface
    ) {
      return;
    }

    $request = $event->getRequest();
    $expected_token = (string) $this->state->get(self::TOKEN_STATE_KEY, '');
    $request_token = (string) $request->headers->get(self::TOKEN_HEADER, '');
    if ($expected_token === '' || !hash_equals($expected_token, $request_token)) {
      return;
    }

    $tags = array_filter(array_map(
      'trim',
      explode(',', (string) $request->headers->get(self::TAGS_HEADER, ''))
    ));
    if (!$tags) {
      return;
    }

    $this->checksum->registerCacheTagsForPreload(array_values($tags));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onRequest', 501];
    return $events;
  }

}
