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
}
