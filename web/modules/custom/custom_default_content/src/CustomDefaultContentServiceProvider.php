<?php

namespace Drupal\custom_default_content;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class CustomDefaultContentServiceProvider
 * @package Drupal\custom_default_content
 */
class CustomDefaultContentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides file entity normalizer class to verify if file exists in locale instead of throw "ClientException".
    $definition = $container->getDefinition('serializer.normalizer.file_entity.hal');
    $definition->setClass('Drupal\custom_default_content\FileEntityNormalizer');

    // Overrides default content importer class for manage field collection item host.
    $definition = $container->getDefinition('default_content.importer');
    $definition->setClass('Drupal\custom_default_content\Importer');
  }
}
