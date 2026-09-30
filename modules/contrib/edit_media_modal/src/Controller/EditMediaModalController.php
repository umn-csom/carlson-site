<?php

namespace Drupal\edit_media_modal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Redirect to the media edit route by UUID.
 */
class EditMediaModalController extends ControllerBase {

  /**
   * The cache for medias entities.
   *
   * @var array
   */
  protected array $medias = [];

  /**
   * Constructs a new EditMediaModalController object.
   */
  public function __construct(protected EntityRepositoryInterface $entityRepository) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository')
    );
  }

  /**
   * Get the edit media url by the UUID.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $uuid
   *   The media uuid.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The ID of the entity if found.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getEditUrl(Request $request, string $uuid): JsonResponse {
    $entity = $this->getMediaEntity($uuid);
    $url = $entity->toUrl('edit-form');
    $options = ['edit_media_in_modal' => TRUE];
    if ($request->query->has('text_format') && !empty($request->query->get('text_format'))) {
      $options['text_format'] = $request->query->get('text_format');
    }
    if ($request->query->has('lang') && !empty($request->query->get('lang'))) {
      $options['language'] = $request->query->get('lang');
    }
    $url->setOption('query', $options);

    return new JsonResponse(['url' => $url->toString()]);
  }

  /**
   * Access callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param string $uuid
   *   The media entity uuid.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, string $uuid): AccessResultInterface {
    $entity = $this->getMediaEntity($uuid);

    if (empty($entity)) {
      return AccessResult::forbidden();
    }

    return $entity->access('update', $account, TRUE);
  }

  /**
   * Get the edit media url by the UUID Access check.
   *
   * @param string $uuid
   *   The media entity uuid.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The ID of the entity if found.
   */
  public function getEditUrlAccessCheck(string $uuid): JsonResponse {
    $account = $this->currentUser();
    $entity = $this->getMediaEntity($uuid);

    if (!$entity instanceof MediaInterface) {
      throw new NotAcceptableHttpException("The media entity with uuid {$uuid} has not been found.");
    }

    if (!$entity->access('update', $account, FALSE)) {
      throw new AccessDeniedHttpException("The user has no access to the media entity with uuid {$uuid}.");
    }

    return new JsonResponse(['message' => 'Access granted']);
  }

  /**
   * Get media entity by uuid.
   *
   * @param string $uuid
   *   The media entity uuid.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getMediaEntity(string $uuid): ?MediaInterface {
    if (array_key_exists($uuid, $this->medias) && $this->medias[$uuid] instanceof MediaInterface) {
      return $this->medias[$uuid];
    }

    $entity = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if ($entity instanceof MediaInterface) {
      $this->medias[$uuid] = $entity;
      return $this->medias[$uuid];
    }

    return NULL;
  }

}
