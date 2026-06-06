<?php

namespace Drupal\carlson_general\EventSubscriber;

use Drupal\carlson_general\Service\CacheTagHeaderCompressor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps DEV cache debug response headers below infrastructure limits.
 */
class CacheDebugHeaderLimitSubscriber implements EventSubscriberInterface {

  /**
   * Maximum debug header size on Acquia DEV (Apache).
   */
  protected const MAX_DEBUG_HEADER_BYTES_ACQUIA_DEV = 6000;

  /**
   * Maximum debug header size on local DDEV (nginx, 32k fastcgi buffer).
   */
  protected const MAX_DEBUG_HEADER_BYTES_LOCAL = 16000;

  /**
   * Debug headers that can become large on content-heavy pages.
   */
  protected const DEBUG_HEADERS = [
    'X-Drupal-Cache-Tags' => 'X-Carlson-Cache-Tags-Compressed',
    'X-Drupal-Cache-Contexts' => 'X-Carlson-Cache-Contexts-Compressed',
  ];

  /**
   * Compresses oversized Drupal cache debug headers on DEV and local.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function limitDebugHeaders(ResponseEvent $event): void {
    if (!$event->isMainRequest() || !$this->isDevEnvironment()) {
      return;
    }

    $compressor = new CacheTagHeaderCompressor();
    $headers = $event->getResponse()->headers;
    $max_header_bytes = $this->getMaxDebugHeaderBytes();

    foreach (static::DEBUG_HEADERS as $header_name => $summary_header_name) {
      $value = $headers->get($header_name);
      if (
        $value === NULL ||
        strlen($value) <= $max_header_bytes
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
            $max_header_bytes,
          ),
        );
        continue;
      }

      $compressed_value = $compressor->compress($value);
      $compressed_tokens = preg_split(
        '/\s+/',
        trim($compressed_value),
        -1,
        PREG_SPLIT_NO_EMPTY,
      );
      if ($compressed_tokens === FALSE) {
        $compressed_tokens = [];
      }

      if (strlen($compressed_value) > $max_header_bytes) {
        $compressed_tokens = $this->getTokensWithinLimit(
          $compressed_tokens,
          $max_header_bytes,
        );
        $compressed_value = implode(' ', $compressed_tokens);
        $headers->set($header_name, $compressed_value);
        $headers->set(
          $summary_header_name,
          sprintf(
            'compressed+truncated; original-count=%d; kept-count=%d; '
            . 'omitted-count=%d; original-bytes=%d; compressed-bytes=%d; '
            . 'limit=%d',
            count($tokens),
            count($compressed_tokens),
            count($tokens) - count($compressed_tokens),
            strlen($value),
            strlen($compressed_value),
            $max_header_bytes,
          ),
        );
        continue;
      }

      $headers->set($header_name, $compressed_value);
      $headers->set(
        $summary_header_name,
        sprintf(
          'compressed; original-count=%d; original-bytes=%d; '
          . 'compressed-bytes=%d; limit=%d',
          count($tokens),
          strlen($value),
          strlen($compressed_value),
          $max_header_bytes,
        ),
      );
    }
  }

  /**
   * Gets the leading set of tokens that fits the configured header size limit.
   *
   * @param string[] $tokens
   *   Header tokens.
   * @param int $max_header_bytes
   *   Maximum header value size in bytes.
   *
   * @return string[]
   *   The leading tokens that fit within the header limit.
   */
  protected function getTokensWithinLimit(
    array $tokens,
    int $max_header_bytes,
  ): array {
    $kept_tokens = [];
    $current_bytes = 0;

    foreach ($tokens as $token) {
      $next_bytes = $current_bytes === 0
        ? strlen($token)
        : $current_bytes + 1 + strlen($token);
      if ($next_bytes > $max_header_bytes) {
        break;
      }

      $kept_tokens[] = $token;
      $current_bytes = $next_bytes;
    }

    if ($kept_tokens === []) {
      $first_token = (string) reset($tokens);
      return [substr($first_token, 0, $max_header_bytes)];
    }

    return $kept_tokens;
  }

  /**
   * Gets the maximum debug header size for the current environment.
   *
   * @return int
   *   Maximum bytes allowed for each debug header value.
   */
  protected function getMaxDebugHeaderBytes(): int {
    $environment = $_ENV['AH_SITE_ENVIRONMENT'] ?? 'local';
    if ($environment === 'local') {
      return static::MAX_DEBUG_HEADER_BYTES_LOCAL;
    }

    return static::MAX_DEBUG_HEADER_BYTES_ACQUIA_DEV;
  }

  /**
   * Determines whether cache debug headers should be limited.
   *
   * @return bool
   *   TRUE on non-production environments that emit debug headers.
   */
  protected function isDevEnvironment(): bool {
    $environment = $_ENV['AH_SITE_ENVIRONMENT'] ?? 'local';
    return in_array($environment, ['dev', 'local'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['limitDebugHeaders', -100];
    return $events;
  }

}
