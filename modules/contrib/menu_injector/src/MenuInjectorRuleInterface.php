<?php

namespace Drupal\menu_injector;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Example entity.
 */
interface MenuInjectorRuleInterface extends ConfigEntityInterface {

  /**
   * Returns the ID of the menu injector rule
   * @return integer
   *    The unique identifier of the menu injector rule
   */
  public function getId();

  /**
   * Returns the administrative title of the menu injector rule
   * @return string
   *    The administrative title of the menu injector rule
   */
  public function getLabel();

  /**
   * Returns the status of the menu injector rule
   * @return boolean
   *    The status of the menu injector rule
   */
  public function getEnabled();

  /**
   * Returns the vocabulary list of the menu injector rule
   * @return string
   *    The vocabulary list of the menu injector rule
   */
  public function getVocabList();

  /**
   * Returns the parent menu item
   * @return string
   *    The parent menu item
   */
  public function getParent();

  /**
   * Returns the menu choice
   * @return string
   *    The menu choice item
   */
  public function getMenuChoice();

  /**
   * Returns the menu links
   * @return string
   *    The menu links item
   */
  public function getMenuLinks();

  /**
   * Returns the parent menu plugin id
   * @return string
   *    The parent menu plugin id
   */
  public function getParentMenuPluginId();

  /**
   * Returns the menu mode
   * @return string
   *    The menu mode
   */
  public function getMenuMode();

  /**
   * Returns the menu links reference
   * @return string
   *    The menu links reference
   */
  public function getMenuLinksReference();

  /**
   * Sets the administrative title of the menu injector rule
   * @param string $label
   *    The administrative title of the menu injector rule
   */
  public function setLabel($label);

  /**
   * Sets the status menu injector rule
   * @param boolean $enabled
   *    The status of the menu injector rule
   */
  public function setEnabled($enabled);

  /**
   * Sets the vocabulary list of the menu injector rule
   * @param string $vocab_list
   *    The vocabulary list of the menu injector rule
   */
  public function setVocabList($vocab_list);

  /**
   * Sets the parent menu
   * @return string $parent
   *    The parent menu
   */
  public function setParent($parent);

  /**
   * Sets the menu choice
   * @return string $menu_choice
   *    The menu choice
   */
  public function setMenuChoice($menu_choice);

  /**
   * Sets the menu links
   * @return string $menu_links
   *    The menu links
   */
  public function setMenuLinks($menu_links);

  /**
   * Sets the menu mode
   * @return string
   *    The menu mode.
   */
  public function setMenuMode($menu_mode);
}
