<?php

namespace Drupal\mukurtu_core;

use Drupal\Core\Field\BaseFieldDefinition as BaseFieldDefinitionCore;

/**
 * Override core's BaseFieldDefinition class.
 *
 * We do this to use our custom BaseFieldOverride class, which has the ability
 * to manually set the protected baseFieldDefinition property.
 */
class BaseFieldDefinition extends BaseFieldDefinitionCore {

  /**
   * {@inheritdoc}
   */
  public function getConfig($bundle) {
    /** @var \Drupal\mukurtu_core\BaseFieldOverride $override */
    $override = BaseFieldOverride::loadByName($this->getTargetEntityTypeId(), $bundle, $this->getName());
    if ($override) {
      $override->setBaseFieldDefinition($this);
      return $override;
    }
    return BaseFieldOverride::createFromBaseFieldDefinition($this, $bundle);
  }

}
