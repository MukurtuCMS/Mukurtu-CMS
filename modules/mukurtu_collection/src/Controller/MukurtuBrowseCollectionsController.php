<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MukurtuBrowseCollectionsController extends ControllerBase {

  protected $backend;
  protected $moduleHandler;

  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
    $this->moduleHandler = $moduleHandler;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName() {
    $views = [
      'db' => 'mukurtu_browse_collections',
    ];
    if ($this->moduleHandler->moduleExists('mukurtu_solr')) {
      $views['solr'] = 'mukurtu_browse_collections_solr';
    }

    return $views[$this->backend];
  }

  /**
   * Return the facet source ID to use based on the search backend config.
   *
   * @return string
   *   The facet source ID.
   */
  protected function getFacetSourceId() {
    $views = [
      'db' => 'search_api:views_block__mukurtu_browse_collections__browse_collections_block',
      'solr' => 'search_api:views_block__mukurtu_browse_collections_solr__browse_collections_block',
    ];

    return $views[$this->backend];
  }

  public function content() {
    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'browse_collections_block',
      '#embed' => TRUE,
    ];

    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = $this->entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => $this->getFacetSourceId()]);

    // Render the facet block for each of them.
    $facets = [];
    if ($facetEntities) {
      $block_manager = \Drupal::service('plugin.manager.block');
      foreach ($facetEntities as $facet_id => $facetEntity) {
        $config = [];
        $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
        if ($block_plugin) {
          $access_result = $block_plugin->access($this->currentUser());
          if ($access_result) {
            $facets[$facet_id] = $block_plugin->build();
          }
        }
      }
    }

    return [
      '#theme' => 'mukurtu_collection_browse',
      '#results' => $browse_view_block,
      '#facets' => $facets,
    ];
  }

  /**
   * Check access for the Collections route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Check if the collections browse view is empty.
    $view_name = $this->getViewName();
    $view = Views::getView($view_name);
    if (!$view) {
      return AccessResult::forbidden()->addCacheTags(["config:views.view.$view_name"]);
    }

    $view->setDisplay('default');
    $view->execute();

    // Which collections a user can see depends on their protocols. The
    // view's tags are invalidated when collections are saved or indexed, so a
    // cached 403 doesn't outlast the first collection someone can see.
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->addCacheTags($view->getCacheTags());

    if (!empty($view->result)) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    // Users who can create collections get the page even when it's empty.
    $create_access = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('collection', $account, [], TRUE);
    $result = $create_access->isAllowed() ? AccessResult::allowed() : AccessResult::forbidden();
    return $result
      ->addCacheableDependency($cacheability)
      ->addCacheableDependency($create_access);
  }

}
