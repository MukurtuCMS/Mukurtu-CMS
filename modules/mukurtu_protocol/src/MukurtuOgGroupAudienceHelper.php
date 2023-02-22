<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * OG audience field helper methods.
 */
class MukurtuOgGroupAudienceHelper implements OgGroupAudienceHelperInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an OgGroupAudienceHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function hasGroupAudienceField($entity_type_id, $bundle_id) {
    if ($entity_type_id == 'protocol') {
      return TRUE;
    }

    if ($bundle_id) {
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
      $bundle_class = $bundle_info[$bundle_id]['class'] ?? NULL;
      if ($bundle_class && in_array('Drupal\mukurtu_protocol\CulturalProtocolControlledInterface', class_implements($bundle_class))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function isGroupAudienceField(FieldDefinitionInterface $field_definition) {
    return $field_definition->getType() == OgGroupAudienceHelperInterface::GROUP_REFERENCE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllGroupAudienceFields($group_content_entity_type_id, $group_content_bundle_id, $group_entity_type_id = NULL, $group_bundle_id = NULL) {
    if ($group_content_entity_type_id == 'protocol') {
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('protocol', 'protocol');
    }

    return [];
  }

}
