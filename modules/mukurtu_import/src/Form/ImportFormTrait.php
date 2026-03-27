<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Session\AccountInterface;

/**
 * Provides shared helper methods for import forms.
 *
 * Classes using this trait must provide:
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
 * @property \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
 * @property \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
 * @property \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager
 */
trait ImportFormTrait {

  /**
   * Memoized field definitions.
   *
   * @var array
   */
  protected array $fieldDefinitions = [];

  /**
   * Get the options array for available text formats.
   *
   * @return array
   *   An array of text format labels indexed by format ID.
   */
  protected function getTextFormatOptions(): array {
    $formats = filter_formats();
    return array_map(fn($format) => $format->label(), $formats);
  }

  /**
   * Get the options array for available target entity types.
   *
   * @return array
   *   An associative array of entity type IDs to labels, filtered by
   *   the current user's create access.
   */
  protected function getEntityTypeIdOptions(): array {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach (['node', 'media', 'community', 'protocol', 'paragraph', 'multipage_item'] as $entity_type_id) {
      if (isset($definitions[$entity_type_id]) && $this->userCanCreateAnyBundleForEntityType($entity_type_id)) {
        $options[$entity_type_id] = $definitions[$entity_type_id]->getLabel();

        if ($entity_type_id === 'paragraph') {
          $options[$entity_type_id] = $this->t('Compound Types (paragraphs)');
        }
      }
    }

    return $options;
  }

  /**
   * Gets the available bundle options for a given entity type.
   *
   * @param string|null $entity_type_id
   *   The entity type ID to get bundles for.
   *
   * @return array
   *   An associative array of bundle options filtered by user access.
   */
  protected function getBundleOptions(?string $entity_type_id): array {
    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();

    if (!isset($bundle_info[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    $options = [];
    if (count($bundle_info[$entity_type_id]) > 1) {
      $options = [-1 => $this->t('None: Base Fields Only')];
    }

    foreach ($bundle_info[$entity_type_id] as $bundle => $info) {
      if ($this->userCanCreateEntity($entity_type_id, $bundle)) {
        $options[$bundle] = $info['label'] ?? $bundle;
      }
    }
    return $options;
  }

  /**
   * Build the target field options for the mapping select elements.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   An associative array of field names/subfields to labels.
   */
  protected function buildTargetOptions(string $entity_type_id, ?string $bundle = NULL): array {
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_keys = $entity_definition->getKeys();

    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      $plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $field_definition]);
      $supported_properties = $plugin->getSupportedProperties($field_definition);

      if (!empty($supported_properties)) {
        foreach ($supported_properties as $property_name => $property_info) {
          $options["{$field_name}/{$property_name}"] = $property_info['label'];
        }
      }
      else {
        $options[$field_name] = $field_definition->getLabel();
      }
    }

    // Disambiguate the Language field from the langcode base field.
    if (isset($options[$entity_keys['langcode']])) {
      $options[$entity_keys['langcode']] .= $this->t(' (langcode)');
    }

    // Keep the "Ignore" option at the top, then sort the rest alphabetically.
    $ignore = [-1 => $options[-1]];
    unset($options[-1]);
    natcasesort($options);
    return $ignore + $options;
  }

  /**
   * Checks if a user has permission to create an entity of a specific type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access.
   */
  protected function userCanCreateEntity(string $entity_type_id, ?string $bundle = NULL, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }
    return $this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle, $account);
  }

  /**
   * Checks if a user can create any bundle of a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access to create at least one bundle.
   */
  protected function userCanCreateAnyBundleForEntityType(string $entity_type_id, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }

    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();
    if (!empty($bundle_info[$entity_type_id])) {
      foreach ($bundle_info[$entity_type_id] as $bundle_id => $info) {
        if ($this->userCanCreateEntity($entity_type_id, $bundle_id, $account)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  protected function getFieldDefinitions(string $entity_type_id, ?string $bundle = NULL): array {
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entityKeys = $entityDefinition->getKeys();
      $fieldDefs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      foreach ($fieldDefs as $field_name => $fieldDef) {
        if ($field_name === $entityKeys['id'] || $field_name === $entityKeys['uuid']) {
          continue;
        }

        // Remove the revision log message as a valid target.
        if ($field_name === 'revision_log') {
          unset($fieldDefs[$field_name]);
        }

        // Remove unwanted 'behavior_settings' paragraph base field.
        if ($entity_type_id === 'paragraph' && $field_name === 'behavior_settings') {
          unset($fieldDefs[$field_name]);
        }

        if ($fieldDef->isComputed() || $fieldDef->isReadOnly()) {
          unset($fieldDefs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $fieldDefs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

}
