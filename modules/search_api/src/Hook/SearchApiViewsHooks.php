<?php

namespace Drupal\search_api\Hook;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Event\MappingViewsFieldHandlersEvent;
use Drupal\search_api\Event\MappingViewsHandlersEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\views\argument\SearchApiAllTerms;
use Drupal\search_api\Plugin\views\argument\SearchApiTerm as TermArgument;
use Drupal\search_api\Plugin\views\filter\SearchApiTerm as TermFilter;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contains Views hook implementations for the Search API module.
 */
class SearchApiViewsHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityTypeFieldManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected ModuleHandlerInterface $moduleHandler,
    protected FieldsHelperInterface $fieldsHelper,
    #[Autowire(service: 'plugin.manager.search_api.data_type')]
    protected PluginManagerInterface $dataTypePluginManager,
    #[Autowire(service: 'logger.channel.search_api')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_views_data().
   *
   * For each search index, we provide the following tables:
   * - One base table, with key "search_api_index_INDEX", which contains field,
   *   filter, argument and sort handlers for all indexed fields. (Field handlers,
   *   too, to allow things like click-sorting.)
   * - Tables for each datasource, by default with key
   *   "search_api_datasource_INDEX_DATASOURCE", with field and (where applicable)
   *   relationship handlers for each property of the datasource. Those will be
   *   joined to the index base table by default.
   *
   * Also, for each entity type encountered in any table, a table with
   * field/relationship handlers for all of that entity type's properties is
   * created. Those tables will use the key "search_api_entity_ENTITY".
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];

    try {
      /** @var \Drupal\search_api\IndexInterface[] $indexes */
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (PluginException) {
      return [];
    }
    foreach ($indexes as $index) {
      try {
        // Fill in base data.
        $key = 'search_api_index_' . $index->id();
        $table = &$data[$key];
        $index_label = $index->label();
        $table['table']['group'] = t('Index @name', ['@name' => $index_label]);
        $table['table']['base'] = [
          'field' => 'search_api_id',
          'index' => $index->id(),
          'title' => t('Index @name', ['@name' => $index_label]),
          'help' => t('Use the @name search index for filtering and retrieving data.', ['@name' => $index_label]),
          'query_id' => 'search_api_query',
        ];

        // Add suitable handlers for all indexed fields.
        foreach ($index->getFields(TRUE) as $field_id => $field) {
          $field_alias = $this->findFieldAlias($field_id, $table);
          $field_definition = $this->getHandlers($field);
          // The field handler has to be extra, since it is a) determined by the
          // field's underlying property and b) needs a different "real field"
          // set.
          if ($field->getPropertyPath()) {
            $field_handler = $this->getFieldHandlerForProperty($field->getDataDefinition(), $field->getPropertyPath());
            if ($field_handler) {
              $field_definition['field'] = $field_handler;
              $field_definition['field']['real field'] = $field->getCombinedPropertyPath();
              $field_definition['field']['search_api field'] = $field_id;
            }
          }
          if ($field_definition) {
            $field_label = $field->getLabel();
            $field_definition += [
              'title' => $field_label,
              'help' => $field->getDescription() ?: t('(No description available)'),
            ];
            if ($datasource = $field->getDatasource()) {
              $field_definition['group'] = t('@datasource datasource', ['@datasource' => $datasource->label()]);
            }
            else {
              // Backend defined fields that don't have a datasource should be
              // treated like special fields.
              $field_definition['group'] = t('Search');
            }
            if ($field_id != $field_alias) {
              $field_definition['real field'] = $field_id;
            }
            if (isset($field_definition['field'])) {
              $field_definition['field']['title'] = t('@field (indexed field)', ['@field' => $field_label]);
            }
            $table[$field_alias] = $field_definition;
          }
        }

        // Add special fields.
        $this->addSpecialFields($table, $index);

        // Add relationships for field data of all datasources.
        $datasource_tables_prefix = 'search_api_datasource_' . $index->id() . '_';
        foreach ($index->getDatasources() as $datasource_id => $datasource) {
          $table_key = $this->findFieldAlias($datasource_tables_prefix . $datasource_id, $data);
          $data[$table_key] = $this->createDatasourceTable($datasource, $data);
          // Automatically join this table for views of this index.
          $data[$table_key]['table']['join'][$key] = [
            'join_id' => 'search_api',
          ];
        }
      }
      catch (\Exception $e) {
        $args = [
          '%index' => $index->label(),
        ];
        Error::logException($this->logger, $e, '%type while computing Views data for index %index: @message in %function (line %line of %file).', $args);
      }
    }

    return array_filter($data);
  }

  /**
   * Implements hook_views_plugins_argument_alter().
   */
  #[Hook('views_plugins_argument_alter')]
  public function argumentPluginsAlter(array &$plugins): void {
    // We have to include the term argument handler like this, since adding it
    // directly (i.e., with an annotation) would cause fatal errors on sites
    // without the Taxonomy module.
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $plugins['search_api_term'] = [
        'plugin_type' => 'argument',
        'id' => 'search_api_term',
        'class' => TermArgument::class,
        'provider' => 'search_api',
      ];
      $plugins['search_api_all_terms'] = [
        'plugin_type' => 'argument',
        'id' => 'search_api_all_terms',
        'class' => SearchApiAllTerms::class,
        'provider' => 'search_api',
      ];
    }
  }

  /**
   * Implements hook_views_plugins_cache_alter().
   */
  #[Hook('views_plugins_cache_alter')]
  public function cachePluginsAlter(array &$plugins): void {
    try {
      /** @var \Drupal\search_api\IndexInterface[] $indexes */
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (PluginException) {
      return;
    }

    // Collect all base tables provided by this module.
    $bases = [];
    foreach ($indexes as $index) {
      $bases[] = 'search_api_index_' . $index->id();
    }

    // If no search indexes are defined yet, declare a dummy index as the base
    // table. This will make sure our plugins do not become available for Views
    // that are not based on search indexes.
    if (!$bases) {
      $bases = ['search_api_index_dummy'];
    }

    $plugins['search_api_none']['base'] = $bases;
    $plugins['search_api_tag']['base'] = $bases;
    $plugins['search_api_time']['base'] = $bases;
    $plugins['search_api_time_tag']['base'] = $bases;
  }

  /**
   * Implements hook_views_plugins_filter_alter().
   */
  #[Hook('views_plugins_filter_alter')]
  public function filterPluginsAlter(array &$plugins): void {
    // We have to include the term filter handler like this, since adding it
    // directly (i.e., with an annotation) would cause fatal errors on sites
    // without the Taxonomy module.
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $plugins['search_api_term'] = [
        'plugin_type' => 'filter',
        'id' => 'search_api_term',
        'class' => TermFilter::class,
        'provider' => 'search_api',
      ];
    }
  }

  /**
   * Implements hook_views_plugins_row_alter().
   */
  #[Hook('views_plugins_row_alter')]
  public function rowPluginsAlter(array &$plugins): void {
    try {
      /** @var \Drupal\search_api\IndexInterface[] $indexes */
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (PluginException) {
      return;
    }

    // Collect all base tables provided by this module.
    $bases = [];
    foreach ($indexes as $index) {
      $bases[] = 'search_api_index_' . $index->id();
    }

    // If no search indexes are defined yet, declare a dummy index as the base
    // table. This will make sure our plugins do not become available for views
    // that are not based on search indexes.
    if (!$bases) {
      $bases = ['search_api_index_dummy'];
    }

    if (isset($plugins['search_api'])) {
      $plugins['search_api']['base'] = $bases;
    }
    if (isset($plugins['search_api_data'])) {
      $plugins['search_api_data']['base'] = $bases;
    }
  }

  /**
   * Finds an unused field alias for a field in a Views table definition.
   *
   * @param string $field_id
   *   The original ID of the Search API field.
   * @param array $table
   *   The Views table definition.
   *
   * @return string
   *   The field alias to use.
   */
  protected function findFieldAlias(string $field_id, array $table): string {
    $base = $field_alias = preg_replace('/[^a-zA-Z0-9]+/S', '_', $field_id);
    $i = 0;
    while (isset($table[$field_alias])) {
      $field_alias = $base . '_' . ++$i;
    }
    return $field_alias;
  }

  /**
   * Returns the Views handlers to use for a given field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field to add to the definition.
   *
   * @return array
   *   The Views definition to add for the given field.
   */
  protected function getHandlers(FieldInterface $field): array {
    $mapping = $this->getHandlerMapping();

    try {
      $types = [];

      $definition = NULL;
      if ($field->getPropertyPath() !== NULL) {
        $definition = $field->getDataDefinition();
      }

      // Since $definition->getClass() can throw an exception in specific setups,
      // but $class is not always needed for determining the mappings, we catch
      // and ignore the exception here.
      try {
        $class = $definition?->getClass();
      }
      /** @noinspection PhpRedundantCatchClauseInspection */
      catch (PluginNotFoundException) {
        // Ignore.
      }

      // Check whether this is an entity reference field.
      if (is_a($class ?? NULL, EntityReferenceItem::class, TRUE)) {
        $entity_type_id = $definition->getSetting('target_type');
        if ($entity_type_id) {
          $entity_type = $this->entityTypeManager
            ->getDefinition($entity_type_id);
          $bundle_of = $entity_type->getBundleOf();
          if ($bundle_of) {
            $types[] = "bundle_of:$bundle_of";
            $types[] = ['bundle_of', $bundle_of];
          }

          $types[] = "entity:$entity_type_id";
          $types[] = ['entity', $entity_type_id];
        }
      }

      // Special treatment for languages (as we have no specific Search API data
      // type for those).
      if ($definition?->getSetting('views_type') === 'language') {
        $types[] = 'language';
      }

      if ($definition?->getSetting('allowed_values')) {
        $types[] = 'options';
      }

      $types[] = $field->getType();
      /** @var \Drupal\search_api\DataType\DataTypeInterface $data_type */
      $data_type = $this->dataTypePluginManager->createInstance($field->getType());
      if (!$data_type->isDefault()) {
        $types[] = $data_type->getFallbackType();
      }

      foreach ($types as $type) {
        $sub_type = NULL;
        if (is_array($type)) {
          [$type, $sub_type] = $type;
        }
        if (isset($mapping[$type])) {
          $this->adjustHandlers($type, $field, $mapping[$type], $sub_type);
          return $mapping[$type];
        }
      }
    }
    catch (SearchApiException | PluginException $e) {
      $vars['%index'] = $field->getIndex()->label();
      $vars['%field'] = $field->getPrefixedLabel();
      Error::logException($this->logger, $e, '%type while adding Views handlers for field %field on index %index: @message in %function (line %line of %file).', $vars);
    }

    return [];
  }

  /**
   * Computes a handler definition for the given property.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property
   *   The property definition.
   * @param string|null $property_path
   *   (optional) The property path of the property. If set, it will be used for
   *   Field API fields to set the "field_name" property of the definition.
   *
   * @return array|null
   *   Either a Views field handler definition for this property, or NULL if the
   *   property shouldn't have one.
   *
   * @see hook_search_api_views_field_handler_mapping_alter()
   */
  protected function getFieldHandlerForProperty(
    DataDefinitionInterface $property,
    ?string $property_path = NULL,
  ): ?array {
    $mappings = $this->getFieldHandlerMapping();

    // First, look for an exact match.
    $data_type = $property->getDataType();
    if (array_key_exists($data_type, $mappings['simple'])) {
      $definition = $mappings['simple'][$data_type];
    }
    else {
      // Then check all the patterns defined by regular expressions, defaulting to
      // the "default" definition.
      $definition = $mappings['default'];
      foreach ($mappings['regex'] as $regex => $mapping_definition) {
        if (preg_match($regex, $data_type)) {
          $definition = $mapping_definition;
        }
      }
    }

    // Field items have a special handler, but need a fallback handler set to be
    // able to optionally circumvent entity field rendering. That's why we just
    // set the "field_item:…" types to their fallback handlers in
    // _search_api_views_get_field_handler_mapping(), along with non-field item
    // types, and here manually update entity field properties to have the correct
    // definition, with "search_api_field" handler, correct fallback handler and
    // "field_name" and "entity_type" correctly set.
    // Since the Views EntityField handler class doesn't support computed fields,
    // neither can we (easily), so keep the fallback handler as the only
    // definition for those.
    if (isset($definition)
        && $property instanceof FieldItemDataDefinition
        && !$property->isComputed()
        && !$property->getFieldDefinition()->isComputed()) {
      [, $field_name] = Utility::splitPropertyPath($property_path);
      if (!isset($definition['fallback_handler'])) {
        $definition['fallback_handler'] = $definition['id'];
        $definition['id'] = 'search_api_field';
      }
      $definition['field_name'] = $field_name;
      $definition['entity_type'] = $property
        ->getFieldDefinition()
        ->getTargetEntityTypeId();
    }

    return $definition;
  }

  /**
   * Adds definitions for our special fields to a Views data table definition.
   *
   * @param array $table
   *   The existing Views data table definition.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which the Views data table is created.
   */
  protected function addSpecialFields(array &$table, IndexInterface $index): void {
    $id_field = $this->findFieldAlias('search_api_id', $table);
    $table[$id_field]['title'] = t('Item ID');
    $table[$id_field]['help'] = t("The item's internal (Search API-specific) ID");
    $table[$id_field]['field']['id'] = 'standard';
    $table[$id_field]['sort']['id'] = 'search_api';
    if ($id_field != 'search_api_id') {
      $table[$id_field]['real field'] = 'search_api_id';
    }

    $datasource_field = $this->findFieldAlias('search_api_datasource', $table);
    $table[$datasource_field]['title'] = t('Datasource');
    $table[$datasource_field]['help'] = t('The datasource ID');
    $table[$datasource_field]['argument']['id'] = 'search_api';
    $table[$datasource_field]['argument']['disable_break_phrase'] = TRUE;
    $table[$datasource_field]['field']['id'] = 'standard';
    $table[$datasource_field]['filter']['id'] = 'search_api_datasource';
    $table[$datasource_field]['sort']['id'] = 'search_api';
    if ($datasource_field != 'search_api_datasource') {
      $table[$datasource_field]['real field'] = 'search_api_datasource';
    }

    $language_field = $this->findFieldAlias('search_api_language', $table);
    $table[$language_field]['title'] = t('Item language');
    $table[$language_field]['help'] = t("The item's language");
    $table[$language_field]['field']['id'] = 'language';
    $table[$language_field]['filter']['id'] = 'search_api_language';
    $table[$language_field]['filter']['allow empty'] = FALSE;
    $table[$language_field]['sort']['id'] = 'search_api';
    if ($language_field != 'search_api_language') {
      $table[$language_field]['real field'] = 'search_api_language';
    }

    $url_field = $this->findFieldAlias('search_api_url', $table);
    $table[$url_field]['title'] = t('Item URL');
    $table[$url_field]['help'] = t("The item's URL");
    $table[$url_field]['field']['id'] = 'search_api';
    if ($url_field != 'search_api_url') {
      $table[$url_field]['real field'] = 'search_api_url';
    }

    $relevance_field = $this->findFieldAlias('search_api_relevance', $table);
    $table[$relevance_field]['group'] = t('Search');
    $table[$relevance_field]['title'] = t('Relevance');
    $table[$relevance_field]['help'] = t('The relevance of this search result with respect to the query');
    $table[$relevance_field]['field']['type'] = 'decimal';
    $table[$relevance_field]['field']['id'] = 'numeric';
    $table[$relevance_field]['field']['search_api field'] = 'search_api_relevance';
    $table[$relevance_field]['sort']['id'] = 'search_api';
    if ($relevance_field != 'search_api_relevance') {
      $table[$relevance_field]['real field'] = 'search_api_relevance';
    }

    $excerpt_field = $this->findFieldAlias('search_api_excerpt', $table);
    $table[$excerpt_field]['group'] = t('Search');
    $table[$excerpt_field]['title'] = t('Excerpt');
    $table[$excerpt_field]['help'] = t('The search result excerpted to show found search terms');
    $table[$excerpt_field]['field']['id'] = 'search_api_text';
    $table[$excerpt_field]['field']['filter_type'] = 'xss';
    if ($excerpt_field != 'search_api_excerpt') {
      $table[$excerpt_field]['real field'] = 'search_api_excerpt';
    }

    $fulltext_field = $this->findFieldAlias('search_api_fulltext', $table);
    $table[$fulltext_field]['group'] = t('Search');
    $table[$fulltext_field]['title'] = t('Fulltext search');
    $table[$fulltext_field]['help'] = t('Search several or all fulltext fields at once.');
    $table[$fulltext_field]['filter']['id'] = 'search_api_fulltext';
    $table[$fulltext_field]['argument']['id'] = 'search_api_fulltext';
    if ($fulltext_field != 'search_api_fulltext') {
      $table[$fulltext_field]['real field'] = 'search_api_fulltext';
    }

    $mlt_field = $this->findFieldAlias('search_api_more_like_this', $table);
    $table[$mlt_field]['group'] = t('Search');
    $table[$mlt_field]['title'] = t('More like this');
    $table[$mlt_field]['help'] = t('Find similar content.');
    $table[$mlt_field]['argument']['id'] = 'search_api_more_like_this';
    if ($mlt_field != 'search_api_more_like_this') {
      $table[$mlt_field]['real field'] = 'search_api_more_like_this';
    }

    $rendered_field = $this->findFieldAlias('search_api_rendered_item', $table);
    $table[$rendered_field]['group'] = t('Search');
    $table[$rendered_field]['title'] = t('Rendered item');
    $table[$rendered_field]['help'] = t('Renders item in a view mode.');
    $table[$rendered_field]['field']['id'] = 'search_api_rendered_item';
    if ($rendered_field != 'search_api_rendered_item') {
      $table[$rendered_field]['real field'] = 'search_api_rendered_item';
    }

    // If at least one datasource is based on an entity type that offers
    // operations, we provide them as a field.
    foreach ($index->getDatasources() as $datasource) {
      if ($entity_type_id = $datasource->getEntityTypeId()) {
        $entity_type = $this->entityTypeManager
          ->getDefinition($entity_type_id, exception_on_invalid: FALSE);
        if ($entity_type->hasListBuilderClass()) {
          $operations_field = $this->findFieldAlias('search_api_operations', $table);
          $table[$operations_field]['title'] = t('Operations links');
          $table[$operations_field]['help'] = t('Provides links to perform entity operations.');
          $table[$operations_field]['field']['id'] = 'search_api_entity_operations';
          if ($operations_field !== 'search_api_operations') {
            $table[$operations_field]['real field'] = 'search_api_operations';
          }
          break;
        }
      }
    }

    // If there are taxonomy term references indexed in the index, include the
    // "All taxonomy term fields" contextual filter. We also save for all fields
    // whether they contain only terms of a certain vocabulary, keying that
    // information by vocabulary for later ease of use.
    $vocabulary_fields = [];
    /** @var \Drupal\search_api\Item\Field $field */
    foreach ($index->getFields() as $field_id => $field) {
      // This can only work if the field is directly on the indexed entity.
      if (str_contains($field->getPropertyPath(), IndexInterface::PROPERTY_PATH_SEPARATOR)) {
        continue;
      }

      // Search for taxonomy term reference fields, and catch problems early on.
      try {
        $property = $field->getDataDefinition();
        $datasource = $field->getDatasource();
      }
      catch (SearchApiException) {
        // Will probably cause other problems elsewhere, but here we can just
        // ignore it.
        continue;
      }
      if (!$datasource) {
        continue;
      }
      if ($property->getDataType() !== 'field_item:entity_reference') {
        continue;
      }
      $settings = $property->getSettings();
      if (($settings['target_type'] ?? '') !== 'taxonomy_term') {
        continue;
      }
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }
      // Field instances per bundle can reference different vocabularies, make
      // sure we add them all.
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $bundle) {
        $bundle_fields = $this->entityTypeFieldManager
          ->getFieldDefinitions($entity_type_id, $bundle_id);
        // Check if this bundle has the taxonomy entity reference field.
        if (array_key_exists($field->getPropertyPath(), $bundle_fields)) {
          $field_definition = $bundle_fields[$field->getPropertyPath()];
          $bundle_settings = $field_definition->getSettings();
          if (!empty($bundle_settings['handler_settings']['target_bundles'])) {
            foreach ($bundle_settings['handler_settings']['target_bundles'] as $vocabulary_id) {
              $vocabulary_fields[$vocabulary_id][] = $field_id;
            }
          }
          else {
            // If we can't determine the referenced vocabularies, we use the
            // special "" key to mean "any vocabulary".
            $vocabulary_fields[''][] = $field_id;
          }
        }
      }
    }

    if ($vocabulary_fields) {
      // Make sure $vocabulary_fields doesn't contain duplicates for fields that
      // are shared between bundles.
      $vocabulary_fields = array_map('array_unique', $vocabulary_fields);

      $all_terms_field = $this->findFieldAlias('search_api_all_terms', $table);
      $table[$all_terms_field]['group'] = t('Search');
      $table[$all_terms_field]['title'] = t('All taxonomy term fields');
      $table[$all_terms_field]['help'] = t('Search all indexed taxonomy term fields');
      $table[$all_terms_field]['argument']['id'] = 'search_api_all_terms';
      $table[$all_terms_field]['argument']['vocabulary_fields'] = $vocabulary_fields;
      if ($all_terms_field != 'search_api_all_terms') {
        $table[$all_terms_field]['real field'] = 'search_api_all_terms';
      }
    }

    $bulk_form_field = $this->findFieldAlias('search_api_bulk_form', $table);
    $table[$bulk_form_field] = [
      'title' => t('Bulk update'),
      'help' => t('Allows users to apply an action to one or more items.'),
      'field' => [
        'id' => 'search_api_bulk_form',
      ],
    ];
  }

  /**
   * Creates a Views table definition for one datasource of an index.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource for which to create a table definition.
   * @param array $data
   *   The existing Views data definitions. Passed by reference so additionally
   *   needed tables can be inserted.
   *
   * @return array
   *   A Views table definition for the given datasource.
   */
  protected function createDatasourceTable(DatasourceInterface $datasource, array &$data): array {
    $datasource_id = $datasource->getPluginId();
    $table = [
      'table' => [
        'group' => t('@datasource datasource', ['@datasource' => $datasource->label()]),
        'index' => $datasource->getIndex()->id(),
        'datasource' => $datasource_id,
      ],
    ];
    $entity_type_id = $datasource->getEntityTypeId();
    if ($entity_type_id) {
      $table['table']['entity type'] = $entity_type_id;
      $table['table']['entity revision'] = FALSE;
    }

    $this->addHandlersForProperties($datasource->getPropertyDefinitions(), $table, $data);

    // Prefix the "real field" of each entry with the datasource ID.
    foreach ($table as $key => $definition) {
      if ($key == 'table') {
        continue;
      }

      $real_field = $definition['real field'] ?? $key;
      $table[$key]['real field'] = Utility::createCombinedId($datasource_id, $real_field);

      // Relationships sometimes have different real fields set, since they might
      // also include the nested property that contains the actual reference. So,
      // if a "real field" is set for that, we need to adapt it as well.
      if (isset($definition['relationship']['real field'])) {
        $real_field = $definition['relationship']['real field'];
        $table[$key]['relationship']['real field'] = Utility::createCombinedId($datasource_id, $real_field);
      }
    }

    return $table;
  }

  /**
   * Makes necessary, field-specific adjustments to Views handler definitions.
   *
   * @param string $type
   *   The type of field, as defined in _search_api_views_handler_mapping().
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field whose handler definitions are being created.
   * @param array $definitions
   *   The handler definitions for the field, as a reference.
   * @param string|null $sub_type
   *   (optional) If applicable, the field's sub-type.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the field's data definition could not be retrieved.
   */
  protected function adjustHandlers(string $type, FieldInterface $field, array &$definitions, ?string $sub_type = NULL): void {
    // By default, all fields can be empty (or at least have to be treated that
    // way by the Search API).
    if (!isset($definitions['filter']['allow empty'])) {
      $definitions['filter']['allow empty'] = TRUE;
    }

    // For taxonomy term references, set the referenced vocabulary.
    $data_definition = $field->getDataDefinition();
    if ($type == 'entity:taxonomy_term') {
      if (isset($data_definition->getSettings()['handler_settings']['target_bundles'])) {
        $target_bundles = $data_definition->getSettings()['handler_settings']['target_bundles'];
        if (count($target_bundles) == 1) {
          $definitions['filter']['vocabulary'] = reset($target_bundles);
        }
      }
    }
    elseif ($type == 'options') {
      if ($data_definition instanceof FieldItemDataDefinition) {
        // If this is a normal Field API field, dynamically retrieve the options
        // list at query time.
        $field_definition = $data_definition->getFieldDefinition();
        $entity_type = $field_definition->getTargetEntityTypeId();
        $bundle = $field_definition->getTargetBundle() ?? $entity_type;
        $field_name = $field_definition->getName();
        $definitions['filter']['options callback'] = [static::class, 'getAllowedValues'];
        $definitions['filter']['options arguments'] = [$entity_type, $bundle, $field_name];
      }
      else {
        // Otherwise, include the options list verbatim in the Views data, unless
        // it's too big (or doesn't look valid).
        $options = $data_definition->getSetting('allowed_values');
        if (is_array($options) && count($options) <= 50) {
          // Since the Views InOperator filter plugin doesn't allow just including
          // the options in the definition, we use this workaround.
          $definitions['filter']['options callback'] = 'array_filter';
          $definitions['filter']['options arguments'] = [$options];
        }
      }
    }
    elseif ($type === 'bundle_of' && $sub_type) {
      $definitions['filter']['options callback'] = [static::class, 'getBundleNames'];
      $definitions['filter']['options arguments'] = [$sub_type];
    }
  }

  /**
   * Determines the mapping of Search API data types to their Views handlers.
   *
   * @return array
   *   An associative array with data types as the keys and Views field data
   *   definitions as the values. In addition to all normally defined data types,
   *   keys can also be "options" for any field with an options list, "entity" for
   *   general entity-typed fields or "entity:ENTITY_TYPE" (with "ENTITY_TYPE"
   *   being the machine name of an entity type) for entities of that type.
   *
   * @see search_api_views_handler_mapping_alter()
   */
  protected function getHandlerMapping(): array {
    $mapping = &drupal_static(__FUNCTION__);

    if ($mapping === NULL) {
      $mapping = [
        'boolean' => [
          'argument' => [
            'id' => 'search_api',
          ],
          'filter' => [
            'id' => 'search_api_boolean',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'date' => [
          'argument' => [
            'id' => 'search_api_date',
          ],
          'filter' => [
            'id' => 'search_api_date',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'decimal' => [
          'argument' => [
            'id' => 'search_api',
            'filter' => 'floatval',
          ],
          'filter' => [
            'id' => 'search_api_numeric',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'integer' => [
          'argument' => [
            'id' => 'search_api',
            'filter' => 'intval',
          ],
          'filter' => [
            'id' => 'search_api_numeric',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'string' => [
          'argument' => [
            'id' => 'search_api',
          ],
          'filter' => [
            'id' => 'search_api_string',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'text' => [
          'argument' => [
            'id' => 'search_api',
          ],
          'filter' => [
            'id' => 'search_api_text',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'language' => [
          'argument' => [
            'id' => 'search_api',
            ],
          'filter' => [
            'id' => 'search_api_language',
            'allow empty' => FALSE,
            ],
          'sort' => [
            'id' => 'search_api',
            ],
          ],
        'options' => [
          'argument' => [
            'id' => 'search_api',
          ],
          'filter' => [
            'id' => 'search_api_options',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'entity:taxonomy_term' => [
          'argument' => [
            'id' => 'search_api_term',
          ],
          'filter' => [
            'id' => 'search_api_term',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'entity:user' => [
          'argument' => [
            'id' => 'search_api',
            'filter' => 'intval',
          ],
          'filter' => [
            'id' => 'search_api_user',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
        'bundle_of' => [
          'argument' => [
            'id' => 'search_api',
          ],
          'filter' => [
            'id' => 'search_api_options',
          ],
          'sort' => [
            'id' => 'search_api',
          ],
        ],
      ];

      $alter_id = 'search_api_views_handler_mapping';
      $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use the "search_api.mapping_views_handlers" event instead. See https://www.drupal.org/node/3059866';
      $this->moduleHandler
        ->alterDeprecated($description, $alter_id, $mapping);

      $event = new MappingViewsHandlersEvent($mapping);
      $this->eventDispatcher
        ->dispatch($event, SearchApiEvents::MAPPING_VIEWS_HANDLERS);
    }

    return $mapping;
  }

  /**
   * Retrieves the field handler mapping used by the Search API Views integration.
   *
   * @return array
   *   An associative array with three keys:
   *   - simple: An associative array mapping property data types to their field
   *     handler definitions.
   *   - regex: An array associative array mapping regular expressions for
   *     property data types to their field handler definitions, ordered by
   *     descending string length of the regular expression.
   *   - default: The default definition for data types that match no other field.
   */
  protected function getFieldHandlerMapping(): array {
    $mappings = &drupal_static(__FUNCTION__);

    if ($mappings === NULL) {
      // First create a plain mapping and pass it to the alter hook.
      $plain_mapping = [];

      $plain_mapping['*'] = [
        'id' => 'search_api',
      ];

      $text_mapping = [
        'id' => 'search_api_text',
        'filter_type' => 'xss',
      ];
      $plain_mapping['field_item:text_long'] = $text_mapping;
      $plain_mapping['field_item:text_with_summary'] = $text_mapping;
      $plain_mapping['search_api_html'] = $text_mapping;
      unset($text_mapping['filter_type']);
      $plain_mapping['search_api_text'] = $text_mapping;

      $numeric_mapping = [
        'id' => 'search_api_numeric',
      ];
      $plain_mapping['field_item:integer'] = $numeric_mapping;
      $plain_mapping['field_item:list_integer'] = $numeric_mapping;
      $plain_mapping['integer'] = $numeric_mapping;
      $plain_mapping['timespan'] = $numeric_mapping;

      $float_mapping = [
        'id' => 'search_api_numeric',
        'float' => TRUE,
      ];
      $plain_mapping['field_item:decimal'] = $float_mapping;
      $plain_mapping['field_item:float'] = $float_mapping;
      $plain_mapping['field_item:list_float'] = $float_mapping;
      $plain_mapping['decimal'] = $float_mapping;
      $plain_mapping['float'] = $float_mapping;

      $date_mapping = [
        'id' => 'search_api_date',
      ];
      $plain_mapping['field_item:created'] = $date_mapping;
      $plain_mapping['field_item:changed'] = $date_mapping;
      $plain_mapping['datetime_iso8601'] = $date_mapping;
      $plain_mapping['timestamp'] = $date_mapping;

      $bool_mapping = [
        'id' => 'search_api_boolean',
      ];
      $plain_mapping['boolean'] = $bool_mapping;
      $plain_mapping['field_item:boolean'] = $bool_mapping;

      $language_mapping = [
        'id' => 'language',
      ];
      $plain_mapping['language'] = $language_mapping;

      $ref_mapping = [
        'id' => 'search_api_entity',
      ];
      $plain_mapping['field_item:entity_reference'] = $ref_mapping;
      $plain_mapping['field_item:comment'] = $ref_mapping;

      // Finally, set a default handler for unknown field items.
      $plain_mapping['field_item:*'] = [
        'id' => 'search_api',
      ];

      // Let other modules change or expand this mapping.
      $alter_id = 'search_api_views_field_handler_mapping';
      $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use the "search_api.mapping_views_field_handlers" event instead. See https://www.drupal.org/node/3059866';
      $this->moduleHandler
        ->alterDeprecated($description, $alter_id, $plain_mapping);

      $event = new MappingViewsFieldHandlersEvent($plain_mapping);
      $this->eventDispatcher
        ->dispatch($event, SearchApiEvents::MAPPING_VIEWS_FIELD_HANDLERS);

      // Then create a new, more practical structure, with the mappings grouped by
      // mapping type.
      $mappings = [
        'simple' => [],
        'regex' => [],
        'default' => NULL,
      ];
      foreach ($plain_mapping as $type => $definition) {
        if ($type == '*') {
          $mappings['default'] = $definition;
        }
        elseif (!str_contains($type, '*')) {
          $mappings['simple'][$type] = $definition;
        }
        else {
          // Transform the type into a PCRE regular expression, taking care to
          // quote everything except for the wildcards.
          $parts = explode('*', $type);
          // Passing the second parameter to preg_quote() is a bit tricky with
          // array_map(), we need to construct an array of slashes.
          $slashes = array_fill(0, count($parts), '/');
          $parts = array_map('preg_quote', $parts, $slashes);
          // Use the "S" modifier for closer analysis of the pattern, since it
          // might be executed a lot.
          $regex = '/^' . implode('.*', $parts) . '$/S';
          $mappings['regex'][$regex] = $definition;
        }
      }
      // Finally, order the regular expressions descending by their lengths.
      $compare = function ($a, $b) {
        return strlen($b) - strlen($a);
      };
      uksort($mappings['regex'], $compare);
    }

    return $mappings;
  }

  /**
   * Adds field and relationship handlers for the given properties.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The properties for which handlers should be added.
   * @param array $table
   *   The existing Views data table definition, passed by reference.
   * @param array $data
   *   The existing Views data definitions, passed by reference.
   */
  protected function addHandlersForProperties(array $properties, array &$table, array &$data): void {
    $entity_reference_types = array_flip([
      'field_item:entity_reference',
      'field_item:image',
      'field_item:file',
    ]);

    foreach ($properties as $property_path => $property) {
      $key = $this->findFieldAlias($property_path, $table);
      $original_property = $property;
      $property = $this->fieldsHelper->getInnerProperty($property);

      // Add a field handler, if applicable.
      $definition = $this->getFieldHandlerForProperty($property, $property_path);
      if ($definition) {
        $table[$key]['field'] = $definition;
      }

      // For entity-typed properties, also add a relationship to the entity type
      // table.
      if ($property instanceof FieldItemDataDefinition && isset($entity_reference_types[$property->getDataType()])) {
        $entity_type_id = $property->getSetting('target_type');
        if ($entity_type_id) {
          $entity_type_table_key = 'search_api_entity_' . $entity_type_id;
          if (!isset($data[$entity_type_table_key])) {
            // Initialize the table definition before calling
            // _search_api_views_entity_type_table() to avoid an infinite
            // recursion.
            $data[$entity_type_table_key] = [];
            $data[$entity_type_table_key] = $this->createEntityTypeTable($entity_type_id, $data);
          }
          // Add the relationship only if we have a non-empty table definition.
          if ($data[$entity_type_table_key]) {
            // Get the entity type to determine the label for the relationship.
            $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, exception_on_invalid: FALSE);
            $entity_type_label = $entity_type ? $entity_type->getLabel() : $entity_type_id;
            $args = [
              '@label' => $entity_type_label,
              '@field_name' => $original_property->getLabel(),
            ];
            // Look through the child properties to find the data reference
            // property that should be the "real field" for the relationship.
            // (For Core entity references, this will usually be ":entity".)
            $suffix = '';
            foreach ($property->getPropertyDefinitions() as $name => $nested_property) {
              if ($nested_property instanceof DataReferenceDefinitionInterface) {
                $suffix = ":$name";
                break;
              }
            }
            $table[$key]['relationship'] = [
              'title' => t('@label referenced from @field_name', $args),
              'label' => t('@field_name: @label', $args),
              'help' => $property->getDescription() ?: t('(No description available)'),
              'id' => 'search_api',
              'base' => $entity_type_table_key,
              'entity type' => $entity_type_id,
              'entity revision' => FALSE,
              'real field' => $property_path . $suffix,
            ];
          }
        }
      }

      if (!empty($table[$key]) && empty($table[$key]['title'])) {
        $table[$key]['title'] = $original_property->getLabel();
        $table[$key]['help'] = $original_property->getDescription() ?: t('(No description available)');
        if ($key != $property_path) {
          $table[$key]['real field'] = $property_path;
        }
      }
    }
  }

  /**
   * Creates a Views table definition for an entity type.
   *
   * @param string $entity_type_id
   *   The ID of the entity type.
   * @param array $data
   *   The existing Views data definitions, passed by reference.
   *
   * @return array
   *   A Views table definition for the given entity type. Or an empty array if
   *   the entity type could not be found.
   */
  protected function createEntityTypeTable(string $entity_type_id, array &$data): array {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, exception_on_invalid: FALSE);
    if (!$entity_type?->entityClassImplements(FieldableEntityInterface::class)) {
      return [];
    }

    $table = [
      'table' => [
        'group' => t('@entity_type relationship', ['@entity_type' => $entity_type->getLabel()]),
        'entity type' => $entity_type_id,
        'entity revision' => FALSE,
      ],
    ];

    $properties = $this->entityTypeFieldManager->getBaseFieldDefinitions($entity_type_id);
    foreach (array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id)) as $bundle_id) {
      $additional = $this->entityTypeFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
      $properties += $additional;
    }
    $this->addHandlersForProperties($properties, $table, $data);

    return $table;
  }

  /**
   * Retrieves the allowed values for a list field instance.
   *
   * @param string $entity_type
   *   The entity type to which the field is attached.
   * @param string $bundle
   *   The bundle to which the field is attached.
   * @param string $field_name
   *   The field's field name.
   *
   * @return array<string, string|\Drupal\Core\StringTranslation\TranslatableMarkup>|null
   *   An array of allowed values in the form key => label, or NULL.
   *
   * @see self::adjustHandlers()
   */
  public static function getAllowedValues(
    string $entity_type,
    string $bundle,
    string $field_name,
  ): ?array {
    $field_manager = \Drupal::getContainer()->get('entity_field.manager');
    $field_definitions = $field_manager->getFieldDefinitions($entity_type, $bundle);
    if (!empty($field_definitions[$field_name])) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_definition = $field_definitions[$field_name];
      $options = $field_definition->getSetting('allowed_values');
      if ($options) {
        return $options;
      }
    }
    return NULL;
  }

  /**
   * Returns an options list for bundles of the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type for which to retrieve the bundle names.
   *
   * @return array<string, string|\Drupal\Core\StringTranslation\TranslatableMarkup>
   *   An array of allowed values, mapping bundle keys to their (translated)
   *   identifiers.
   *
   * @see self::adjustHandlers()
   */
  public static function getBundleNames(string $entity_type_id): array {
    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo($entity_type_id);
    return array_map(function ($bundle_info) {
      return $bundle_info['label'];
    }, $bundles);
  }

}
