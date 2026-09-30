<?php

namespace Drupal\paragraphs_admin\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Link;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Defines a field handler for displaying the host entity of a given paragraph.
 *
 * A host entity is defined as the top-level parent entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("paragraphs_host_entity")
 */
class ParagraphsHostEntity extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid altering the query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Logic to determine the host entity ID.
    $renderedHostEntity = $this->getRenderedHostEntity($values);

    return $renderedHostEntity;
  }

  /**
   * Retrieves the top-level host entity of a paragraph entity.
   *
   * Recursively traverses up the paragraph entity hierarchy to find the host
   * entity. If found, returns a link to the host entity. If the top-level
   * parent is of type "Paragraph" then we assume the entity is orphaned and
   * the link has been lost to a content entity.
   *
   * @param \Drupal\views\ResultRow $values
   *   The result row object containing the entity.
   *
   * @return string|null
   *   The rendered link to the host entity. If the host entity has no canonical
   *   URI, as is the case for Paragraphs, then the entity title is returned
   *   without a link.  If no parent can be found, NULL is returned.
   */
  protected function getRenderedHostEntity(ResultRow $values) {
    $entity = $values->_entity;

    // Traverse up the hierarchy to find the host entity.
    while ($entity instanceof Paragraph) {
      $parent = $entity->getParentEntity();
      if ($parent) {
        $entity = $parent;
      }
      else {
        // No more parents, break the loop.
        break;
      }
    }

    // Check if the entity has a canonical URL.
    if ($entity && $entity->hasLinkTemplate('canonical')) {
      $url = $entity->toUrl();
      return Link::fromTextAndUrl($entity->label(), $url)->toString();
    }
    // Paragraph entities have no canonical URL. This often indicates
    // the Paragraph may be orphaned which will be apparent from the
    // unlinked Parent/Host entity title.
    elseif ($entity && $entity->label()) {
      return $entity->label();
    }

    // Return NULL if no suitable entity is found.
    return NULL;
  }

}
