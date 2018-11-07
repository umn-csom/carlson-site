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
   * Vocabulary list id.
   *
   * @var string
   */
  protected $vocab_list;

  /**
   * Taxonomy term.
   *
   * @var int
   */
  protected $taxonomy_term;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The parent menu link id for this rule.
   *
   * @var string
   */
  protected $parent;

  /**
   * The menu links to inject.
   *
   * @var string
   */
  protected $menu_links;

  /**
   * The menu choice to inject the menu links into.
   *
   * @var string
   */
  protected $menu_choice;

  /**
   * The menu mode.
   *
   * @var string
   */
  protected $menu_mode;

  /**
   * Is a root menu.
   *
   * @var boolean
   */
  protected $is_root;
  
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
  public function getVocabList() {
    return $this->vocab_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getTaxonomyTerm() {
    return $this->taxonomy_term;
  }

  /**
   * {@inheritdoc}
   */
  public function getTaxonomyTerms() {
    $taxonomy_terms = explode(',', $this->taxonomy_term);
    return $taxonomy_terms;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentMenuPluginId() {
    $parent_menu_id = explode(':', $this->parent);
    if( !empty($parent_menu_id) ) {
      $parent_menu_id = ( $parent_menu_id[1] . ':' . $parent_menu_id[2] );
    } else {
      $parent_menu_id = '';
    }
    
    return $parent_menu_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuChoice() {
    return $this->menu_choice;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinks() {
    return $this->menu_links;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinksReference() {
    $menu_links_ids = explode(':', $this->menu_links);

    if( count($menu_links_ids) === 2) {
      $menu_links_ids = $menu_links_ids[0];
      $this->is_root = true;
    }

    if( count($menu_links_ids) === 3) {
      $menu_links_ids = ( $menu_links_ids[1] . ':' . $menu_links_ids[2] );
      $this->is_root = false;
    }

    return $menu_links_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuMode() {
    return $this->menu_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getIsRoot() {
    return $this->is_root;
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
   * {@inheritdoc}
   */
  public function setVocabList($vocab_list) {
    $this->vocab_list = $vocab_list;
  }

  /**
   * {@inheritdoc}
   */
  public function setTaxonomyTerm($taxonomy_term) {
    $this->taxonomy_term = $taxonomy_term;
  }

  /**
   * {@inheritdoc}
   */
  public function setParent($parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function setMenuLinks($menu_links) {
    $this->menu_links = $menu_links;
  }

  /**
   * {@inheritdoc}
   */
  public function setMenuChoice($menu_choice) {
    $this->menu_choice = $menu_choice;
  }

  /**
   * {@inheritdoc}
   */
  public function setMenuMode($menu_mode) {
    $this->menu_mode = $menu_mode;
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