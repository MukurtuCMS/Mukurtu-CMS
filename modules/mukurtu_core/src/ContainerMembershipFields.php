<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Describes the computed "contained in" fields that import and export write.
 *
 * Collection and word list membership is stored only on the container
 * (field_items_in_collection on a collection, field_words on a word list).
 * Each item carries a computed, read-only reverse field listing the
 * containers it is in. Import and export treat that reverse field as a
 * column: export writes the containers' identifiers, and import adds the
 * item to each named container after the item is saved.
 */
final class ContainerMembershipFields {

  /**
   * Reverse field name => container bundle, items field, and noun.
   *
   * The noun is only used in import row errors, which (like the importer's
   * other row errors) are untranslated.
   */
  public const FIELDS = [
    'field_in_collection' => [
      'bundle' => 'collection',
      'items_field' => 'field_items_in_collection',
      'noun' => 'collection',
    ],
    'field_in_word_list' => [
      'bundle' => 'word_list',
      'items_field' => 'field_words',
      'noun' => 'word list',
    ],
  ];

  /**
   * Returns the container definition if the field applies to the bundle.
   *
   * A reverse field applies when the container bundle exists, has its items
   * field, and that field accepts the item's bundle. Every node bundle gets
   * field_in_collection, but only some can be put into a collection.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param string $field_name
   *   The reverse field name, for example field_in_collection.
   * @param string|null $item_bundle
   *   The bundle of the item (node) being imported or exported.
   *
   * @return array|null
   *   The matching self::FIELDS entry, or NULL.
   */
  public static function forItemBundle(EntityFieldManagerInterface $entity_field_manager, string $field_name, ?string $item_bundle): ?array {
    $container = self::FIELDS[$field_name] ?? NULL;
    if (!$container || !$item_bundle) {
      return NULL;
    }

    $container_fields = $entity_field_manager->getFieldDefinitions('node', $container['bundle']);
    $items_field = $container_fields[$container['items_field']] ?? NULL;
    if (!$items_field || $container['bundle'] === $item_bundle) {
      return NULL;
    }

    $target_bundles = $items_field->getSetting('handler_settings')['target_bundles'] ?? NULL;
    if (!empty($target_bundles) && !in_array($item_bundle, $target_bundles, TRUE)) {
      return NULL;
    }

    return $container;
  }

}
