<?php

namespace Drupal\element_class_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\file\IconMimeTypes;
use Drupal\file\Plugin\Field\FieldFormatter\DescriptionAwareFileFormatterBase;

/**
 * Plugin implementation of the 'file link with class' formatter.
 *
 * @FieldFormatter(
 *   id = "file_link_class",
 *   label = @Translation("File link (with class)"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileLinkClassFormatter extends DescriptionAwareFileFormatterBase {

  use ElementClassTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $default_settings = parent::defaultSettings() + [
      'use_label_as_fallback' => '1',
      'show_filesize' => '0',
      'show_filetype' => '0',
    ];

    return self::elementClassDefaultSettings($default_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $class = $this->getSetting('class');

    $elements['use_label_as_fallback'] = [
      '#title' => $this->t('Use entity label as link text'),
      '#description' => $this->t('Replace the file name by the entity label when no description is available.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('use_label_as_fallback'),
    ];

    $elements['show_filesize'] = [
      '#title' => $this->t('Display the file size'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_filesize'),
    ];

    $elements['show_filetype'] = [
      '#title' => $this->t('Display the file type'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_filetype'),
    ];

    return $this->elementClassSettingsForm($elements, $class);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $class = $this->getSetting('class');

    if (!empty($this->getSetting('use_label_as_fallback'))) {
      $summary[] = $this->t('Use entity label as link text');
    }
    if (!empty($this->getSetting('show_filesize'))) {
      $summary[] = $this->t('Show file size');
    }
    if (!empty($this->getSetting('show_filetype'))) {
      $summary[] = $this->t('Show file type');
    }

    return $this->elementClassSettingsSummary($summary, $class);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $class = $this->getSetting('class');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      // Get default link text.
      $fallback_text = $this->getSetting('use_label_as_fallback') ? $item->getEntity()->label() : $file->getFilename();
      $link_text = $this->getSetting('use_description_as_link_text') && !empty($item->description) ? $item->description : $fallback_text;
      $attributes = new Attribute();
      $attributes->setAttribute('title', $file->getFilename());

      // File meta data.
      $file_type = strtoupper(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
      $file_size = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '10.2.0',
        fn() => ByteSizeMarkup::create($file->getSize()),
        // @phpstan-ignore-next-line
        fn() => format_size($file->getSize())
      );
      $mime_type = $file->getMimeType();
      $attributes->setAttribute('type', $mime_type . '; length=' . $file->getSize());

      // Classes for styling.
      $classes = [
        'file',
        'file--mime-' . strtr($mime_type, ['/' => '-', '.' => '-']),
        'file--' . DeprecationHelper::backwardsCompatibleCall(
          \Drupal::VERSION,
          '10.3.0',
          fn() => IconMimeTypes::getIconClass($mime_type),
          // @phpstan-ignore-next-line
          fn() => file_icon_class($mime_type)
        ),
        $class,
      ];
      $attributes->addClass($classes);

      // Customise link text.
      $show_filesize = $this->getSetting('show_filesize');
      $show_filetype = $this->getSetting('show_filetype');
      if ($show_filesize && $show_filetype) {
        $link_text = $link_text . ' (' . $file_type . ', ' . $file_size . ')';
      }
      elseif ($show_filesize && !$show_filetype) {
        $link_text = $link_text . ' (' . $file_size . ')';
      }
      elseif (!$show_filesize && $show_filetype) {
        $link_text = $link_text . ' (' . $file_type . ')';
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $link_text,
        '#url' => Url::fromUri($file->createFileUrl(FALSE)),
        '#attributes' => $attributes->toArray(),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
    }
    return $elements;
  }

}
