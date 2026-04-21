<?php

namespace Drupal\facets_searchbox_widget\Plugin\facets\widget;

use Drupal\facets\Plugin\facets\widget\CheckboxWidget;

/**
 * The searchable checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "searchbox_checkbox",
 *   label = @Translation("Searchable list of checkboxes"),
 *   description = @Translation("A configurable widget that shows a searchable list of checkboxes"),
 * )
 */
class SearchboxCheckboxWidget extends CheckboxWidget {

  /**
   * {@inheritdoc}
   */
  protected function appendWidgetLibrary(array &$build) {
    $build['#attributes']['class'][] = 'js-facets-checkbox-links';
    $build['#attached']['library'][] = 'facets/drupal.facets.checkbox-widget';
    $build['#attached']['library'][] = 'facets_searchbox_widget/searchbox';
  }

}
