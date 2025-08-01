<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class MukurtuNode extends Node implements CulturalProtocolControlledInterface, MukurtuDraftInterface {
  use CulturalProtocolControlledTrait;
  use MukurtuDraftTrait;

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    // Allow all users to view landing pages.
    if ($operation == 'view' && isset($this->entityKeys['bundle']) && ($this->entityKeys['bundle'] == 'landing_page')) {
      return AccessResult::allowed();
    }
    // This override exists to set the operation to the default value "view".
    return parent::access($operation, $account, $return_as_object);
  }

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    // Add the drafts field.
    $definitions += static::draftBaseFieldDefinitions($entity_type);

    return $definitions;
  }
}
