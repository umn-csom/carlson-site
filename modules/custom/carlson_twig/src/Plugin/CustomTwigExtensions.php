<?php

namespace Drupal\carlson_twig\Plugin;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


/**
 * extend Drupal's Twig_Extension class
 */
class CustomTwigExtensions extends AbstractExtension
{

    /**
     * {@inheritdoc}
     * Let Drupal know the name of your extension
     * must be unique name, string
     */
    public function getName()
    {
        return 'carlson_twig.customtwigextensions';
    }

    /**
     * {@inheritdoc}
     * Return your custom twig function to Drupal
     */
    public function getFunctions()
    {
        return [
        new TwigFunction('find_parent_by_node', [$this, 'find_parent_by_node']),
        ];
    }

    /**
     * Returns the parent menu object.
     *
     * @param string $node
     *   node id
     *
     * @return string
     *   menu object
     */
    public static function find_parent_by_node($node, $isInside = false)
    {
        $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
        $menu_link = $menu_link_manager->loadLinksByRoute('entity.node.canonical', array('node' => $node));

        if (is_array($menu_link) && count($menu_link)) {
            $menu_link = reset($menu_link);
            if ($menu_link->getParent()) {
                $parents = $menu_link_manager->getParentIds($menu_link->getParent());
                $parent = reset($parents);

                $parent_menu_instance = $menu_link_manager->createInstance($parent);
                $parent_menu_plugin_def = $parent_menu_instance->getPluginDefinition();
                $parent_title = $parent_menu_instance->getTitle();
                $parent_node_id = $parent_menu_plugin_def['route_parameters']['node'];
                $parent_alias = \Drupal::service('path_alias.manager')->getAliasByPath("/node/" . $parent_node_id);
        
                if(!$isInside) {
                    return ('<a href="' . $parent_alias . '" class="sticky-menu__label" data-drupal-link-system-path="node/' . $parent_node_id . '"><span class="sticky-menu__label--inside">' . $parent_title . '</span></a>' );
                } else {
                    return ('<li class="sticky-menu__item sticky-menu__item--extra"><a href="' . $parent_alias . '" data-drupal-link-system-path="node/' . $parent_node_id . '">' . $parent_title . '</a></li>' );
                }
            }
        }
    }

}