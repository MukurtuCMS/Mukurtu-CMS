<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\geofield\Element\GeofieldLatLon;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldBaseWidget;

/**
 * Plugin implementation of the 'geofield_mukurtu_latlon' widget.
 *
 * @FieldWidget(
 *   id = "geofield_mukurtu_latlon",
 *   label = @Translation("Mukurtu Map Points (accessible list editor)"),
 *   description = @Translation("Accessible, keyboard-only alternative to the map widget. Supports points, lines, and simple polygons."),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldMukurtuLatLonWidget extends GeofieldBaseWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'instructions' => '',
      'show_descriptions' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#description' => $this->t('Optional text shown above the shape list, replacing the field description. Leave blank to use the field description.'),
      '#default_value' => $this->getSetting('instructions'),
      '#rows' => 2,
    ];
    $elements['show_descriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a description field for each shape'),
      '#default_value' => $this->getSetting('show_descriptions'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('show_descriptions')
      ? $this->t('Shows a description field for each shape')
      : $this->t('No per-shape description field');
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * field_coverage stores raw GeoJSON FeatureCollections, not WKT (see
   * GeofieldMukurtuWidget::geofieldBackendValue()). massageFormValues()
   * below builds the final value directly and never calls this method; it
   * is overridden only so no inherited/future code path can silently
   * WKT-normalize the stored value.
   */
  protected function geofieldBackendValue($value) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_parents = $element['#field_parents'] ?? [];
    $field_name = $this->fieldDefinition->getName();

    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $original = $element['value'];

    $parsed = $this->parseStoredValue($items[$delta]->value ?? NULL);

    $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
    if (!isset($field_state['mukurtu_shapes'])) {
      if ($parsed['shapes']) {
        $field_state['mukurtu_shapes'] = array_map(function (array $shape): array {
          return ['type' => $shape['type'], 'vertex_count' => count($shape['vertices'])];
        }, $parsed['shapes']);
      }
      else {
        // Nothing stored yet: start with one empty Point, matching the
        // original widget's "always show at least one row" behavior.
        $field_state['mukurtu_shapes'] = [['type' => 'Point', 'vertex_count' => 1]];
      }
      static::setWidgetState($field_parents, $field_name, $form_state, $field_state);
    }
    $shapes_state = $field_state['mukurtu_shapes'];

    $wrapper_id = Html::getUniqueId('mukurtu-geofield-shapes-' . $field_name);

    $value_element = [
      '#type' => 'fieldset',
      '#title' => $original['#title'] ?? $this->fieldDefinition->getLabel(),
      '#description' => $this->getSetting('instructions') ?: ($original['#description'] ?? NULL),
      '#required' => $original['#required'] ?? FALSE,
      '#attributes' => ['class' => ['mukurtu-geofield-latlon']],
      '#attached' => $original['#attached'] ?? [],
      '#tree' => TRUE,
      '#field_parents' => $field_parents,
      '#mukurtu_field_name' => $field_name,
    ];

    // Legacy value this widget can't parse (e.g. WKT): show read-only,
    // change nothing on save.
    if ($parsed['passthrough'] !== NULL) {
      $value_element['passthrough_warning'] = [
        '#type' => 'markup',
        '#markup' => '<p class="mukurtu-geofield-warning">' . $this->t("This location's data isn't in a format this widget can edit. It's shown below unchanged and won't be modified when you save.") . '</p>',
      ];
      $value_element['raw_passthrough'] = [
        '#type' => 'value',
        '#value' => $parsed['passthrough'],
      ];
      $value_element['raw_display'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Stored location data'),
        '#value' => $parsed['passthrough'],
        '#attributes' => ['readonly' => 'readonly'],
        '#rows' => 4,
      ];
      return ['value' => $value_element];
    }

    $value_element['preserved_features'] = [
      '#type' => 'value',
      '#value' => $parsed['other'],
    ];

    if ($parsed['other']) {
      $value_element['preserved_notice'] = [
        '#type' => 'markup',
        '#markup' => '<p class="mukurtu-geofield-warning" role="status">' . $this->formatPlural(
          count($parsed['other']),
          "This location also includes 1 shape (a multi-part or complex shape) drawn on the map, which can't be edited here and will be kept unchanged when you save. Use the map widget to edit it.",
          "This location also includes @count shapes (multi-part or complex shapes) drawn on the map, which can't be edited here and will be kept unchanged when you save. Use the map widget to edit them."
        ) . '</p>',
      ];
    }

    $show_descriptions = $this->getSetting('show_descriptions');
    $geocoder_available = \Drupal::hasService('geocoder');

    $value_element['shapes'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#mukurtu_wrapper_id' => $wrapper_id,
    ];

    foreach ($shapes_state as $n => $shape_info) {
      $shape_type = $shape_info['type'] ?? 'Point';
      $vertex_count = max(1, $shape_info['vertex_count'] ?? 1);
      $shape_row = $parsed['shapes'][$n] ?? NULL;
      $shape_id = $wrapper_id . '-shape-' . $n;
      $is_point = $shape_type === 'Point';

      $value_element['shapes'][$n] = [
        '#type' => 'fieldset',
        '#title' => $is_point
          ? $this->t('Point @n', ['@n' => $n + 1])
          : ($shape_type === 'LineString'
            ? $this->t('Shape @n: Line', ['@n' => $n + 1])
            : $this->t('Shape @n: Polygon', ['@n' => $n + 1])),
        '#attributes' => [
          'id' => $shape_id,
          'tabindex' => '-1',
          'class' => ['mukurtu-geofield-shape'],
        ],
      ];
      $value_element['shapes'][$n]['shape_type'] = ['#type' => 'value', '#value' => $shape_type];
      $value_element['shapes'][$n]['source_feature'] = [
        '#type' => 'value',
        '#value' => $shape_row['feature'] ?? ['type' => 'Feature', 'properties' => []],
      ];
      $value_element['shapes'][$n]['source_index'] = [
        '#type' => 'value',
        '#value' => $shape_row['index'] ?? NULL,
      ];

      if ($is_point) {
        $vertex = $shape_row['vertices'][0] ?? NULL;
        $geocoded = $field_state['mukurtu_geocoded'][$n][0] ?? NULL;
        $value_element['shapes'][$n] += $this->buildVertexFields(
          $shape_id, $n, 0, $vertex, $geocoded, $geocoder_available, $field_name, $field_parents,
          $this->t('Coordinates for point @n', ['@n' => $n + 1]),
          $this->t('Point @n', ['@n' => $n + 1]),
        );
        if ($show_descriptions) {
          $value_element['shapes'][$n]['location_description'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Description for point @n', ['@n' => $n + 1]),
            '#maxlength' => 255,
            '#default_value' => $shape_row['description'] ?? '',
          ];
        }
      }
      else {
        if ($show_descriptions) {
          $value_element['shapes'][$n]['location_description'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Description for shape @n', ['@n' => $n + 1]),
            '#maxlength' => 255,
            '#default_value' => $shape_row['description'] ?? '',
          ];
        }

        $vertices_wrapper_id = $shape_id . '-vertices';
        $value_element['shapes'][$n]['vertices'] = [
          '#type' => 'container',
          '#mukurtu_wrapper_id' => $vertices_wrapper_id,
          '#prefix' => '<div id="' . $vertices_wrapper_id . '">',
          '#suffix' => '</div>',
        ];

        for ($v = 0; $v < $vertex_count; $v++) {
          $vertex = $shape_row['vertices'][$v] ?? NULL;
          $geocoded = $field_state['mukurtu_geocoded'][$n][$v] ?? NULL;
          $vertex_id = $shape_id . '-vertex-' . $v;

          $value_element['shapes'][$n]['vertices'][$v] = [
            '#type' => 'container',
            '#attributes' => ['id' => $vertex_id, 'tabindex' => '-1', 'class' => ['mukurtu-geofield-vertex']],
          ] + $this->buildVertexFields(
            $vertex_id, $n, $v, $vertex, $geocoded, $geocoder_available, $field_name, $field_parents,
            $this->t('Coordinates for vertex @v of shape @n', ['@v' => $v + 1, '@n' => $n + 1]),
            $this->t('Shape @n, vertex @v', ['@n' => $n + 1, '@v' => $v + 1]),
          );

          if ($v > 0) {
            $value_element['shapes'][$n]['vertices'][$v]['move_up'] = [
              '#type' => 'submit',
              '#value' => $this->t('Move vertex up'),
              '#name' => str_replace('-', '_', $vertex_id) . '_move_up',
              '#limit_validation_errors' => [],
              '#submit' => [[static::class, 'moveVertexUpSubmit']],
              '#ajax' => ['callback' => [static::class, 'shapesAjax']],
              '#mukurtu_shape_delta' => $n,
              '#mukurtu_vertex_delta' => $v,
            ];
          }
          if ($v < $vertex_count - 1) {
            $value_element['shapes'][$n]['vertices'][$v]['move_down'] = [
              '#type' => 'submit',
              '#value' => $this->t('Move vertex down'),
              '#name' => str_replace('-', '_', $vertex_id) . '_move_down',
              '#limit_validation_errors' => [],
              '#submit' => [[static::class, 'moveVertexDownSubmit']],
              '#ajax' => ['callback' => [static::class, 'shapesAjax']],
              '#mukurtu_shape_delta' => $n,
              '#mukurtu_vertex_delta' => $v,
            ];
          }
          if ($vertex_count > 1) {
            $value_element['shapes'][$n]['vertices'][$v]['remove_vertex'] = [
              '#type' => 'submit',
              '#value' => $this->t('Remove vertex @v', ['@v' => $v + 1]),
              '#name' => str_replace('-', '_', $vertex_id) . '_remove',
              '#limit_validation_errors' => [],
              '#submit' => [[static::class, 'removeVertexSubmit']],
              '#ajax' => ['callback' => [static::class, 'shapesAjax']],
              '#attributes' => [
                'aria-label' => $this->t('Remove vertex @v from shape @n', ['@v' => $v + 1, '@n' => $n + 1]),
              ],
              '#mukurtu_shape_delta' => $n,
              '#mukurtu_vertex_delta' => $v,
            ];
          }
        }

        $value_element['shapes'][$n]['vertices']['add_vertex'] = [
          '#type' => 'submit',
          '#value' => $this->t('Add vertex'),
          '#name' => str_replace('-', '_', $shape_id) . '_add_vertex',
          '#limit_validation_errors' => [],
          '#submit' => [[static::class, 'addVertexSubmit']],
          '#ajax' => ['callback' => [static::class, 'shapesAjax']],
          '#mukurtu_shape_delta' => $n,
        ];
      }

      if (count($shapes_state) > 1) {
        $value_element['shapes'][$n]['remove_shape'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove shape @n', ['@n' => $n + 1]),
          '#name' => str_replace('-', '_', $shape_id) . '_remove_shape',
          '#limit_validation_errors' => [],
          '#submit' => [[static::class, 'removeShapeSubmit']],
          '#ajax' => ['callback' => [static::class, 'shapesAjax']],
          '#attributes' => ['aria-label' => $this->t('Remove shape @n', ['@n' => $n + 1])],
          '#mukurtu_shape_delta' => $n,
        ];
      }
    }

    $value_element['add_point'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a point'),
      '#name' => str_replace('-', '_', $wrapper_id) . '_add_point',
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addPointShapeSubmit']],
      '#ajax' => ['callback' => [static::class, 'shapesAjax']],
    ];
    $value_element['add_line'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a line'),
      '#name' => str_replace('-', '_', $wrapper_id) . '_add_line',
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addLineShapeSubmit']],
      '#ajax' => ['callback' => [static::class, 'shapesAjax']],
    ];
    $value_element['add_polygon'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a polygon'),
      '#name' => str_replace('-', '_', $wrapper_id) . '_add_polygon',
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addPolygonShapeSubmit']],
      '#ajax' => ['callback' => [static::class, 'shapesAjax']],
    ];

    if ($geocoder_available) {
      $value_element['geocoder_attribution'] = [
        '#type' => 'markup',
        '#markup' => '<p class="mukurtu-geofield-attribution">' . $this->t('Location search powered by OpenStreetMap Nominatim.') . '</p>',
      ];
    }

    $value_element['#element_validate'][] = [static::class, 'validateWidget'];

    return ['value' => $value_element];
  }

  /**
   * Builds the place-search + coordinates fields shared by a Point shape
   * and each vertex of a Line/Polygon shape.
   */
  protected function buildVertexFields(
    string $id,
    int $shape_delta,
    int $vertex_delta,
    ?array $vertex,
    ?array $geocoded,
    bool $show_search,
    string $field_name,
    array $field_parents,
    $coordinates_title,
    $error_label,
  ): array {
    $fields = [];

    if ($show_search) {
      $fields['place_search'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search for a place'),
        '#size' => 40,
        '#attributes' => ['class' => ['mukurtu-geofield-place-search']],
      ];
      $fields['search'] = [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
        '#name' => str_replace('-', '_', $id) . '_search',
        '#limit_validation_errors' => [],
        '#submit' => [[static::class, 'searchPlaceSubmit']],
        '#ajax' => ['callback' => [static::class, 'shapesAjax']],
        '#mukurtu_shape_delta' => $shape_delta,
        '#mukurtu_vertex_delta' => $vertex_delta,
        '#mukurtu_field_name' => $field_name,
        '#mukurtu_field_parents' => $field_parents,
      ];
    }

    $fields['coordinates'] = [
      '#type' => 'geofield_latlon',
      '#title' => $coordinates_title,
      '#title_display' => 'invisible',
      '#error_label' => $error_label,
      '#id' => $id . '-coordinates',
      '#default_value' => [
        'lat' => $geocoded['lat'] ?? ($vertex['lat'] ?? ''),
        'lon' => $geocoded['lon'] ?? ($vertex['lon'] ?? ''),
      ],
      '#process' => [
        [GeofieldLatLon::class, 'latlonProcess'],
        [static::class, 'processCoordinateInputs'],
      ],
    ];

    return $fields;
  }

  /**
   * Adds input-mode/format hints to the geofield_latlon element's fields.
   */
  public static function processCoordinateInputs(array $element, FormStateInterface $form_state, array &$complete_form) {
    $examples = ['lat' => '46.7298', 'lon' => '-117.1817'];
    foreach ($examples as $component => $example) {
      if (isset($element[$component])) {
        $element[$component]['#attributes']['inputmode'] = 'decimal';
        $element[$component]['#attributes']['autocomplete'] = 'off';
        $element[$component]['#attributes']['id'] = ($element['#id'] ?? 'mukurtu-geofield-latlon') . '-' . $component;
        $element[$component]['#description'] = t('Decimal degrees, e.g. @example.', ['@example' => $example]);
      }
    }
    return $element;
  }

  /**
   * Element validate callback for the widget's outer fieldset.
   */
  public static function validateWidget(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (!isset($element['shapes'])) {
      return;
    }
    foreach (Element::children($element['shapes']) as $n) {
      if (!is_numeric($n)) {
        continue;
      }
      $shape = &$element['shapes'][$n];
      $shape_type = $shape['shape_type']['#value'] ?? 'Point';

      if ($shape_type === 'Point') {
        if (!isset($shape['coordinates'])) {
          continue;
        }
        $lat = trim((string) ($shape['coordinates']['lat']['#value'] ?? ''));
        $lon = trim((string) ($shape['coordinates']['lon']['#value'] ?? ''));
        if (($lat === '') xor ($lon === '')) {
          $form_state->setError($shape['coordinates'], t('Point @n: enter both a latitude and a longitude.', ['@n' => $n + 1]));
        }
        continue;
      }

      if (!isset($shape['vertices'])) {
        continue;
      }
      foreach (Element::children($shape['vertices']) as $v) {
        if (!is_numeric($v)) {
          continue;
        }
        $vertex = &$shape['vertices'][$v];
        if (!isset($vertex['coordinates'])) {
          continue;
        }
        $lat = trim((string) ($vertex['coordinates']['lat']['#value'] ?? ''));
        $lon = trim((string) ($vertex['coordinates']['lon']['#value'] ?? ''));
        if (($lat === '') xor ($lon === '')) {
          $form_state->setError($vertex['coordinates'], t('Shape @n, vertex @v: enter both a latitude and a longitude.', ['@n' => $n + 1, '@v' => $v + 1]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      $submitted = is_array($value['value'] ?? NULL) ? $value['value'] : [];

      if (!empty($submitted['raw_passthrough'])) {
        $values[$delta]['value'] = $submitted['raw_passthrough'];
        continue;
      }

      $features = [];
      foreach (($submitted['preserved_features'] ?? []) as $index => $feature) {
        if (is_array($feature)) {
          $features[(int) $index] = $feature;
        }
      }
      $next_index = $features ? max(array_keys($features)) + 1 : 0;

      foreach (($submitted['shapes'] ?? []) as $key => $shape_row) {
        if (!is_numeric($key) || !is_array($shape_row)) {
          continue;
        }
        $shape_type = $shape_row['shape_type'] ?? 'Point';

        if ($shape_type === 'Point') {
          $lat = trim((string) ($shape_row['coordinates']['lat'] ?? ''));
          $lon = trim((string) ($shape_row['coordinates']['lon'] ?? ''));
          if ($lat === '' || $lon === '' || !is_numeric($lat) || !is_numeric($lon)) {
            // Empty row: nothing to save (deleted or never filled in).
            continue;
          }
          $coordinates = [(float) $lon, (float) $lat];
        }
        else {
          $vertices = [];
          foreach (($shape_row['vertices'] ?? []) as $vkey => $vertex_row) {
            if (!is_numeric($vkey) || !is_array($vertex_row)) {
              continue;
            }
            $vlat = trim((string) ($vertex_row['coordinates']['lat'] ?? ''));
            $vlon = trim((string) ($vertex_row['coordinates']['lon'] ?? ''));
            if ($vlat === '' || $vlon === '' || !is_numeric($vlat) || !is_numeric($vlon)) {
              continue;
            }
            $vertices[] = [(float) $vlon, (float) $vlat];
          }

          // Under-populated shapes are dropped silently rather than
          // blocked with a validation error, mirroring how an empty Point
          // row is already silently skipped above.
          $min_vertices = $shape_type === 'Polygon' ? 3 : 2;
          if (count($vertices) < $min_vertices) {
            continue;
          }

          if ($shape_type === 'Polygon') {
            // Explicitly close the ring - this must happen here regardless
            // of geoPHP's own leniency, since the JSON is read directly by
            // MukurtuLeafletFormatter and the map widget's JS.
            $vertices[] = $vertices[0];
            $coordinates = [$vertices];
          }
          else {
            $coordinates = $vertices;
          }
        }

        $feature = is_array($shape_row['source_feature'] ?? NULL) ? $shape_row['source_feature'] : ['type' => 'Feature', 'properties' => []];
        $feature['type'] = 'Feature';
        $feature['geometry'] = [
          'type' => $shape_type,
          'coordinates' => $coordinates,
        ];
        $properties = is_array($feature['properties'] ?? NULL) ? $feature['properties'] : [];
        $description = trim((string) ($shape_row['location_description'] ?? ''));
        if ($description !== '') {
          $properties['location_description'] = $description;
        }
        else {
          unset($properties['location_description']);
        }
        // json_encode([]) produces "[]", not the "{}" a GeoJSON properties
        // object requires; cast an empty array so it encodes as an object.
        $feature['properties'] = $properties ?: new \stdClass();

        $index = isset($shape_row['source_index']) && $shape_row['source_index'] !== NULL
          ? (int) $shape_row['source_index']
          : $next_index++;
        $features[$index] = $feature;
      }

      ksort($features);
      // Emit '' (not an empty FeatureCollection) when nothing remains, so
      // FieldItemList::filterEmptyItems() drops the delta cleanly instead
      // of tripping the GeoType validation constraint.
      $values[$delta]['value'] = $features
        ? json_encode(['type' => 'FeatureCollection', 'features' => array_values($features)])
        : '';
    }

    return $values;
  }

  /**
   * Parses a stored geofield value into editable shapes (Point, LineString,
   * or single-ring Polygon), preserved unsupported features, and/or an
   * unparseable passthrough value.
   */
  protected function parseStoredValue($value): array {
    $result = ['shapes' => [], 'other' => [], 'passthrough' => NULL];
    if ($value === NULL || $value === '') {
      return $result;
    }

    $decoded = json_decode($value, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
      $result['passthrough'] = $value;
      return $result;
    }

    if (($decoded['type'] ?? NULL) === 'FeatureCollection' && is_array($decoded['features'] ?? NULL)) {
      $features = $decoded['features'];
    }
    elseif (($decoded['type'] ?? NULL) === 'Feature') {
      $features = [$decoded];
    }
    elseif (isset($decoded['type'], $decoded['coordinates'])) {
      $features = [['type' => 'Feature', 'properties' => [], 'geometry' => $decoded]];
    }
    else {
      $result['passthrough'] = $value;
      return $result;
    }

    foreach ($features as $index => $feature) {
      if (!is_array($feature)) {
        $result['other'][$index] = $feature;
        continue;
      }
      $shape = $this->extractEditableShape($feature['geometry'] ?? NULL);
      if ($shape !== NULL) {
        $properties = is_array($feature['properties'] ?? NULL) ? $feature['properties'] : [];
        $result['shapes'][] = [
          'index' => $index,
          'type' => $shape['type'],
          'vertices' => $shape['vertices'],
          'description' => (string) ($properties['location_description'] ?? ''),
          'feature' => $feature,
        ];
      }
      else {
        $result['other'][$index] = $feature;
      }
    }

    return $result;
  }

  /**
   * Extracts editable vertices from a geometry if it's a Point, LineString,
   * or single-ring Polygon (no holes); NULL otherwise (multi-part
   * geometries, polygons with holes, or malformed data - these are
   * preserved untouched rather than edited here).
   */
  protected function extractEditableShape($geometry): ?array {
    if (!is_array($geometry) || !isset($geometry['type'], $geometry['coordinates']) || !is_array($geometry['coordinates'])) {
      return NULL;
    }
    $type = $geometry['type'];
    $coordinates = $geometry['coordinates'];

    if ($type === 'Point') {
      if (count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
        return ['type' => 'Point', 'vertices' => [$this->vertexFromCoordinatePair($coordinates)]];
      }
      return NULL;
    }

    if ($type === 'LineString') {
      $vertices = $this->verticesFromCoordinateList($coordinates);
      return $vertices ? ['type' => 'LineString', 'vertices' => $vertices] : NULL;
    }

    if ($type === 'Polygon' && count($coordinates) === 1 && is_array($coordinates[0])) {
      $ring = $coordinates[0];
      // Drop the duplicated closing point before exposing it for editing;
      // it's re-added on save.
      if (count($ring) > 1 && $ring[0] == $ring[count($ring) - 1]) {
        array_pop($ring);
      }
      $vertices = $this->verticesFromCoordinateList($ring);
      return $vertices ? ['type' => 'Polygon', 'vertices' => $vertices] : NULL;
    }

    return NULL;
  }

  /**
   * Converts a single [lon, lat] pair into a vertex array.
   */
  protected function vertexFromCoordinatePair(array $pair): array {
    return ['lon' => (string) $pair[0], 'lat' => (string) $pair[1]];
  }

  /**
   * Converts a list of [lon, lat] pairs into vertices, or NULL if any pair
   * is malformed.
   */
  protected function verticesFromCoordinateList($list): ?array {
    if (!is_array($list) || !$list) {
      return NULL;
    }
    $vertices = [];
    foreach ($list as $pair) {
      if (!is_array($pair) || count($pair) < 2 || !is_numeric($pair[0]) || !is_numeric($pair[1])) {
        return NULL;
      }
      $vertices[] = $this->vertexFromCoordinatePair($pair);
    }
    return $vertices;
  }

  /**
   * Shared logic for the "Add a point/line/polygon" submit handlers.
   */
  protected static function addShape(array $form, FormStateInterface $form_state, string $type, int $initial_vertex_count): void {
    $button = $form_state->getTriggeringElement();
    // #array_parents: [..., 'value', 'add_point'|'add_line'|'add_polygon'] -
    // these buttons are direct siblings of 'value' (and of 'shapes'), not
    // nested inside 'shapes', so 'value' is simply one level up.
    $value_element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $value_element['#mukurtu_field_name'];
    $field_parents = $value_element['#field_parents'];

    $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
    $field_state['mukurtu_shapes'][] = ['type' => $type, 'vertex_count' => $initial_vertex_count];
    static::setWidgetState($field_parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "Add a point" button.
   */
  public static function addPointShapeSubmit(array $form, FormStateInterface $form_state) {
    static::addShape($form, $form_state, 'Point', 1);
  }

  /**
   * Submit handler for the "Add a line" button.
   */
  public static function addLineShapeSubmit(array $form, FormStateInterface $form_state) {
    static::addShape($form, $form_state, 'LineString', 2);
  }

  /**
   * Submit handler for the "Add a polygon" button.
   */
  public static function addPolygonShapeSubmit(array $form, FormStateInterface $form_state) {
    static::addShape($form, $form_state, 'Polygon', 3);
  }

  /**
   * Submit handler for a "Remove shape" button.
   */
  public static function removeShapeSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
    $array_parents = $button['#array_parents'];
    $shapes_index = array_search('shapes', $array_parents, TRUE);
    $shapes_element = NestedArray::getValue($form, array_slice($array_parents, 0, $shapes_index + 1));
    $value_element = NestedArray::getValue($form, array_slice($array_parents, 0, $shapes_index));
    $field_name = $value_element['#mukurtu_field_name'];
    $field_parents = $value_element['#field_parents'];

    $user_input = $form_state->getUserInput();
    $shapes_input_parents = $shapes_element['#parents'] ?? NULL;
    if ($shapes_input_parents) {
      $shapes_input = NestedArray::getValue($user_input, $shapes_input_parents, $exists);
      if ($exists && is_array($shapes_input)) {
        unset($shapes_input[$delta]);
        NestedArray::setValue($user_input, $shapes_input_parents, array_values($shapes_input));
        $form_state->setUserInput($user_input);
      }
    }

    $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
    if (isset($field_state['mukurtu_shapes'][$delta])) {
      array_splice($field_state['mukurtu_shapes'], $delta, 1);
    }
    unset($field_state['mukurtu_geocoded'][$delta], $field_state['mukurtu_geocode_result'][$delta]);
    static::setWidgetState($field_parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "Add vertex" button.
   */
  public static function addVertexSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
    $array_parents = $button['#array_parents'];
    $shapes_index = array_search('shapes', $array_parents, TRUE);
    $value_element = NestedArray::getValue($form, array_slice($array_parents, 0, $shapes_index));
    $field_name = $value_element['#mukurtu_field_name'];
    $field_parents = $value_element['#field_parents'];

    $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
    if (isset($field_state['mukurtu_shapes'][$delta])) {
      $field_state['mukurtu_shapes'][$delta]['vertex_count'] = ($field_state['mukurtu_shapes'][$delta]['vertex_count'] ?? 1) + 1;
    }
    static::setWidgetState($field_parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Submit handler for a "Remove vertex" button.
   */
  public static function removeVertexSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $shape_delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
    $vertex_delta = (int) ($button['#mukurtu_vertex_delta'] ?? 0);
    $array_parents = $button['#array_parents'];
    $shapes_index = array_search('shapes', $array_parents, TRUE);
    $value_element = NestedArray::getValue($form, array_slice($array_parents, 0, $shapes_index));
    $field_name = $value_element['#mukurtu_field_name'];
    $field_parents = $value_element['#field_parents'];

    $vertices_render_parents = array_merge(array_slice($array_parents, 0, $shapes_index + 1), [$shape_delta, 'vertices']);
    $vertices_element = NestedArray::getValue($form, $vertices_render_parents);

    $user_input = $form_state->getUserInput();
    $vertices_input_parents = $vertices_element['#parents'] ?? NULL;
    if ($vertices_input_parents) {
      $vertices_input = NestedArray::getValue($user_input, $vertices_input_parents, $exists);
      if ($exists && is_array($vertices_input)) {
        unset($vertices_input[$vertex_delta]);
        NestedArray::setValue($user_input, $vertices_input_parents, array_values($vertices_input));
        $form_state->setUserInput($user_input);
      }
    }

    $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
    if (isset($field_state['mukurtu_shapes'][$shape_delta])) {
      $field_state['mukurtu_shapes'][$shape_delta]['vertex_count'] = max(0, ($field_state['mukurtu_shapes'][$shape_delta]['vertex_count'] ?? 1) - 1);
    }
    unset($field_state['mukurtu_geocoded'][$shape_delta][$vertex_delta]);
    static::setWidgetState($field_parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Shared logic for the "Move vertex up/down" submit handlers - swaps two
   * adjacent vertices' submitted input, since drag-and-drop reordering
   * isn't keyboard/screen-reader operable.
   */
  protected static function swapVertices(array $form, FormStateInterface $form_state, int $offset): void {
    $button = $form_state->getTriggeringElement();
    $vertex_delta = (int) ($button['#mukurtu_vertex_delta'] ?? 0);
    $other_delta = $vertex_delta + $offset;
    $shape_delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
    $array_parents = $button['#array_parents'];
    $shapes_index = array_search('shapes', $array_parents, TRUE);

    $vertices_render_parents = array_merge(array_slice($array_parents, 0, $shapes_index + 1), [$shape_delta, 'vertices']);
    $vertices_element = NestedArray::getValue($form, $vertices_render_parents);

    $user_input = $form_state->getUserInput();
    $vertices_input_parents = $vertices_element['#parents'] ?? NULL;
    if ($vertices_input_parents) {
      $vertices_input = NestedArray::getValue($user_input, $vertices_input_parents, $exists);
      if ($exists && is_array($vertices_input) && isset($vertices_input[$vertex_delta], $vertices_input[$other_delta])) {
        $tmp = $vertices_input[$vertex_delta];
        $vertices_input[$vertex_delta] = $vertices_input[$other_delta];
        $vertices_input[$other_delta] = $tmp;
        NestedArray::setValue($user_input, $vertices_input_parents, $vertices_input);
        $form_state->setUserInput($user_input);
      }
    }

    $form_state->setRebuild();
  }

  /**
   * Submit handler for a "Move vertex up" button.
   */
  public static function moveVertexUpSubmit(array $form, FormStateInterface $form_state) {
    static::swapVertices($form, $form_state, -1);
  }

  /**
   * Submit handler for a "Move vertex down" button.
   */
  public static function moveVertexDownSubmit(array $form, FormStateInterface $form_state) {
    static::swapVertices($form, $form_state, 1);
  }

  /**
   * Submit handler for a vertex's "Search" button.
   *
   * Runs the geocoding lookup server-side so the search works with or
   * without JavaScript. The found coordinates are written directly into
   * $form_state's user input (not just #default_value) because on a
   * rebuild, FormBuilder::doBuildForm() prefers existing submitted input
   * (here, the still-empty lat/lon the user hadn't filled in) over
   * #default_value - #default_value alone would not actually populate
   * these fields in the browser. shapesAjax() only re-renders the
   * already-updated state and adds a screen reader announcement.
   */
  public static function searchPlaceSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $shape_delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
    $vertex_delta = (int) ($button['#mukurtu_vertex_delta'] ?? 0);
    $row_parents = array_slice($button['#parents'], 0, -1);
    $query = trim((string) $form_state->getValue(array_merge($row_parents, ['place_search'])));

    $field_name = $button['#mukurtu_field_name'] ?? NULL;
    $field_parents = $button['#mukurtu_field_parents'] ?? [];

    if ($query !== '' && $field_name !== NULL) {
      $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
      $found = static::geocodeQuery($query);

      if ($found) {
        $field_state['mukurtu_geocoded'][$shape_delta][$vertex_delta] = $found;

        $user_input = $form_state->getUserInput();
        $coordinates_parents = array_merge($row_parents, ['coordinates']);
        NestedArray::setValue($user_input, array_merge($coordinates_parents, ['lat']), $found['lat']);
        NestedArray::setValue($user_input, array_merge($coordinates_parents, ['lon']), $found['lon']);
        $form_state->setUserInput($user_input);

        $message = t('Found @query at @lat, @lon. Adjust the coordinates below if needed.', [
          '@query' => $query,
          '@lat' => $found['lat'],
          '@lon' => $found['lon'],
        ]);
        \Drupal::messenger()->addStatus($message);
      }
      else {
        $message = t('No matching place found. Try a different search or enter coordinates directly.');
        \Drupal::messenger()->addWarning($message);
      }
      $field_state['mukurtu_geocode_result'][$shape_delta][$vertex_delta] = (string) $message;
      static::setWidgetState($field_parents, $field_name, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * Looks up a free-text place name via the site's configured Nominatim
   * geocoder provider.
   *
   * @return array{lat: string, lon: string}|null
   */
  protected static function geocodeQuery(string $query): ?array {
    try {
      $provider = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->load('nominatim');
      if (!$provider) {
        return NULL;
      }
      $collection = \Drupal::service('geocoder')->geocode($query, [$provider]);
      if (!$collection || $collection->isEmpty()) {
        return NULL;
      }
      $coordinates = $collection->first()->getCoordinates();
      if (!$coordinates) {
        return NULL;
      }
      return [
        'lat' => (string) round($coordinates->getLatitude(), 6),
        'lon' => (string) round($coordinates->getLongitude(), 6),
      ];
    }
    catch (\Throwable $e) {
      \Drupal::logger('mukurtu_core')->warning('Place search failed for query "@query": @message', [
        '@query' => $query,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Shared AJAX callback for every shape/vertex button (add/remove shape,
   * add/remove/move vertex, search) - always replaces the whole shapes
   * wrapper, which is simple, always correct, and cheap at the scale this
   * widget is used at.
   */
  public static function shapesAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $array_parents = $button['#array_parents'];
    $last = end($array_parents);

    $response = new AjaxResponse();

    if (in_array($last, ['add_point', 'add_line', 'add_polygon'], TRUE)) {
      // These buttons are siblings of 'shapes' (both children of 'value'),
      // not nested inside it.
      $shapes_parents = array_merge(array_slice($array_parents, 0, -1), ['shapes']);
    }
    else {
      $shapes_index = array_search('shapes', $array_parents, TRUE);
      if ($shapes_index === FALSE) {
        return $response;
      }
      $shapes_parents = array_slice($array_parents, 0, $shapes_index + 1);
    }

    $shapes_element = NestedArray::getValue($form, $shapes_parents);
    $wrapper_id = $shapes_element['#mukurtu_wrapper_id'] ?? NULL;

    if ($wrapper_id) {
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $shapes_element));
    }

    $shape_count = count(Element::children($shapes_element));

    switch ($last) {
      case 'add_point':
      case 'add_line':
      case 'add_polygon':
        $response->addCommand(new AnnounceCommand((string) t('Shape added.')));
        if ($wrapper_id) {
          $response->addCommand(new InvokeCommand('#' . $wrapper_id . '-shape-' . ($shape_count - 1), 'trigger', ['focus']));
        }
        break;

      case 'remove_shape':
        $response->addCommand(new AnnounceCommand((string) t('Shape removed. @count shapes remain.', ['@count' => $shape_count])));
        if ($wrapper_id && $shape_count > 0) {
          $response->addCommand(new InvokeCommand('#' . $wrapper_id . '-shape-0', 'trigger', ['focus']));
        }
        break;

      case 'add_vertex':
        $response->addCommand(new AnnounceCommand((string) t('Vertex added.')));
        break;

      case 'remove_vertex':
        $response->addCommand(new AnnounceCommand((string) t('Vertex removed.')));
        break;

      case 'move_up':
        $response->addCommand(new AnnounceCommand((string) t('Vertex moved up.')));
        break;

      case 'move_down':
        $response->addCommand(new AnnounceCommand((string) t('Vertex moved down.')));
        break;

      case 'search':
        $field_name = $button['#mukurtu_field_name'] ?? NULL;
        $field_parents = $button['#mukurtu_field_parents'] ?? [];
        $shape_delta = (int) ($button['#mukurtu_shape_delta'] ?? 0);
        $vertex_delta = (int) ($button['#mukurtu_vertex_delta'] ?? 0);
        if ($field_name !== NULL) {
          $field_state = static::getWidgetState($field_parents, $field_name, $form_state);
          $outcome = $field_state['mukurtu_geocode_result'][$shape_delta][$vertex_delta] ?? NULL;
          if ($outcome) {
            $response->addCommand(new AnnounceCommand($outcome));
          }
        }
        break;
    }

    return $response;
  }

}
