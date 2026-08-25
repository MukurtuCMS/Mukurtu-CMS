<?php

namespace Drupal\mukurtu_core\Entity;

/**
 * Marker interface for entity types supported by import/export roundtrip.
 *
 * Implement this on a custom content entity class (alongside the entity's
 * own interface) to make it discoverable by
 * \Drupal\mukurtu_core\Service\RoundtripEntityTypeRegistry without editing
 * mukurtu_import or mukurtu_export directly.
 */
interface RoundtripEntityInterface {

}
