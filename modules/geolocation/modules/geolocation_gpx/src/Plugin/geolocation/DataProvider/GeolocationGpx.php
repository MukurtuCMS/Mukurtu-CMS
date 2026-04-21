<?php

namespace Drupal\geolocation_gpx\Plugin\geolocation\DataProvider;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\file\Entity\File;
use Drupal\geolocation\DataProviderBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\geolocation_gpx\Plugin\Field\FieldType\GeolocationGpxFile;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use phpGPX\phpGPX;

/**
 * Provides GPX.
 *
 * @DataProvider(
 *   id = "geolocation_gpx",
 *   name = @Translation("Geolocation GPX"),
 *   description = @Translation("Tracks & Waypoints."),
 * )
 */
class GeolocationGpx extends DataProviderBase implements DataProviderInterface {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['return_tracks'] = TRUE;
    $settings['return_waypoints'] = TRUE;
    $settings['return_track_locations'] = FALSE;
    $settings['return_waypoint_locations'] = FALSE;
    $settings['track_stroke_color'] = '#FF0044';
    $settings['track_stroke_color_randomize'] = TRUE;
    $settings['track_stroke_width'] = 2;
    $settings['track_stroke_opacity'] = 1;

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

        if ($field_storage_definition->getType() == 'geolocation_gpx_file') {
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

    if (!empty($this->viewsField)) {
      $form_parent = "style_options[data_provider_settings]";
    }
    elseif (!empty($this->fieldDefinition)) {
      $form_parent = "fields['" . $this->fieldDefinition->getName() . "'][settings_edit_form][settings]";
    }
    else {
      $form_parent = '';
    }

    $element['return_tracks'] = [
      '#weight' => -99,
      '#type' => 'checkbox',
      '#title' => $this->t('Add tracks'),
      '#description' => $this->t('Will be displayed as polylines; names should show up on hover/click.'),
      '#default_value' => $settings['return_tracks'],
    ];

    $element['return_waypoints'] = [
      '#weight' => -100,
      '#type' => 'checkbox',
      '#title' => $this->t('Add waypoints'),
      '#description' => $this->t('Will be displayed as regular markers, with the name as marker title.'),
      '#default_value' => $settings['return_waypoints'],
    ];

    $element['return_track_locations'] = [
      '#weight' => -100,
      '#type' => 'checkbox',
      '#title' => $this->t('Add raw track locations'),
      '#default_value' => $settings['return_track_locations'],
    ];

    $element['return_waypoint_locations'] = [
      '#weight' => -100,
      '#type' => 'checkbox',
      '#title' => $this->t('Add raw waypoint locations'),
      '#default_value' => $settings['return_waypoint_locations'],
    ];

    $element['track_stroke_color'] = [
      '#weight' => -98,
      '#type' => 'color',
      '#title' => $this->t('Track color'),
      '#default_value' => $settings['track_stroke_color'],
      '#states' => [
        'visible' => [
          ':input[name="' . $form_parent . '[return_tracks]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['track_stroke_color_randomize'] = [
      '#weight' => -98,
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize track colors'),
      '#default_value' => $settings['track_stroke_color_randomize'],
      '#states' => [
        'visible' => [
          ':input[name="' . $form_parent . '[return_tracks]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['track_stroke_width'] = [
      '#weight' => -98,
      '#type' => 'number',
      '#title' => $this->t('Track Width'),
      '#description' => $this->t('Width of the tracks in pixels.'),
      '#default_value' => $settings['track_stroke_width'],
      '#states' => [
        'visible' => [
          ':input[name="' . $form_parent . '[return_tracks]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['track_stroke_opacity'] = [
      '#weight' => -98,
      '#type' => 'number',
      '#step' => 0.01,
      '#title' => $this->t('Track Opacity'),
      '#description' => $this->t('Opacity of the tracks from 1 = fully visible, 0 = complete see through.'),
      '#default_value' => $settings['track_stroke_opacity'],
      '#states' => [
        'visible' => [
          ':input[name="' . $form_parent . '[return_tracks]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromItem(FieldItemInterface $fieldItem) {
    $settings = $this->getSettings();
    if (!$settings['return_tracks']) {
      return [];
    }

    $gpxFile = $this->getGpxFileFromItem($fieldItem);
    if (empty($gpxFile)) {
      \Drupal::logger('geolocation_gpx')->warning('Reading file as GPX failed');
      return [];
    }

    $positions = [];

    foreach ($gpxFile->tracks as $track) {
      $coordinates = '';
      foreach ($track->segments as $segment) {
        foreach ($segment->points as $point) {
          $coordinates .= $point->latitude . ',' . $point->longitude . ' ';
        }
      }

      $positions[] = [
        '#type' => 'geolocation_map_polyline',
        '#title' => $track->name,
        '#coordinates' => $coordinates,
        '#stroke_color' => $settings['track_stroke_color_randomize'] ? sprintf('#%06X', mt_rand(0, 0xFFFFFF)) : $settings['track_stroke_color'],
        '#stroke_width' => (int) $settings['track_stroke_width'],
        '#stroke_opacity' => (float) $settings['track_stroke_opacity'],
      ];
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromItem(FieldItemInterface $fieldItem) {
    $settings = $this->getSettings();
    if (!$settings['return_waypoints']) {
      return [];
    }
    $gpxFile = $this->getGpxFileFromItem($fieldItem);
    if (empty($gpxFile)) {
      \Drupal::logger('geolocation_gpx')->warning('Reading file as GPX failed');
      return [];
    }

    $positions = [];

    foreach ($gpxFile->waypoints as $waypoint) {

      $positions[] = [
        '#type' => 'geolocation_map_location',
        '#title' => $waypoint->name,
        '#coordinates' => [
          'lat' => $waypoint->latitude,
          'lng' => $waypoint->longitude,
        ],
      ];
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition) {
    return ($fieldDefinition->getType() == 'geolocation_gpx_file');
  }

  /**
   * Get GPX file.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Field item.
   *
   * @return \phpGPX\Models\GpxFile|false
   *   GPX file or false.
   */
  public function getGpxFileFromItem(FieldItemInterface $fieldItem) {
    if ($fieldItem instanceof GeolocationGpxFile) {
      $target_id = $fieldItem->get('target_id')->getValue();
      if (empty($target_id)) {
        return FALSE;
      }

      $file = File::load($target_id);
      if (empty($file)) {
        return FALSE;
      }

      $filename = $file->getFileUri();
      $gpx = new phpGPX();

      $file = $gpx->load($filename);
      if (empty($file)) {
        return FALSE;
      }

      return $file;
    }

    return FALSE;
  }

}
