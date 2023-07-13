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
   * Instance reference to the derived menu root.
   *
   * @var string
   */
  protected $menuRoot;

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();

    $form = parent::blockForm($form, $form_state);
    $menu_parent_selector = \Drupal::service('menu.parent_form_selector');

    // If there exists a config value for Expand all menu links (expand), that
    // value should populate core's Expand all menu items checkbox
    // (expand_all_items).
    if (isset($config['expand'])) {
      $form['menu_levels']['expand_all_items']['#default_value'] = $config['expand'];
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
      '#process' => [[get_class(), 'processMenuBlockFieldSets']],
    ];

    $menu_name = $this->getDerivativeId();
    $menus = Menu::loadMultiple([$menu_name]);
    $menus[$menu_name] = $menus[$menu_name]->label();

    $form['advanced']['parent'] = $menu_parent_selector->parentSelectElement($config['parent'], '', $menus);

    $form['advanced']['parent'] += [
      '#title' => $this->t('Fixed parent item'),
      '#description' => $this->t('Alter the options in “Menu levels” to be relative to the fixed parent item. The block will only contain children of the selected menu link.'),
    ];

    $form['advanced']['label_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Use as title'),
      '#description' => $this->t('Replace the block title with an item from the menu.'),
      '#options' => [
        self::LABEL_BLOCK => $this->t('Block title'),
        self::LABEL_MENU => $this->t('Menu title'),
        self::LABEL_FIXED => $this->t("Fixed parent item's title"),
        self::LABEL_ACTIVE_ITEM => $this->t("Active item's title"),
        self::LABEL_PARENT => $this->t("Active trail's parent title"),
        self::LABEL_ROOT => $this->t("Active trail's root title"),
      ],
      '#default_value' => $config['label_type'],
      '#states' => [
        'visible' => [
          ':input[name="settings[label_display]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['advanced']['label_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link the title?'),
      '#default_value' => $config['label_link'],
      '#states' => [
        'visible' => [
          ':input[name="settings[label_display]"]' => ['checked' => TRUE],
          ':input[name="settings[label_type]"]' => [
            ['value' => self::LABEL_ACTIVE_ITEM],
            ['value' => self::LABEL_PARENT],
            ['value' => self::LABEL_ROOT],
            ['value' => self::LABEL_FIXED],
          ],
        ],
      ],
    ];

    $form['style'] = [
      '#type' => 'details',
      '#title' => $this->t('HTML and style options'),
      '#open' => FALSE,
      '#process' => [[get_class(), 'processMenuBlockFieldSets']],
    ];

    $form['advanced']['follow'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Make the initial visibility level follow the active menu item.</strong>'),
      '#default_value' => $config['follow'],
      '#description' => $this->t('If the active menu item is deeper than the initial visibility level set above, the initial visibility level will be relative to the active menu item. Otherwise, the initial visibility level of the tree will remain fixed.'),
    ];

    $form['advanced']['follow_parent'] = [
      '#type' => 'radios',
      '#title' => $this->t('Initial visibility level will be'),
      '#description' => $this->t('When following the active menu item, select whether the initial visibility level should be set to the active menu item, its root parent, or its children.'),
      '#default_value' => $config['follow_parent'],
      '#options' => [
        'active' => $this->t('Active menu item'),
        'child' => $this->t('Children of active menu item'),
        'child_or_active' => $this->t('Children of active menu item; active menu item if no children'),
        'root' => $this->t('Level 1 root of active menu item'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="settings[follow]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['style']['suggestion'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Theme hook suggestion'),
      '#default_value' => $config['suggestion'],
      '#field_prefix' => '<code>menu__</code>',
      '#description' => $this->t('A theme hook suggestion can be used to override the default HTML and CSS classes for menus found in <code>menu.html.twig</code>.'),
      '#machine_name' => [
        'error' => $this->t('The theme hook suggestion must contain only lowercase letters, numbers, and underscores.'),
        'exists' => [$this, 'suggestionExists'],
      ],
    ];

    // Open the details field sets if their config is not set to defaults.
    foreach (['menu_levels', 'advanced', 'style'] as $fieldSet) {
      foreach (array_keys($form[$fieldSet]) as $field) {
        if (isset($defaults[$field]) && $defaults[$field] !== $config[$field]) {
          $form[$fieldSet]['#open'] = TRUE;
        }
      }
    }

    return $form;
  }

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
    $depth = $this->configuration['depth'];
    // For blocks placed in Layout Builder or similar, check for the deprecated
    // 'expand' config property in case the menu block's configuration has not
    // yet been updated.
    $expand_all_items = $this->configuration['expand'] ?? $this->configuration['expand_all_items'];
    $parent = $this->configuration['parent'] ?? '';
    $follow = $this->configuration['follow'];
    $follow_parent = $this->configuration['follow_parent'];
    $following = FALSE;

    $parameters->setMinDepth($level);

    // If we're following the active trail and the active trail is deeper than
    // the initial starting level, we update the level to match the active menu
    // item's level in the menu.
    if ($follow && count($parameters->activeTrail) > $level) {
      $level = count($parameters->activeTrail);
      $following = TRUE;
    }

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    // If we're currently following an active menu item, or for menu blocks with
    // start level greater than 1, only show menu items from the current active
    // trail. Adjust the root according to the current position in the menu in
    // order to determine if we can show the subtree. If we're not following an
    // active trail and using a fixed parent item, we'll skip this step.
    $fixed_parent_menu_link_id = str_replace($menu_name . ':', '', $parent);
    if ($following || ($level > 1 && !$fixed_parent_menu_link_id)) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
        if ($follow_parent === 'root') {
          $this->menuRoot = empty($menu_trail_ids[0]) ? $menu_trail_ids[1] : $menu_trail_ids[0];
        } else {
          $offset = ($following && in_array($follow_parent, ['active', 'child_or_active'])) ? 2 : 1;
          $this->menuRoot = $menu_trail_ids[$level - $offset];
          if ($follow_parent == 'child_or_active') {
            $active_menu_link_id = end($menu_trail_ids);
            $has_children = $this->menuTree->getSubtreeHeight($active_menu_link_id) > 1;
            if ($has_children) {
              $menu_root = $active_menu_link_id;
            }
          }
        }
        $parameters->setRoot($this->menuRoot)->setMinDepth(1);
        if ($depth > 0) {
          $parameters->setMaxDepth(min($depth, $this->menuTree->maxDepth()));
        }
      }
      else {
        if (empty($all_rules)) {
          return [];
        }
      }
    }

    // If expandedParents is empty, the whole menu tree is built.
    if ($expand_all_items) {
      $parameters->expandedParents = [];
    }

    // When a fixed parent item is set, root the menu tree at the given ID.
    if ($fixed_parent_menu_link_id) {
      // Clone the parameters so we can fall back to using them if we're
      // following the active menu item and the current page is part of the
      // active menu trail.
      $fixed_parameters = clone $parameters;
      $fixed_parameters->setRoot($fixed_parent_menu_link_id);
      $tree = $this->menuTree->load($menu_name, $fixed_parameters);

      // Check if the tree contains links.
      if (empty($tree)) {
        // If the starting level is 1, we always want the child links to appear,
        // but the requested tree may be empty if the tree does not contain the
        // active trail. We're accessing the configuration directly since the
        // $level variable may have changed by this point.
        if ($this->configuration['level'] === 1 || $this->configuration['level'] === '1') {
          // Change the request to expand all children and limit the depth to
          // the immediate children of the root.
          $fixed_parameters->expandedParents = [];
          $fixed_parameters->setMinDepth(1);
          $fixed_parameters->setMaxDepth(1);
          // Re-load the tree.
          $tree = $this->menuTree->load($menu_name, $fixed_parameters);
        }
      }
      elseif ($following) {
        // If we're following the active menu item, and the tree isn't empty
        // (which indicates we're currently in the active trail), we unset
        // the tree we made and just let the active menu parameters from before
        // do their thing.
        unset($tree);
      }
    }

    // Load the tree if we haven't already.
    if (!isset($tree)) {
      $tree = $this->menuTree->load($menu_name, $parameters);
    }
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    $this->tree = $tree;

    // Run through menu injector rules if available.
    if (!empty($all_rules)) {
      foreach ($all_rules as $rule) {
        if (!$rule['is_root']) {
          $parameters->setRoot($rule['menu_reference']);
          $parameters->setMinDepth(0);
        } else {
          $new_menu_name = $rule['menu_reference'];
          $new_menu_tree = \Drupal::menuTree();
          $new_parameters = $new_menu_tree->getCurrentRouteMenuTreeParameters($rule['menu_reference']);
          $new_parameters->setMinDepth(0);

          $new_tree = $new_menu_tree->load($new_menu_name, $new_parameters);
          $new_tree = $new_menu_tree->transform($new_tree, $manipulators);
          $combined_tree[] = $new_tree;
        }
      }
    }

    // Build the tree.
    if (!empty($combined_tree) && isset($tree)) {
      $combined_tree = array_merge($tree, $combined_tree[0]);
      $build = $this->menuTree->build($combined_tree);
    } else {
      $tree = $this->menuTree->transform($tree, $manipulators);
      $build = $this->menuTree->build($tree);
    }

    $label = $this->getBlockLabel() ?: $this->label();
    // Set the block's #title (label) to the dynamic value.
    $build['#title'] = [
      '#markup' => $label,
    ];
    if (!empty($build['#theme'])) {
      // Add the configuration for use in menu_block_theme_suggestions_menu().
      $build['#menu_block_configuration'] = $this->configuration;
      // Set the generated label into the configuration array so it is
      // propagated to the theme preprocessor and template(s) as needed.
      $build['#menu_block_configuration']['label'] = $label;
      // Remove the menu name-based suggestion so we can control its precedence
      // better in menu_block_theme_suggestions_menu().
      $build['#theme'] = 'menu';
    }

    $build['#contextual_links']['menu'] = [
      'route_parameters' => ['menu' => $menu_name],
    ];

    $build['#cache']['contexts'][] = 'route.menu_active_trails:' . $menu_name;

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  protected function getActiveTrailRootTitle() {
    /** @var array $active_trail_ids */
    $active_trail_ids = $this->getDerivativeActiveTrailIds();

    if ($active_trail_ids) {
      if ($this->menuRoot && in_array($this->menuRoot, $active_trail_ids)) {
        $active_trail_ids = [ $this->menuRoot ];
      }
      return $this->getLinkTitleFromLink(end($active_trail_ids));
    }
    return NULL;
  }

  protected function getAllRules($menu_name, $active_trail) {
    $node = \Drupal::routeMatch()->getParameter('node');
    //$rules = \Drupal::entityManager()->getStorage('menu_injector_rule')->loadMultiple();
    $rules = \Drupal::entityTypeManager()->getStorage('menu_injector_rule')->loadMultiple();
    $active_trail_parent_menu_plugin_id = reset($active_trail);
    $results = [];
    $index = 0;

    // Iterate over the rules.
    foreach ($rules as $rule) {

      if ($rule->isActive() &&
          $menu_name === $rule->getMenuChoice() &&
          isset($node)
      ) {

        if( $node->bundle() === $rule->getContentType() &&
            !empty( $rule->getTaxonomyTerms() ) ) {

          // Check for any taxonomy term matches.
          $nodes_matches = [];
          if( strlen( $rule->getTaxonomyMapField() ) > 0 ) {
            $nodes_matches = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'field_menu_rule' => $rule->getTaxonomyTerms(),
            ]);
          }

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
    }

    return $results;
  }

}
