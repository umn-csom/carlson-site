<?php

namespace Drupal\edit_media_modal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirect to the media edit route by UUID.
 */
class EditMediaModalController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(\Drupal::service('entity.repository'));
  }

  /**
   * Constructs a new EditMediaModalController object.
   */
  public function __construct(protected EntityRepositoryInterface $entityRepository) {}

  /**
   * Get the edit media url by the UUID.
   *
   * At the time where the CKEditor5 plugin wants to open the edit modal it
   * only has access to the UUID of the media entity, not the ID.
   *
   * @param string $uuid
   *   The media uuid.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The ID of the entity if found.
   */
  public function getEditUrl(string $uuid) {
    $entity = $this->entityRepository->loadEntityByUuid('media', $uuid);
    $url = $entity->toUrl('edit-form');
    $url->setOption('query', ['edit_media_in_modal' => TRUE]);
    return new JsonResponse(['url' => $url->toString()]);
  }

  /**
   * Access callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, string $uuid) {
    $entity = $this->entityRepository->loadEntityByUuid('media', $uuid);

    if (empty($entity)) {
      return AccessResult::forbidden();
    }

    return $entity->access('update', $account, TRUE);
  }

}
