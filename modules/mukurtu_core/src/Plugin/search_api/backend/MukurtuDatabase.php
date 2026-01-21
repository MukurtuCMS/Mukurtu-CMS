<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\search_api\backend;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiBackend;
use Drupal\search_api_db\Plugin\search_api\backend\Database;

/**
 * Custom database backend for Search API with taxonomy field handling.
 */
#[SearchApiBackend(
  id: 'mukurtu_db',
  label: new TranslatableMarkup('Mukurtu Database'),
  description: new TranslatableMarkup('Database backend with custom handling for taxonomy fields.'),
)]
class MukurtuDatabase extends Database {

  /**
   * {@inheritdoc}
   */
  protected function sqlType($type): array {
    // Override the return value for text type to use varchar(255).
    if ($type === 'text') {
      return ['type' => 'varchar', 'length' => 255];
    }
    // For all other types, use the parent implementation.
    return parent::sqlType($type);
  }

}
