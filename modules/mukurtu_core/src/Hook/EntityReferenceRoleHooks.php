<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Lets entity reference widgets and formatters handle reference-with-role.
 *
 * EntityReferenceRoleItem extends EntityReferenceItem, so any plugin that
 * works with entity references works with it too and ignores the role. This
 * keeps existing displays (browse cards, teasers, Tagify) working when a
 * field is converted. A widget that doesn't know about roles drops them when
 * the entity is saved, so only the role-aware widget should be used on forms
 * where roles are entered.
 */
final class EntityReferenceRoleHooks {

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    $this->addRoleFieldType($info);
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    $this->addRoleFieldType($info);
  }

  /**
   * Adds the role field type to every plugin that supports entity_reference.
   */
  protected function addRoleFieldType(array &$info): void {
    foreach ($info as &$definition) {
      $types = $definition['field_types'] ?? [];
      if (in_array('entity_reference', $types, TRUE) && !in_array('mukurtu_entity_reference_role', $types, TRUE)) {
        $definition['field_types'][] = 'mukurtu_entity_reference_role';
      }
    }
  }

}
