<?php

namespace Drupal\search_api\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\Backend\BackendPluginManager;
use Drupal\search_api\Event\DeterminingServerFeaturesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   label_collection = @Translation("Search servers"),
 *   label_singular = @Translation("search server"),
 *   label_plural = @Translation("search servers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count search server",
 *     plural = "@count search servers",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\search_api\Entity\SearchApiConfigEntityStorage",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\ServerForm",
 *       "edit" = "Drupal\search_api\Form\ServerForm",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\ServerDisableConfirmForm",
 *       "clear" = "Drupal\search_api\Form\ServerClearConfirmForm",
 *     },
 *   },
 *   admin_permission = "administer search_api",
 *   config_prefix = "server",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "backend",
 *     "backend_config",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search-api/server/{search_api_server}",
 *     "add-form" = "/admin/config/search/search-api/add-server",
 *     "edit-form" = "/admin/config/search/search-api/server/{search_api_server}/edit",
 *     "delete-form" = "/admin/config/search/search-api/server/{search_api_server}/delete",
 *     "disable" = "/admin/config/search/search-api/server/{search_api_server}/disable",
 *     "enable" = "/admin/config/search/search-api/server/{search_api_server}/enable",
 *   }
 * )
 */
#[ConfigEntityType(
  id: 'search_api_server',
  label: new TranslatableMarkup('Search server'),
  label_collection: new TranslatableMarkup('Search servers'),
  label_singular: new TranslatableMarkup('search server'),
  label_plural: new TranslatableMarkup('search servers'),
  config_prefix: 'server',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'status' => 'status',
  ],
  handlers: [
    'storage' => 'Drupal\search_api\Entity\SearchApiConfigEntityStorage',
    'form' => [
      'default' => 'Drupal\search_api\Form\ServerForm',
      'edit' => 'Drupal\search_api\Form\ServerForm',
      'delete' => 'Drupal\search_api\Form\ServerDeleteConfirmForm',
      'disable' => 'Drupal\search_api\Form\ServerDisableConfirmForm',
      'clear' => 'Drupal\search_api\Form\ServerClearConfirmForm',
    ],
  ],
  links: [
    'canonical' => '/admin/config/search/search-api/server/{search_api_server}',
    'add-form' => '/admin/config/search/search-api/add-server',
    'edit-form' => '/admin/config/search/search-api/server/{search_api_server}/edit',
    'delete-form' => '/admin/config/search/search-api/server/{search_api_server}/delete',
    'disable' => '/admin/config/search/search-api/server/{search_api_server}/disable',
    'enable' => '/admin/config/search/search-api/server/{search_api_server}/enable',
  ],
  admin_permission: 'administer search_api',
  label_count: [
    'singular' => '@count search server',
    'plural' => '@count search servers',
  ],
  config_export: [
    'id',
    'name',
    'description',
    'backend',
    'backend_config',
  ],
)]
class Server extends ConfigEntityBase implements ServerInterface {

  use InstallingTrait;
  use LoggerTrait;

  /**
   * The ID of the server.
   *
   * @var string
   */
  protected $id;

  /**
   * The displayed name of the server.
   *
   * @var string
   */
  protected $name;

  /**
   * The displayed description of the server.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The ID of the backend plugin.
   *
   * @var string
   */
  protected $backend;

  /**
   * The backend plugin configuration.
   *
   * @var array
   */
  protected $backend_config = [];

  /**
   * The backend plugin instance.
   *
   * @var \Drupal\search_api\Backend\BackendInterface
   */
  protected $backendPlugin;

