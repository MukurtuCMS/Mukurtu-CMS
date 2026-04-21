<?php

namespace Drupal\geolocation_geometry\Plugin\geolocation\DataProvider;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\geolocation\DataProviderBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides GPX.
 *
 * @DataProvider(
 *   id = "geolocation_geometry",
 *   name = @Translation("Geolocation Geometry"),
 *   description = @Translation("Points, Polygons, Polyines."),
 * )
 */
class GeolocationGeometry extends DataProviderBase implements DataProviderInterface {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['stroke_color'] = '#FF0044';
    $settings['stroke_color_randomize'] = TRUE;
    $settings['stroke_width'] = 1;
    $settings['stroke_opacity'] = 0.8;

    $settings['fill_color'] = '#0033FF';
    $settings['fill_color_randomize'] = TRUE;
    $settings['fill_opacity'] = 0.1;

    return $settings;

  }

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $views_field) {
    if (
      $views_field instanceof EntityField
      && $views_field->getPluginId() == 'field'
    ) {
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($views_field->getEntityType());
      if (!empty($field_storage_definitions[$views_field->field])) {
        $field_storage_definition = $field_storage_definitions[$views_field->field];

        if (in_array($field_storage_definition->getType(), [
          'geolocation_geometry_geometry',
          'geolocation_geometry_geometrycollection',
          'geolocation_geometry_point',
          'geolocation_geometry_linestring',
          'geolocation_geometry_polygon',
          'geolocation_geometry_multipoint',
          'geolocation_geometry_multilinestring',
          'geolocation_geometry_multipolygon',
        ])) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []) {
    $element = parent::getSettingsForm($settings, $parents);

    $settings = $this->getSettings($settings);

    $element['stroke_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Stroke color'),
      '#default_value' => $settings['stroke_color'],
    ];

    $element['stroke_color_randomize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize stroke colors'),
      '#default_value' => $settings['stroke_color_randomize'],
    ];

    $element['stroke_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Stroke Width'),
      '#description' => $this->t('Width of the stroke in pixels.'),
      '#default_value' => $settings['stroke_width'],
    ];

    $element['stroke_opacity'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#title' => $this->t('Stroke Opacity'),
      '#description' => $this->t('Opacity of the stroke from 1 = fully visible, 0 = complete see through.'),
      '#default_value' => $settings['stroke_opacity'],
    ];

    $element['fill_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Fill color'),
      '#default_value' => $settings['fill_color'],
    ];

    $element['fill_color_randomize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize fill colors'),
      '#default_value' => $settings['fill_color_randomize'],
    ];

    $element['fill_opacity'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#title' => $this->t('Fill Opacity'),
      '#description' => $this->t('Opacity of the polygons from 1 = fully visible, 0 = complete see through.'),
      '#default_value' => $settings['fill_opacity'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL) {
    $locations = parent::getLocationsFromViewsRow($row, $viewsField);

    $current_style = $viewsField->displayHandler->getPlugin('style');

    if (
      empty($current_style)
      || !is_subclass_of($current_style, 'Drupal\geolocation\Plugin\views\style\GeolocationStyleBase')
    ) {
      return $locations;
    }

    foreach ($locations as &$location) {
      if (!is_array($location)) {
        continue;
      }
      $location['#title'] = $current_style->getTitleField($row);
      $location['#label'] = $current_style->getLabelField($row);
    }

    return $locations;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL) {
    $shapes = parent::getShapesFromViewsRow($row, $viewsField);

    if (empty($shapes)) {
      return $shapes;
    }

    $current_style = $viewsField->displayHandler->getPlugin('style');

    if (
      empty($current_style)
      || !is_subclass_of($current_style, 'Drupal\geolocation\Plugin\views\style\GeolocationStyleBase')
    ) {
      return $shapes;
    }

    foreach ($shapes as &$shape) {
      if (!is_array($shape)) {
        continue;
      }
      $shape['#title'] = $current_style->getTitleField($row);
    }

    return $shapes;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromItem(FieldItemInterface $fieldItem) {
    $settings = $this->getSettings();

    $shapes = $locations = [];

    $this->parseGeoJson($fieldItem->get('geojson')->getString(), $locations, $shapes);
    $positions = [];

    foreach ($shapes as $shape) {
      $random_color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
      switch ($shape->type) {
        case 'Polygon':
          $coordinates = '';
          foreach ($shape->coordinates[0] as $coordinate) {
            $coordinates .= $coordinate[1] . ',' . $coordinate[0] . ' ';
          }

          $position = [
            '#type' => 'geolocation_map_polygon',
            '#coordinates' => $coordinates,
            '#stroke_color' => $settings['stroke_color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
            '#fill_color' => $settings['fill_color_randomize'] ? $random_color : $settings['fill_color'],
            '#fill_opacity' => (float) $settings['fill_opacity'],
          ];
          $positions[] = $position;
          break;

        case 'MultiPolygon':
          $container = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'geolocation-multipolygon',
              ],
            ],
          ];
          foreach ($shape->coordinates as $key => $polygon) {
            $coordinates = '';
            foreach ($polygon[0] as $coordinate) {
              $coordinates .= $coordinate[1] . ',' . $coordinate[0] . ' ';
            }

            $position = [
              '#type' => 'geolocation_map_polygon',
              '#coordinates' => $coordinates,
              '#stroke_color' => $settings['stroke_color_randomize'] ? $random_color : $settings['stroke_color'],
              '#stroke_width' => (int) $settings['stroke_width'],
              '#stroke_opacity' => (float) $settings['stroke_opacity'],
              '#fill_color' => $settings['fill_color_randomize'] ? $random_color : $settings['fill_color'],
              '#fill_opacity' => (float) $settings['fill_opacity'],
            ];
            $container[$key] = $position;
          }
          $positions[] = $container;
          break;

        case 'LineString':
          $coordinates = '';
          foreach ($shape->coordinates as $coordinate) {
            $coordinates .= $coordinate[1] . ',' . $coordinate[0] . ' ';
          }

          $position = [
            '#type' => 'geolocation_map_polyline',
            '#coordinates' => $coordinates,
            '#stroke_color' => $settings['stroke_color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
          ];
          $positions[] = $position;
          break;

        case 'MultiLineString':
          $container = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'geolocation-multipolyline',
              ],
            ],
          ];
          foreach ($shape->coordinates as $key => $polyline) {
            $coordinates = '';
            foreach ($polyline as $coordinate) {
              $coordinates .= $coordinate[1] . ',' . $coordinate[0] . ' ';
            }

            $position = [
              '#type' => 'geolocation_map_polyline',
              '#coordinates' => $coordinates,
              '#stroke_color' => $settings['stroke_color_randomize'] ? $random_color : $settings['stroke_color'],
              '#stroke_width' => (int) $settings['stroke_width'],
              '#stroke_opacity' => (float) $settings['stroke_opacity'],
            ];
            $container[$key] = $position;
          }
          $positions[] = $container;
          break;
      }
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromItem(FieldItemInterface $fieldItem) {
    $shapes = $locations = [];

    $this->parseGeoJson($fieldItem->get('geojson')->getString(), $locations, $shapes);

    $positions = [];

    foreach ($locations as $location) {

      switch ($location->type) {
        case 'Point':
          $position = [
            '#type' => 'geolocation_map_location',
            '#coordinates' => [
              'lat' => $location->coordinates[1],
              'lng' => $location->coordinates[0],
            ],
          ];
          $positions[] = $position;
          break;

        case 'MultiPoint':
          $container = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'geolocation-multipoint',
              ],
            ],
          ];
          foreach ($location->coordinates as $key => $point) {
            $position = [
              '#type' => 'geolocation_map_location',
              '#coordinates' => [
                'lat' => $point->coordinates[1],
                'lng' => $point->coordinates[0],
              ],
            ];
            $container[$key] = $position;
          }
          $positions[] = $container;
          break;
      }
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition) {
    return (in_array($fieldDefinition->getType(), [
      'geolocation_geometry_geometry',
      'geolocation_geometry_geometrycollection',
      'geolocation_geometry_point',
      'geolocation_geometry_linestring',
      'geolocation_geometry_polygon',
      'geolocation_geometry_multipoint',
      'geolocation_geometry_multilinestring',
      'geolocation_geometry_multipolygon',
    ]));
  }

  /**
   * Parse GeoJson for content.
   *
   * @param string $geoJson
   *   GeoJSON.
   * @param array $locations
   *   Locations to be filled.
   * @param array $shapes
   *   Shapes to be filled.
   */
  protected function parseGeoJson(string $geoJson, array &$locations, array &$shapes) {
    $json = json_decode($geoJson);

    if (
      is_object($json)
      && isset($json->type)
    ) {
      $json = [$json];
    }

    foreach ($json as $entry) {
      if (empty($entry->type)) {
        continue;
      }
      switch ($entry->type) {
        case 'FeatureCollection':
          if (empty($entry->features)) {
            continue 2;
          }
          $this->parseGeoJson(is_string($entry->features) ?: json_encode($entry->features), $locations, $shapes);
          break;

        case 'Feature':
          if (empty($entry->geometry)) {
            continue 2;
          }
          $this->parseGeoJson(is_string($entry->geometry) ?: json_encode($entry->geometry), $locations, $shapes);
          break;

        case 'GeometryCollection':
          if (empty($entry->geometries)) {
            continue 2;
          }
          $this->parseGeoJson(is_string($entry->geometries) ?: json_encode($entry->geometries), $locations, $shapes);
          break;

        case 'MultiPolygon':
        case 'Polygon':
        case 'MultiLineString':
        case 'LineString':
          if (empty($entry->coordinates)) {
            continue 2;
          }
          $shapes[] = $entry;
          break;

        case 'MultiPoint':
        case 'Point':
          if (empty($entry->coordinates)) {
            continue 2;
          }
          $locations[] = $entry;
          break;
      }
    }
  }

}
