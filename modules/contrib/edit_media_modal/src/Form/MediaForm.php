<?php

namespace Drupal\edit_media_modal\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\media\MediaForm as CoreMediaForm;

/**
 * Override Media Entity Edit Form.
 */
class MediaForm extends CoreMediaForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (!$form_state->get('delete_mode')) {
      $form = parent::form($form, $form_state);
    }
    else {
      $media_type = $this->entity->bundle->entity;

      $form['#title'] = $this->t('Delete %type_label @label', [
        '%type_label' => $media_type->label(),
        '@label' => $this->entity->label(),
      ]);

      $form['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Are you sure you want to delete the @entity-type %label?', [
          '@entity-type' => $media_type->label(),
          '%label' => $this->entity->label(),
        ]),
      ];

      $form['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This action cannot be undone.'),
      ];

      $form['#process'][] = '::processForm';
    }

    if (
      !$form_state->has('edit_media_in_modal') &&
      $this->getRequest()->isXmlHttpRequest() &&
      $this->getRequest()->query->has('edit_media_in_modal') &&
      $this->getRequest()->query->get('edit_media_in_modal')
    ) {
      $form_state->set('edit_media_in_modal', TRUE);
    }

    if ($this->getRequest()->query->has('destination')) {
      $form_state->set('destination', $this->getRequest()->query->get('destination'));
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if ($form_state->get('edit_media_in_modal')) {
      $form['#prefix'] = '<div id="edit-media-form-modal">';
      $form['#suffix'] = '</div>';

      if ($form_state->get('delete_mode')) {
        $form['actions']['delete']['#access'] = FALSE;
        $form['actions']['submit']['#access'] = FALSE;

        $form['actions']['yes'] = [
          '#type' => 'submit',
          '#value' => $this->t('Confirm'),
          '#limit_validation_errors' => [],
          '#submit' => ['::deleteEntity'],
          '#button_type' => 'danger',
          '#ajax' => [
            'callback' => '::ajaxSubmit',
            'wrapper' => 'edit-media-form-modal',
          ],
        ];

        $form['actions']['no'] = [
          '#type' => 'submit',
          '#value' => $this->t('Cancel'),
          '#limit_validation_errors' => [],
          '#submit' => ['::deleteMode'],
          '#button_type' => 'primary',
          '#ajax' => [
            'callback' => '::ajaxFormRerender',
            'wrapper' => 'edit-media-form-modal',
          ],
        ];
      }
      else {
        $form['actions']['delete']['#type'] = 'submit';
        $form['actions']['delete']['#value'] = $form['actions']['delete']['#title'];
        $form['actions']['delete']['#weight'] = 4;
        $form['actions']['delete']['#limit_validation_errors'] = [];
        $form['actions']['delete']['#submit'] = [
          '::deleteMode',
        ];
        $form['actions']['delete']['#ajax'] = [
          'callback' => '::ajaxFormRerender',
          'wrapper' => 'edit-media-form-modal',
        ];
        unset($form['actions']['delete']['#title']);
        unset($form['actions']['delete']['#url']);
        unset($form['actions']['delete']['#options']);

        $form['actions']['submit']['#ajax'] = [
          'callback' => '::ajaxSubmit',
          'wrapper' => 'edit-media-form-modal',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $saved = parent::save($form, $form_state);

    if ($form_state->get('edit_media_in_modal')) {
      $form_state->disableRedirect();
      $this->entity->save();
    }

    return $saved;
  }

  /**
   * Enable delete mode callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function deleteMode(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $form_state->set('delete_mode', !$form_state->get('delete_mode'));
    $form_state->setRebuild();
  }

  /**
   * Delete entity mode callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteEntity(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->entity->delete();
  }

  /**
   * Additional ajax callback for modal window forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The ajax response.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $response = new AjaxResponse();
      $values = [
        'attributes' => [
          'updated' => time(),
        ],
      ];

      if ($form_state->hasValue('field_media_image')) {
        $values['attributes']['alt'] = $form_state->getValue([
          'field_media_image',
          0,
          'alt',
        ]);
      }

      $response->addCommand(new EditorDialogSave($values));
      $response->addCommand(new CloseModalDialogCommand());

      if ($form_state->has('destination')) {
        $response->addCommand(new RedirectCommand($form_state->get('destination')));
      }

      return $response;
    }

    return $form;
  }

  /**
   * Rerender ajax callback for modal window forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The ajax response.
   */
  public function ajaxFormRerender(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
