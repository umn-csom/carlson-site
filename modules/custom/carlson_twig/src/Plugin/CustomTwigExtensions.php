<?php

namespace Drupal\carlson_twig\Plugin;

use Drupal\Component\Utility\Html;
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
                $parent_title = Html::escape((string) $parent_menu_instance->getTitle());

                // Link to the parent menu item's own destination so the section
                // link works whether the parent targets a node, a view, an
                // internal path or an external URL. Items with no destination
                // (<nolink>, <button>, <none>) have nothing to link to, so
                // render nothing rather than a broken "/node/" link.
                $url = $parent_menu_instance->getUrlObject();
                if ($url->isRouted() && in_array($url->getRouteName(), ['<nolink>', '<button>', '<none>'], TRUE)) {
                    return NULL;
                }
                $href = Html::escape($url->toString(TRUE)->getGeneratedUrl());

                // The active-link JS keys off data-drupal-link-system-path,
                // which only routed, internal links have.
                $system_path = (!$url->isExternal() && $url->isRouted()) ? $url->getInternalPath() : NULL;
                $data_attr = $system_path !== NULL ? ' data-drupal-link-system-path="' . Html::escape($system_path) . '"' : '';

                if (!$isInside) {
                    return '<a href="' . $href . '" class="sticky-menu__label"' . $data_attr . '><span class="sticky-menu__label--inside">' . $parent_title . '</span></a>';
                }
                return '<li class="sticky-menu__item sticky-menu__item--extra"><a href="' . $href . '"' . $data_attr . '>' . $parent_title . '</a></li>';
            }
        }
    }

}