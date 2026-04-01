<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
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
    $field_item = $node->get($field_name)->first();
    $webform = $field_item?->entity;

    if (!$webform) {
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

    $default_data = [];
    if (!empty($field_item->default_data)) {
      $default_data = Yaml::decode($field_item->default_data) ?? [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['carlson-deferred-webform'],
      ],
      'webform' => [
        '#type' => 'webform',
        '#webform' => $webform,
        '#default_data' => $default_data,
        '#entity' => $node,
        '#action' => $node->toUrl()->toString(),
      ],
    ];
  }

}
