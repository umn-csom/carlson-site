<?php

namespace Drupal\bigmenu\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to change the module settings.
 */
class BigMenuSettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'bigmenu_settings';
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['bigmenu.settings'];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bigmenu.settings');
    $form['max_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Max depth'),
      '#description' => $this->t('The max depth to display on a menu.'),
      '#default_value' => $config->get('max_depth'),
      '#min' => 0,
      '#max' => 10,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('bigmenu.settings')
      ->set('max_depth', $form_state->getValue('max_depth'))
      ->save();
    parent::submitForm($form, $form_state);

  }

}
