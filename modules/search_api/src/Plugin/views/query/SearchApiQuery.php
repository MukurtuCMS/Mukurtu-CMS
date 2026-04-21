<?php

namespace Drupal\search_api\Plugin\views\query;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Plugin\search_api\parse_mode\Terms;
use Drupal\search_api\Plugin\views\field\SearchApiStandard;
use Drupal\search_api\Plugin\views\ResultRow;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\user\Entity\User;
use Drupal\views\Attribute\ViewsQuery;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Views query class for searching on Search API indexes.
 */
#[ViewsQuery(
  id: 'search_api_query',
  title: new TranslatableMarkup('Search API Query'),
  help: new TranslatableMarkup('The query will be generated and run using the Search API.'),
)]
class SearchApiQuery extends QueryPluginBase {

  use LoggerTrait;

  /**
   * Number of results to display.
   *
   * @var int
   */
  protected $limit;

  /**
   * The index this view accesses.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The query that will be executed.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * Array of all encountered errors.
   *
   * Each of these is fatal, meaning that a non-empty $errors property will
   * result in an empty result being returned.
   *
   * @var array
   */
  protected $errors = [];

  /**
   * Whether to abort the search instead of executing it.
   *
   * @var bool
   */
  protected $abort = FALSE;

  /**
   * The IDs of fields whose values should be retrieved by the backend.
   *
   * @var string[]
   */
  protected $retrievedFieldValues = [];

  /**
   * The query's conditions representing the different Views filter groups.
   *
   * @var array
   */
  protected $where = [];

  /**
   * Not actually used.
   *
   * Copied over from \Drupal\views\Plugin\views\query\Sql::$orderby since
   * ExposedFormPluginBase depends on it.
   *
   * @var array
   */
  public $orderby = NULL;

  /**
   * The conjunction with which multiple filter groups are combined.
   *
   * @var string
   */
  protected $groupOperator = 'AND';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|null
   */
  protected $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|null
   */
  protected $messenger;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->offset = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setModuleHandler($container->get('module_handler'));
    $plugin->setMessenger($container->get('messenger'));
    $plugin->setLogger($container->get('logger.channel.search_api'));

