<?php

namespace Drupal\carlson_general;
use Symfony\Component\DependencyInjection\Reference;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class CarlsonGeneralServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('plugin.manager.ckeditor_template');
    $definition->setClass('Drupal\carlson_general\CustomCkeditorTemplatePluginManager');
    $definition->addArgument(new Reference('current_user'));
  }

}
