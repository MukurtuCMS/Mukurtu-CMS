<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\search_api\Attribute\SearchApiViewsDisplay;

/**
 * Represents a Views page display.
 */
#[SearchApiViewsDisplay(
  id: 'views_page',
  deriver: ViewsDisplayDeriver::class,
  views_display_type: 'page'
)]
class ViewsPage extends ViewsDisplayBase {}