    return $plugin;
  }

  /**
   * Loads the search index belonging to the given Views base table.
   *
   * @param string $table
   *   The Views base table ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   (optional) The entity type manager to use.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The requested search index, or NULL if it could not be found and loaded.
   */
  public static function getIndexFromTable($table, ?EntityTypeManagerInterface $entity_type_manager = NULL) {
    // @todo Instead use Views::viewsData() – injected, too – to load the base
    //   table definition and use the "index" (or maybe rename to
    //   "search_api_index") field from there.
    if (str_starts_with($table, 'search_api_index_')) {
      $index_id = substr($table, 17);
      if ($entity_type_manager) {
        return $entity_type_manager->getStorage('search_api_index')
          ->load($index_id);
      }
      return Index::load($index_id);
    }
    return NULL;
  }

  /**
   * Retrieves the contained entity from a Views result row.
   *
   * @param \Drupal\search_api\Plugin\views\ResultRow $row
   *   The Views result row.
   * @param string $relationship_id
   *   The ID of the view relationship to use.
   * @param \Drupal\views\ViewExecutable $view
   *   The current view object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity contained in the result row, if any.
   */
  public static function getEntityFromRow(ResultRow $row, $relationship_id, ViewExecutable $view) {
    if ($relationship_id === 'none') {
      try {
        $object = $row->_object ?: $row->_item->getOriginalObject();
      }
      catch (SearchApiException) {
        return NULL;
      }
      $entity = $object->getValue();
      if ($entity instanceof EntityInterface) {
        return $entity;
      }
      return NULL;
    }

    // To avoid code duplication, just create a dummy field handler and use it
    // to retrieve the entity.
    $handler = new SearchApiStandard([], '', ['title' => '']);
    $options = ['relationship' => $relationship_id];
    $handler->init($view, $view->display_handler, $options);
    return $handler->getEntity($row);
  }

  /**
   * Retrieves the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler ?: \Drupal::moduleHandler();
  }

  /**
   * Sets the module handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The new module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * Retrieves the messenger.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger.
   */
  public function getMessenger() {
    return $this->messenger ?: \Drupal::service('messenger');
  }

  /**
   * Sets the messenger.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The new messenger.
   *
   * @return $this
   */
  public function setMessenger(MessengerInterface $messenger) {
    $this->messenger = $messenger;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    try {
      parent::init($view, $display, $options);
      $this->index = static::getIndexFromTable($view->storage->get('base_table'));
      if (!$this->index) {
        $this->abort(new FormattableMarkup('View %view is not based on Search API but tries to use its query plugin.', ['%view' => $view->storage->label()]));
      }
      $this->query = $this->index->query();
      $this->query->addTag('views');
      $this->query->addTag('views_' . $view->id());
      $display_type = $display->getPluginId();
      if ($display_type === 'rest_export') {
        $display_type = 'rest';
      }
      $this->query->setSearchId("views_$display_type:" . $view->id() . '__' . $display->display['id']);
      $this->query->setOption('search_api_view', $view);
    }
    catch (\Exception $e) {
      $this->abort($e->getMessage());
    }
  }

  /**
   * Adds a property to be retrieved.
   *
   * Currently doesn't serve any purpose, but might be added to the search query
   * in the future to help backends that support returning fields determine
   * which of the fields should actually be returned.
   *
   * @param string $combined_property_path
   *   The combined property path of the property that should be retrieved.
   *
   * @return $this
   *
   * @deprecated in search_api:8.x-1.11 and is removed from search_api:2.0.0.
   *   Use addRetrievedFieldValue() instead.
   *
   * @see https://www.drupal.org/node/3011060
   */
  public function addRetrievedProperty($combined_property_path) {
    @trigger_error('\Drupal\search_api\Plugin\views\query\SearchApiQuery::addRetrievedProperty() is deprecated in search_api:8.x-1.11 and is removed from search_api:2.0.0. Use addRetrievedFieldValue() instead. See https://www.drupal.org/node/3011060', E_USER_DEPRECATED);
    $this->addField(NULL, $combined_property_path);
    return $this;
  }

  /**
   * Adds a field value to be retrieved.
   *
   * Helps backends that support returning fields to determine which of the
   * fields should actually be returned.
   *
   * @param string $field_id
   *   The ID of the field whose value should be retrieved.
   *
   * @return $this
   */
  public function addRetrievedFieldValue($field_id) {
    $this->retrievedFieldValues[$field_id] = $field_id;
    return $this;
  }

  /**
   * Adds a field to the table.
   *
   * This replicates the interface of Views' default SQL backend to simplify
   * the Views integration of the Search API. If you are writing Search
   * API-specific Views code, you should better use the addRetrievedFieldValue()
   * method.
   *
   * @param string|null $table
   *   Ignored.
   * @param string $field
   *   The combined property path of the property that should be retrieved.
   * @param string $alias
   *   (optional) Ignored.
   * @param array $params
   *   (optional) Ignored.
   *
   * @return string
   *   The name that this field can be referred to as (always $field).
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addField()
   * @see \Drupal\search_api\Plugin\views\query\SearchApiQuery::addRetrievedFieldValue()
   */
  public function addField($table, $field, $alias = '', array $params = []) {
    // Ignore calls for built-in fields which don't need to be retrieved.
    if (isset(ResultRow::LAZY_LOAD_PROPERTIES[$field])) {
      return $field;
    }

    foreach ($this->getIndex()->getFields(TRUE) as $field_id => $field_object) {
      if ($field_object->getCombinedPropertyPath() === $field) {
        $this->addRetrievedFieldValue($field_id);
        break;
      }
    }
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    return parent::defineOptions() + [
      'bypass_access' => [
        'default' => FALSE,
      ],
      'skip_access' => [
        'default' => FALSE,
      ],
      'preserve_facet_query_args' => [
        'default' => FALSE,
      ],
      'query_tags' => [
        'default' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['skip_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip item access checks'),
      '#description' => $this->t("By default, an additional access check will be executed for each item returned by the search query. However, since removing results this way will break paging and result counts, it is preferable to configure the view in a way that it will only return accessible results. If you are sure that only accessible results will be returned in the search, or if you want to show results to which the user normally wouldn't have access, you can enable this option to skip those additional access checks. This should be used with care."),
      '#default_value' => $this->options['skip_access'],
      '#weight' => -1,
    ];

    $form['bypass_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass access checks'),
      '#description' => $this->t('If the underlying search index has access checks enabled (for example, through the "Content access" processor), this option allows you to disable them for this view. This will never disable any filters placed on this view.'),
      '#default_value' => $this->options['bypass_access'],
    ];
    $form['bypass_access']['#states']['visible'][':input[name="query[options][skip_access]"]']['checked'] = TRUE;

    if ($this->getModuleHandler()->moduleExists('facets')) {
      $form['preserve_facet_query_args'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Preserve facets while using filters'),
        '#description' => $this->t("By default, changing an exposed filter would reset all selected facets. This option allows you to prevent this behavior."),
        '#default_value' => $this->options['preserve_facet_query_args'],
      ];
    }
    else {
      $form['preserve_facet_query_args'] = [
        '#type' => 'value',
        '#value' => FALSE,
      ];
    }

    $form['query_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query Tags'),
      '#description' => $this->t('If set, these tags will be appended to the query and can be used to identify the query in a module. This can be helpful for altering queries.'),
      '#default_value' => implode(', ', $this->options['query_tags']),
      '#element_validate' => ['views_element_validate_tags'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $value = &$form_state->getValue(['query', 'options', 'query_tags']);
    if (is_array($value)) {
      // We already ran on this form state. This happens when the user toggles a
      // display to override defaults or vice-versa – the submit handler gets
      // invoked twice, and we don't want to bash the values  from the original
      // call.
      return;
    }
    $value = array_filter(array_map('trim', explode(',', $value)));
  }

  /**
   * Checks for entity types contained in the current view's index.
   *
   * @param bool $return_bool
   *   (optional) If TRUE, returns a boolean instead of a list of datasources.
   *
   * @return string[]|bool
   *   If $return_bool is FALSE, an associative array mapping all datasources
   *   containing entities to their entity types. Otherwise, TRUE if there is at
   *   least one such datasource.
   *
   * @deprecated in search_api:8.x-1.5 and is removed from search_api:2.0.0. Use
   *   \Drupal\search_api\IndexInterface::getEntityTypes() instead.
   *
   * @see https://www.drupal.org/node/2899682
   */
  public function getEntityTypes($return_bool = FALSE) {
    @trigger_error('\Drupal\search_api\Plugin\views\query\SearchApiQuery::getEntityTypes() is deprecated in search_api:8.x-1.5 and is removed from search_api:2.0.0. Use \Drupal\search_api\IndexInterface::getEntityTypes() instead. See https://www.drupal.org/node/2899682', E_USER_DEPRECATED);
    $types = $this->index->getEntityTypes();
    return $return_bool ? (bool) $types : $types;
  }

  /**
   * {@inheritdoc}
   */
  public function query($get_count = FALSE) {
    // Try to determine whether build() has been called yet.
    if (empty($this->view->build_info['query'])) {
      // If not, call it in case we at least have a view set. If we don't, we
      // can't really do anything.
      if (!$this->view) {
        return NULL;
      }
      $this->build($this->view);
    }

    $query = clone $this->query;
    // A count query doesn't need to return any results.
    if ($get_count) {
      $query->range(0, 0);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function build(ViewExecutable $view) {
    $this->view = $view;

    // Initialize the pager and let it modify the query to add limits. This has
    // to be done even for aborted queries since it might otherwise lead to a
    // fatal error when Views tries to access $view->pager.
    $view->initPager();
    $view->pager->query();

    // If the query was aborted by some plugin (or, possibly, hook), we don't
    // need to do anything else here. Adding conditions or other options to an
    // aborted query doesn't make sense.
    if ($this->shouldAbort()) {
      return;
    }

    // Setup the nested filter structure for this query.
    if (!empty($this->where)) {
      // If the different groups are combined with the OR operator, we have to
      // add a new OR filter to the query to which the filters for the groups
      // will be added.
      if ($this->groupOperator === 'OR') {
        $base = $this->query->createAndAddConditionGroup('OR');
      }
      else {
        $base = $this->query;
      }
      // Add a nested filter for each filter group, with its set conjunction.
      foreach ($this->where as $group_id => $group) {
        if (!empty($group['conditions']) || !empty($group['condition_groups'])) {
          $group += ['type' => 'AND'];
          // Filters in the default group 0 (used by arguments) should not take
          // $this->groupOperator into account, but use a separate conditions
          // group just for them, placed directly on the query.
          $conditions = $this->query->createConditionGroup($group['type']);
          if ($group_id == 0) {
            $this->query->addConditionGroup($conditions);
          }
          else {
            $base->addConditionGroup($conditions);
          }
          if (!empty($group['conditions'])) {
            foreach ($group['conditions'] as $condition) {
              [$field, $value, $operator] = $condition;
              $conditions->addCondition($field, $value, $operator);
            }
          }
          if (!empty($group['condition_groups'])) {
            foreach ($group['condition_groups'] as $nested_conditions) {
              $conditions->addConditionGroup($nested_conditions);
            }
          }
        }
      }
    }

    // Add the "search_api_bypass_access" option to the query, if desired.
    if (!empty($this->options['bypass_access'])) {
      $this->query->setOption('search_api_bypass_access', TRUE);
    }

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $this->query->addTag($tag);
      }
    }

    // Save query information for Views UI.
    $view->build_info['query'] = (string) $this->query;

    // Add the fields to be retrieved to the query, as information for the
    // backend.
    $this->query->setOption('search_api_retrieved_field_values', array_values($this->retrievedFieldValues));
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ViewExecutable $view) {
    $this->getModuleHandler()->invokeAll('views_query_alter', [$view, $this]);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    if ($this->shouldAbort()) {
      if (error_displayable()) {
        foreach ($this->errors as $msg) {
          $this->getMessenger()->addError($msg);
        }
      }
      $view->result = [];
      $view->total_rows = 0;
      $view->execute_time = 0;
      return;
    }

    // Calculate the "skip result count" option, if it wasn't already set to
    // FALSE.
    $skip_result_count = $this->query->getOption('skip result count', TRUE);
    if ($skip_result_count) {
      $skip_result_count = !$view->pager->useCountQuery() && empty($view->get_total_rows);
      $this->query->setOption('skip result count', $skip_result_count);
    }

    try {
      // Trigger pager preExecute().
      $view->pager->preExecute($this->query);

      // Views passes sometimes NULL and sometimes the integer 0 for "All" in a
      // pager. If set to 0 items, a string "0" is passed. Therefore, we unset
      // the limit if an empty value OTHER than a string "0" was passed.
      if (!$this->limit && $this->limit !== '0') {
        $this->limit = NULL;
      }
      // Set the range. We always set this, as there might be an offset even if
      // all items are shown.
      $this->query->range($this->offset, $this->limit);

      $start = microtime(TRUE);

      // Execute the search.
      $results = $this->query->execute();

      // Store the results.
      if (!$skip_result_count) {
        $view->pager->total_items = $results->getResultCount();
        if (!empty($view->pager->options['offset'])) {
          $view->pager->total_items -= $view->pager->options['offset'];
        }
        $view->total_rows = $view->pager->total_items;
      }
      $view->result = [];
      if ($results->getResultItems()) {
        $this->addResults($results, $view);
      }
      $view->execute_time = microtime(TRUE) - $start;

      // Trigger pager postExecute().
      $view->pager->postExecute($view->result);
      $view->pager->updatePageInfo();
    }
    catch (\Exception $e) {
      $this->abort($e->getMessage());
      // Recursion to get the same error behavior as above.
      $this->execute($view);
    }
  }

  /**
   * Aborts this search query.
   *
   * Used by handlers to flag a fatal error which shouldn't be displayed but
   * still lead to the view returning empty and the search not being executed.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string|null $msg
   *   Optionally, a translated, unescaped error message to display.
   */
  public function abort($msg = NULL) {
    if ($msg) {
      $this->errors[] = $msg;
    }
    $this->abort = TRUE;
    if (isset($this->query)) {
      $this->query->abort($msg);
    }
  }

  /**
   * Checks whether this query should be aborted.
   *
   * @return bool
   *   TRUE if the query should/will be aborted, FALSE otherwise.
   *
   * @see SearchApiQuery::abort()
   */
  public function shouldAbort() {
    return $this->abort || !$this->query || $this->query->wasAborted();
  }

  /**
   * Adds Search API result items to a view's result set.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result_set
   *   The search results.
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   */
  protected function addResults(ResultSetInterface $result_set, ViewExecutable $view) {
    $results = $result_set->getResultItems();

    // Views \Drupal\views\Plugin\views\style\StylePluginBase::renderFields()
    // uses a numeric results index to key the rendered results.
    // The ResultRow::index property is the key then used to retrieve these.
    $count = 0;

    // First, unless disabled, check access for all entities in the results.
    if (!$this->options['skip_access']) {
      $account = $this->getAccessAccount();
      // If search items are not loaded already, pre-load them now in bulk to
      // avoid them being individually loaded inside checkAccess().
      $result_set->preLoadResultItems();
      foreach ($results as $item_id => $result) {
        if (!$result->getAccessResult($account)->isAllowed()) {
          unset($results[$item_id]);
        }
      }
    }

    foreach ($results as $result) {
      $values = [];
      $values['_item'] = $result;
      try {
        $object = $result->getOriginalObject(FALSE);
        if ($object) {
          $values['_object'] = $object;
          $values['_relationship_objects'][NULL] = [$object];
          if ($object instanceof EntityAdapter) {
            $values['_entity'] = $object->getEntity();
          }
        }
      }
      catch (SearchApiException) {
        // Can't actually be thrown here, but catch for the static analyzer's
        // sake.
      }

      // Gather any properties from the search results.
      foreach ($result->getFields(FALSE) as $field_id => $field) {
        // Ignore calls for built-in fields which don't need to be retrieved.
        if (isset(ResultRow::LAZY_LOAD_PROPERTIES[$field_id])) {
          continue;
        }
        $path = $field->getCombinedPropertyPath();
        try {
          $property = $field->getDataDefinition();
          // For configurable processor-defined properties, our Views field
          // handlers use a special property path to distinguish multiple
          // fields with the same property path. Therefore, we here also set
          // the values using that special property path so this will work
          // correctly.
          if ($property instanceof ConfigurablePropertyInterface) {
            $path .= '|' . $field_id;
          }
        }
        catch (SearchApiException) {
          // If we're not able to retrieve the data definition at this point,
          // it doesn't really matter.
        }
        $values[$path] = $field->getValues();
      }

      $values['index'] = $count++;

      $view->result[] = new ResultRow($values);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $query = $this->getSearchApiQuery();
    if ($query instanceof CacheableDependencyInterface) {
      return $query->getCacheContexts();
    }

    // We are not returning the cache contexts from the parent class since these
    // are based on the default SQL storage from Views, while our results are
    // coming from the search engine.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    $query = $this->getSearchApiQuery();
    if ($query instanceof CacheableDependencyInterface) {
      // Add the list cache tag of the search index, so that the view will be
      // invalidated if any items on the index are indexed or deleted.
      $tags[] = 'search_api_list:' . $this->getIndex()->id();
      $tags = Cache::mergeTags($query->getCacheTags(), $tags);
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $max_age = parent::getCacheMaxAge();

    $query = $this->getSearchApiQuery();
    if ($query instanceof CacheableDependencyInterface) {
      $max_age = Cache::mergeMaxAges($query->getCacheMaxAge(), $max_age);
    }

    return $max_age;
  }

  /**
   * Retrieves the conditions placed on this query.
   *
   * @return array
   *   The conditions placed on this query, separated by groups, as an
   *   associative array with a structure like this:
   *   - GROUP_ID:
   *     - type: "AND"/"OR"
   *     - conditions:
   *       - [FILTER, VALUE, OPERATOR]
   *       - [FILTER, VALUE, OPERATOR]
   *       …
   *     - condition_groups:
   *       - ConditionGroupInterface object
   *       - ConditionGroupInterface object
   *       …
   *   - GROUP_ID:
   *     …
   *   Returned by reference.
   */
  public function &getWhere() {
    return $this->where;
  }

  /**
   * Retrieves the account object to use for access checks for this query.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The account for which to check access to returned or displayed entities.
   *   Or NULL to use the currently logged-in user.
   */
  public function getAccessAccount() {
    $account = $this->getOption('search_api_access_account');
    if ($account && is_scalar($account)) {
      $account = User::load($account);
    }
    return $account;
  }

  /**
   * Returns the Search API query object used by this Views query.
   *
   * @return \Drupal\search_api\Query\QueryInterface|null
   *   The search query object used internally by this plugin, if any has been
   *   successfully created. NULL otherwise.
   */
  public function getSearchApiQuery() {
    return $this->query;
  }

  /**
   * Sets the Search API query object.
   *
   * Usually this is done by the query plugin class itself, but in rare cases
   * (such as for caching purposes) it might be necessary to set it from
   * outside.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The new query.
   *
   * @return $this
   */
  public function setSearchApiQuery(QueryInterface $query) {
    $this->query = $query;
    return $this;
  }

  /**
   * Retrieves the Search API result set returned for this query.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface|null
   *   The result set of this query, or NULL if no search query has been
   *   created for this view. If a result set is returned, it might not contain
   *   the actual results yet if the query hasn't been executed yet.
   */
  public function getSearchApiResults() {
    return $this->query?->getResults();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $dependencies[$this->index->getConfigDependencyKey()][] = $this->index->getConfigDependencyName();
    return $dependencies;
  }

  //
  // Query interface methods (proxy to $this->query)
  //

  /**
   * Retrieves the parse mode.
   *
   * @return \Drupal\search_api\ParseMode\ParseModeInterface
   *   The parse mode.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getParseMode()
   */
  public function getParseMode() {
    if (!$this->shouldAbort()) {
      return $this->query->getParseMode();
    }
    return new Terms([], 'terms', []);
  }

  /**
   * Sets the parse mode.
   *
   * @param \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode
   *   The parse mode.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::setParseMode()
   */
  public function setParseMode(ParseModeInterface $parse_mode) {
    if (!$this->shouldAbort()) {
      $this->query->setParseMode($parse_mode);
    }
    return $this;
  }

  /**
   * Retrieves the languages that will be searched by this query.
   *
   * @return string[]|null
   *   The language codes of languages that will be searched by this query, or
   *   NULL if there shouldn't be any restriction on the language.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getLanguages()
   */
  public function getLanguages() {
    if (!$this->shouldAbort()) {
      return $this->query->getLanguages();
    }
    return NULL;
  }

  /**
   * Sets the languages that should be searched by this query.
   *
   * @param string[]|null $languages
   *   The language codes to search for, or NULL to not restrict the query to
   *   specific languages.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::setLanguages()
   */
  public function setLanguages(?array $languages = NULL) {
    if (!$this->shouldAbort()) {
      $this->query->setLanguages($languages);
    }
    return $this;
  }

  /**
   * Creates a new condition group to use with this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the condition group – either 'AND' or 'OR'.
   * @param string[] $tags
   *   (optional) Tags to set on the condition group.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   A condition group object that is set to use the specified conjunction.
   *
   * @see \Drupal\search_api\Query\QueryInterface::createConditionGroup()
   */
  public function createConditionGroup($conjunction = 'AND', array $tags = []) {
    if (!$this->shouldAbort()) {
      return $this->query->createConditionGroup($conjunction, $tags);
    }
    return new ConditionGroup($conjunction, $tags);
  }

  /**
   * Creates a new condition group and adds it to this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the condition group – either 'AND' or 'OR'.
   * @param string[] $tags
   *   (optional) Tags to set on the condition group.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   The newly added condition group object.
   */
  public function createAndAddConditionGroup(string $conjunction = 'AND', array $tags = []): ConditionGroupInterface {
    if (!$this->shouldAbort()) {
      return $this->query->createAndAddConditionGroup($conjunction, $tags);
    }
    return new ConditionGroup($conjunction, $tags);
  }

  /**
   * Sets the keys to search for.
   *
   * If this method is not called on the query before execution, this will be a
   * filter-only query.
   *
   * @param string|array|null $keys
   *   A string with the search keys, in one of the formats specified by
   *   getKeys(). A passed string will be parsed according to the set parse
   *   mode. Use NULL to not use any search keys.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::keys()
   */
  public function keys($keys = NULL) {
    if (!$this->shouldAbort()) {
      $this->query->keys($keys);
    }
    return $this;
  }

  /**
   * Sets the fields that will be searched for the search keys.
   *
   * If this is not called, all fulltext fields will be searched.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::setFulltextFields()
   */
  public function setFulltextFields(?array $fields = NULL) {
    if (!$this->shouldAbort()) {
      $this->query->setFulltextFields($fields);
    }
    return $this;
  }

  /**
   * Adds a nested condition group.
   *
   * If $group is given, the filter is added to the relevant filter group
   * instead.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   A condition group that should be added.
   * @param int $group
   *   (optional) The Views query filter group to add this filter to.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::addConditionGroup()
   */
  public function addConditionGroup(ConditionGroupInterface $condition_group, $group = 0) {
    if (!is_int($group) && !(is_string($group) && ctype_digit($group))) {
      trigger_error('Passing a non-integer as the second parameter of \Drupal\search_api\Plugin\views\query\SearchApiQuery::addConditionGroup() is deprecated in search_api:8.x-1.24 and is removed from search_api:2.0.0. If passing NULL or an empty string, pass 0 instead (or omit the parameter entirely). See https://www.drupal.org/node/3029582', E_USER_DEPRECATED);
    }
    if (!$this->shouldAbort()) {
      // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
      // the default group.
      if (empty($group)) {
        $group = 0;
      }
      $this->where[$group]['condition_groups'][] = $condition_group;
    }
    return $this;
  }

  /**
   * Adds a new ($field $operator $value) condition filter.
   *
   * @param string $field
   *   The ID of the field to filter on, for example "status". The special
   *   fields "search_api_datasource" (filter on datasource ID),
   *   "search_api_language" (filter on language code) and "search_api_id"
   *   (filter on item ID) can be used in addition to all indexed fields on the
   *   index.
   *   However, for filtering on language code, using
   *   \Drupal\search_api\Plugin\views\query\SearchApiQuery::setLanguages is the
   *   preferred method, unless a complex condition containing the language code
   *   is required.
   * @param mixed $value
   *   The value the field should have (or be related to by the operator). If
   *   $operator is "IN" or "NOT IN", $value has to be an array of values. If
   *   $operator is "BETWEEN" or "NOT BETWEEN", it has to be an array with
   *   exactly two values: the lower bound in key 0 and the upper bound in key 1
   *   (both inclusive). Otherwise, $value must be a scalar.
   * @param string $operator
   *   The operator to use for checking the constraint. The following operators
   *   are always supported for primitive types: "=", "<>", "<", "<=", ">=",
   *   ">", "IN", "NOT IN", "BETWEEN", "NOT BETWEEN". They have the same
   *   semantics as the corresponding SQL operators. Other operators might be
   *   added by backend features.
   *   If $field is a fulltext field, $operator can only be "=" or "<>", which
   *   are in this case interpreted as "contains" or "doesn't contain",
   *   respectively.
   *   If $value is NULL, $operator also can only be "=" or "<>", meaning the
   *   field must have no or some value, respectively.
   * @param int $group
   *   (optional) The Views query filter group to add this filter to.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::addCondition()
   */
  public function addCondition($field, $value, $operator = '=', $group = 0) {
    if (!is_int($group) && !(is_string($group) && ctype_digit($group))) {
      trigger_error('Passing a non-integer as the fourth parameter of \Drupal\search_api\Plugin\views\query\SearchApiQuery::addCondition() is deprecated in search_api:8.x-1.24 and is removed from search_api:2.0.0. If passing NULL or an empty string, pass 0 instead (or omit the parameter entirely). See https://www.drupal.org/node/3029582', E_USER_DEPRECATED);
    }
    if (!$this->shouldAbort()) {
      // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
      // the default group.
      if (empty($group)) {
        $group = 0;
      }
      $condition = [$field, $value, $operator];
      $this->where[$group]['conditions'][] = $condition;
    }
    return $this;
  }

  /**
   * Adds a simple condition to the query.
   *
   * This replicates the interface of Views' default SQL backend to simplify
   * the Views integration of the Search API. If you are writing Search
   * API-specific Views code, you should better use the addConditionGroup() or
   * addCondition() methods.
   *
   * @param int $group
   *   The condition group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param string|\Drupal\Core\Database\Query\ConditionInterface|\Drupal\search_api\Query\ConditionGroupInterface $field
   *   The ID of the field to check; or a filter object to add to the query; or,
   *   for compatibility purposes, a database condition object to transform into
   *   a search filter object and add to the query. If a field ID is passed and
   *   starts with a period (.), it will be stripped.
   * @param mixed $value
   *   (optional) The value the field should have (or be related to by the
   *   operator). Or NULL if an object is passed as $field.
   * @param string|null $operator
   *   (optional) The operator to use for checking the constraint. The following
   *   operators are supported for primitive types: "=", "<>", "<", "<=", ">=",
   *   ">". They have the same semantics as the corresponding SQL operators.
   *   If $field is a fulltext field, $operator can only be "=" or "<>", which
   *   are in this case interpreted as "contains" or "doesn't contain",
   *   respectively.
   *   If $value is NULL, $operator also can only be "=" or "<>", meaning the
   *   field must have no or some value, respectively.
   *   To stay compatible with Views, "!=" is supported as an alias for "<>".
   *   If an object is passed as $field, $operator should be NULL.
   *
   * @return $this
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addWhere()
   * @see \Drupal\search_api\Plugin\views\query\SearchApiQuery::addConditionGroup()
   * @see \Drupal\search_api\Plugin\views\query\SearchApiQuery::addCondition()
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    if ($this->shouldAbort()) {
      return $this;
    }

    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all the
    // default group.
    if (empty($group)) {
      $group = 0;
    }

    if (is_object($field)) {
      if ($field instanceof ConditionInterface) {
        $field = $this->transformDbCondition($field);
      }
      if ($field instanceof ConditionGroupInterface) {
        $this->where[$group]['condition_groups'][] = $field;
      }
      elseif (!$this->shouldAbort()) {
        // We only need to abort if that wasn't done by transformDbCondition()
        // already.
        $this->abort('Unexpected condition passed to addWhere().');
      }
    }
    else {
      $condition = [
        $this->sanitizeFieldId($field),
        $value,
        $this->sanitizeOperator($operator),
      ];
      $this->where[$group]['conditions'][] = $condition;
    }

    return $this;
  }

  /**
   * Retrieves the conjunction with which multiple filter groups are combined.
   *
   * @return string
   *   Either "AND" or "OR".
   */
  public function getGroupOperator() {
    return $this->groupOperator;
  }

  /**
   * Returns the group type of the given group.
   *
   * @param int $group
   *   The group whose type should be retrieved.
   *
   * @return string
   *   The group type – "AND" or "OR".
   */
  public function getGroupType($group) {
    return $this->where[$group]['type'] ?? 'AND';
  }

  /**
   * Transforms a database condition to an equivalent search filter.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $db_condition
   *   The condition to transform.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface|null
   *   A search filter equivalent to $condition, or NULL if the transformation
   *   failed.
   */
  protected function transformDbCondition(ConditionInterface $db_condition) {
    $conditions = $db_condition->conditions();
    $filter = $this->query->createConditionGroup($conditions['#conjunction']);
    unset($conditions['#conjunction']);
    foreach ($conditions as $condition) {
      if ($condition['operator'] === NULL) {
        $this->abort('Trying to include a raw SQL condition in a Search API query.');
        return NULL;
      }
      if ($condition['field'] instanceof ConditionInterface) {
        $nested_filter = $this->transformDbCondition($condition['field']);
        if ($nested_filter) {
          $filter->addConditionGroup($nested_filter);
        }
        else {
          return NULL;
        }
      }
      else {
        $filter->addCondition($this->sanitizeFieldId($condition['field']), $condition['value'], $this->sanitizeOperator($condition['operator']));
      }
    }
    return $filter;
  }

  /**
   * Adapts a field ID for use in a Search API query.
   *
   * This method will remove a leading period (.), if present. This is done
   * because in the SQL Views query plugin field IDs are always prefixed with a
   * table alias (in our case always empty) followed by a period.
   *
   * @param string $field_id
   *   The field ID to adapt for use in the Search API.
   *
   * @return string
   *   The sanitized field ID.
   */
  protected function sanitizeFieldId($field_id) {
    if ($field_id && $field_id[0] === '.') {
      $field_id = substr($field_id, 1);
    }
    return $field_id;
  }

  /**
   * Adapts an operator for use in a Search API query.
   *
   * This method maps Views' "!=" to the "<>" Search API uses.
   *
   * @param string $operator
   *   The operator to adapt for use in the Search API.
   *
   * @return string
   *   The sanitized operator.
   */
  protected function sanitizeOperator($operator) {
    if ($operator === '!=') {
      $operator = '<>';
    }
    elseif (in_array($operator, ['in', 'not in', 'between', 'not between'])) {
      $operator = strtoupper($operator);
    }
    elseif (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
      $operator = ($operator == 'IS NULL') ? '=' : '<>';
    }
    return $operator;
  }

  /**
   * Adds a sort directive to this search query.
   *
   * If no sort is manually set, the results will be sorted descending by
   * relevance.
   *
   * @param string $field
   *   The field to sort by. The special fields 'search_api_relevance' (sort by
   *   relevance) and 'search_api_id' (sort by item id) may be used.
   * @param string $order
   *   The order to sort items in - either 'ASC' or 'DESC'.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::sort()
   */
  public function sort($field, $order = 'ASC') {
    if (!$this->shouldAbort()) {
      $this->query->sort($field, $order);
    }
    return $this;
  }

  /**
   * Adds an ORDER BY clause to the query.
   *
   * This replicates the interface of Views' default SQL backend to simplify
   * the Views integration of the Search API. If you are writing Search
   * API-specific Views code, you should better use the sort() method directly.
   *
   * Currently, only random sorting (by passing "rand" as the table) is
   * supported (for backends that support it), all other calls are silently
   * ignored.
   *
   * @param string|null $table
   *   The table this field is part of. If you want to order the results
   *   randomly, use "rand" as table and nothing else. Otherwise, use NULL.
   * @param string|null $field
   *   (optional) Ignored.
   * @param string $order
   *   (optional) Either ASC or DESC. (Lowercase variants will be uppercased.)
   * @param string $alias
   *   (optional) The field to sort on. Unless sorting randomly, "search_api_id"
   *   and "search_api_datasource" are supported.
   * @param array $params
   *   (optional) For sorting randomly, additional random sort parameters can be
   *   passed through here. Otherwise, the parameter is ignored.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the searched index's server couldn't be loaded.
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addOrderBy()
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', array $params = []) {
    $server = $this->getIndex()->getServerInstance();
    if ($table == 'rand') {
      if ($server->supportsFeature('search_api_random_sort')) {
        $this->sort('search_api_random', $order);
        if ($params) {
          $this->setOption('search_api_random_sort', $params);
        }
      }
      else {
        $variables['%server'] = $server->label() ?? $server->id();
        $this->getLogger()->warning('Tried to sort results randomly on server %server which does not support random sorting.', $variables);
      }
    }
    elseif (in_array($alias, ['search_api_id', 'search_api_datasource'])) {
      $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
      $this->sort($alias, $order);
    }
  }

  /**
   * Adds a range of results to return.
   *
   * This will be saved in the query's options. If called without parameters,
   * this will remove all range restrictions previously set.
   *
   * @param int|null $offset
   *   The zero-based offset of the first result returned.
   * @param int|null $limit
   *   The number of results to return.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::range()
   */
  public function range($offset = NULL, $limit = NULL) {
    if (!$this->shouldAbort()) {
      $this->query->range($offset, $limit);
    }
    return $this;
  }

  /**
   * Retrieves the index associated with this search.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index this query should be executed on.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getIndex()
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * Retrieves the search keys for this query.
   *
   * @return array|string|null
   *   This object's search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput(). Or NULL if
   *   the query doesn't have any search keys set.
   *
   * @see \Drupal\search_api\Plugin\views\query\SearchApiQuery::keys()
   *
   * @see \Drupal\search_api\Query\QueryInterface::getKeys()
   */
  public function &getKeys() {
    if (!$this->shouldAbort()) {
      return $this->query->getKeys();
    }
    $ret = NULL;
    return $ret;
  }

  /**
   * Retrieves the unparsed search keys for this query as originally entered.
   *
   * @return array|string|null
   *   The unprocessed search keys, exactly as passed to this object. Has the
   *   same format as the return value of getKeys().
   *
   * @see keys()
   *
   * @see \Drupal\search_api\Query\QueryInterface::getOriginalKeys()
   */
  public function getOriginalKeys() {
    if (!$this->shouldAbort()) {
      return $this->query->getOriginalKeys();
    }
    return NULL;
  }

  /**
   * Retrieves the fulltext fields that will be searched for the search keys.
   *
   * @return string[]|null
   *   An array containing the fields that should be searched for the search
   *   keys.
   *
   * @see setFulltextFields()
   * @see \Drupal\search_api\Query\QueryInterface::getFulltextFields()
   */
  public function &getFulltextFields() {
    if (!$this->shouldAbort()) {
      return $this->query->getFulltextFields();
    }
    $ret = NULL;
    return $ret;
  }

  /**
   * Retrieves the filter object associated with this search query.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   This object's associated filter object.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getConditionGroup()
   */
  public function getFilter() {
    if (!$this->shouldAbort()) {
      return $this->query->getConditionGroup();
    }
    return NULL;
  }

  /**
   * Retrieves the sorts set for this query.
   *
   * @return array
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   *
   * @see sort()
   *
   * @see \Drupal\search_api\Query\QueryInterface::getSorts()
   */
  public function &getSort() {
    if (!$this->shouldAbort()) {
      return $this->query->getSorts();
    }
    $ret = NULL;
    return $ret;
  }

  /**
   * Retrieves an option set on this search query.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $default
   *   The value to return if the specified option is not set.
   *
   * @return mixed
   *   The value of the option with the specified name, if set. NULL otherwise.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getOption()
   */
  public function getOption($name, $default = NULL) {
    if (!$this->shouldAbort()) {
      return $this->query->getOption($name, $default);
    }
    return $default;
  }

  /**
   * Sets an option for this search query.
   *
   * @param string $name
   *   The name of an option. The following options are recognized by default:
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *   - 'search id': A string that will be used as the identifier when storing
   *     this search in the Search API's static cache.
   *   - 'skip result count': If present and set to TRUE, the search's result
   *     count will not be needed. Service classes can check for this option to
   *     possibly avoid executing expensive operations to compute the result
   *     count in cases where it is not needed.
   *   - search_api_access_account: The account which will be used for entity
   *     access checks, if available and enabled for the index.
   *   - search_api_bypass_access: If set to TRUE, entity access checks will be
   *     skipped, even if enabled for the index.
   *   However, contrib modules might introduce arbitrary other keys with their
   *   own, special meaning. (Usually they should be prefixed with the module
   *   name, though, to avoid conflicts.)
   * @param mixed $value
   *   The new value of the option.
   *
   * @return mixed
   *   The option's previous value, or NULL if none was set.
   *
   * @see \Drupal\search_api\Query\QueryInterface::setOption()
   */
  public function setOption($name, $value) {
    if (!$this->shouldAbort()) {
      return $this->query->setOption($name, $value);
    }
    return NULL;
  }

  /**
   * Retrieves all options set for this search query.
   *
   * The return value is a reference to the options so they can also be altered
   * this way.
   *
   * @return array
   *   An associative array of query options.
   *
   * @see \Drupal\search_api\Query\QueryInterface::getOptions()
   */
  public function &getOptions() {
    if (!$this->shouldAbort()) {
      return $this->query->getOptions();
    }
    $ret = NULL;
    return $ret;
  }

  //
  // Methods from Views' SQL query plugin, to simplify integration.
  //

  /**
   * Ensures a table exists in the query.
   *
   * This replicates the interface of Views' default SQL backend to simplify
   * the Views integration of the Search API. Since the Search API has no
   * concept of "tables", this method implementation does nothing. If you are
   * writing Search API-specific Views code, there is therefore no reason at all
   * to call this method.
   * See https://www.drupal.org/node/2484565 for more information.
   *
   * @return string
   *   An empty string.
   */
  public function ensureTable() {
    return '';
  }

}
