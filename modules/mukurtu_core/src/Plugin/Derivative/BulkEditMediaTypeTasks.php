<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Derivative;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One tab per media type on the bulk edit media page.
 */
class BulkEditMediaTypeTasks extends BulkEditTypeTasks {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('mukurtu_core.bulk_edit_media_columns'));
  }

}
