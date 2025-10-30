<?php

namespace Drupal\mukurtu_core;

use Drupal\Core\Field\Entity\BaseFieldOverride as BaseFieldOverrideCore;

/**
 * Override core's BaseFieldOverride class.
 *
 * Drupal core's BaseFieldOverride class expects to be able to restore the
 * baseFieldDefinition property, from the entity_field.manager service.
 *
 * However, due to lack of API support, we're currently using
 * BaseFieldDefinition objects in return from bundleFieldDefinitions. For an
 * example of this see
 * \Drupal\mukurtu_collection\Entity\Collection::bundleFieldDefinitions.
 * Returning BaseFieldDefinition objects allows content_translation module to
 * load base_field_override entities based on these. There is no companion
 * bundle_field_override entities unfortunately. We're mostly able to get
 * away with this setup, only, we need a way to set the baseFieldDefinition
 * property ourselves on a base_field_override entity after its been created,
 * b/c serialization unsets is and relies on being able to load it from
 * the entity_field.manager service, which fails since we define it in
 * bundleFieldDefinitions and not baseFieldDefinitions.
 *
 * @see https://www.drupal.org/node/2346347
 * @see https://www.drupal.org/node/2935978
 */
class BaseFieldOverride extends BaseFieldOverrideCore {

  /**
   * Set the base field definition.
   *
   * @param \Drupal\mukurtu_core\BaseFieldDefinition $base_field_definition
   *   The base field definition.
   */
  public function setBaseFieldDefinition(BaseFieldDefinition $base_field_definition): void {
    $this->baseFieldDefinition = $base_field_definition;
  }

}
