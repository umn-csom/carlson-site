<?php

namespace Drupal\menu_injector\Plugin\Block;

use Drupal\menu_block\Plugin\Block\MenuBlock as SuperMenuBlock;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\system\Entity\Menu;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides an extended Menu block.
 *
 * @Block(
 *   id = "menu_block",
 *   admin_label = @Translation("Menu block"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_block\Plugin\Derivative\MenuBlock"
 * )
 */
class MenuBlock extends SuperMenuBlock {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getDerivativeId();
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    $all_rules = $this->getAllRules($menu_name, $parameters->activeTrail);
    $combined_tree = [];

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $original_level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $expand = $this->configuration['expand'];
    $parent = $this->configuration['parent'];
    $follow = $this->configuration['follow'];
    $follow_parent = $this->configuration['follow_parent'];

    $max_depth = $level + $depth - 1;
    $parameters->setMinDepth($level);
    $min_depth = $level;

    if ($follow) {
      $level += count($parameters->activeTrail) - 1;
      end($parameters->activeTrail);
      $root_item = current($parameters->activeTrail);
      if (empty($root_item) && count($parameters->activeTrail) > 1) {
        $root_item = prev($parameters->activeTrail);
        $level--;
      }
      if ($follow_parent == '1' && (($level - 1) > $min_depth)) {
        $root_item = prev($parameters->activeTrail);
        $level--;
      }
      while ($follow_parent == '-1' && (($level - 1) > $min_depth) && $level > 2) {
        $root_item = prev($parameters->activeTrail);
        $level--;
      }
      $parameters->setRoot($root_item);
    }

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($max_depth > 0) {
      $parameters->setMaxDepth(min($max_depth, $this->menuTree->maxDepth()));
    }

    // If the active trail contains less (non-empty) items then the original
    // level, hide the block.
    if ($follow_parent == '-1' && 
        count(array_filter($parameters->activeTrail)) < $original_level && 
        empty($all_rules)) {
      return array();
    }

    // For menu blocks with start level greater than 1, only show menu items
    // from the current active trail. Adjust the root according to the current
    // position in the menu in order to determine if we can show the subtree.
    // If we're using a fixed parent item, we'll skip this step.
    $fixed_parent_menu_link_id = str_replace($menu_name . ':', '', $parent);
    if ($level > 1 && !$fixed_parent_menu_link_id) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
        $menu_root = $menu_trail_ids[$level - 1];
        $parameters->setRoot($menu_root)->setMinDepth(1);
        if ($depth > 0) {
          $max_depth = min($level - 1 + $depth - 1, $this->menuTree->maxDepth());
          $parameters->setMaxDepth($max_depth);
        }
      }
      else {
        if( empty($all_rules) ) {
          return [];
        }
      }
    }

    // If expandedParents is empty, the whole menu tree is built.
    if ($expand) {
      $parameters->expandedParents = [];
    }

    // When a fixed parent item is set, root the menu tree at the given ID.
    if ($fixed_parent_menu_link_id) {
      $parameters->setRoot($fixed_parent_menu_link_id);

      // If the starting level is 1, we always want the child links to appear,
      // but the requested tree may be empty if the tree does not contain the
      // active trail.
      if ($level === 1 || $level === '1') {
        // Check if the tree contains links.
        $tree = $this->menuTree->load($menu_name, $parameters);
        if (empty($tree)) {
          // Change the request to expand all children and limit the depth to
          // the immediate children of the root.
          $parameters->expandedParents = [];
          $parameters->setMinDepth(1);
          $parameters->setMaxDepth(1);
          // Re-load the tree.
          $tree = $this->menuTree->load($menu_name, $parameters);
        }
      }
    }

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    // Run through menu injector rules if available.
    if( !empty($all_rules) ) {
      foreach($all_rules as $rule) {
        // var_dump( $rule['label'] );
        // var_dump( $rule['menu_reference'] );
        // var_dump( $rule['is_root'] );
        // var_dump( $rule['menu_mode'] );

        if( !$rule['is_root'] ) {
          $parameters->setRoot( $rule['menu_reference'] );
          $parameters->setMinDepth(0);
        } else {
          $new_menu_name = $rule['menu_reference'];
          $new_menu_tree = \Drupal::menuTree();
          $new_parameters = $new_menu_tree->getCurrentRouteMenuTreeParameters( $rule['menu_reference'] );
          $new_parameters->setMinDepth(0);

          $new_tree = $new_menu_tree->load($new_menu_name, $new_parameters);
          $new_tree = $new_menu_tree->transform($new_tree, $manipulators);
          $combined_tree[] = $new_tree;
        }
      }
    }

    // Load the tree if we haven't already.
    if (!isset($tree)) {
      $tree = $this->menuTree->load($menu_name, $parameters);
    }

    // Build the tree.
    if( !empty($combined_tree) && isset($tree) ) {
      $combined_tree = array_merge($tree, $combined_tree[0]);
      $build = $this->menuTree->build($combined_tree);
    } else {
      $tree = $this->menuTree->transform($tree, $manipulators);
      $build = $this->menuTree->build($tree);
    }
  
    if (!empty($build['#theme'])) {
      // Add the configuration for use in menu_block_theme_suggestions_menu().
      $build['#menu_block_configuration'] = $this->configuration;
      // Remove the menu name-based suggestion so we can control its precedence
      // better in menu_block_theme_suggestions_menu().
      $build['#theme'] = 'menu';
    }

    $build['#contextual_links']['menu'] = [
      'route_parameters' => ['menu' => $menu_name],
    ];

    return $build;
  }

  protected function getAllRules($menu_name, $active_trail) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $rules = \Drupal::entityManager()->getStorage('menu_injector_rule')->loadMultiple();
    $active_trail_parent_menu_plugin_id = reset($active_trail);
    $results = [];
    $index = 0;

    // Iterate over the rules.
    foreach ($rules as $rule) {

      if ($rule->isActive() && 
          $menu_name === $rule->getMenuChoice() &&
          isset($node)
      ) {

        // Check for any taxonomy term matches.
        // TODO: Map this to a field selector with the CMS form.
        $nodes_matches = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
          'field_menu_rule' => $rule->getTaxonomyTerms(),
        ]);
        
        if( !empty($nodes_matches) ) {
          foreach($nodes_matches as $node_item) {
            if($node_item->id() === $node->id()) {
              $results[$index] = array(
                'label' => $rule->getLabel(),
                'menu_reference' => $rule->getMenuLinksReference(),
                'is_root' => $rule->getIsRoot(),
                'menu_mode' => $rule->getMenuMode()
              );
            }
          }
        }

        if( $rule->getMenuMode() === 'active_trail' && 
            $active_trail_parent_menu_plugin_id !== $rule->getParentMenuPluginId() ) {
          unset( $results[$index] );
        }

        $index++;
      }
    }

    return $results;
  }

}
