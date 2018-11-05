<?php

namespace Drupal\menu_injector\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\menu_injector\MenuInjectorRuleInterface;

/**
 * Defines the MenuInjectorRule entity.
 *
 * @ConfigEntityType(
 *   id = "menu_injector_rule",
 *   label = @Translation("Menu Injector Rule"),
 *   handlers = {
 *     "list_builder" = "Drupal\menu_injector\Controller\MenuInjectorRuleListBuilder",
 *     "form" = {
 *       "default" = "Drupal\menu_injector\Form\MenuInjectorRuleForm",
 *       "delete" = "Drupal\menu_injector\Form\MenuInjectorRuleDeleteForm"
 *     }
 *   },
 *   config_prefix = "menu_injector_rule",
 *   admin_permission = "administer_injector_permissions",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/menu-injector/add",
 *     "edit-form" = "/admin/structure/menu-injector/{menu_injector_rule}/edit",
 *     "delete-form" = "/admin/structure/menu-injector/{menu_injector_rule}/delete",
 *     "collection" = "/admin/structure/menu-injector"
 *   }
 * )
 */
class MenuInjectorRule extends ConfigEntityBase implements MenuInjectorRuleInterface {

  /**
   * The MenuInjectorRule ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The MenuInjectorRule label.
   *
   * @var string
   */
  protected $label;

  /**
   * Whether the rule is enabled or not.
   *
   * @var boolean
   */
  protected $enabled;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabled() {
    return $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled) {
    $this->enabled = $enabled;
  }

  /**
   * Evaluates all conditions attached to this rule and determines if this rule
   * is "active" or not.
   *
   * @return boolean Whether or not this rule is active.
   */
  public function isActive() {
    // Must be enabled.
    if (!$this->getEnabled()) {
      return false;
    }

    // No objections, rule is active.
    return true;
  }

  /**
   * Gets the condition plugin manager.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The condition plugin manager.
   */
  protected function contextRepository() {
    if (!isset($this->contextRepository)) {
      $this->contextRepository = \Drupal::service('context.repository');
    }
    return $this->contextRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
  }

  /**
   *
   * Rebuild routes to create menu links.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($this->isSyncing()) {
      // Rebuild menu position links when new rule is created.
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
  }

}