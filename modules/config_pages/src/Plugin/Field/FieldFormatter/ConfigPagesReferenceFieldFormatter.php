<?php

namespace Drupal\config_pages\Plugin\Field\FieldFormatter;

use Drupal\config_pages\ConfigPagesInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'config page entity reference' formatter.
 *
 * @FieldFormatter(
 *   id = "cp_entity_reference_label",
 *   label = @Translation("Config Page entity"),
 *   description = @Translation("Display the referenced config page."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
#[FieldFormatter(
  id: "cp_entity_reference_label",
  label: new TranslatableMarkup("Config Page entity"),
  description: new TranslatableMarkup("Display the referenced config page."),
  field_types: [
    "entity_reference",
  ],
)]
class ConfigPagesReferenceFieldFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = [];

    foreach ($items as $delta => $item) {
      // Ignore items where no entity could be loaded in prepareView().
      if (empty($item->_loaded)) {
        continue;
      }

      $entity = $item->entity;
      $configPageType = $entity->id();
      $storage = $this->entityTypeManager->getStorage('config_pages');
      $configPage = $storage->load($configPageType);
      if ($configPage instanceof ConfigPagesInterface) {
        $access = $this->checkAccess($configPage);
        // Add the access result's cacheability, ::view() needs it.
        $item->_accessCacheability = CacheableMetadata::createFromObject($access);
        if ($access->isAllowed()) {
          // Add the referring item, in case the formatter needs it.
          $entity->_referringItem = $items[$delta];
          $entities[$delta] = $configPage;
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for 'config_pages_type' target type.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');

    return $target_type === 'config_pages_type';
  }

}
