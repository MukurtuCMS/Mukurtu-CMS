<?php

namespace Drupal\facets_searchbox_widget\Plugin\facets\widget;

use Drupal\facets\Plugin\facets\widget\LinksWidget;

/**
 * The searchable links widget.
 *
 * @FacetsWidget(
 *   id = "searchbox_links",
 *   label = @Translation("Searchable list of links"),
 *   description = @Translation("A simple widget that shows a searchable list of links"),
 * )
 */
class SearchboxLinksWidget extends LinksWidget {

  /**
   * {@inheritdoc}
   */
  protected function appendWidgetLibrary(array &$build) {
    $build['#attributes']['class'][] = 'js-facets-links';
    $build['#attached']['library'][] = 'facets/drupal.facets.link-widget';
    $build['#attached']['library'][] = 'facets_searchbox_widget/searchbox';
  }

}
