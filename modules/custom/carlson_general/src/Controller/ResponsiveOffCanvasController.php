<?php

namespace Drupal\carlson_general\Controller;

use Drupal\carlson_general\ResponsiveOffCanvasAjax;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Returns the responsive off-canvas menu markup on demand.
 */
class ResponsiveOffCanvasController implements ContainerInjectionInterface {

  /**
   * Constructs a ResponsiveOffCanvasController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The router without access checks.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $routeMatch
   *   The current route match service.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected RequestStack $requestStack,
    protected RequestMatcherInterface $router,
    protected ResettableStackedRouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('router.no_access_checks'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Returns off-canvas menu markup for the requested current path.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The endpoint request.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   A cacheable HTML fragment response.
   */
  public function menu(Request $request): HtmlResponse {
    $current_path = $this->normalizeCurrentPath($request);
    $subrequest = Request::create($current_path, 'GET');

    try {
      $subrequest->attributes->add($this->router->matchRequest($subrequest));
    }
    catch (ResourceNotFoundException $e) {
      throw new NotFoundHttpException('Current path was not found.', $e);
    }

    $this->requestStack->push($subrequest);
    $this->routeMatch->resetRouteMatch();

    try {
      $build = ResponsiveOffCanvasAjax::buildMenu();
      $markup = $this->renderer->renderInIsolation($build);
      $cacheability = CacheableMetadata::createFromRenderArray($build);
    }
    finally {
      $this->requestStack->pop();
      $this->routeMatch->resetRouteMatch();
    }

    $response = new HtmlResponse((string) $markup);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Normalizes and validates the current_path query parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The endpoint request.
   *
   * @return string
   *   A root-relative internal path, including an optional query string.
   */
  protected function normalizeCurrentPath(Request $request): string {
    $current_path = $request->query->get('current_path', '/');
    if (!is_string($current_path) || $current_path === '') {
      throw new BadRequestHttpException('Missing current_path.');
    }

    if (
      str_starts_with($current_path, '//') ||
      preg_match('/^[a-z][a-z0-9+.-]*:/i', $current_path)
    ) {
      throw new BadRequestHttpException('Invalid current_path.');
    }

    return '/' . ltrim($current_path, '/');
  }

}
