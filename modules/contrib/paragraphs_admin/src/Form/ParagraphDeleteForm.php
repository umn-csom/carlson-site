<?php

namespace Drupal\paragraphs_admin\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to delete a paragraph.
 */
class ParagraphDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the paragraph %title?', ['%title' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromUri('internal:/admin/content/paragraphs');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();
    $this->messenger()->addStatus($this->t('The paragraph %label has been deleted.', ['%label' => $entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
