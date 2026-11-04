<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;

/**
 * Decides which fields the per-item grid offers and shows.
 *
 * The grid offers the item's label field plus the fields the bulk edit page
 * offers as columns, leaving out those whose CSV text is only IDs or WKT:
 * paragraphs, files, images and map coverage. It starts with the fields the
 * editor shows on the bulk edit table; their grid choice is saved per type.
 */
class GridColumns {

  /**
   * Field types left out of the grid.
   */
  public const EXCLUDED_TYPES = ['entity_reference_revisions', 'file', 'image', 'geofield'];

  public function __construct(
    protected readonly BulkEditColumns $columns,
  ) {}

  /**
   * Returns the fields the grid offers for a type, keyed by field name.
   *
   * @return array<string, string|\Stringable>
   *   Field labels, the label field first.
   */
  public function available(string $type): array {
    $entity_type_id = $this->columns->entityTypeId();
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $type);
    $label_key = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getKey('label');

    $fields = [$label_key => $definitions[$label_key]->getLabel()];
    foreach ($this->columns->available($type) as $key => $label) {
      if (isset($definitions[$key])
        && !in_array($key, BulkEditColumns::ITEM_COLUMNS, TRUE)
        && !in_array($definitions[$key]->getType(), self::EXCLUDED_TYPES, TRUE)) {
        $fields[$key] = $label;
      }
    }
    return $fields;
  }

  /**
   * Returns the field names to show for a type, label field first.
   */
  public function selected(string $type, AccountInterface $account): array {
    $available = $this->available($type);
    $saved = \Drupal::service('user.data')->get('mukurtu_import', (int) $account->id(), $this->key($type));
    $chosen = is_array($saved) ? $saved : $this->columns->selected($type, $account);
    $label_key = array_key_first($available);
    return array_values(array_unique([$label_key, ...array_intersect(array_keys($available), $chosen)]));
  }

  /**
   * Saves an editor's grid column choice for a type.
   *
   * @param string $type
   *   The content or media type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The editor.
   * @param string[] $fields
   *   Field names. Unknown ones are dropped.
   */
  public function save(string $type, AccountInterface $account, array $fields): void {
    $valid = array_values(array_intersect(array_keys($this->available($type)), $fields));
    \Drupal::service('user.data')->set('mukurtu_import', (int) $account->id(), $this->key($type), $valid);
  }

  /**
   * Returns the user data key for a type's grid column choice.
   */
  protected function key(string $type): string {
    return "bulk_edit_grid_columns.{$this->columns->entityTypeId()}.$type";
  }

}
