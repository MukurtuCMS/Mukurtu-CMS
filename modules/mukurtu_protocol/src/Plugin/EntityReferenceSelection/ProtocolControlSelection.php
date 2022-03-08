<?php

namespace Drupal\mukurtu_protocol\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides an entity reference selection for protocols.
 *
 * @EntityReferenceSelection(
 *   id = "default:protocol_control",
 *   label = @Translation("Protocol Control Entity Selection"),
 *   entity_types = {
 *     "protocol_control"
 *   },
 *   group = "default",
 *   weight = 1
 * )
 */
class ProtocolControlSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    return $ids;
  }

}
