<?php

namespace Drupal\search_api\Plugin\search_api\datasource;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\external_entities\Entity\Query\External\Query as ExternalEntitiesQuery;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\search_api\Attribute\SearchApiDatasource;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Utility\Dependencies;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a datasource which exposes the content entities.
 */
#[SearchApiDatasource(
  id: 'entity',
  deriver: ContentEntityDeriver::class,
)]
class ContentEntity extends DatasourcePluginBase implements PluginFormInterface {

  use LoggerTrait;
  use PluginFormTrait;

  /**
   * The key for accessing last tracked ID information in site state.
   */
  protected const TRACKING_PAGE_STATE_KEY = 'search_api.datasource.entity.last_ids';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity memory cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $memoryCache;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|null
   */
  protected $entityFieldManager;

  /**
   * The entity display repository manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null
   */
  protected $entityDisplayRepository;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null
   */
  protected $entityTypeBundleInfo;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface|null
   */
  protected $typedDataManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface|null
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (($configuration['#index'] ?? NULL) instanceof IndexInterface) {
      $this->setIndex($configuration['#index']);
      unset($configuration['#index']);
    }

    // Since defaultConfiguration() depends on the plugin definition, we need to
    // override the constructor and set the definition property before calling
    // that method.
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $datasource->setDatabaseConnection($container->get('database'));
    $datasource->setEntityTypeManager($container->get('entity_type.manager'));
    $datasource->setEntityFieldManager($container->get('entity_field.manager'));
    $datasource->setEntityDisplayRepository($container->get('entity_display.repository'));
    $datasource->setEntityTypeBundleInfo($container->get('entity_type.bundle.info'));
    $datasource->setTypedDataManager($container->get('typed_data_manager'));
    $datasource->setConfigFactory($container->get('config.factory'));
    $datasource->setLanguageManager($container->get('language_manager'));
    $datasource->setFieldsHelper($container->get('search_api.fields_helper'));
    $datasource->setState($container->get('state'));
    $datasource->setEntityMemoryCache($container->get('entity.memory_cache'));
    $datasource->setLogger($container->get('logger.channel.search_api'));

