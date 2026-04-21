<?php

namespace Drupal\search_api\Hook;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\IndexTaskManager;
use Drupal\search_api\Task\ServerTaskManagerInterface;
use Drupal\search_api\Task\TaskManagerInterface;
use Drupal\search_api\Utility\TrackingHelperInterface;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains hook implementations for the Search API module.
 */
class SearchApiHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected RequestStack $requestStack,
    protected TaskManagerInterface $taskManager,
    protected ServerTaskManagerInterface $serverTaskManager,
    protected TrackingHelperInterface $trackingHelper,
    #[Autowire(service: 'plugin.manager.search_api.display')]
    protected PluginManagerInterface $displayPluginManager,
    protected MessengerInterface $messenger,
    #[Autowire(service: 'logger.channel.search_api')]
    protected LoggerInterface $logger,
    #[Autowire(service: 'plugin.manager.views.row')]
    protected ?PluginManagerInterface $viewsRowPluginManager = NULL,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name): string|\Stringable|array|null {
    switch ($route_name) {
      case 'search_api.overview':
        $message = t('Below is a list of indexes grouped by the server they are associated with. A server is the definition of the actual indexing, querying and storage engine (for example, an Apache Solr server, the database, …). An index defines the indexed content (for example, all content and all comments on "Article" posts).');

        $search_module_warning = $this->checkForSearchModule();
        if ($search_module_warning) {
          $message = new FormattableMarkup("<p>@message</p><p>@warning</p>", [
            '@message' => $message,
            '@warning' => $search_module_warning,
          ]);
        }
        return $message;
    }
    return NULL;
  }

  /**
   * Implements hook_cron().
   *
   * This will first execute pending tasks (if there are any). After that, items
   * will be indexed on all enabled indexes with a non-zero cron limit. Indexing
   * will run for the time set in the cron_worker_runtime config setting
   * (defaulting to 15 seconds), but will at least index one batch of items on
   * each index.
   */
  #[Hook('cron')]
  public function cron(): void {
    // Execute pending server tasks.
    $this->serverTaskManager->execute();

    // Load all enabled, not read-only indexes.
    $conditions = [
      'status' => TRUE,
    ];
    try {
      $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    }
    catch (PluginException) {
      return;
    }
    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $index_storage->loadByProperties($conditions);
    if (!$indexes) {
      return;
    }

    // Add items to the tracking system for all indexes for which this hasn't
    // happened yet.
    foreach ($indexes as $index_id => $index) {
      $conditions = [
        'type' => IndexTaskManager::TRACK_ITEMS_TASK_TYPE,
        'index_id' => $index_id,
      ];
      try {
        $this->taskManager->executeSingleTask($conditions);
      }
      catch (SearchApiException $e) {
        Error::logException($this->logger, $e);
      }

      // Filter out read-only indexes here, since we want to have tracking but
      // not index items for them.
      if ($index->isReadOnly()) {
        unset($indexes[$index_id]);
      }
    }

    // Now index items.
    // Remember servers which threw an exception.
    $ignored_servers = [];

    // Continue indexing, one batch from each index, until the time is up, but at
    // least index one batch per index.
    $settings = $this->configFactory->get('search_api.settings');
    $default_cron_limit = $settings->get('default_cron_limit');
    $end = time() + $settings->get('cron_worker_runtime');
    $first_pass = TRUE;
    while (TRUE) {
      if (!$indexes) {
        break;
      }
      foreach ($indexes as $id => $index) {
        if (!$first_pass && time() >= $end) {
          break 2;
        }
        if (!empty($ignored_servers[$index->getServerId()])) {
          continue;
        }

        $limit = $index->getOption('cron_limit', $default_cron_limit);
        $num = 0;
        if ($limit) {
          $num = $index->indexItems($limit);
          if ($num) {
            $variables = [
              '@num' => $num,
              '%name' => $index->label() ?? $index->id(),
            ];
            $this->logger->info('Indexed @num items for index %name.', $variables);
          }
        }
        if (!$num) {
          // Couldn't index any items => stop indexing for this index in this
          // cron run.
          unset($indexes[$id]);
        }
      }
      $first_pass = FALSE;
    }
  }

  /**
   * Implements hook_config_import_steps_alter().
   */
  #[Hook('config_import_steps_alter')]
  public function configImportStepsAlter(&$sync_steps, ConfigImporter $config_importer): void {
    if (Settings::get('search_api.disable_tracking_on_import', FALSE)) {
      return;
    }
    $new = $config_importer->getUnprocessedConfiguration('create');
    $changed = $config_importer->getUnprocessedConfiguration('update');
    $new_or_changed = array_merge($new, $changed);
    try {
      $entity_type = $this->entityTypeManager->getDefinition('search_api_index');
    }
    catch (PluginNotFoundException) {
      return;
    }
    $prefix = $entity_type->getConfigPrefix() . '.';
    $prefix_length = strlen($prefix);
    foreach ($new_or_changed as $config_id) {
      if (substr($config_id, 0, $prefix_length) === $prefix) {
        $sync_steps[] = ['Drupal\search_api\Task\IndexTaskManager', 'processIndexTasks'];
      }
    }
  }

  /**
   * Implements hook_entity_update().
   *
   * Attempts to mark all items as needing to be reindexed that contain an
   * indirect reference to the changed entity.
   *
   * @see \Drupal\search_api\Utility\TrackingHelper::trackReferencedEntityUpdate()
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->trackingHelper->trackReferencedEntityUpdate($entity);
  }

  /**
   * Implements hook_entity_delete().
   *
   *  Attempts to mark all items as needing to be reindexed that contain an
   *  indirect reference to the deleted entity.
   *
   * @see \Drupal\search_api\Utility\TrackingHelper::trackReferencedEntityUpdate()
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->trackingHelper->trackReferencedEntityUpdate($entity, TRUE);
  }

  /**
   * Implements hook_node_access_records_alter().
   *
   * Marks the node and its comments changed for indexes that use the "Content
   * access" processor.
   */
  #[Hook('node_access_records_alter')]
  public function nodeAccessRecordsAlter(array &$grants, NodeInterface $node): void {
    try {
      // @todo This could use an entity query to only retrieve the indexes that
      //   have the "content_access" processor enabled.
      /** @var \Drupal\search_api\IndexInterface[] $indexes */
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (PluginException) {
      return;
    }

    foreach ($indexes as $index) {
      if (!$index->hasValidTracker() || !$index->status()) {
        continue;
      }
      if (!$index->isValidProcessor('content_access')) {
        continue;
      }

      foreach ($index->getDatasources() as $datasource_id => $datasource) {
        switch ($datasource->getEntityTypeId()) {
          case 'node':
            // Don't index the node if search_api_skip_tracking is set on it.
            if (!empty($node->search_api_skip_tracking)) {
              continue 2;
            }
            $item_id = $datasource->getItemId($node->getTypedData());
            if ($item_id !== NULL) {
              $index->trackItemsUpdated($datasource_id, [$item_id]);
            }
            break;

          case 'comment':
            if (!isset($comments)) {
              try {
                $comment_storage = $this->entityTypeManager->getStorage('comment');
                $comment_ids = $comment_storage->getQuery()
                  ->accessCheck(FALSE)
                  ->condition('entity_id', (int) $node->id())
                  ->condition('entity_type', 'node')
                  ->execute();
                /** @var \Drupal\comment\CommentInterface[] $comments */
                $comments = $comment_storage->loadMultiple($comment_ids);
              }
              catch (PluginException) {
                $comments = [];
              }
            }
            $item_ids = [];
            foreach ($comments as $comment) {
              $item_id = $datasource->getItemId($comment->getTypedData());
              if ($item_id !== NULL) {
                $item_ids[] = $item_id;
              }
            }
            if ($item_ids) {
              $index->trackItemsUpdated($datasource_id, $item_ids);
            }
            break;
        }
      }
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'search_api_admin_fields_table' => [
        'render element' => 'element',
      ],
      'search_api_admin_data_type_table' => [
        'variables' => [
          'data_types' => [],
          'fallback_mapping' => [],
          'table' => NULL,
        ],
      ],
      'search_api_form_item_list' => [
        'render element' => 'element',
      ],
      'search_api_server' => [
        'variables' => [
          'server' => NULL,
          'server_info_table' => NULL,
          'description' => NULL,
        ],
      ],
      'search_api_index' => [
        'variables' => [
          'index' => NULL,
          'server_count' => NULL,
          'server_count_error' => NULL,
          'index_process' => NULL,
          'description' => NULL,
          'table' => NULL,
        ],
      ],
      'search_api_excerpt' => [
        'variables' => [
          'excerpt' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for type "search_api_index".
   *
   * Clears the Views row plugin cache, so our row plugin will become available
   * for new indexes right away.
   *
   * @see \Drupal\search_api\Hook\SearchApiViewsHooks::rowPluginsAlter()
   */
  #[Hook('search_api_index_insert')]
  public function searchApiIndexInsert(Index $index): void {
    $this->viewsRowPluginManager?->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for type "view".
   */
  #[Hook('view_insert')]
  public function viewInsert(ViewEntityInterface $view): void {
    $this->viewCrudEvent($view);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for type "view".
   */
  #[Hook('view_presave')]
  public function viewPresave(ViewEntityInterface $view): void {
    // Set query type to "search_api_query" and disable Views' default caching
    // mechanisms on Search API views.
    if (SearchApiQuery::getIndexFromTable($view->get('base_table'), $this->entityTypeManager)) {
      $displays = $view->get('display');
      $changed_cache = FALSE;
      foreach ($displays as $id => $display) {
        if (($display['display_options']['query']['type'] ?? '') === 'views_query') {
          $displays[$id]['display_options']['query']['type'] = 'search_api_query';
        }
        if (in_array($display['display_options']['cache']['type'] ?? '', ['none', 'tag', 'time'])) {
          $displays[$id]['display_options']['cache']['type'] = 'search_api_none';
          $changed_cache = TRUE;
        }
      }
      $view->set('display', $displays);

      if ($changed_cache) {
        $warning = t('The selected caching mechanism does not work with views on Search API indexes. Use one of the Search API-specific caching options. The selected caching mechanism was changed accordingly for the view %view.', ['%view' => $view->label()]);
        $this->messenger->addWarning($warning);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for type "view".
   */
  #[Hook('view_update')]
  public function viewUpdate(ViewEntityInterface $view): void {
    $this->viewCrudEvent($view);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for type "view".
   */
  #[Hook('view_delete')]
  public function viewDelete(ViewEntityInterface $view): void {
    $this->viewCrudEvent($view);
  }

  /**
   * Reacts to a view CRUD event.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view that was created, changed or deleted.
   */
  protected function viewCrudEvent(ViewEntityInterface $view): void {
    // Whenever a view is created, updated (displays might have been added or
    // removed) or deleted, we need to clear our cached display definitions.
    if (SearchApiQuery::getIndexFromTable($view->get('base_table'), $this->entityTypeManager)) {
      $this->displayPluginManager->clearCachedDefinitions();
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for form "views_ui_edit_display_form".
   */
  #[Hook('form_views_ui_edit_display_form_alter')]
  public function viewsUiEditDisplayFormAlter(&$form, FormStateInterface $form_state): void {
    // Disable Views' default caching mechanisms on Search API views.
    $displays = $form_state->getStorage()['view']->get('display');
    if ($displays['default']['display_options']['query']['type'] === 'search_api_query') {
      unset($form['options']['cache']['type']['#options']['none']);
      unset($form['options']['cache']['type']['#options']['tag']);
      unset($form['options']['cache']['type']['#options']['time']);
    }
  }

  /**
   * Returns a warning message if the Core Search module is enabled.
   *
   * @return \Stringable|null
   *   A warning message if needed, NULL otherwise.
   *
   * @see search_api_requirements()
   */
  public function checkForSearchModule(): ?\Stringable {
    if ($this->moduleHandler->moduleExists('search')) {
      $args = [
        ':url' => Url::fromRoute('system.modules_uninstall')->toString(),
        ':documentation' => 'https://www.drupal.org/docs/8/modules/search-api/getting-started/common-pitfalls#core-search',
      ];
      return t('The default Drupal core Search module is still enabled. If you are using Search API, you probably want to <a href=":url">uninstall</a> the Search module for performance reasons. For more information see <a href=":documentation">the Search API handbook</a>.', $args);
    }
    return NULL;
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form().
   *
   * Custom integration for facets. When a Views exposed filter is modified on a
   * search results page it will lose any facets which have been already selected.
   * This adds hidden fields for each facet so their values are retained.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function viewsExposedFormAlter(&$form, FormStateInterface $form_state): void {
    // Retrieve the view object and the query plugin.
    $storage = $form_state->getStorage();
    if (!isset($storage['view'])) {
      return;
    }
    $view = $storage['view'];
    if (!($view instanceof ViewExecutable)) {
      return;
    }
    $query_plugin = $view->getQuery();

    // Make sure the view is based on Search API and has the "Preserve facets"
    // option enabled, and that the Facets module is installed.
    $preserve_facets = !empty($query_plugin->options['preserve_facet_query_args'])
      && $query_plugin instanceof SearchApiQuery
      && $this->moduleHandler->moduleExists('facets');
    if ($preserve_facets) {
      $filter_key = 'f';

      // Attempt to retrieve the facet source to use the actual facets filter
      // parameter as configured by the admin. (Facet source config entities are
      // not always actually saved in the storage, if the admin didn't change
      // their settings.)
      $query = $query_plugin->getSearchApiQuery();
      if (empty($query)) {
        return;
      }
      $display_id = $query->getSearchId(FALSE);
      $facet_source_id = str_replace(':', '__', 'search_api:' . $display_id);
      try {
        $facet_source = $this->entityTypeManager
          ->getStorage('facets_facet_source')
          ->load($facet_source_id);
      }
      catch (PluginException) {
        return;
      }
      if ($facet_source) {
        $filter_key = $facet_source->getFilterKey() ?: 'f';
      }

      // Get the active facet filters from the query parameters.
      $query_params = $this->requestStack->getCurrentRequest()->query->all();
      $filters = $query_params[$filter_key] ?? [];

      // Do not iterate over facet filters if the parameter is not an array.
      if (!is_array($filters)) {
        return;
      }

      // Iterate through the facet filters.
      foreach ($filters as $key => $value) {
        if (!is_string($value)) {
          continue;
        }
        // Add a hidden form field for the facet parameter.
        $form[$filter_key][$key] = [
          '#type' => 'hidden',
          '#value' => $value,
          '#name' => "{$filter_key}[$key]",
        ];
      }
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra = [];

    // Add an extra "excerpt" field to every content entity.
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityType) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        foreach ($bundles as $bundle => $data) {
          $extra[$entity_type_id][$bundle]['display']['search_api_excerpt'] = [
            'label' => t('Search result excerpt'),
            'description' => t('An excerpt provided by Search API when rendered in a search.'),
            'weight' => 100,
            'visible' => FALSE,
          ];
        }
      }
    }
    return $extra;
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    $excerpt_component = $display->getComponent('search_api_excerpt');
    if ($excerpt_component !== NULL && isset($build['#search_api_excerpt'])) {
      $build['search_api_excerpt'] = [
        '#theme' => 'search_api_excerpt',
        '#excerpt' => [
          '#type' => 'markup',
          '#markup' => $build['#search_api_excerpt'],
        ],
        '#cache' => [
          'contexts' => ['url.query_args'],
        ],
      ];
    }
  }

}
