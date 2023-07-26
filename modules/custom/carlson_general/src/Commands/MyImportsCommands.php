<?php

namespace Drupal\carlson_general\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\pathauto\AliasStorageHelperInterface;

/**
 * A Drush command file.
 *
 * @package Drupal\my_import\Commands
 */
class MyImportsCommands extends DrushCommands {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The menu link manager service.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path alias storage helper service.
   *
   * @var \Drupal\pathauto\AliasStorageHelperInterface
   */
  protected $aliasStorageHelper;

  /**
   * Class constructor.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree, MenuLinkManagerInterface $menu_link_manager, EntityTypeManagerInterface $entity_type_manager, AliasStorageHelperInterface $alias_storage_helper) {
    $this->menuLinkTree = $menu_link_tree;
    $this->menuLinkManager = $menu_link_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasStorageHelper = $alias_storage_helper;
  }

  /**
   * Imports menu links from CSV.
   *
   * @command my_import:menu_links
   * @aliases miml
   */
  /**
   * Imports menu links from CSV.
   *
   * @command my_import:menu_links
   * @aliases miml
   */
  public function menuLinks() {
    $module_handler = \Drupal::service('module_handler');
    $path = $module_handler->getModule('carlson_general')->getPath();

    $csv = new \SplFileObject($path . '/test.csv');

    // Store menu links by title.
    $menu_links_by_title = [];

    // Store last title by level.
    $last_title_by_level = [];

    while (!$csv->eof()) {
      $row = $csv->fgetcsv();

      // Skip the header row and rows where Menu is not 'main'.
      if ($row[4] == 'main' && $csv->key() > 0) {
        $title = '';
        $parent = '';

        // Find the most specific title that is not empty.
        for ($i = 5; $i <= 11; $i++) {
          if (!empty($row[$i])) {
            $title = $row[$i];
            // Look for a parent in the previously created menu links.
            $parent = isset($last_title_by_level[$i - 1]) ? $menu_links_by_title[$last_title_by_level[$i - 1]] : '';
            // Update the last known title for the current level.
            $last_title_by_level[$i] = $title;
          }
        }

        // Update the node with the new title and breadcrumb.
        $nodeId = substr($row[0], 6);
        $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
        if ($node === NULL) {
          $this->output()->writeln('No node found with ID: ' . $nodeId);
          continue;
        }
        $node->setTitle($row[2]);
        $node->set('field_breadcrumb_title', $row[3]);
        $node->save();

        // Create a menu link.
        $menu_link = MenuLinkContent::create([
          'title' => $title,
          'link' => ['uri' => 'internal:' . $row[0]],
          'menu_name' => $row[4],
          'expanded' => TRUE,
          'enabled' => $row[11] == 'FALSE' ? 0 : 1,
          'parent' => $parent,
        ]);
        $menu_link->save();

        // Store the menu link by its title.
        $menu_links_by_title[$title] = $menu_link->getPluginId();
      }
    }

    $this->output()->writeln('Menu links have been imported.');
  }





  /**
   * Deletes all menu links from the main menu.
   *
   * @command my_import:delete_menu_links
   * @aliases midml
   */
  public function deleteMenuLinks() {
    $menu_links = $this->entityTypeManager->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => 'main']);

    foreach ($menu_links as $menu_link) {
      $menu_link->delete();
    }

    $this->output()->writeln('Menu links from the main menu have been deleted.');
  }
}
