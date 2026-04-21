<?php

namespace Drupal\search_api\DataType;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiDataType as SearchApiDataTypeAnnotation;
use Drupal\search_api\Attribute\SearchApiDataType as SearchApiDataTypeAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages data type plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see \Drupal\search_api\DataType\DataTypePluginBase
 * @see plugin_api
 */
class DataTypePluginManager extends SearchApiPluginManager {

  /**
   * Static cache for the data type definitions.
   *
   * @var \Drupal\search_api\DataType\DataTypeInterface[]
   *
   * @see \Drupal\search_api\DataType\DataTypePluginManager::createInstance()
   * @see \Drupal\search_api\DataType\DataTypePluginManager::getInstances()
   */
  protected $dataTypes;

  /**
   * Whether all plugin instances have already been created.
   *
   * @var bool
   *
   * @see \Drupal\search_api\DataType\DataTypePluginManager::getInstances()
   */
  protected $allCreated = FALSE;

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct(
      'Plugin/search_api/data_type',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      DataTypeInterface::class,
      SearchApiDataTypeAttribute::class,
      SearchApiDataTypeAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_data_type');
    $this->alterInfo('search_api_data_type_info');
    $this->alterEvent(SearchApiEvents::GATHERING_DATA_TYPES);
  }

  /**
   * Creates or retrieves a data type plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   (optional) An array of configuration relevant to the plugin instance.
   *   Ignored for data type plugins.
   *
   * @return \Drupal\search_api\DataType\DataTypeInterface
   *   The requested data type plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if (empty($this->dataTypes[$plugin_id])) {
      $this->dataTypes[$plugin_id] = parent::createInstance($plugin_id, $configuration);
    }
    return $this->dataTypes[$plugin_id];
  }

  /**
   * Returns all known data types.
   *
   * @return \Drupal\search_api\DataType\DataTypeInterface[]
   *   An array of data type plugins, keyed by type identifier.
   */
  public function getInstances() {
    if (!$this->allCreated) {
      $this->allCreated = TRUE;
      if (!isset($this->dataTypes)) {
        $this->dataTypes = [];
      }

      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        if (class_exists($definition['class']) && empty($this->dataTypes[$plugin_id])) {
          $data_type = $this->createInstance($plugin_id);
          $this->dataTypes[$plugin_id] = $data_type;
        }
      }
    }

    return $this->dataTypes;
  }

  /**
   * Returns all field data types known by the Search API as an options list.
   *
   * @return string[]
   *   An associative array with all recognized types as keys, mapped to their
   *   translated display names.
   *
   * @see \Drupal\search_api\DataType\DataTypePluginManager::getInstances()
   */
  public function getInstancesOptions() {
    $types = [];
    foreach ($this->getInstances() as $id => $info) {
      $types[$id] = $info->label();
    }
    return $types;
  }

}
