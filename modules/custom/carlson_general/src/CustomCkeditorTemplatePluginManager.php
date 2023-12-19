<?php

namespace Drupal\carlson_general;

use Drupal\ckeditor_templates\CkeditorTemplatePluginManager;
use Drupal\Core\Session\AccountInterface;

class CustomCkeditorTemplatePluginManager extends CkeditorTemplatePluginManager {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  public function __construct($namespaces, $cache_backend, $module_handler, AccountInterface $current_user) {
    parent::__construct($namespaces, $cache_backend, $module_handler);
    $this->currentUser = $current_user;
  }

  public function getTemplates() {
    $templates = parent::getTemplates();
    $filtered_templates = [];

    foreach ($templates as $id => $template) {
      $actual_id = strpos($id, 'config_template:') === 0 ? explode(':', $id)[1] : $id;
      if ($this->currentUser->hasPermission('use_ckeditor_template_' . $actual_id)) {
        $filtered_templates[$actual_id] = $template;
      }
    }

    return $filtered_templates;
  }
}
