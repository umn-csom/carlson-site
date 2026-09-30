<?php

namespace Drupal\critical_css\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for critical_css.
 */
class CriticalCssHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_help().
     */
    #[Hook('help')]
    public function help($route_name, \Drupal\Core\Routing\RouteMatchInterface $route_match)
    {
        switch ($route_name) {
            case 'help.page.critical_css':
                $output = '<h3>' . $this->t('About') . '</h3>';
                $output .= '<p>' . $this->t('The Critical CSS module embeds critical CSS file into a page\'s HTML head, and loads the rest of non-critical CSS asynchronously. For more information, see the <a href=":documentation">online documentation for the Critical CSS module</a>.', [
                    ':documentation' => 'https://www.drupal.org/project/critical_css',
                ]) . '</p>';
                $output .= '<h3>' . $this->t('Uses') . '</h3>';
                $output .= '<dl>';
                $output .= '<dt>' . $this->t('Speeding up your site') . '</dt>';
                $output .= '<dd>' . $this->t('Administrators can define, on the <a href=":config_page">Critical CSS</a> page where this module should look when trying to find a matching critical CSS files. There are some options that alter that search to accommodate it to your needs.', [
                    ':config_page' => "/admin/config/development/performance/critical-css",
                ]) . '</dd>';
                $output .= '</dl>';
                return $output;
        }
    }
}