    return $datasource;
  }

  /**
   * Retrieves the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  public function getDatabaseConnection(): Connection {
    return $this->database ?: \Drupal::database();
  }

  /**
   * Sets the database connection.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The new database connection.
   *
   * @return $this
   */
  public function setDatabaseConnection(Connection $connection): self {
    $this->database = $connection;
    return $this;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Retrieves the entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  protected function getEntityStorage() {
    return $this->getEntityTypeManager()->getStorage($this->getEntityTypeId());
  }

  /**
   * Returns the definition of this datasource's entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type definition.
   */
  protected function getEntityType() {
    return $this->getEntityTypeManager()
      ->getDefinition($this->getEntityTypeId());
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  public function getEntityFieldManager() {
    return $this->entityFieldManager ?: \Drupal::service('entity_field.manager');
  }

  /**
   * Sets the entity field manager.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The new entity field manager.
   *
   * @return $this
   */
  public function setEntityFieldManager(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
    return $this;
  }

  /**
   * Retrieves the entity display repository.
   *
   * @return \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   *   The entity entity display repository.
   */
  public function getEntityDisplayRepository() {
    return $this->entityDisplayRepository ?: \Drupal::service('entity_display.repository');
  }

  /**
   * Sets the entity display repository.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The new entity display repository.
   *
   * @return $this
   */
  public function setEntityDisplayRepository(EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->entityDisplayRepository = $entity_display_repository;
    return $this;
  }

  /**
   * Retrieves the entity display repository.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   The entity entity display repository.
   */
  public function getEntityTypeBundleInfo() {
    return $this->entityTypeBundleInfo ?: \Drupal::service('entity_type.bundle.info');
  }

  /**
   * Sets the entity type bundle info.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The new entity type bundle info.
   *
   * @return $this
   */
  public function setEntityTypeBundleInfo(EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    return $this;
  }

  /**
   * Retrieves the typed data manager.
   *
   * @return \Drupal\Core\TypedData\TypedDataManagerInterface
   *   The typed data manager.
   */
  public function getTypedDataManager() {
    return $this->typedDataManager ?: \Drupal::typedDataManager();
  }

  /**
   * Sets the typed data manager.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The new typed data manager.
   *
   * @return $this
   */
  public function setTypedDataManager(TypedDataManagerInterface $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
    return $this;
  }

  /**
   * Retrieves the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function getConfigFactory() {
    return $this->configFactory ?: \Drupal::configFactory();
  }

  /**
   * Retrieves the config value for a certain key in the Search API settings.
   *
   * @param string $key
   *   The key whose value should be retrieved.
   *
   * @return mixed
   *   The config value for the given key.
   */
  protected function getConfigValue($key) {
    return $this->getConfigFactory()->get('search_api.settings')->get($key);
  }

  /**
   * Sets the config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The new config factory.
   *
   * @return $this
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    return $this;
  }

  /**
   * Retrieves the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function getLanguageManager() {
    return $this->languageManager ?: \Drupal::languageManager();
  }

  /**
   * Sets the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The new language manager.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * Retrieves the fields helper.
   *
   * @return \Drupal\search_api\Utility\FieldsHelperInterface
   *   The fields helper.
   */
  public function getFieldsHelper() {
    return $this->fieldsHelper ?: \Drupal::service('search_api.fields_helper');
  }

  /**
   * Sets the fields helper.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The new fields helper.
   *
   * @return $this
   */
  public function setFieldsHelper(FieldsHelperInterface $fields_helper) {
    $this->fieldsHelper = $fields_helper;
    return $this;
  }

  /**
   * Retrieves the state service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The entity type manager.
   */
  public function getState() {
    return $this->state ?: \Drupal::state();
  }

  /**
   * Sets the state service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   *
   * @return $this
   */
  public function setState(StateInterface $state) {
    $this->state = $state;
    return $this;
  }

  /**
   * Retrieves the entity memory cache service.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface|null
   *   The memory cache, or NULL.
   */
  public function getEntityMemoryCache() {
    return $this->memoryCache;
  }

  /**
   * Sets the entity memory cache service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $memory_cache
   *   The memory cache.
   *
   * @return $this
   */
  public function setEntityMemoryCache(CacheBackendInterface $memory_cache) {
    $this->memoryCache = $memory_cache;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $type = $this->getEntityTypeId();
    $properties = $this->getEntityFieldManager()->getBaseFieldDefinitions($type);
    if ($bundles = array_keys($this->getBundles())) {
      foreach ($bundles as $bundle_id) {
        $properties += $this->getEntityFieldManager()->getFieldDefinitions($type, $bundle_id);
      }
    }
    // Exclude properties with custom storage, since we can't extract them
    // currently, due to a shortcoming of Core's Typed Data API. See #2695527.
    // Computed properties should mostly be OK, though, even though they still
    // count as having "custom storage". The "Path" field from the Core module
    // does not work, though, so we explicitly exclude it here to avoid
    // confusion.
    foreach ($properties as $key => $property) {
      if (!$property->isComputed() || $key === 'path') {
        if ($property->getFieldStorageDefinition()->hasCustomStorage()) {
          unset($properties[$key]);
        }
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $allowed_languages = $this->getLanguages();

    $entity_ids = [];
    foreach ($ids as $item_id) {
      $pos = strrpos($item_id, ':');
      // This can only happen if someone passes an invalid ID, since we always
      // include a language code. Still, no harm in guarding against bad input.
      if ($pos === FALSE) {
        continue;
      }
      $entity_id = substr($item_id, 0, $pos);
      $langcode = substr($item_id, $pos + 1);
      if (isset($allowed_languages[$langcode])) {
        $entity_ids[$entity_id][$item_id] = $langcode;
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $this->getEntityStorage()->loadMultiple(array_keys($entity_ids));
    $items = [];
    $allowed_bundles = $this->getBundles();
    foreach ($entity_ids as $entity_id => $langcodes) {
      if (empty($entities[$entity_id]) || !isset($allowed_bundles[$entities[$entity_id]->bundle()])) {
        continue;
      }
      foreach ($langcodes as $item_id => $langcode) {
        if ($entities[$entity_id]->hasTranslation($langcode)) {
          $items[$item_id] = $entities[$entity_id]->getTranslation($langcode)->getTypedData();
        }
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_configuration = [];

    if ($this->hasBundles()) {
      $default_configuration['bundles'] = [
        'default' => TRUE,
        'selected' => [],
      ];
    }

    if ($this->isTranslatable()) {
      $default_configuration['languages'] = [
        'default' => TRUE,
        'selected' => [],
      ];
    }

    return $default_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($this->hasBundles() && ($bundles = $this->getEntityBundleOptions())) {
      $form['bundles'] = [
        '#type' => 'details',
        '#title' => $this->t('Bundles'),
        '#open' => TRUE,
      ];
      $form['bundles']['default'] = [
        '#type' => 'radios',
        '#title' => $this->t('Which bundles should be indexed?'),
        '#options' => [
          0 => $this->t('Only those selected'),
          1 => $this->t('All except those selected'),
        ],
        '#default_value' => (int) $this->configuration['bundles']['default'],
      ];
      $form['bundles']['selected'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Bundles'),
        '#options' => $bundles,
        '#default_value' => $this->configuration['bundles']['selected'],
        '#size' => min(4, count($bundles)),
        '#multiple' => TRUE,
      ];
    }

    if ($this->isTranslatable()) {
      $form['languages'] = [
        '#type' => 'details',
        '#title' => $this->t('Languages'),
        '#open' => TRUE,
      ];
      $form['languages']['default'] = [
        '#type' => 'radios',
        '#title' => $this->t('Which languages should be indexed?'),
        '#options' => [
          0 => $this->t('Only those selected'),
          1 => $this->t('All except those selected'),
        ],
        '#default_value' => (int) $this->configuration['languages']['default'],
      ];
      $form['languages']['selected'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Languages'),
        '#options' => $this->getTranslationOptions(),
        '#default_value' => $this->configuration['languages']['selected'],
        '#multiple' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * Retrieves the available bundles of this entity type as an options list.
   *
   * @return array
   *   An associative array of bundle labels, keyed by the bundle name.
   */
  protected function getEntityBundleOptions() {
    $options = [];
    if (($bundles = $this->getEntityBundles())) {
      foreach ($bundles as $bundle => $bundle_info) {
        $options[$bundle] = Utility::escapeHtml($bundle_info['label']);
      }
    }
    return $options;
  }

  /**
   * Retrieves the available languages of this entity type as an options list.
   *
   * @return array
   *   An associative array of language labels, keyed by the language name.
   */
  protected function getTranslationOptions() {
    $options = [];
    foreach ($this->getLanguageManager()->getLanguages() as $language) {
      $options[$language->getId()] = $language->getName();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Filter out empty checkboxes.
    foreach (['bundles', 'languages'] as $key) {
      if ($form_state->hasValue($key)) {
        $parents = [$key, 'selected'];
        $value = $form_state->getValue($parents, []);
        $value = array_keys(array_filter($value));
        $form_state->setValue($parents, $value);
      }
    }

    // Make sure not to overwrite any options not included in the form (like
    // "disable_db_tracking") by adding any existing configuration back to the
    // new values.
    $this->setConfiguration($form_state->getValues() + $this->configuration);
  }

  /**
   * Retrieves the entity from a search item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object represented by that item, or NULL if none could be
   *   found.
   */
  protected function getEntity(ComplexDataInterface $item) {
    $value = $item->getValue();
    return $value instanceof EntityInterface ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    if ($entity = $this->getEntity($item)) {
      $langcode = $entity->language()->getId();
      if (isset($this->getBundles()[$entity->bundle()])
          && isset($this->getLanguages()[$langcode])) {
        return static::formatItemId($entity->getEntityTypeId(), $entity->id(), $langcode);
      }
    }
    return NULL;
  }

  /**
   * Computes the item ID for the given entity and language.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string|int $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The language ID of the entity.
   *
   * @return string
   *   The datasource-specific item ID.
   */
  public static function formatItemId(string $entity_type, string|int $entity_id, string $langcode): string {
    return $entity_id . ':' . $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item) {
    if ($entity = $this->getEntity($item)) {
      return $entity->label();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemBundle(ComplexDataInterface $item) {
    if ($entity = $this->getEntity($item)) {
      return $entity->bundle();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item) {
    if ($entity = $this->getEntity($item)) {
      if ($entity->hasLinkTemplate('canonical')) {
        return $entity->toUrl('canonical');
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemAccessResult(ComplexDataInterface $item, ?AccountInterface $account = NULL) {
    $entity = $this->getEntity($item);
    if ($entity) {
      return $this->getEntityTypeManager()
        ->getAccessControlHandler($this->getEntityTypeId())
        ->access($entity, 'view', $account, TRUE);
    }
    return AccessResult::neutral('Item is not an entity, so cannot check access');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemIds($page = NULL) {
    return $this->getPartialItemIds($page);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['entity_type'];
  }

  /**
   * Determines whether the entity type supports bundles.
   *
   * @return bool
   *   TRUE if the entity type supports bundles, FALSE otherwise.
   */
  protected function hasBundles() {
    return $this->getEntityType()->hasKey('bundle');
  }

  /**
   * Determines whether the entity type supports translations.
   *
   * @return bool
   *   TRUE if the entity is translatable, FALSE otherwise.
   */
  protected function isTranslatable() {
    return $this->getEntityType()->isTranslatable();
  }

  /**
   * Retrieves all bundles of this datasource's entity type.
   *
   * @return array
   *   An associative array of bundle infos, keyed by the bundle names.
   */
  protected function getEntityBundles() {
    return $this->hasBundles() ? $this->getEntityTypeBundleInfo()->getBundleInfo($this->getEntityTypeId()) : [];
  }

  /**
   * Retrieves all item IDs of entities of the specified bundles.
   *
   * @param int|null $page
   *   The zero-based page of IDs to retrieve, for the paging mechanism
   *   implemented by this datasource; or NULL to retrieve all items at once.
   * @param string[]|null $bundles
   *   (optional) The bundles for which all item IDs should be returned; or NULL
   *   to retrieve IDs from all enabled bundles in this datasource.
   * @param string[]|null $languages
   *   (optional) The languages for which all item IDs should be returned; or
   *   NULL to retrieve IDs from all enabled languages in this datasource.
   *
   * @return string[]|null
   *   An array of all item IDs matching these conditions; or NULL if a page was
   *   specified and there are no more items for that and all following pages.
   *   In case both bundles and languages are specified, they are combined with
   *   OR.
   */
  public function getPartialItemIds($page = NULL, ?array $bundles = NULL, ?array $languages = NULL) {
    // These would be pretty pointless calls, but for the sake of completeness
    // we should check for them and return early. (Otherwise makes the rest of
    // the code more complicated.)
    if (($bundles === [] && !$languages) || ($languages === [] && !$bundles)) {
      return NULL;
    }

    $entity_type = $this->getEntityType();
    $entity_id = $entity_type->getKey('id');

    // Use a direct database query when an entity has a defined base table. This
    // should prevent performance issues associated with the use of entity query
    // on large data sets. This allows for better control over what tables are
    // included in the query.
    // If no base table is present, then perform an entity query instead.
    if ($entity_type->getBaseTable()
        && empty($this->configuration['disable_db_tracking'])) {
      $select = $this->getDatabaseConnection()
        ->select($entity_type->getBaseTable(), 'base_table')
        ->fields('base_table', [$entity_id]);
    }
    else {
      $select = $this->getEntityTypeManager()
        ->getStorage($this->getEntityTypeId())
        ->getQuery();
      // When tracking items, we never want access checks.
      $select->accessCheck(FALSE);
    }

    // Build up the context for tracking the last ID for this batch page.
    $batch_page_context = [
      'index_id' => $this->getIndex()->id(),
      // The derivative plugin ID includes the entity type ID.
      'datasource_id' => $this->getPluginId(),
      'bundles' => $bundles,
      'languages' => $languages,
    ];
    $context_key = Crypt::hashBase64(serialize($batch_page_context));
    $last_ids = $this->getState()->get(self::TRACKING_PAGE_STATE_KEY, []);

    // We want to determine all entities of either one of the given bundles OR
    // one of the given languages. That means we can't just filter for $bundles
    // if $languages is given. Instead, we have to filter for all bundles we
    // might want to include and later sort out those for which we want only the
    // translations in $languages and those (matching $bundles) where we want
    // all (enabled) translations.
    if ($this->hasBundles()) {
      $bundle_property = $entity_type->getKey('bundle');
      if ($bundles && !$languages) {
        $select->condition($bundle_property, $bundles, 'IN');
      }
      else {
        $enabled_bundles = array_keys($this->getBundles());
        // Since this is also called for removed bundles/languages,
        // $enabled_bundles might not include $bundles.
        if ($bundles) {
          $enabled_bundles = array_unique(array_merge($bundles, $enabled_bundles));
        }
        if (count($enabled_bundles) < count($this->getEntityBundles())) {
          $select->condition($bundle_property, $enabled_bundles, 'IN');
        }
      }
    }

    if (isset($page)) {
      $page_size = $this->getConfigValue('tracking_page_size');
      assert($page_size, 'Tracking page size is not set.');

      // If known, use a condition on the last tracked ID for paging instead of
      // the offset, for performance reasons on large sites.
      $offset = $page * $page_size;
      if ($page > 0) {
        // We only handle the case of picking up from where the last page left
        // off. (This will cause an infinite loop if anyone ever wants to index
        // Search API tasks in an index, so check for that to be on the safe
        // side. Also, the external_entities module doesn't reliably support
        // conditions on entity queries, so disable this functionality in that
        // case, too.)
        if (isset($last_ids[$context_key])
            && $last_ids[$context_key]['page'] == ($page - 1)
            && $this->getEntityTypeId() !== 'search_api_task'
            && !($select instanceof ExternalEntitiesQuery)) {
          $select->condition($entity_id, $last_ids[$context_key]['last_id'], '>');
          $offset = 0;
        }
      }
      $select->range($offset, $page_size);

      // For paging to reliably work, a sort should be present.
      if ($select instanceof SelectInterface) {
        $select->orderBy($entity_id);
      }
      else {
        $select->sort($entity_id);
      }
    }

    if ($select instanceof SelectInterface) {
      $entity_ids = $select->execute()->fetchCol();
    }
    else {
      $entity_ids = $select->execute();
    }

    if (!$entity_ids) {
      if (isset($page)) {
        // Clean up state tracking of last ID.
        unset($last_ids[$context_key]);
        $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
      }
      return NULL;
    }

    // Remember the last tracked ID for the next call.
    if (isset($page)) {
      $last_ids[$context_key] = [
        'page' => (int) $page,
        'last_id' => end($entity_ids),
      ];
      $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
    }

    // For all loaded entities, compute all their item IDs (one for each
    // translation we want to include). For those matching the given bundles (if
    // any), we want to include translations for all enabled languages. For all
    // other entities, we just want to include the translations for the
    // languages passed to the method (if any).
    $item_ids = [];
    $enabled_languages = array_keys($this->getLanguages());
    // As above for bundles, $enabled_languages might not include $languages.
    if ($languages) {
      $enabled_languages = array_unique(array_merge($languages, $enabled_languages));
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($this->getEntityStorage()->loadMultiple($entity_ids) as $entity_id => $entity) {
      $translations = array_keys($entity->getTranslationLanguages());
      $translations = array_intersect($translations, $enabled_languages);
      // If only languages were specified, keep only those translations matching
      // them. If bundles were also specified, keep all (enabled) translations
      // for those entities that match those bundles.
      if ($languages !== NULL
          && (!$bundles || !in_array($entity->bundle(), $bundles))) {
        $translations = array_intersect($translations, $languages);
      }
      foreach ($translations as $langcode) {
        $item_ids[] = static::formatItemId($entity->getEntityTypeId(), $entity_id, $langcode);
      }
    }

    if (Utility::isRunningInCli()) {
      // When running in the CLI, this might be executed for all entities from
      // within a single process. To avoid running out of memory, reset the
      // static cache after each batch.
      $this->getEntityMemoryCache()->deleteAll();
    }

    return $item_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    if (!$this->hasBundles()) {
      // For entity types that have no bundle, return a default pseudo-bundle.
      return [$this->getEntityTypeId() => $this->label()];
    }

    $configuration = $this->getConfiguration();

    // If "default" is TRUE (that is, "All except those selected"),remove all
    // the selected bundles from the available ones to compute the indexed
    // bundles. Otherwise, return all the selected bundles.
    $bundles = [];
    $entity_bundles = $this->getEntityBundles();
    $selected_bundles = array_flip($configuration['bundles']['selected']);
    $function = $configuration['bundles']['default'] ? 'array_diff_key' : 'array_intersect_key';
    $entity_bundles = $function($entity_bundles, $selected_bundles);
    foreach ($entity_bundles as $bundle_id => $bundle_info) {
      $bundles[$bundle_id] = $bundle_info['label'] ?? $bundle_id;
    }
    return $bundles ?: [$this->getEntityTypeId() => $this->label()];
  }

  /**
   * Retrieves the enabled languages, including "not applicable/specified".
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   All languages that should be processed for this datasource, keyed by
   *   language code.
   */
  protected function getLanguages() {
    $all_languages = $this->getLanguageManager()
      ->getLanguages(LanguageInterface::STATE_ALL);

    if ($this->isTranslatable()) {
      $selected_languages = array_flip($this->configuration['languages']['selected']);
      if ($this->configuration['languages']['default']) {
        return array_diff_key($all_languages, $selected_languages);
      }
      else {
        $returned_languages = array_intersect_key($all_languages, $selected_languages);

        // We always want to include entities with unknown language.
        $not_specified = LanguageInterface::LANGCODE_NOT_SPECIFIED;
        $not_applicable = LanguageInterface::LANGCODE_NOT_APPLICABLE;
        $returned_languages[$not_specified] = $all_languages[$not_specified];
        $returned_languages[$not_applicable] = $all_languages[$not_applicable];

        return $returned_languages;
      }
    }

    return $all_languages;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes($bundle = NULL) {
    return $this->getEntityDisplayRepository()
      ->getViewModeOptions($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL) {
    try {
      if ($entity = $this->getEntity($item)) {
        $langcode = $langcode ?: $entity->language()->getId();
        return $this->getEntityTypeManager()->getViewBuilder($this->getEntityTypeId())->view($entity, $view_mode, $langcode);
      }
    }
    catch (\Exception) {
      // The most common reason for this would be a
      // \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException in
      // getViewBuilder(), because the entity type definition doesn't specify a
      // view_builder class.
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL) {
    try {
      $view_builder = $this->getEntityTypeManager()
        ->getViewBuilder($this->getEntityTypeId());
      // Langcode passed, use that for viewing.
      if (isset($langcode)) {
        $entities = [];
        foreach ($items as $i => $item) {
          if ($entity = $this->getEntity($item)) {
            $entities[$i] = $entity;
          }
        }
        if ($entities) {
          return $view_builder->viewMultiple($entities, $view_mode, $langcode);
        }
        return [];
      }
      // Otherwise, separate the items by language, keeping the keys.
      $items_by_language = [];
      foreach ($items as $i => $item) {
        if ($entity = $this->getEntity($item)) {
          $items_by_language[$entity->language()->getId()][$i] = $entity;
        }
      }
      // Then build the items for each language. We initialize $build beforehand
      // and use array_replace() to add to it so the order stays the same.
      $build = array_fill_keys(array_keys($items), []);
      foreach ($items_by_language as $langcode => $language_items) {
        $build = array_replace($build, $view_builder->viewMultiple($language_items, $view_mode, $langcode));
      }
      return $build;
    }
    catch (\Exception) {
      // The most common reason for this would be a
      // \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException in
      // getViewBuilder(), because the entity type definition doesn't specify a
      // view_builder class.
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    $this->addDependency('module', $this->getEntityType()->getProvider());

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDependencies(array $fields) {
    $dependencies = [];
    $properties = $this->getPropertyDefinitions();

    foreach ($fields as $field_id => $property_path) {
      $dependencies[$field_id] = $this->getPropertyPathDependencies($property_path, $properties);
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function canContainEntityReferences(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAffectedItemsForEntityChange(EntityInterface $entity, array $foreign_entity_relationship_map, ?EntityInterface $original_entity = NULL): array {
    if (!($entity instanceof ContentEntityInterface)) {
      return [];
    }

    $ids_to_reindex = [];
    $path_separator = IndexInterface::PROPERTY_PATH_SEPARATOR;
    foreach ($foreign_entity_relationship_map as $relation_info) {
      // Ignore relationships belonging to other datasources.
      if (!empty($relation_info['datasource'])
          && $relation_info['datasource'] !== $this->getPluginId()) {
        continue;
      }
      // Check whether entity type and (if specified) bundles match the entity.
      if ($relation_info['entity_type'] !== $entity->getEntityTypeId()) {
        continue;
      }
      if (!empty($relation_info['bundles'])
          && !in_array($entity->bundle(), $relation_info['bundles'])) {
        continue;
      }
      // Maybe this entity belongs to a bundle that does not have this field
      // attached. Hence we have this check to ensure the field is present on
      // this particular entity.
      if (!$entity->hasField($relation_info['field_name'])) {
        continue;
      }

      $items = $entity->get($relation_info['field_name']);

      // We trigger re-indexing if either it is a removed entity or the
      // entity has changed its field value (in case it's an update).
      if (!$original_entity || !$items->equals($original_entity->get($relation_info['field_name']))) {
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeId())
          ->getQuery();
        $query->accessCheck(FALSE);

        // Luckily, to translate from property path to the entity query
        // condition syntax, all we have to do is replace the property path
        // separator with the entity query path separator (a dot) and that's it.
        $property_path = $relation_info['property_path_to_foreign_entity'];
        $property_path = str_replace($path_separator, '.', $property_path);
        $query->condition($property_path, $entity->id());

        try {
          $entity_ids = array_values($query->execute());
        }
        // @todo Switch back to \Exception once Core bug #2893747 is fixed.
        catch (\Throwable $e) {
          // We don't want to catch all PHP \Error objects thrown, but just the
          // ones caused by #2893747.
          if (!($e instanceof \Exception)
              && (get_class($e) !== \Error::class || $e->getMessage() !== 'Call to a member function getColumns() on bool')) {
            throw $e;
          }
          $vars = [
            '%index' => $this->index->label() ?? $this->index->id(),
            '%entity_type' => $entity->getEntityType()->getLabel(),
            '@entity_id' => $entity->id(),
          ];
          try {
            $link = $entity->toLink($this->t('Go to changed %entity_type with ID "@entity_id"', $vars))
              ->toString()->getGeneratedLink();
          }
          catch (\Throwable) {
            // Ignore any errors here, it's not that important that the log
            // message contains a link.
            $link = NULL;
          }
          $this->logException($e, '%type while attempting to find indexed entities referencing changed %entity_type with ID "@entity_id" for index %index: @message in %function (line %line of %file).', $vars, RfcLogLevel::ERROR, $link);
          continue;
        }
        foreach ($entity_ids as $entity_id) {
          foreach ($this->getLanguages() as $language) {
            $ids_to_reindex[static::formatItemId($this->getEntityTypeId(), $entity_id, $language->getId())] = 1;
          }
        }
      }
    }
    $ids_to_reindex = array_keys($ids_to_reindex);

    if ($ids_to_reindex) {
      $combined_ids = array_map($this->createCombinedId(...), $ids_to_reindex);
      $this->getIndex()->registerUnreliableItemIds($combined_ids);
    }

    return $ids_to_reindex;
  }

  /**
   * Computes all dependencies of the given property path.
   *
   * @param string $property_path
   *   The property path of the property.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The properties which form the basis for the property path.
   *
   * @return string[][]
   *   An associative array with the dependencies for the given property path,
   *   mapping dependency types to arrays of dependency names.
   */
  protected function getPropertyPathDependencies($property_path, array $properties) {
    [$key, $nested_path] = Utility::splitPropertyPath($property_path, FALSE);
    if (!isset($properties[$key])) {
      return [];
    }

    $dependencies = new Dependencies();
    $property = $properties[$key];
    if ($property instanceof FieldConfigInterface) {
      $storage = $property->getFieldStorageDefinition();
      if ($storage instanceof FieldStorageConfigInterface) {
        $name = $storage->getConfigDependencyName();
        $dependencies->addDependency($storage->getConfigDependencyKey(), $name);
      }
    }

    // The field might be provided by a module which is not the provider of the
    // entity type, therefore we need to add a dependency on that module.
    if ($property instanceof FieldStorageDefinitionInterface) {
      $dependencies->addDependency('module', $property->getProvider());
    }

    $property = $this->getFieldsHelper()->getInnerProperty($property);

    if ($property instanceof EntityDataDefinitionInterface) {
      $entity_type_definition = $this->getEntityTypeManager()
        ->getDefinition($property->getEntityTypeId());
      if ($entity_type_definition) {
        $module = $entity_type_definition->getProvider();
        $dependencies->addDependency('module', $module);
      }
    }

    if ($nested_path !== NULL
        && $property instanceof ComplexDataDefinitionInterface) {
      $nested = $this->getFieldsHelper()->getNestedProperties($property);
      $nested_dependencies = $this->getPropertyPathDependencies($nested_path, $nested);
      $dependencies->addDependencies($nested_dependencies);
    }

    return $dependencies->toArray();
  }

  /**
   * Retrieves all indexes that are configured to index the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to check.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   All indexes that are configured to index the given entity (using this
   *   datasource class).
   */
  public static function getIndexesForEntity(ContentEntityInterface $entity) {
    return \Drupal::getContainer()
      ->get('search_api.entity_datasource.tracking_manager')
      ->getIndexesForEntity($entity);
  }

  /**
   * Filters a set of datasource-specific item IDs.
   *
   * Returns only those item IDs that are valid for the given datasource and
   * index. This method only checks the item language, though – whether an
   * entity with that ID actually exists, or whether it has a bundle included
   * for that datasource, is not verified.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to validate.
   * @param string $datasource_id
   *   The ID of the datasource on the index for which to validate.
   * @param string[] $item_ids
   *   The item IDs to be validated.
   *
   * @return string[]
   *   All given item IDs that are valid for that index and datasource.
   *
   * @deprecated in search_api:8.x-1.22 and is removed from search_api:2.0.0.
   *   Use
   *   \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::filterValidItemIds()
   *   instead.
   *
   * @see https://www.drupal.org/node/3257943
   */
  public static function filterValidItemIds(IndexInterface $index, $datasource_id, array $item_ids) {
    @trigger_error('\Drupal\search_api\Plugin\search_api\datasource\ContentEntity::filterValidItemIds() is deprecated in search_api:8.x-1.21 and is removed from search_api:2.0.0. Use \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::filterValidItemIds() instead. See https://www.drupal.org/node/3257943', E_USER_DEPRECATED);
    return ContentEntityTrackingManager::filterValidItemIds($index, $datasource_id, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getListCacheContexts() {
    $contexts = parent::getListCacheContexts();
    $entity_list_contexts = $this->getEntityType()->getListCacheContexts();
    return Cache::mergeContexts($entity_list_contexts, $contexts);
  }

}
