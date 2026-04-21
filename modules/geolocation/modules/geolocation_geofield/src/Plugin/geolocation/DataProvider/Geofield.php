<?php

namespace Drupal\geolocation_geofield\Plugin\geolocation\DataProvider;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\geofield\Plugin\Field\FieldType\GeofieldItem;
use Drupal\geolocation\DataProviderBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Provides Google Maps.
 *
 * @DataProvider(
 *   id = "geofield",
 *   name = @Translation("Geofield"),
 *   description = @Translation("Geofield."),
 * )
 */
class Geofield extends DataProviderBase implements DataProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $views_field) {
    if (
      $views_field instanceof EntityField
      && in_array($views_field->getPluginId(), ['field', 'search_api_field'])
    ) {
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($views_field->getEntityType());
      if (!empty($field_storage_definitions[$views_field->field])) {
        $field_storage_definition = $field_storage_definitions[$views_field->field];

        if ($field_storage_definition->getType() == 'geofield') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition) {
    return ($fieldDefinition->getType() == 'geofield');
  }

  /**
   * {@inheritdoc}
   */
  public function getPositionsFromItem(FieldItemInterface $fieldItem) {
    if ($fieldItem instanceof GeofieldItem) {
      return [
        [
          'lat' => $fieldItem->get('lat')->getValue(),
          'lng' => $fieldItem->get('lon')->getValue(),
        ],
      ];
    }

    return FALSE;
  }

}
