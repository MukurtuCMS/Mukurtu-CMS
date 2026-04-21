<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\search_api\Attribute\SearchApiViewsDisplay;

/**
 * Represents a Views feed display.
 */
#[SearchApiViewsDisplay(
  id: 'views_feed',
  deriver: ViewsDisplayDeriver::class,
  views_display_type: 'feed'
)]
class ViewsFeed extends ViewsDisplayBase {}
