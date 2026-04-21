<?php

namespace Drupal\facets\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;

/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "checkbox",
 *   label = @Translation("List of checkboxes"),
 *   description = @Translation("A configurable widget that shows a list of checkboxes"),
 * )
 */
class CheckboxWidget extends LinksWidget {

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(FacetInterface $facet, ResultInterface $result) {
    $items = parent::buildListItems($facet, $result);

    $items['#attributes']['data-drupal-facet-widget-element-class'] = 'facets-checkbox';

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  protected function appendWidgetLibrary(array &$build) {
    $build['#attributes']['class'][] = 'js-facets-checkbox-links';
    $build['#attached']['library'][] = 'facets/drupal.facets.checkbox-widget';
  }

}
