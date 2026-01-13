<?php

namespace Drupal\mukurtu_dictionary;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\mukurtu_dictionary\DatabaseCompatibility\MukurtuDictionaryMySql;

/**
 * Service provider for Mukurtu Dictionary module.
 */
class MukurtuDictionaryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override the MySQL database compatibility service for Search API DB.
    if ($container->hasDefinition('mysql.search_api_db.database_compatibility')) {
      $definition = $container->getDefinition('mysql.search_api_db.database_compatibility');
      $definition->setClass(MukurtuDictionaryMySql::class);
    }
  }

}
