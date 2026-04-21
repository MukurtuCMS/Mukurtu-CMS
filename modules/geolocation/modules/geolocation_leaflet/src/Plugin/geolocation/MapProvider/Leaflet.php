<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapProvider;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapProviderBase;

/**
 * Provides Leaflet maps.
 *
 * @MapProvider(
 *   id = "leaflet",
 *   name = @Translation("Leaflet"),
 *   description = @Translation("Leaflet support."),
 * )
 */
class Leaflet extends MapProviderBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'zoom' => 10,
        'height' => '400px',
        'width' => '100%',
        'minZoom' => 0,
        'maxZoom' => 0,
        'maxBounds' => [
          'north_east_bound' => [],
          'south_west_bound' => [],
        ],
        'crs' => 'EPSG3857',
        'preferCanvas' => FALSE,
        'zoomSnap' => 1,
        'zoomDelta' => 1,
        'trackResize' => TRUE,
        'boxZoom' => TRUE,
        'doubleClickZoom' => TRUE,
        'dragging' => TRUE,
        'zoomAnimation' => TRUE,
        'zoomAnimationThreshold' => 4,
        'fadeAnimation' => TRUE,
        'markerZoomAnimation' => TRUE,
        'inertia' => FALSE,
        'inertiaDeceleration' => 3000,
        'easeLinearity' => 0.2,
        'worldCopyJump' => FALSE,
        'maxBoundsViscosity' => 0.0,
        'keyboard' => TRUE,
        'keyboardPanDelta' => 80,
        'scrollWheelZoom' => TRUE,
        'wheelDebounceTime' => 40,
        'wheelPxPerZoomLevel' => 60,
        'tap' => TRUE,
        'tapTolerance' => 15,
        'touchZoom' => TRUE,
        'bounceAtZoomLimits' => TRUE,
        'map_features' => [
          'leaflet_control_zoom' => [
            'enabled' => TRUE,
          ],
          'leaflet_control_attribution' => [
            'enabled' => TRUE,
            'settings' => [
              'position' => 'bottomright',
            ],
          ],
          'leaflet_marker_popup' => [
            'enabled' => TRUE,
          ],
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings) {
    $settings = parent::getSettings($settings);

    $settings['zoom'] = (int) $settings['zoom'];

    if (empty($settings['minZoom'])) {
      unset($settings['minZoom']);
    }
    else {
      $settings['minZoom'] = (int) $settings['minZoom'];
    }
    if (empty($settings['maxZoom'])) {
      unset($settings['maxZoom']);
    }
    else {
      $settings['maxZoom'] = (int) $settings['maxZoom'];
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings) {
    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );
    $summary = parent::getSettingsSummary($settings);
    $summary[] = $this->t('Zoom level: @zoom', ['@zoom' => $settings['zoom']]);
    $summary[] = $this->t('Height: @height', ['@height' => $settings['height']]);
    $summary[] = $this->t('Width: @width', ['@width' => $settings['width']]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []) {
    $settings += self::getDefaultSettings();
    if ($parents) {
      $parents_string = implode('][', $parents);
    }
    else {
      $parents_string = NULL;
    }

    $form = parent::getSettingsForm($settings, $parents);

    $form['height'] = [
      '#group' => $parents_string,
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['height'],
    ];
    $form['width'] = [
      '#group' => $parents_string,
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['width'],
    ];
    $form['zoom'] = [
      '#group' => $parents_string,
      '#type' => 'select',
      '#title' => $this->t('Zoom level'),
      '#options' => range(0, 20),
      '#description' => $this->t('The initial resolution at which to display the map, where zoom 0 corresponds to a map of the Earth fully zoomed out, and higher zoom levels zoom in at a higher resolution.'),
      '#default_value' => $settings['zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
    ];
    $advanced_parents_string = $parents_string . '][advanced_settings';

    $form['minZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'select',
      '#title' => $this->t('Min Zoom level'),
      '#options' => range(0, 20),
      '#description' => $this->t('Minimum zoom level of the map. If not specified and at least one GridLayer or TileLayer is in the map, the lowest of their minZoom options will be used instead. 0 to skip.'),
      '#default_value' => $settings['minZoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['maxZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'select',
      '#title' => $this->t('Max Zoom level'),
      '#options' => range(0, 20),
      '#description' => $this->t('Maximum zoom level of the map. If not specified and at least one GridLayer or TileLayer is in the map, the highest of their maxZoom options will be used instead. 0 to skip.'),
      '#default_value' => $settings['maxZoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    $form['maxBounds'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'fieldset',
      '#title' => $this->t('Max Bounds'),
      '#description' => $this->t('When this option is set, the map restricts the view to the given geographical bounds, bouncing the user back if the user tries to pan outside the view. To set the restriction dynamically, use setMaxBounds method.'),
      'north_east_bound' => [
        '#title' => $this->t('North East'),
        '#type' => 'geolocation_input',
        '#default_value' => $settings['maxBounds']['north_east_bound'],
      ],
      'south_west_bound' => [
        '#title' => $this->t('South West'),
        '#type' => 'geolocation_input',
        '#default_value' => $settings['maxBounds']['south_west_bound'],
      ],
    ];
    $form['crs'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'select',
      '#title' => $this->t('CRS'),
      '#options' => [
        'EPSG3395' => 'L.CRS.EPSG3395',
        'EPSG3857' => 'L.CRS.EPSG3857',
        'EPSG4326' => 'L.CRS.EPSG4326',
        'Earth' => 'L.CRS.Earth',
        'Simple' => 'L.CRS.Simple',
        'Base' => 'L.CRS.Base',
      ],
      '#description' => $this->t("The Coordinate Reference System to use. Don't change this if you're not sure what it means. Do not add 'L.CRS.' in front."),
      '#default_value' => $settings['crs'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['preferCanvas'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t('Render Paths on a Canvas renderer. By default, all Paths are rendered in a SVG renderer.'),
      '#default_value' => $settings['preferCanvas'],
    ];
    $form['zoomSnap'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Zoom Snap'),
      '#description' => $this->t("Forces the map's zoom level to always be a multiple of this, particularly right after a fitBounds() or a pinch-zoom. By default, the zoom level snaps to the nearest integer; lower values (e.g. 0.5 or 0.1) allow for greater granularity. A value of 0 means the zoom level will not be snapped after fitBounds or a pinch-zoom."),
      '#default_value' => $settings['zoomSnap'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['zoomDelta'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Tap Tolerance'),
      '#description' => $this->t("Controls how much the map's zoom level will change after a zoomIn(), zoomOut(), pressing + or - on the keyboard, or using the zoom controls. Values smaller than 1 (e.g. 0.5) allow for greater granularity."),
      '#default_value' => $settings['zoomDelta'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['trackResize'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically update map on browser window resize.'),
      '#default_value' => $settings['trackResize'],
    ];
    $form['boxZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t('Map can be zoomed to a rectangular area specified by dragging the mouse while pressing the shift key.'),
      '#default_value' => $settings['boxZoom'],
    ];
    $form['doubleClickZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Map can be zoomed in by double clicking on it and zoomed out by double clicking while holding shift. If passed 'center', double-click zoom will zoom to the center of the view regardless of where the mouse was."),
      '#default_value' => $settings['doubleClickZoom'],
    ];
    $form['dragging'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t('Map is draggable with mouse/touch or not.'),
      '#default_value' => $settings['dragging'],
    ];
    $form['zoomAnimation'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Map zoom animation is enabled. By default it's enabled in all browsers that support CSS3 Transitions except Android."),
      '#default_value' => $settings['zoomAnimation'],
    ];
    $form['zoomAnimationThreshold'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Zoom Animation Threshold'),
      '#description' => $this->t("Won't animate zoom if the zoom difference exceeds this value."),
      '#default_value' => $settings['zoomAnimationThreshold'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['fadeAnimation'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Tile fade animation is enabled. By default it's enabled in all browsers that support CSS3 Transitions except Android."),
      '#default_value' => $settings['fadeAnimation'],
    ];
    $form['markerZoomAnimation'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Markers animate their zoom with the zoom animation, if disabled they will disappear for the length of the animation. By default it's enabled in all browsers that support CSS3 Transitions except Android."),
      '#default_value' => $settings['markerZoomAnimation'],
    ];
    $form['inertia'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("If enabled, panning of the map will have an inertia effect where the map builds momentum while dragging and continues moving in the same direction for some time. Feels especially nice on touch devices. Enabled by default unless running on old Android devices."),
      '#default_value' => $settings['inertia'],
    ];
    $form['inertiaDeceleration'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Inertia Deceleration'),
      '#description' => $this->t("The rate with which the inertial movement slows down, in pixels/second²."),
      '#default_value' => $settings['inertiaDeceleration'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['worldCopyJump'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("With this option enabled, the map tracks when you pan to another 'copy' of the world and seamlessly jumps to the original one so that all overlays like markers and vector layers are still visible."),
      '#default_value' => $settings['worldCopyJump'],
    ];

    $form['easeLinearity'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#step' => 0.1,
      '#title' => $this->t('Ease Linearity'),
      '#description' => $this->t("The curvature factor of panning animation easing (third parameter of the Cubic Bezier curve). 1.0 means linear animation, the less the more bowed the curve."),
      '#default_value' => $settings['easeLinearity'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['maxBoundsViscosity'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#step' => 0.1,
      '#title' => $this->t('Max Bounds Viscosity'),
      '#description' => $this->t("If maxBounds is set, this option will control how solid the bounds are when dragging the map around. The default value of 0.0 allows the user to drag outside the bounds at normal speed, higher values will slow down map dragging outside bounds, and 1.0 makes the bounds fully solid, preventing the user from dragging outside the bounds."),
      '#default_value' => $settings['maxBoundsViscosity'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['keyboard'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Makes the map focusable and allows users to navigate the map with keyboard arrows and +/- keys."),
      '#default_value' => $settings['keyboard'],
    ];
    $form['keyboardPanDelta'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Keyboard Pan Delta'),
      '#description' => $this->t("Amount of pixels to pan when pressing an arrow key."),
      '#default_value' => $settings['keyboardPanDelta'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['scrollWheelZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Map can be zoomed by using the mouse wheel. If passed 'center', it will zoom to the center of the view regardless of where the mouse was."),
      '#default_value' => $settings['scrollWheelZoom'],
    ];
    $form['wheelDebounceTime'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Wheel Debounce Time'),
      '#description' => $this->t("Limits the rate at which a wheel can fire (in milliseconds). By default user can't zoom via wheel more often than once per 40 ms."),
      '#default_value' => $settings['wheelDebounceTime'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['wheelPxPerZoomLevel'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Wheel Pixel Per Zoom Level'),
      '#description' => $this->t("How many scroll pixels (as reported by L.DomEvent.getWheelDelta) mean a change of one full zoom level. Smaller values will make wheel-zooming faster (and vice versa)."),
      '#default_value' => $settings['wheelPxPerZoomLevel'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['tap'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Enables mobile hacks for supporting instant taps (fixing 200ms click delay on iOS/Android) and touch holds (fired as contextmenu events)."),
      '#default_value' => $settings['tap'],
    ];
    $form['tapTolerance'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'number',
      '#title' => $this->t('Tap Tolerance'),
      '#description' => $this->t("The max number of pixels a user can shift his finger during touch for it to be considered a valid tap."),
      '#default_value' => $settings['tapTolerance'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['touchZoom'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Map can be zoomed by touch-dragging with two fingers. If passed 'center', it will zoom to the center of the view regardless of where the touch events (fingers) were. Enabled for touch-capable web browsers except for old Androids."),
      '#default_value' => $settings['touchZoom'],
    ];
    $form['bounceAtZoomLimits'] = [
      '#group' => $advanced_parents_string,
      '#type' => 'checkbox',
      '#title' => $this->t("Set it to false if you don't want the map to zoom beyond min/max zoom and then bounce back when pinch-zooming."),
      '#default_value' => $settings['bounceAtZoomLimits'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []) {
    if (
      !empty($map_settings['maxBounds'])
      && !empty($map_settings['maxBounds']['north_east_bound'])
      && !empty($map_settings['maxBounds']['south_west_bound'])
      && isset($map_settings['maxBounds']['north_east_bound']['lat'])
      && isset($map_settings['maxBounds']['north_east_bound']['lng'])
      && isset($map_settings['maxBounds']['south_west_bound']['lat'])
      && isset($map_settings['maxBounds']['south_west_bound']['lng'])
      && $map_settings['maxBounds']['north_east_bound']['lat'] != ''
      && $map_settings['maxBounds']['north_east_bound']['lng'] != ''
      && $map_settings['maxBounds']['south_west_bound']['lat'] != ''
      && $map_settings['maxBounds']['south_west_bound']['lng'] != ''
    ) {
      $map_settings['maxBounds'] = [
        [
          (float) $map_settings['maxBounds']['north_east_bound']['lat'],
          (float) $map_settings['maxBounds']['north_east_bound']['lng'],
        ],
        [
          (float) $map_settings['maxBounds']['south_west_bound']['lat'],
          (float) $map_settings['maxBounds']['south_west_bound']['lng'],
        ],
      ];
    }
    else {
      unset($map_settings['maxBounds']);
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/geolocation.leaflet',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'settings' => [
                  'leaflet_settings' => $map_settings,
                ],
              ],
            ],
          ],
        ],
      ]
    );

    return parent::alterRenderArray($render_array, $map_settings, $context);
  }

  /**
   * {@inheritdoc}
   */
  public static function getControlPositions() {
    return [
      'topleft' => t('Top left'),
      'topright' => t('Top right'),
      'bottomleft' => t('Bottom left'),
      'bottomright' => t('Bottom right'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterCommonMap(array $render_array, array $map_settings, array $context) {
    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/commonmap.leaflet',
        ],
      ]
    );

    return $render_array;
  }

}
