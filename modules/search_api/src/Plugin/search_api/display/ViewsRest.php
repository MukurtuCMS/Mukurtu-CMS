<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\search_api\Attribute\SearchApiViewsDisplay;

/**
 * Represents a Views REST display.
 */
#[SearchApiViewsDisplay(
  id: 'views_rest',
  deriver: ViewsDisplayDeriver::class,
  views_display_type: 'rest_export'
)]
class ViewsRest extends ViewsDisplayBase {}
