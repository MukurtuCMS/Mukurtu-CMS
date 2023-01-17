<?php

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Link;

class MukurtuBrowseController extends ControllerBase {

  public function content($view) {
    // Map browse link.
    $options = ['attributes' => ['id' => 'mukurtu-browse-mode-switch-link']];

    $map_browse_link = NULL;
    $access_manager = \Drupal::accessManager();
    if ($access_manager->checkNamedRoute('mukurtu_browse.map_browse_page')) {
      $map_browse_link = Link::createFromRoute(t('Switch to Map View'), 'mukurtu_browse.map_browse_page', [], $options);
    }

    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => $view,
      '#display_id' => "{$view}_block",
      '#embed' => TRUE,
    ];

    // Load all facets configured to use our browse block as a datasource.

    $facetSourceId = "search_api:views_block__{$view}__{$view}_block";
    $facetEntities = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => $facetSourceId]);

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

}
