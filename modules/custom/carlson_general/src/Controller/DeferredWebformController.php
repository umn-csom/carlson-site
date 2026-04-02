<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Render\Element;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns deferred direct node Webforms.
 */
class DeferredWebformController extends ControllerBase {

  /**
   * Loads a direct node Webform outside the initial page response.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string $field_name
   *   The Webform field being requested.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that replaces the placeholder with the real form.
   */
  public function load(NodeInterface $node, string $field_name): AjaxResponse {
    if (!\carlson_general_is_deferred_webform_field($node, $field_name)) {
      throw new NotFoundHttpException();
    }

    $wrapper_id = \carlson_general_get_deferred_webform_wrapper_id($node, $field_name);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#' . $wrapper_id,
      $this->buildDeferredWebform($node, $field_name, $wrapper_id)
    ));

    return $response;
  }

  /**
   * Displays a standalone fallback page for no-JavaScript form access.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string $field_name
   *   The Webform field being requested.
   *
   * @return array
   *   A render array containing the inline form.
   */
  public function fallback(NodeInterface $node, string $field_name): array {
    if (!\carlson_general_is_deferred_webform_field($node, $field_name)) {
      throw new NotFoundHttpException();
    }

    return $this->buildDeferredWebform(
      $node,
      $field_name,
      \carlson_general_get_deferred_webform_wrapper_id($node, $field_name)
    );
  }

  /**
   * Builds the deferred webform wrapper.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string $field_name
   *   The Webform field being rendered.
   * @param string $wrapper_id
   *   The wrapper ID being replaced.
   *
   * @return array
   *   A render array containing the deferred form.
   */
  protected function buildDeferredWebform(NodeInterface $node, string $field_name, string $wrapper_id): array {
    $items = $node->get($field_name);
    $display = EntityViewDisplay::collectRenderDisplay($node, 'full');
    $formatter = $display->getRenderer($field_name);

    if ($items->isEmpty() || !$formatter) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'id' => $wrapper_id,
          'class' => ['carlson-deferred-webform', 'carlson-deferred-webform--error'],
        ],
        'message' => [
          '#plain_text' => $this->t('Unable to load the form.'),
        ],
      ];
    }

    $formatter->prepareView([$node->id() => $items]);
    $build = $formatter->view($items, $node->language()->getId());
    $this->applyWebformAction($build, $node->toUrl()->toString());

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['carlson-deferred-webform'],
      ],
      'webform' => $build,
    ];
  }

  /**
   * Ensures deferred Webforms post back to the canonical node URL.
   *
   * @param array $build
   *   The render array to update.
   * @param string $action
   *   The form action URL.
   */
  protected function applyWebformAction(array &$build, string $action): void {
    if (($build['#type'] ?? NULL) === 'webform') {
      $build['#action'] = $action;
      $build['#lazy'] = FALSE;
    }

    foreach (Element::children($build) as $child_key) {
      if (!is_array($build[$child_key])) {
        continue;
      }

      $this->applyWebformAction($build[$child_key], $action);
    }
  }

}
