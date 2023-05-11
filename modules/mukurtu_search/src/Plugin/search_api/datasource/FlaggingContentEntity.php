<?php

namespace Drupal\mukurtu_search\Plugin\search_api\datasource;

use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\Core\Plugin\PluginFormInterface;


/**
 * Represents a datasource which exposes the content entities.
 *
 * @SearchApiDatasource(
 *   id = "entity",
 *   deriver = "Drupal\search_api\Plugin\search_api\datasource\ContentEntityDeriver"
 * )
 */
class FlaggingContentEntity extends ContentEntity implements PluginFormInterface {
  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $type = $this->getEntityTypeId();
    $properties = $this->getEntityFieldManager()->getBaseFieldDefinitions($type);

    if ($bundles = array_keys($this->getBundles())) {
      foreach ($bundles as $bundle_id) {
        /**
         * This is the only change compared to the default ContentEntity datasource plugin.
         * The Flag module is abusing base field handling and altering the base field definition
         * for the flagged_entity field in the bundle definitions to account for the different
         * entity target types. In the default getPropertyDefinitions, the array union
         * prevents bundle definitions from overriding base definitions. Here we do it
         * in reverse so search api can index flagged_entity correctly per bundle.
         */
        $properties = $this->getEntityFieldManager()->getFieldDefinitions($type, $bundle_id) + $properties;
      }
    }
    // Exclude properties with custom storage, since we can't extract them
    // currently, due to a shortcoming of Core's Typed Data API. See #2695527.
    // Computed properties should mostly be OK, though, even though they still
    // count as having "custom storage". The "Path" field from the Core module
    // does not work, though, so we explicitly exclude it here to avoid
    // confusion.
    foreach ($properties as $key => $property) {
      if (!$property->isComputed() || $key === 'path') {
        if ($property->getFieldStorageDefinition()->hasCustomStorage()) {
          unset($properties[$key]);
        }
      }
    }
    return $properties;
  }

}