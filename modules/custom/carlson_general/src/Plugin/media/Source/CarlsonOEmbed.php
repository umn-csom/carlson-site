<?php

declare(strict_types=1);

namespace Drupal\carlson_general\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\Plugin\media\Source\OEmbed;

/**
 * oEmbed media source that logs (instead of flashing) fetch failures.
 *
 * Core's oEmbed source calls messenger()->addError() in getMetadata() when an
 * oEmbed resource cannot be fetched. For anonymous traffic that flash message
 * opens a session, which makes the whole response uncacheable
 * (private, no-store) and forces an origin render on every hit. A transient or
 * temporarily unreachable provider should not silently make video pages
 * uncacheable, so this source warms the resource fetcher first and logs any
 * failure rather than letting the parent surface it as a message. See CSM-392.
 */
class CarlsonOEmbed extends OEmbed {

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    $media_url = $this->getSourceFieldValue($media);
    // Resolve and fetch the resource up front. On success the fetcher caches
    // it, so the parent call below is a cache hit and never re-fetches; on
    // failure we log and bail before the parent can flash an error message.
    if (!empty($media_url)) {
      try {
        $resource_url = $this->urlResolver->getResourceUrl($media_url);
        $this->resourceFetcher->fetchResource($resource_url);
      }
      catch (ResourceException $e) {
        $this->logger->error('Could not retrieve the oEmbed resource for @url: %error', [
          '@url' => $media_url,
          '%error' => $e->getMessage(),
          'exception' => $e,
        ]);
        return NULL;
      }
    }

    return parent::getMetadata($media, $name);
  }

}
