<?php

namespace Drupal\search_api\Plugin\search_api\tracker;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Tracker\TrackerPluginBase;

/**
 * Provides a tracker implementation which uses a FIFO-like processing order.
 */
#[SearchApiTracker(
  id: 'default',
  label: new TranslatableMarkup('Default'),
  description: new TranslatableMarkup('Default index tracker which uses a simple database table for tracking items.')
)]
class Basic extends TrackerPluginBase implements PluginFormInterface {
}
