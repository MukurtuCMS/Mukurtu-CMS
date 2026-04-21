<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\pager;

use Drupal\better_exposed_filters\Attribute\PagerWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[PagerWidget(
  id: 'default',
  title: new TranslatableMarkup('Default'),
)]
class DefaultWidget extends PagerWidgetBase {

}
