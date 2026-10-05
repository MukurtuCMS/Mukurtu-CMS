<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Lets entity reference formatters display reference-with-role fields.
 *
 * EntityReferenceRoleItem extends EntityReferenceItem, so any formatter that
 * works with entity references works with it too and simply omits the role.
 * This keeps existing displays (browse cards, teasers) working when a field
 * is converted.
 *
 * Widgets are deliberately not opened up the same way. A widget that doesn't
 * know about roles submits only the referenced entity, which wipes every role
 * on the field when the entity is saved, so only the role-aware widget is
 * offered for this field type.
 */
final class EntityReferenceRoleHooks {

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    foreach ($info as &$definition) {
      $types = $definition['field_types'] ?? [];
      if (in_array('entity_reference', $types, TRUE) && !in_array('mukurtu_entity_reference_role', $types, TRUE)) {
        $definition['field_types'][] = 'mukurtu_entity_reference_role';
      }
    }
  }

}
