<?php

namespace Drupal\facets_exposed_filters\Plugin\search_api\display;

use Drupal\search_api\Plugin\search_api\display\ViewsDisplayBase;

/**
 * Represents a Views default display.
 *
 * @SearchApiDisplay(
 *   id = "views_default",
 *   views_display_type = "default",
 *   deriver = "Drupal\search_api\Plugin\search_api\display\ViewsDisplayDeriver"
 * )
 */
class ViewsDefault extends ViewsDisplayBase {}
