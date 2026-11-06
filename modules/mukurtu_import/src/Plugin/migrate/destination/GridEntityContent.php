<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\Plugin\Derivative\GridEntityContentDeriver;

/**
 * Saves one row of the per-item bulk edit grid, as the importer would.
 *
 * Everything but validation is the importer's: lookups, person roles, the
 * access check and the revision. Validation covers only the fields the
 * editor changed, like Modify field values, so a problem an item already has
 * elsewhere, such as a migrated item missing a required field, doesn't
 * block the edit.
 *
 * Configuration: "grid_fields", the names of the fields being changed.
 */
#[MigrateDestination(
  id: 'mukurtu_grid_entity',
  deriver: GridEntityContentDeriver::class,
)]
class GridEntityContent extends ProtocolAwareEntityContent {

  /**
   * The last row's validation messages, keyed by field name.
   *
   * Messages not tied to one field are keyed by an empty string.
   *
   * @var array<string, string[]>
   */
  protected array $fieldErrors = [];

  /**
   * {@inheritdoc}
   *
   * Some fields, such as Original Date, refuse a value by throwing while it's
   * set. Trying each changed field on a copy first ties that to its cell.
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    $this->fieldErrors = [];
    if ($entity instanceof FieldableEntityInterface) {
      $probe = clone $entity;
      foreach ($this->configuration['grid_fields'] ?? [] as $field) {
        if ($probe->hasField($field) && $row->hasDestinationProperty($field)) {
          try {
            $probe->get($field)->setValue($row->getDestinationProperty($field));
          }
          catch (\InvalidArgumentException $e) {
            $this->fieldErrors[$field][] = $e->getMessage();
          }
        }
      }
      if ($this->fieldErrors) {
        throw new MigrateException(implode("\n", array_merge(...array_values($this->fieldErrors))));
      }
    }
    return parent::updateEntity($entity, $row);
  }

  /**
   * {@inheritdoc}
   */
  public function validateEntity(FieldableEntityInterface $entity): void {
    $this->fieldErrors = [];
    $this->addImageAltConstraint($entity);

    $all = $entity->validate();
    $violations = array_merge(
      iterator_to_array($all->getByFields($this->configuration['grid_fields'] ?? [])),
      iterator_to_array($all->getEntityViolations()),
    );
    foreach ($violations as $violation) {
      $field = explode('.', $violation->getPropertyPath())[0];
      $this->fieldErrors[$field][] = $this->formatViolationMessage($violation, $entity);
    }
    if ($this->fieldErrors) {
      throw new MigrateException(implode("\n", array_merge(...array_values($this->fieldErrors))));
    }
  }

  /**
   * Returns the last row's validation messages, keyed by field name.
   *
   * @return array<string, string[]>
   *   Messages, with an empty-string key for those not tied to one field.
   */
  public function getFieldErrors(): array {
    return $this->fieldErrors;
  }

}
