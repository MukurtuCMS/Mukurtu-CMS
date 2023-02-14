<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

class MukurtuNode extends Node implements CulturalProtocolControlledInterface {
  use CulturalProtocolControlledTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();
    return $definitions;
  }

}
