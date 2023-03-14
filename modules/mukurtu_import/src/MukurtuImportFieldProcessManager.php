<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class MukurtuImportFieldProcessManager extends DefaultPluginManager {
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Archiver',
      $namespaces,
      $module_handler,
      'Drupal\Core\Archiver\ArchiverInterface',
      'Drupal\Core\Archiver\Annotation\Archiver'
    );
    $this->alterInfo('archiver_info');
    $this->setCacheBackend($cache_backend, 'archiver_info_plugins');
  }

}
