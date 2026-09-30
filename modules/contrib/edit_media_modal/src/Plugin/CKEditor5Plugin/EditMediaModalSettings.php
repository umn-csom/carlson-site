<?php

namespace Drupal\edit_media_modal\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 EditMediaModal plugin configuration.
 */
class EditMediaModalSettings extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The list of media bundles.
   *
   * @var array
   */
  protected array $mediaBundles = [];

  /**
   * The EditMediaModalSettings object construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'editMediaModal' => [
        'dialogSettings' => [
          'height' => '75',
        ],
        'editMediaModalForms' => [],
        'extras' => [
          'skipAccessCheck' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form_modes = $this->entityTypeManager->getStorage('entity_form_mode')->loadByProperties([
      'targetEntityType' => 'media',
    ]);

    $form_modes_options['default'] = $this->t('Default');
    foreach ($form_modes as $form_mode) {
      $form_modes_options[$form_mode->id()] = $form_mode->label();
    }

    $media_bundles = $this->getMediaBundles();
    $form['extras'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('The extra settings'),
      '#tree' => TRUE,
      'skipAccessCheck' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Skip access check'),
        '#description' => $this->t("The access check validates whether a user can edit each media entity directly within CKEditor. It's usually unnecessary for small projects where all users share the same access level and there's no complex permission system."),
        '#default_value' => $this->configuration['editMediaModal']['extras']['skipAccessCheck'] ?? FALSE,
      ],
    ];
    $form['dialogSettings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('The dialog window settings'),
      '#tree' => TRUE,
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#min' => 1,
        '#max' => 100,
        '#default_value' => $this->configuration['editMediaModal']['dialogSettings']['height'] ?? '75',
        '#field_suffix' => '%',
      ],
    ];
    $form['editMediaModalForms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Media bundles form view mode configuration'),
      '#tree' => TRUE,
    ];
    foreach ($media_bundles as $media_bundle) {
      $form['editMediaModalForms'][$media_bundle->id()] = [
        '#type' => 'select',
        '#options' => $form_modes_options,
        '#title' => $media_bundle->label(),
        '#default_value' => $this->configuration['editMediaModal']['editMediaModalForms'][$media_bundle->id()] ?? 'default',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validation can be added here if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['editMediaModal']['editMediaModalForms'] = $values['editMediaModalForms'];
    $this->configuration['editMediaModal']['dialogSettings'] = $values['dialogSettings'];
    $this->configuration['editMediaModal']['extras'] = $values['extras'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = array_merge_recursive($static_plugin_config, $this->getConfiguration());
    return parent::getDynamicPluginConfig($static_plugin_config, $editor);
  }

  /**
   * Get media bundles.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   *   The list of media bundles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getMediaBundles() {
    if (empty($this->mediaBundles)) {
      $this->mediaBundles = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    }

    return $this->mediaBundles;
  }

}
