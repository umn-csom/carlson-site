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

    // Store last mlid by level.
    $last_parent_mlid_by_level = [];

    // Store item weights by level.
    $menu_item_weights = [
      0 => '0',
      1 => '0',
      2 => '0',
      3 => '0',
      4 => '0',
      5 => '0',
      6 => '0',
      7 => '0',
    ];

    // Allow comparing current menu level with previous menu link level.
    $last_level = 0;

    while (!$csv->eof()) {
      $row = $csv->fgetcsv();

      // Skip the header row and rows where Menu is not 'main'.
      if (isset($row[4]) && $row[4] == 'main' && $csv->key() > 0) {
        $title = '';
        $parent = '';
        $menu_level = 0;

        // Find the first menu link level title that is not empty.
        for ($i = 1; $i <= 7; $i++) {
          if (!empty($row[$i+4])) {
            $title = $row[$i+4];
            $menu_level = $i;

            // Set the parent mlid if we're not at the top level and
            // we find a parent mlid from a previous row.
            if ($i > 1) {
              if (isset($last_parent_mlid_by_level[$i - 1])) {
                $parent = $last_parent_mlid_by_level[$i - 1];
              }
              else {
                $message = str_repeat("  ", $menu_level) . " no parent found for L$i $title.";
                \Drupal::logger('carlson_general')->warning($message);
              }
            }
            break;
          }
        }

        // Zero out deeper menu link weights when we go back up a level.
        if ($menu_level < $last_level) {
          $i = $menu_level+1;
          while ($i < count($menu_item_weights)) {
            $menu_item_weights[$i++] = '0';
          }
        }

        $weight = $menu_item_weights[$menu_level];
        // Increment menu link weight at the current level.
        $menu_item_weights[$menu_level] = $menu_item_weights[$menu_level] + 1;

        $uri = $row[0];
        if (in_array($uri, ['<nolink>', '<button>'])) {
          $uri = 'route:' . $uri;
        }
        // Update the node with the new title and breadcrumb.
        elseif (preg_match('/^\/node\/(\d+)$/', $uri, $matches)) {
          $nodeId = $matches[1] ?? '';
          $uri = 'internal:/node/' . $nodeId;
          $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
          if ($node) {
            $node->setTitle($row[2]);
            $node->set('field_breadcrumb_title', $row[3]);
            $node->save();
          }
          else {
            $message = 'No node found with ID: ' . $nodeId;
            \Drupal::logger('carlson_general')->warning($message);
          }
        }
        elseif (preg_match('/^\//', $uri)) {
          $uri = 'internal:' . $uri;
        }

        // Create a menu link.
        $menu_link = MenuLinkContent::create([
          'title' => $title,
          'link' => ['uri' => $uri],
          'menu_name' => $row[4],
          'expanded' => TRUE,
          'enabled' => $row[11] == 'FALSE' ? 0 : 1,
          'parent' => $parent,
          'weight' => $weight,
        ]);
        $menu_link->save();
        // Store the parent link id for following children rows.
        $last_parent_mlid_by_level[$menu_level] = $menu_link->getPluginId();
        $last_level = $menu_level;

        $this->output()->writeln(str_pad(str_repeat(" -", $menu_level) . " $weight $title", 60) . " " . $menu_link->getPluginId() . " " . $uri);
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
