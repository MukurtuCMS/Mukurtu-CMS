<?php

namespace Drupal\geolocation_geometry\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a geolocation field mapper for WKT/JSON sources.
 *
 * @FeedsTarget(
 *   id = "geolocation_geometry_base_feeds_target",
 *   field_types = {
 *     "geolocation_geometry_geometry",
 *     "geolocation_geometry_geometrycollection",
 *     "geolocation_geometry_linestring",
 *     "geolocation_geometry_multilinestring",
 *     "geolocation_geometry_multipoint",
 *     "geolocation_geometry_multipolygon",
 *     "geolocation_geometry_point",
 *     "geolocation_geometry_polygon",
 *   }
 * )
 */
class GeolocationGeometryBase extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      // This placeholder will be replaced by either wkt or json.
      ->addProperty('placeholder');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['format' => 'wkt'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Format'),
      '#options' => [
        'wkt' => $this->t('WKT (Well Known Text)'),
        'geojson' => $this->t('GeoJSON'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['format'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return $this->t('Format: %format', [
      '%format' => $this->formatOptions()[$this->configuration['format']],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    switch ($this->configuration['format']) {
      case 'wkt':
        $values['wkt'] = $values['placeholder'];
        break;

      case 'json':
        $values['json'] = $values['placeholder'];
        break;
    }
    unset($values['placeholder']);
  }

  /**
   * {@inheritdoc}
   */
  public function isTargetTranslatable() {
    return FALSE;
  }

  /**
   * Get the formatting options available.
   *
   * @return array
   *   Array of allowed formats.
   */
  protected function formatOptions() {
    return [
      'wkt' => $this->t('WKT (Well Known Text)'),
      'geojson' => $this->t('GeoJSON'),
    ];
  }

}
