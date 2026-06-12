<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\simple_megamenu\Entity\SimpleMegaMenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns desktop simple megamenu panels on demand.
 *
 * The desktop navigation no longer renders every simple_megamenu entity during
 * the initial page request. Instead, the Twig template renders a lightweight
 * placeholder with this route as its endpoint. The JavaScript behavior fetches
 * the endpoint on first interaction and inserts the returned panel HTML.
 *
 * This controller keeps the same server-side guarantees as the old Twig helper:
 * it loads the requested simple_megamenu entity, resolves the current language,
 * checks view access, renders the configured view mode, and returns the render
 * cache metadata on the response so normal cache tag invalidation still works.
 */
class SimpleMegaMenuPanelController implements ContainerInjectionInterface {

  /**
   * Constructs a SimpleMegaMenuPanelController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('renderer'),
    );
  }

  /**
   * Returns a rendered simple megamenu panel.
   *
   * @param string $simple_mega_menu
   *   The simple megamenu entity ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The cacheable rendered panel response.
   */
  public function panel(string $simple_mega_menu): HtmlResponse {
    $entity = $this->loadPanelEntity($simple_mega_menu);
    $entity = $this->entityRepository->getTranslationFromContext($entity);
    $access = $entity->access('view', NULL, TRUE);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    [$markup, $cacheability] = $this->renderPanel($entity, $access);

    $response = new HtmlResponse($markup);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Returns all rendered navbar simple megamenu panels in one response.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The cacheable rendered panel response keyed by simple megamenu ID.
   */
  public function panels(): CacheableJsonResponse {
    $storage = $this->entityTypeManager->getStorage('simple_mega_menu');
    $entity_type = $this->entityTypeManager->getDefinition('simple_mega_menu');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'navbar_mega_menu')
      ->sort('id')
      ->execute();

    $panels = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheTags($entity_type->getListCacheTags());

    foreach ($storage->loadMultiple($ids) as $entity) {
      if (!$entity instanceof SimpleMegaMenuInterface) {
        continue;
      }

      $entity = $this->entityRepository->getTranslationFromContext($entity);
      $access = $entity->access('view', NULL, TRUE);
      $cacheability
        ->addCacheableDependency($access)
        ->addCacheableDependency($entity);

      if (!$access->isAllowed()) {
        continue;
      }

      [$markup, $panel_cacheability] = $this->renderPanel($entity, $access);
      $cacheability->addCacheableDependency($panel_cacheability);
      $panels[(string) $entity->id()] = $markup;
    }

    $response = new CacheableJsonResponse(['panels' => $panels]);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Loads a simple megamenu panel entity.
   *
   * @param string $simple_mega_menu
   *   The simple megamenu entity ID.
   *
   * @return \Drupal\simple_megamenu\Entity\SimpleMegaMenuInterface
   *   The loaded simple megamenu entity.
   */
  protected function loadPanelEntity(string $simple_mega_menu): SimpleMegaMenuInterface {
    $storage = $this->entityTypeManager->getStorage('simple_mega_menu');
    $entity = $storage->load($simple_mega_menu);

    if (!$entity instanceof SimpleMegaMenuInterface) {
      throw new NotFoundHttpException('Simple megamenu panel was not found.');
    }

    return $entity;
  }

  /**
   * Renders a simple megamenu panel with cacheability metadata.
   *
   * @param \Drupal\simple_megamenu\Entity\SimpleMegaMenuInterface $entity
   *   The simple megamenu entity.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result for this entity.
   *
   * @return array
   *   A two-item array containing rendered markup and cacheability metadata.
   */
  protected function renderPanel(
    SimpleMegaMenuInterface $entity,
    AccessResultInterface $access,
  ): array {
    // Match the old desktop nav behavior, which rendered the megamenu entity
    // with view_megamenu(item.url, 'before') inside the full menu template.
    $view_builder = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, 'before');

    $markup = $this->renderer->renderInIsolation($build);
    $cacheability = CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->addCacheableDependency($entity);

    return [(string) $markup, $cacheability];
  }

}
