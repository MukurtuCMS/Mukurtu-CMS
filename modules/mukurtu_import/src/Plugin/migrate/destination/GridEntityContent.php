<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\BulkEdit\GridCellText;
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

  use StringTranslationTrait;

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
   * Values the importer couldn't find, such as an unknown term with
   * auto-create off or a Local Contexts project the site doesn't have, would
   * be dropped silently, so they're reported too.
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    $this->fieldErrors = [];
    if ($entity instanceof FieldableEntityInterface) {
      $probe = clone $entity;
      foreach ($this->configuration['grid_fields'] ?? [] as $field) {
        if (!$probe->hasField($field)) {
          continue;
        }
        $missing = $this->notFound($row, $field, $probe->getFieldDefinition($field)->getFieldStorageDefinition()->isMultiple());
        if ($missing) {
          $this->fieldErrors[$field][] = (string) $this->t("Some values weren't found: @values. Check the spelling, or use the ID instead.", ['@values' => implode(', ', $missing)]);
          continue;
        }
        if ($row->hasDestinationProperty($field)) {
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
   * Returns the cell's values that came out of processing as nothing.
   *
   * @param \Drupal\migrate\Row $row
   *   The processed row.
   * @param string $field
   *   The field name, also the cell's source key.
   * @param bool $multiple
   *   Whether the field takes several values, one per piece of the cell.
   *
   * @return string[]
   *   The values typed in the cell that weren't found.
   */
  protected function notFound(Row $row, string $field, bool $multiple): array {
    $text = trim((string) $row->getSourceProperty($field));
    if ($text === '') {
      return [];
    }
    $pieces = $multiple ? array_values(array_filter(array_map('trim', explode(GridCellText::DELIMITER, $text)), 'strlen')) : [$text];
    $value = $row->getDestinationProperty($field);
    $values = $multiple && is_array($value) && array_is_list($value) ? $value : [$value];
    if (count(array_filter($values, fn($item) => !self::isBlank($item))) >= count($pieces)) {
      return [];
    }

    // Fewer values than pieces: find which, one piece at a time, so a value
    // typed twice isn't mistaken for one that wasn't found.
    $process = [$field => $this->migration->getProcess()[$field] ?? []];
    $executable = new MigrateExecutable($this->migration);
    $missing = [];
    foreach (array_unique($pieces) as $piece) {
      // The processed row is frozen; a fresh one holds just this piece.
      $single = new Row([$field => $piece] + $row->getSource());
      try {
        $executable->processRow($single, $process);
        $found = !self::isBlank($single->getDestinationProperty($field));
      }
      catch (\Exception) {
        $found = FALSE;
      }
      if (!$found) {
        $missing[] = $piece;
      }
    }
    return $missing;
  }

  /**
   * Returns whether a processed value holds nothing.
   */
  protected static function isBlank(mixed $value): bool {
    if (is_array($value)) {
      return array_filter($value, fn($item) => !self::isBlank($item)) === [];
    }
    return $value === NULL || $value === '';
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