  /**
   * The features this server supports.
   *
   * @var string[]|null
   */
  protected $features;

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value): static {
    if ($property_name === 'backend_config') {
      $this->setBackendConfig($value);
      return $this;
    }

    parent::set($property_name, $value);

    // Make sure to reset the loaded backend plugin if the plugin ID changes.
    if ($property_name === 'backend') {
      $this->backendPlugin = NULL;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidBackend() {
    /** @noinspection PhpUnhandledExceptionInspection */
    return (bool) $this->backendPluginManager()->getDefinition($this->getBackendId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendId() {
    return $this->backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackend() {
    if (!$this->backendPlugin) {
      $backend_plugin_manager = $this->backendPluginManager();
      $config = $this->backend_config;
      $config['#server'] = $this;
      try {
        $this->backendPlugin = $backend_plugin_manager->createInstance($this->getBackendId(), $config);
      }
      catch (PluginException) {
        $backend_id = $this->getBackendId();
        $label = $this->label();
        throw new SearchApiException("The backend with ID '$backend_id' could not be retrieved for server '$label'.");
      }
    }
    return $this->backendPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendIfAvailable(): ?BackendInterface {
    try {
      return $this->hasValidBackend() ? $this->getBackend() : NULL;
    }
    catch (SearchApiException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendConfig() {
    return $this->backend_config;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Set backend config'), pluralize: FALSE)]
  public function setBackendConfig(array $backend_config) {
    $this->backend_config = $backend_config;
    try {
      // Update the backend plugin's configuration, also allowing it to react to
      // this change.
      if ($this->getBackend()->getConfiguration() !== $backend_config) {
        $this->getBackend()->setConfiguration($backend_config);
      }
    }
    catch (SearchApiException) {
      // Just ignore the exception in this instance and skip the call to
      // BackendPluginInterface::setConfiguration().
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexes(array $properties = []) {
    $storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    return $storage->loadByProperties(['server' => $this->id()] + $properties);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return $this->getBackendIfAvailable()?->viewSettings() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return (bool) $this->getBackendIfAvailable()?->isAvailable();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return in_array($feature, $this->getSupportedFeatures());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    if (!isset($this->features)) {
      $this->features = $this->getBackendIfAvailable()?->getSupportedFeatures() ?? [];
      $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use the "search_api.determining_server_features" event instead. See https://www.drupal.org/node/3059866';
      \Drupal::moduleHandler()
        ->alterDeprecated($description, 'search_api_server_features', $this->features, $this);
      /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher */
      $eventDispatcher = \Drupal::getContainer()->get('event_dispatcher');
      $eventDispatcher->dispatch(new DeterminingServerFeaturesEvent($this->features, $this), SearchApiEvents::DETERMINING_SERVER_FEATURES);
    }

    return $this->features;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    return (bool) $this->getBackendIfAvailable()?->supportsDataType($type);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    return $this->getBackendIfAvailable()?->getDiscouragedProcessors() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendDefinedFields(IndexInterface $index) {
    return $this->getBackendIfAvailable()?->getBackendDefinedFields($index) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');
    // When freshly adding an index to a server, it doesn't make any sense to
    // execute possible other tasks for that server/index combination.
    // (removeIndex() is implicit when adding an index which was already added.)
    $server_task_manager->delete($this, $index);

    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->addIndex($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = [
        '%server' => $this->label() ?? $this->id(),
        '%index' => $index->label() ?? $index->id(),
      ];
      $this->logException($e, '%type while adding index %index to server %server: @message in %function (line %line of %file).', $vars);
    }

    $task_manager = \Drupal::getContainer()
      ->get('search_api.task_manager');
    $task_manager->addTask(__FUNCTION__, $this, $index);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->updateIndex($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = [
        '%server' => $this->label() ?? $this->id(),
        '%index' => $index->label() ?? $index->id(),
      ];
      $this->logException($e, '%type while updating the fields of index %index on server %server: @message in %function (line %line of %file).', $vars);
    }

    $task_manager = \Drupal::getContainer()
      ->get('search_api.task_manager');
    $original = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2',
      fn () => $index->getOriginal(),
      fn () => $index->original ?? NULL,
    );
    $task_manager->addTask(__FUNCTION__, $this, $index, $original);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');
    // When removing an index from a server, it doesn't make any sense anymore
    // to delete items from it, or react to other changes.
    $server_task_manager->delete($this, $index);

    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->removeIndex($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = [
        '%server' => $this->label() ?? $this->id(),
        '%index' => is_object($index) ? ($index->label() ?? $index->id()) : $index,
      ];
      $this->logException($e, '%type while removing index %index from server %server: @message in %function (line %line of %file).', $vars);
    }

    $task_manager = \Drupal::getContainer()
      ->get('search_api.task_manager');
    $data = NULL;
    if (!is_object($index)) {
      $data = $index;
      $index = NULL;
    }
    $task_manager->addTask(__FUNCTION__, $this, $index, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');
    if ($server_task_manager->execute($this)) {
      return $this->getBackend()->indexItems($index, $items);
    }
    $index_label = $index->label();
    throw new SearchApiException("Could not index items on index '$index_label' because pending server tasks could not be executed.");
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids) {
    if ($index->isReadOnly()) {
      $vars = [
        '%index' => $index->label() ?? $index->id(),
      ];
      $this->getLogger()->warning('Trying to delete items from index %index which is marked as read-only.', $vars);
      return;
    }

    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteItems($index, $item_ids);
        // Clear search api list caches.
        Cache::invalidateTags(['search_api_list:' . $index->id()]);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = [
        '%server' => $this->label() ?? $this->id(),
      ];
      $this->logException($e, '%type while deleting items from server %server: @message in %function (line %line of %file).', $vars);
    }

    $task_manager = \Drupal::getContainer()
      ->get('search_api.task_manager');
    $task_manager->addTask(__FUNCTION__, $this, $index, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    if ($index->isReadOnly()) {
      $vars = [
        '%index' => $index->label() ?? $index->id(),
      ];
      $this->getLogger()->warning('Trying to delete items from index %index which is marked as read-only.', $vars);
      return;
    }

    $server_task_manager = \Drupal::getContainer()->get('search_api.server_task_manager');

    if (!$datasource_id) {
      // If we're deleting all items of the index, there's no point in keeping
      // any other "delete items" tasks.
      $types = [
        'deleteItems',
        'deleteAllIndexItems',
      ];
      $server_task_manager->delete($this, $index, $types);
    }

    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteAllIndexItems($index, $datasource_id);
        // Clear search api list caches.
        Cache::invalidateTags(['search_api_list:' . $index->id()]);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = [
        '%server' => $this->label() ?? $this->id(),
        '%index' => $index->label() ?? $index->id(),
      ];
      $this->logException($e, '%type while deleting items of index %index from server %server: @message in %function (line %line of %file).', $vars);
    }

    $task_manager = \Drupal::getContainer()
      ->get('search_api.task_manager');
    $task_manager->addTask(__FUNCTION__, $this, $index, $datasource_id);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems() {
    $failed = [];
    $properties['status'] = TRUE;
    $properties['read_only'] = FALSE;
    foreach ($this->getIndexes($properties) as $index) {
      try {
        $this->getBackend()->deleteAllIndexItems($index);
        Cache::invalidateTags(['search_api_list:' . $index->id()]);
      }
      catch (SearchApiException $e) {
        $args = [
          '%index' => $index->label() ?? $index->id(),
        ];
        $this->logException($e, '%type while deleting all items from index %index: @message in %function (line %line of %file).', $args);
        $failed[] = $index->label();
      }
    }
    if (!empty($e)) {
      $server_name = $this->label();
      $failed = implode(', ', $failed);
      throw new SearchApiException("Deleting all items from server '$server_name' failed for the following (write-enabled) indexes: $failed.", 0, $e);
    }

    $types = [
      'deleteItems',
      'deleteAllIndexItems',
    ];
    \Drupal::getContainer()
      ->get('search_api.server_task_manager')
      ->delete($this, NULL, $types);
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $this->getBackend()->search($query);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // The rest of the code only applies to updates.
    $original = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2',
      fn () => $this->getOriginal(),
      fn () => $this->original ?? NULL,
    );
    if (!$original) {
      return;
    }
    // Retrieve active config overrides for this server.
    $overrides = Utility::getConfigOverrides($this);

    // If there are overrides for the backend or its configuration, attempt to
    // apply them for the preUpdate() call.
    if (isset($overrides['backend']) || isset($overrides['backend_config'])) {
      $backend_config = $this->getBackendConfig();
      if (isset($overrides['backend_config'])) {
        $backend_config = $overrides['backend_config'];
      }
      $backend_id = $this->getBackendId();
      if (isset($overrides['backend'])) {
        $backend_id = $overrides['backend'];
      }
      $backend_plugin_manager = $this->backendPluginManager();
      $backend_config['#server'] = $this;
      if (!($backend = $backend_plugin_manager->createInstance($backend_id, $backend_config))) {
        $label = $this->label();
        throw new SearchApiException("The backend with ID '$backend_id' could not be retrieved for server '$label'.");
      }
    }
    else {
      $backend = $this->getBackend();
    }

    $backend->preUpdate();

    // If the server is being disabled, also disable all its indexes.
    if (!$this->isSyncing()
        && !$this->isInstallingFromExtension()
        && !isset($overrides['status'])
        && !$this->status()
        && $original->status()) {
      foreach ($this->getIndexes(['status' => TRUE]) as $index) {
        /** @var \Drupal\search_api\IndexInterface $index */
        $index->setStatus(FALSE)->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($update) {
      $reindexing_necessary = $this->getBackendIfAvailable()?->postUpdate();
      if ($reindexing_necessary) {
        foreach ($this->getIndexes() as $index) {
          $index->reindex();
        }
      }
    }
    else {
      $this->getBackendIfAvailable()?->postInsert();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // @todo This will, via Index::onDependencyRemoval(), remove all indexes
    //   from this server, triggering the server's removeIndex() method. This
    //   is, at best, wasted performance and could at worst lead to a bug if
    //   removeIndex() saves the server. We should try what happens when this is
    //   the case, whether there really is a bug, and try to fix it somehow –
    //   maybe clever detection of this case in removeIndex() or
    //   Index::postSave(). $server->isUninstalling() might help?
    parent::preDelete($storage, $entities);

    // Iterate through the servers, executing the backends' preDelete() methods
    // and removing all their pending server tasks.
    foreach ($entities as $server) {
      $server->getBackendIfAvailable()?->preDelete();
      \Drupal::getContainer()->get('search_api.server_task_manager')->delete($server);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add the backend's dependencies.
    if ($this->hasValidBackend()) {
      $this->calculatePluginDependencies($this->getBackendIfAvailable());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    $backend = $this->getBackendIfAvailable();
    if ($backend) {
      $removed_backend_dependencies = [];
      foreach ($backend->calculateDependencies() as $dependency_type => $list) {
        if (isset($dependencies[$dependency_type])) {
          $removed_backend_dependencies[$dependency_type] = array_intersect_key($dependencies[$dependency_type], array_flip($list));
        }
      }
      $removed_backend_dependencies = array_filter($removed_backend_dependencies);
      if ($removed_backend_dependencies) {
        if ($backend->onDependencyRemoval($removed_backend_dependencies)) {
          $this->backend_config = $backend->getConfiguration();
          $changed = TRUE;
        }
      }
    }

    return $changed;
  }

  /**
   * Retrieves the backend plugin manager.
   *
   * @return \Drupal\search_api\Backend\BackendPluginManager
   *   The backend plugin manager.
   */
  protected function backendPluginManager(): BackendPluginManager {
    return \Drupal::service('plugin.manager.search_api.backend');
  }

  /**
   * Implements the magic __clone() method.
   *
   * Prevents the backend plugin instance from being cloned.
   */
  public function __clone() {
    $this->backendPlugin = NULL;
  }

}
