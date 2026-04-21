<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[FiltersWidget(
  id: 'default',
  title: new TranslatableMarkup('Default'),
)]
class DefaultWidget extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    return TRUE;
  }

}
