<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * MukurtuExport plugin manager.
 */
class MukurtuExporterPluginManager extends DefaultPluginManager {

  /**
   * Constructs MukurtuExporterPluginManager object.
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
      'Plugin/MukurtuExporter',
      $namespaces,
      $module_handler,
      'Drupal\mukurtu_export\MukurtuExporterInterface',
      'Drupal\mukurtu_export\Annotation\MukurtuExporter'
    );
    $this->alterInfo('mukurtu_exporter_info');
    $this->setCacheBackend($cache_backend, 'mukurtu_exporter_plugins');
  }

  public function getInstance(array $options) {
    $plugin_id = $options['id'] ?? NULL;
    $configuration = $options['configuration'] ?? [];

    return $this->createInstance($plugin_id, $configuration);
  }

}
