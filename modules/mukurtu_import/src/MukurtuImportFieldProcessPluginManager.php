<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * MukurtuImportFieldProcess plugin manager.
 */
class MukurtuImportFieldProcessPluginManager extends DefaultPluginManager {

  /**
   * Constructs MukurtuImportFieldProcessPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/MukurtuImportFieldProcess',
      $namespaces,
      $module_handler,
      'Drupal\mukurtu_import\MukurtuImportFieldProcessInterface',
      'Drupal\mukurtu_import\Annotation\MukurtuImportFieldProcess'
    );
    $this->alterInfo('mukurtu_import_field_process_info');
    $this->setCacheBackend($cache_backend, 'mukurtu_import_field_process_plugins');
  }

  public function getInstance(array $options) {
    $configuration = $options['configuration'] ?? [];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $options['field_definition'];
    $field_type = $field_definition->getType();
    $plugin_definitions = $this->getDefinitions();
    $instance_definition = NULL;
    foreach ($plugin_definitions as $definition) {
      if (!in_array($field_type, $definition['field_types'])) {
        continue;
      }

      if ($definition['class']::isApplicable($field_definition)) {
        if (!$instance_definition || $instance_definition['weight'] > $definition['weight']) {
          $instance_definition = $definition;
        }
      }
    }

    $plugin_id = $instance_definition ? $instance_definition['id'] : 'default';

    return $this->createInstance($plugin_id, $configuration);
  }

}
