<?php

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;

class MukurtuDigitalHeritageBrowseController extends ControllerBase {
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
      'db' => 'mukurtu_digital_heritage_browse',
      'solr' => 'mukurtu_digital_heritage_browse_solr',
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
      'db' => 'search_api:views_block__mukurtu_digital_heritage_browse__mukurtu_digital_heritage_browse_block',
      'solr' => 'search_api:views_block__mukurtu_digital_heritage_browse_solr__mukurtu_digital_heritage_browse_block',
    ];

    return $views[$this->backend];
  }

  public function content() {
    // Map browse link.
    $options = ['attributes' => ['id' => 'mukurtu-browse-mode-switch-link']];

    $map_browse_link = NULL;
    $access_manager = \Drupal::accessManager();
    if ($access_manager->checkNamedRoute('mukurtu_browse.map_browse_digital_heritage_page')) {
      $map_browse_link = Link::createFromRoute(t('Switch to Map View'), 'mukurtu_browse.map_browse_digital_heritage_page', [], $options);
    }

    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_digital_heritage_browse_block',
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
      '#maplink' => $map_browse_link,
      '#results' => $browse_view_block,
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
