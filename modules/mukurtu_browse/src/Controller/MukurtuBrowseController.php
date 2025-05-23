<?php

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;

class MukurtuBrowseController extends ControllerBase {
  protected $backend;

  public function __construct() {
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName() {
    $views = [
      'db' => 'mukurtu_browse',
      'solr' => 'mukurtu_browse_solr',
    ];

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
      'db' => 'search_api:views_block__mukurtu_browse__mukurtu_browse_block',
      'solr' => 'search_api:views_block__mukurtu_browse_solr__mukurtu_browse_block',
    ];

    return $views[$this->backend];
  }

  public function content() {
    // Map browse link.
    $map_browse_link = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Map'),
      '#attributes' => [
        'id' => 'mukurtu-browse-map',
        'aria-label' => $this->t('Switch to Map'),
      ],
    ];

    // Render the browse view block. This is the list display.
    $list_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block',
      '#embed' => TRUE,
    ];

    // Render the browse view block. This is the grid display.
    $grid_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block_grid',
      '#embed' => TRUE,
    ];

    // Render the browse view block. This is the map display.
    $map_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block_map',
      '#embed' => TRUE,
    ];

    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = \Drupal::entityTypeManager()
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
          $access_result = $block_plugin->access(\Drupal::currentUser());
          if ($access_result) {
            $facets[$facet_id] = $block_plugin->build();
          }
        }
      }
    }

    return [
      '#theme' => 'mukurtu_browse',
      '#is_dh' => false,
      '#maplink' => $map_browse_link,
      '#list_results' => $list_view_block,
      '#grid_results' => $grid_view_block,
      '#map_results' => $map_view_block,
      '#facets' => $facets,
      '#attached' => [
        'library' => [
          'mukurtu_browse/mukurtu-browse-view-switch',
        ],
      ],
    ];
  }

  /**
   * Check access for the browse DH route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    $view = Views::getView($this->getViewName());
    if (!$view) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
