<?php

namespace Drupal\carlson_general;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ckeditor_templates\CkeditorTemplatePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CkeditorTemplatePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The CKEditor template plugin manager.
   *
   * @var \Drupal\ckeditor_templates\CkeditorTemplatePluginManager
   */
  protected $ckeditorTemplateManager;

  /**
   * Constructs a new CkeditorTemplatePermissions instance.
   *
   * @param \Drupal\ckeditor_templates\CkeditorTemplatePluginManager $ckeditor_template_manager
   *   The CKEditor template plugin manager.
   */
  public function __construct(CkeditorTemplatePluginManager $ckeditor_template_manager) {
    $this->ckeditorTemplateManager = $ckeditor_template_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ckeditor_template')
    );
  }

  /**
   * Returns an array of CKEditor template permissions.
   *
   * @return array
   */
  public function permissions() {
    $permissions = [];
    $templates = $this->ckeditorTemplateManager->getTemplates();

    foreach ($templates as $template_id => $template) {
      $permissions['use_ckeditor_template_' . $template_id] = [
        'title' => $this->t('Use CKEditor Template: <strong>@name</strong>', ['@name' => $template->label()]),
        'description' => $this->t('Allows using the <strong>@name</strong> template in CKEditor.', ['@name' => $template->label()]),
      ];
    }

    return $permissions;
  }
}
