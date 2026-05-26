<?php

namespace Drupal\carlson_general\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps DEV cache debug response headers below infrastructure limits.
 */
class CacheDebugHeaderLimitSubscriber implements EventSubscriberInterface {

  /**
   * Maximum value size for each debug header.
   */
  protected const MAX_DEBUG_HEADER_BYTES = 6000;

  /**
   * Debug headers that can become large on content-heavy pages.
   */
  protected const DEBUG_HEADERS = [
    'X-Drupal-Cache-Tags' => 'X-Carlson-Cache-Tags-Truncated',
    'X-Drupal-Cache-Contexts' => 'X-Carlson-Cache-Contexts-Truncated',
  ];

  /**
   * Trims oversized Drupal cache debug headers on DEV.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function limitDebugHeaders(ResponseEvent $event): void {
    if (!$event->isMainRequest() || !$this->isDevEnvironment()) {
      return;
    }

    $headers = $event->getResponse()->headers;
    foreach (static::DEBUG_HEADERS as $header_name => $summary_header_name) {
      $value = $headers->get($header_name);
      if (
        $value === NULL ||
        strlen($value) <= static::MAX_DEBUG_HEADER_BYTES
      ) {
        continue;
      }

      $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
      if ($tokens === FALSE || $tokens === []) {
        $headers->remove($header_name);
        $headers->set(
          $summary_header_name,
          sprintf(
            'omitted; original-bytes=%d; limit=%d',
            strlen($value),
            static::MAX_DEBUG_HEADER_BYTES,
          ),
        );
        continue;
      }

      $kept_tokens = $this->getTokensWithinLimit($tokens);
      $headers->set($header_name, implode(' ', $kept_tokens));
      $headers->set(
        $summary_header_name,
        sprintf(
          'truncated; original-count=%d; kept-count=%d; '
          . 'omitted-count=%d; original-bytes=%d; limit=%d',
          count($tokens),
          count($kept_tokens),
          count($tokens) - count($kept_tokens),
          strlen($value),
          static::MAX_DEBUG_HEADER_BYTES,
        ),
      );
    }
  }

  /**
   * Gets the leading set of tokens that fits the configured header size limit.
   *
   * @param string[] $tokens
   *   Header tokens.
   *
   * @return string[]
   *   The leading tokens that fit within the header limit.
   */
  protected function getTokensWithinLimit(array $tokens): array {
    $kept_tokens = [];
    $current_bytes = 0;

    foreach ($tokens as $token) {
      $next_bytes = $current_bytes === 0
        ? strlen($token)
        : $current_bytes + 1 + strlen($token);
      if ($next_bytes > static::MAX_DEBUG_HEADER_BYTES) {
        break;
      }

      $kept_tokens[] = $token;
      $current_bytes = $next_bytes;
    }

    if ($kept_tokens === []) {
      $first_token = (string) reset($tokens);
      return [substr($first_token, 0, static::MAX_DEBUG_HEADER_BYTES)];
    }

    return $kept_tokens;
  }

  /**
   * Determines whether the current request is running on Acquia DEV.
   *
   * @return bool
   *   TRUE when this request is running on Acquia DEV.
   */
  protected function isDevEnvironment(): bool {
    return ($_ENV['AH_SITE_ENVIRONMENT'] ?? NULL) === 'dev';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['limitDebugHeaders', -100];
    return $events;
  }

}
