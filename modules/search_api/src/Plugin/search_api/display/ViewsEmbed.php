<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\search_api\Attribute\SearchApiViewsDisplay;

/**
 * Represents a Views embed display.
 */
#[SearchApiViewsDisplay(
  id: 'views_embed',
  deriver: ViewsDisplayDeriver::class,
  views_display_type: 'embed'
)]
class ViewsEmbed extends ViewsDisplayBase {}
