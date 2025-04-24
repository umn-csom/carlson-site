<?php

namespace Drupal\edit_media_modal;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle caching of media preview.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ResponceSubscriber object.
   */
  public function __construct(protected RouteMatchInterface $routeMatch) {}

  /**
   * Remove the max age from the media preview response.
   *
   * The MediaFilterController::preview method sets a 300 second max-age on the
   * response. Remove so the rerendering shows the updated media.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    if ($this->routeMatch->getRouteName() === 'media.filter.preview') {
      $response = $event->getResponse();
      $response->setMaxAge(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

}
