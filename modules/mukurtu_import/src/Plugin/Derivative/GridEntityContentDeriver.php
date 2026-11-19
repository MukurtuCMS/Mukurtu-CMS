<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * One grid destination per entity type with a bulk edit page.
 */
class GridEntityContentDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (['node', 'media'] as $entity_type_id) {
      $this->derivatives[$entity_type_id] = $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
