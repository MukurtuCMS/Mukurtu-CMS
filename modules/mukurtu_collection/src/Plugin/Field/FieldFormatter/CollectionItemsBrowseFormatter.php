<?php

namespace Drupal\mukurtu_collection\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\views\Views;

/**
 * Renders the items in a collection with a grid/list/map view switcher.
 *
 * @FieldFormatter(
 *   id = "mukurtu_collection_items_browse",
 *   label = @Translation("Collection items grid/list/map"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CollectionItemsBrowseFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $collection = $items->getEntity();
    $nid = $collection->id();

    if (!$nid) {
      return [];
    }

    $displays = [
      'list_results' => 'mukurtu_collection_items_block',
      'grid_results' => 'mukurtu_collection_items_block_grid',
      'map_results' => 'mukurtu_collection_items_block_map',
    ];

    $results = [];
    foreach ($displays as $key => $display_id) {
      $view = Views::getView('mukurtu_collection_items');
      if (!$view || !$view->access($display_id)) {
        $results[$key] = [];
        continue;
      }
      $results[$key] = $view->buildRenderable($display_id, [$nid]);
    }

    return [
      [
        '#theme' => 'mukurtu_collection_items_browse',
        '#list_results' => $results['list_results'],
        '#grid_results' => $results['grid_results'],
        '#map_results' => $results['map_results'],
        '#attached' => [
          'library' => ['mukurtu_browse/mukurtu-browse-view-switch'],
        ],
      ],
    ];
  }

}
