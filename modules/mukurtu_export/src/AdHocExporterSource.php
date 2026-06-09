<?php

namespace Drupal\mukurtu_export;

/**
 * Exporter source backed by an ad-hoc array of entity IDs.
 *
 * Used when content is exported directly without being added to a named list.
 */
class AdHocExporterSource implements MukurtuExporterSourceInterface {

  public function __construct(protected array $items) {}

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    return $this->items;
  }

}
