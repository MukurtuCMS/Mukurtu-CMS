<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\config_test\TestInstallStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Provides tests for Solr related drupal configs.
 *
 * @group search_api_solr
 */
class SolrConfigsSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Tests all available Solr related configs against the schema.
   */
  public function testDefaultConfig() {
    // Create a typed config manager with access to configuration schema in
    // every module, profile and theme.
    $typed_config = new TypedConfigManager(
      \Drupal::service('config.storage'),
      new TestInstallStorage(InstallStorage::CONFIG_SCHEMA_DIRECTORY),
      \Drupal::service('cache.discovery'),
      \Drupal::service('module_handler'),
      \Drupal::service('class_resolver')
    );
    $typed_config->setValidationConstraintManager(\Drupal::service('validation.constraint'));
    // Avoid restricting to the config schemas discovered.
    $this->container->get('cache.discovery')->delete('typed_config_definitions');

    // Scan all modules and sub-modules for search_api_solr.* configs.
    $install_storage = new TestInstallStorage(InstallStorage::CONFIG_INSTALL_DIRECTORY);
    $install_configs = $install_storage->listAll('search_api_solr');
    foreach ($install_configs as $config_name) {
      $data = $install_storage->read($config_name);
      $this->assertConfigSchema($typed_config, $config_name, $data);
    }

    $optional_storage = new TestInstallStorage(InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
    $optional_configs = $optional_storage->listAll('search_api_solr');
    foreach ($optional_configs as $config_name) {
      $data = $optional_storage->read($config_name);
      $this->assertConfigSchema($typed_config, $config_name, $data);
    }

    $this->assertGreaterThan(100, count($install_configs + $optional_configs));
  }

}
