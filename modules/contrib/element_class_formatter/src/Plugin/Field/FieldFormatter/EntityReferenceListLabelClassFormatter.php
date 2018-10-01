<?php

namespace Drupal\element_class_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Template\Attribute;

/**
 * Plugin implementation of the 'file with class' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_list_label_class",
 *   label = @Translation("Label list (with class)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceListLabelClassFormatter extends EntityReferenceLabelFormatter {

  use ElementClassTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $default_settings = parent::defaultSettings() + [
      'list_type' => 'ul',
    ];

    return ElementClassTrait::elementClassDefaultSettings($default_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $class = $this->getSetting('class');

    $elements['list_type'] = [
      '#title' => $this->t('List type'),
      '#type' => 'select',
      '#options' => [
        'ul' => 'Un-ordered list',
        'ol' => 'Ordered list',
      ],
      '#default_value' => $this->getSetting('list_type'),
    ];

    return $this->elementClassSettingsForm($elements, $class);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $class = $this->getSetting('class');
    if ($tag = $this->getSetting('list_type')) {
      $summary[] = $this->t('List type: @tag', ['@tag' => $tag]);
    }

    return $this->elementClassSettingsSummary($summary, $class);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $attributes = new Attribute();
    $class = $this->getSetting('class');
    if (!empty($class)) {
      $attributes->addClass($class);
    }

    return [
      [
        '#theme' => 'item_list',
        '#items' => $elements,
        '#list_type' => $this->getSetting('list_type'),
        '#attributes' => $attributes->toArray(),
      ],
    ];
  }

}
