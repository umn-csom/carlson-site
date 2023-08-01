<?php

namespace Drupal\carlson_megamenu_layouts\Plugin\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

class MegaMenuWithImageColumn extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'image_location' => 'left',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['image_location'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Location'),
      '#default_value' => $config['image_location'] ?? 'left',
      '#options' => [
        'left' => $this->t('Left'),
        'right' => $this->t('Right'),
        'none' => $this->t('None'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // any additional form validation that is required
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['image_location'] = $form_state->getValue('image_location');
  }

}
