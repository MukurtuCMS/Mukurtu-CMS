<?php

namespace Drupal\leaflet;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\leaflet\Plugin\Field\FieldWidget\LeafletDefaultWidget;
use Drupal\views\Plugin\views\ViewsPluginInterface;

/**
 * Class LeafletSettingsElementsTrait.
 *
 * Provide common functions for Leaflet Settings Elements.
 *
 * @package Drupal\leaflet
 */
trait LeafletSettingsElementsTrait {

  /**
   * Get maps available for use with Leaflet.
   */
  protected static function getLeafletMaps() {
    $options = [];
    foreach (leaflet_map_get_info() as $key => $map) {
      $options[$key] = $map['label'];
    }
    return $options;
  }

  /**
   * Leaflet Controls Positions Options.
   *
   * @var array
   */
  protected $controlPositionsOptions = [
    'topleft' => 'Top Left',
    'topright' => 'Top Right',
    'bottomleft' => 'Bottom Left',
    'bottomright' => 'Bottom Right',
  ];

  /**
   * Leaflet Circle Radius Marker Field Types Options.
   *
   * @var array
   */
  protected $leafletCircleRadiusFieldTypesOptions = [
    'integer',
    'float',
    'decimal',
  ];

  /**
   * Generate the Token Replacement Disclaimer.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated markup.
   */
  protected function getTokenReplacementDisclaimer(): TranslatableMarkup {
    return $this->moduleHandler->moduleExists('token') ? $this->t('Using <strong>Tokens or Replacement Patterns</strong> it is possible to dynamically define the Path geometries options, based on the entity properties or fields values.')
      : $this->t('Using the @token_module_link it is possible to use <strong>Replacement Patterns</strong> and dynamically define the Path geometries options, based on the entity properties or fields values.', [
        '@token_module_link' => $this->link->generate($this->t('Toke module'), Url::fromUri('https://www.drupal.org/project/token', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]);
  }

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultSettings() {
    $base_layers = self::getLeafletMaps();

    return [
      'multiple_map' => FALSE,
      'leaflet_map' => $base_layers['OSM Mapnik'] ? 'OSM Mapnik' : array_shift($base_layers),
      'height' => 400,
      'height_unit' => 'px',
      'hide_empty_map' => FALSE,
      'disable_wheel' => FALSE,
      'gesture_handling' => FALSE,
      'fitbounds_options' => '{"padding":[0,0]}',
      // @todo Keep this for backword compatibility with Leaflet < 2.x.
      'popup' => FALSE,
      // @todo Keep this for backword compatibility with Leaflet < 2.x.
      'popup_content' => '',
      'leaflet_popup' => [
        'value' => '',
        'options' => '{"maxWidth":"300","minWidth":"50", "autoPan": true}',
        'view_mode' => 'full',
        'control' => FALSE,
        'content' => '',
      ],
      'leaflet_tooltip' => [
        'value' => '',
        'options' => '{"permanent":false,"direction":"center"}',
      ],
      'map_position' => [
        'force' => FALSE,
        'center' => [
          'lat' => 0,
          'lon' => 0,
        ],
        'zoomControlPosition' => 'topleft',
        'zoom' => 5,
        'minZoom' => 1,
        'maxZoom' => 18,
        'zoomFiner' => 0,
      ],
      'weight' => 0,
      'icon' => [
        'iconType' => 'marker',
        'iconUrl' => '',
        'shadowUrl' => '',
        'className' => '',
        'iconSize' => ['x' => NULL, 'y' => NULL],
        'iconAnchor' => ['x' => NULL, 'y' => NULL],
        'shadowSize' => ['x' => NULL, 'y' => NULL],
        'shadowAnchor' => ['x' => NULL, 'y' => NULL],
        'popupAnchor' => ['x' => NULL, 'y' => NULL],
        'html' => '<div></div>',
        'html_class' => 'leaflet-map-divicon',
        'circle_marker_options' => '{"radius": 100, "color": "red", "fillColor": "#f03", "fillOpacity": 0.5}',
      ],
      'leaflet_markercluster' => [
        'control' => FALSE,
        'options' => '{"spiderfyOnMaxZoom":true,"showCoverageOnHover":true,"removeOutsideVisibleBounds": false}',
        'excluded' => FALSE,
        'include_path' => FALSE,
      ],
      'fullscreen' => [
        'control' => FALSE,
        'options' => '{"position":"topleft","pseudoFullscreen":false}',
      ],
      'reset_map' => [
        'control' => FALSE,
        'options' => '{"position": "topleft", "title": "Reset View"}',
      ],
      'map_scale' => [
        'control' => FALSE,
        'options' => '{"position":"bottomright","maxWidth":100,"metric":true,"imperial":false,"updateWhenIdle":false}',
      ],
      'locate' => [
        'control' => FALSE,
        'options' => '{"position": "topright", "setView": "untilPanOrZoom", "returnToPrevBounds":true, "keepCurrentZoomLevel": true, "strings": {"title": "Locate my position"}}',
        'automatic' => FALSE,
      ],
      'path' => '{"color":"#3388ff","opacity":"1.0","stroke":true,"weight":3,"fill":"depends","fillColor":"*","fillOpacity":"0.2","radius":"6"}',
      'feature_properties' => [
        'values' => '',
      ],
      'geocoder' => [
        'control' => FALSE,
        'settings' => [
          'set_marker' => FALSE,
          'popup' => FALSE,
          'autocomplete' => [
            'placeholder' => 'Search Address',
            'title' => 'Search an Address on the Map',
          ],
          'position' => 'topright',
          'input_size' => 20,
          'providers' => [],
          'min_terms' => 4,
          'delay' => 800,
          'zoom' => 16,
          'options' => '',
        ],
      ],
      'map_lazy_load' => [
        'lazy_load' => 0,
      ],
    ];
  }

  /**
   * Generate the Leaflet Map General Settings.
   *
   * @param array $elements
   *   The form elements.
   * @param array $settings
   *   The settings.
   */
  protected function generateMapGeneralSettings(array &$elements, array $settings) {

    $leaflet_map_options = [];
    foreach (leaflet_map_get_info() as $key => $map) {
      $leaflet_map_options[$key] = $map['label'];
    }

    $leaflet_map = $settings['leaflet_map'] ?? $settings['map'];

    $elements['leaflet_map'] = [
      '#title' => $this->t('Leaflet Map Tiles Layer'),
      '#description' => $this->t('Choose the @leaflet_map_tiles Layer to start the Map with (@see hook_leaflet_map_info).', [
        '@leaflet_map_tiles' => $this->link->generate("Leaflet Js Library Map Tiles", Url::fromUri("https://leafletjs.com/reference.html#tilelayer", ['attributes' => ['target' => 'blank']])),
      ]),
      '#type' => 'select',
      '#options' => $leaflet_map_options,
      '#default_value' => $leaflet_map,
      '#required' => TRUE,
    ];

    $elements['height'] = [
      '#title' => $this->t('Map Height'),
      '#type' => 'number',
      '#default_value' => $settings['height'],
      '#description' => $this->t('Note: This can be left empty to make the Map fill its parent container height.'),
    ];

    $elements['height_unit'] = [
      '#title' => t('Map height unit'),
      '#type' => 'select',
      '#options' => [
        'px' => t('px'),
        '%' => t('%'),
        'vh' => t('vh'),
      ],
      '#default_value' => $settings['height_unit'],
      '#description' => t("Whether height is absolute (pixels) or relative (percent, vertical height).<br><strong>Note:</strong> In case of Percent the Leaflet Map should be wrapped in a container element with defined Height, otherwise won't show up."),
    ];

    $elements['hide_empty_map'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Map if empty'),
      '#description' => $this->t('Check this option not to render the Map at all, if empty (no output results).'),
      '#default_value' => $settings['hide_empty_map'],
      '#return_value' => 1,
    ];

    $elements['gesture_handling'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Gesture Handling'),
      '#description' => $this->t('Enable the @gesture_handling_link functionality for the Map.', [
        '@gesture_handling_link' => $this->link->generate($this->t('Leaflet Gesture Handling Library'), Url::fromUri('https://github.com/elmarquis/Leaflet.GestureHandling', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => $settings['gesture_handling'],
      '#return_value' => 1,
    ];

    $elements['disable_wheel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable zoom using mouse wheel'),
      '#description' => $this->t("If enabled, the mouse wheel won't change the zoom level of the map."),
      '#default_value' => $settings['disable_wheel'],
      '#return_value' => 1,
      '#states' => [
        'invisible' => [
          ':input[name="style_options[gesture_handling]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Set FitBounds Options Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setFitBoundsOptionsElement(array &$element, array $settings) {
    $default_settings = $this::getDefaultSettings();

    $fitbounds_options_description = $this->t('Set here options that will be applied when fitBounds is triggered (e.g. Zoom, Pan, Padding).<br>Refer to the @fitbounds_options_documentation.', [
      '@fitbounds_options_documentation' => $this->link->generate($this->t('Leaflet Fitbound Options Documentation'), Url::fromUri('https://leafletjs.com/reference.html#fitbounds-options', [
        'absolute' => TRUE,
        'attributes' => ['target' => 'blank'],
      ])),
    ]);

    $element['fitbounds_options'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('FitBounds Options'),
      '#description' => $fitbounds_options_description,
      '#default_value' => $settings['fitbounds_options'] ?? $default_settings['fitbounds_options'],
      '#placeholder' => $default_settings['fitbounds_options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Generate the Leaflet Map Position Form Element.
   *
   * @param array $map_position_options
   *   The map position options array definition.
   *
   * @return array
   *   The Leaflet Map Position Form Element.
   */
  protected function generateMapPositionElement(array $map_position_options) {

    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Map Center & Zoom'),
    ];

    if (isset($this->fieldDefinition)) {
      $force_checkbox_selector = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_position][force]"]';
      $force_checkbox_selector_widget = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map][map_position][force]"]';
    }
    elseif ($this instanceof ViewsPluginInterface) {
      $force_checkbox_selector = ':input[name="style_options[map_position][force]"]';
    }

    $element['description'] = [
      '#type' => 'container',
      'html_tag' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('These settings are be applied in case of single Marker/Feature on the Map, otherwise the Zoom will be set to Fit Elements bounds, and respecting the Min Zoom.<br>
<b>Note:</b> It is possible to remove the Zoom Control making Min and Max Zoom coincident (the initial Zoom value will be forced to those).'),
      ],
      '#states' => [
        'invisible' => isset($force_checkbox_selector_widget) ? [
          [$force_checkbox_selector => ['checked' => TRUE]],
          'or',
          [$force_checkbox_selector_widget => ['checked' => TRUE]],
        ] : [$force_checkbox_selector => ['checked' => TRUE]],
      ],
    ];

    $element['force'] = [
      '#type' => 'checkbox',
      '#description' => $this->t('These settings will be forced anyway as starting Map state.'),
      '#default_value' => $map_position_options['force'],
      '#return_value' => 1,
    ];

    $element['description']['#value'] = $this->t('These settings will be applied in case of empty Map.');
    $element['force']['#title'] = $this->t('Force Map Center & Zoom');

    $element['center'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Center'),
      'lat' => [
        '#title' => $this->t('Latitude'),
        '#type' => 'number',
        '#step' => 'any',
        '#size' => 4,
        '#default_value' => $map_position_options['center']['lat'] ?? $this->getDefaultSettings()['map_position']['center']['lat'],
        '#required' => FALSE,
      ],
      'lon' => [
        '#title' => $this->t('Longitude'),
        '#type' => 'number',
        '#step' => 'any',
        '#size' => 4,
        '#default_value' => $map_position_options['center']['lon'] ?? $this->getDefaultSettings()['map_position']['center']['lon'],
        '#required' => FALSE,
      ],
    ];

    $element['zoomControlPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom control position'),
      '#options' => $this->controlPositionsOptions,
      '#default_value' => $map_position_options['zoomControlPosition'] ?? $this->getDefaultSettings()['map_position']['zoomControlPosition'],
    ];

    $element['zoom'] = [
      '#title' => $this->t('Initial Zoom'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 22,
      '#description' => $this->t('The initial Zoom level for the Map in case of a Single Marker or when Forced (or when empty).<br><u>In case of multiple Markers/Features, the initial Zoom will automatically set so to extend the Map to the boundaries of all of them.</u><br>Admitted values usually range from 0 (the whole world) to 20 - 22, depending on the max zoom supported by the specific Map Tile in use.<br>As a reference consider Zoom 5 for a large country, 10 for a city, 15 for a road or a district.'),
      '#default_value' => $map_position_options['zoom'] ?? $this->getDefaultSettings()['map_position']['zoom'],
      '#required' => TRUE,
      '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
    ];

    $element['minZoom'] = [
      '#title' => $this->t('Min. Zoom'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 22,
      '#default_value' => $map_position_options['minZoom'] ?? $this->getDefaultSettings()['map_position']['minZoom'],
      '#required' => TRUE,
    ];

    $element['maxZoom'] = [
      '#title' => $this->t('Max. Zoom'),
      '#type' => 'number',
      '#min' => 1,
      '#max' => 22,
      '#default_value' => $map_position_options['maxZoom'] ?? $this->getDefaultSettings()['map_position']['maxZoom'],
      '#element_validate' => [[get_class($this), 'maxZoomLevelValidate']],
      '#required' => TRUE,
    ];

    $element['zoomFiner'] = [
      '#title' => $this->t('Zoom Finer'),
      '#type' => 'number',
      '#max' => 10,
      '#min' => -10,
      '#step' => 1,
      '#description' => $this->t('Use this selector (-10 | +10) to <u>zoom in or out on the Initial Zoom level, in case of multiple Markers/Features on the Map</u>.<br>Example: -2 will zoom out, adding padding around the markers, while 2 will zoom in, leaving out peripheral markers.<br>Note: This will still be constrained according with your Max & Min Zoom settings.'),
      '#default_value' => $map_position_options['zoomFiner'] ?? $this->getDefaultSettings()['map_position']['zoomFiner'],
      '#states' => [
        'invisible' => isset($force_checkbox_selector_widget) ? [
          [$force_checkbox_selector => ['checked' => TRUE]],
          'or',
          [$force_checkbox_selector_widget => ['checked' => TRUE]],
        ] : [$force_checkbox_selector => ['checked' => TRUE]],
      ],
    ];

    return $element;
  }

  /**
   * Generate the weight Form Element.
   *
   * @param string $weight
   *   The weight string definition.
   *
   * @return array
   *   The Leaflet weight Form Element.
   */
  protected function generateWeightElement($weight) {
    $default_settings = $this::getDefaultSettings();
    return [
      '#title' => $this->t('Weight / zIndex Offset'),
      '#type' => 'textfield',
      '#size' => 20,
      '#description' => $this->t('This option supports <b>Replacement Patterns</b> and should end up into an Integer (positive or negative value).<br>This will apply to each Leaflet Feature/Marker result, and might be used to dynamically set its position/visibility on top (or below) of each others (features with higher value will be rendered as last, and thus on top)<br>Note: this is not driving the "zIndex" css style of the features output on the Map, but only setting their rendering order.'),
      '#default_value' => $weight ?? $default_settings['weight'],
    ];
  }

  /**
   * Generate the Leaflet Icon Form Element.
   *
   * @param array $icon_options
   *   The icon array definition.
   *
   * @return array
   *   The Leaflet Icon Form Element.
   */
  protected function generateIconFormElement(array $icon_options) {
    $default_settings = $this::getDefaultSettings();
    $icon_token_replacement_disclaimer = $this->t('<b>Note: </b> Using <strong>Replacement Patterns</strong> it is possible to dynamically define the Marker Icon output, with the composition of Marker Icon paths including entity properties or fields values.');
    $icon_url_description = $this->t('Can be an absolute or relative URL (as Drupal root folder relative paths <strong>without the leading slash</strong>) <br><b>If left empty the default Leaflet Marker will be used.</b><br>@token_replacement_disclaimer', [
      '@token_replacement_disclaimer' => $icon_token_replacement_disclaimer,
    ]);

    if (isset($this->fieldDefinition)) {
      $icon_type = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][icon][iconType]"]';
    }
    else {
      $icon_type = ':input[name="style_options[icon][iconType]"]';
    }

    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Icon'),
      'description' => [
        '#markup' => $this->t('For details on the following setup refer to @leaflet_icon_documentation_link', [
          '@leaflet_icon_documentation_link' => $this->leafletService->leafletIconDocumentationLink(),
        ]),
      ],
    ];

    $element['iconType'] = [
      '#type' => 'radios',
      '#title' => t('Icon Source'),
      '#default_value' => $icon_options['iconType'] ?? $default_settings['icon']['iconType'],
      '#options' => [
        'marker' => $this->t('Icon Image Url/Path'),
        'html' => $this->t('Field (html DivIcon)'),
        'circle_marker' => $this->t('Circle Marker (@more_info)', [
          '@more_info' => $this->link->generate('more info', Url::fromUri('https://leafletjs.com/reference.html#circlemarker', [
            'absolute' => TRUE,
            'attributes' => ['target' => 'blank'],
          ])
          ),
        ]
        ),
      ],
    ];

    $element['iconUrl'] = [
      '#title' => $this->t('Icon URL'),
      '#description' => $icon_url_description,
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $icon_options['iconUrl'] ?? $default_settings['icon']['iconUrl'],
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'marker'],
        ],
      ],
    ];

    $element['shadowUrl'] = [
      '#title' => $this->t('Icon Shadow URL'),
      '#description' => $icon_url_description,
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $icon_options['shadowUrl'] ?? $default_settings['icon']['shadowUrl'],
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'marker'],
        ],
      ],
    ];

    $element['className'] = [
      '#title' => $this->t('Icon Class Name'),
      '#description' => $this->t('A custom class name to assign to both icon and shadow images.<br>Supports <b>Replacement Patterns</b>.'),
      '#type' => 'textfield',
      '#default_value' => $icon_options['className'] ?? $default_settings['icon']['className'],
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'marker'],
        ],
      ],
    ];

    $element['html'] = [
      '#title' => $this->t('Html'),
      '#type' => 'textarea',
      '#description' => $this->t('Insert here the Html code that will be used as marker html markup. <b>If left empty the default Leaflet Marker will be used.</b><br>@token_replacement_disclaimer', [
        '@token_replacement_disclaimer' => $this->getTokenReplacementDisclaimer(),
      ]),
      '#default_value' => $icon_options['html'] ?? $default_settings['icon']['html'],
      '#rows' => 3,
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'html'],
        ],
        'required' => [
          $icon_type => ['value' => 'html'],
        ],
      ],
    ];

    $element['html_class'] = [
      '#type' => 'textfield',
      '#title' => t('Marker HTML class'),
      '#description' => t('Required class name for the div used to wrap field output. For multiple classes, separate with a space.'),
      '#default_value' => $icon_options['html_class'] ?? $default_settings['icon']['html_class'],
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'html'],
        ],
      ],
    ];

    $element['circle_marker_options'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Circle Marker Options'),
      '#description' => $this->t('An object literal of Circle Marker options, that comply with the @leaflet_circle_marker_object.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br><b>Note: </b> Use <strong>Replacement Patterns</strong> to input dynamic values.<br>Empty value will fall back to default Leaflet Circle Marker style.', [
        '@leaflet_circle_marker_object' => $this->link->generate('Leaflet Circle Marker object', Url::fromUri('https://leafletjs.com/reference.html#circlemarker', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])
        ),
      ]),
      '#default_value' => $icon_options['circle_marker_options'] ?? $default_settings['icon']['circle_marker_options'],
      '#placeholder' => $default_settings['icon']['circle_marker_options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
      '#states' => [
        'visible' => [
          $icon_type => ['value' => 'circle_marker'],
        ],
      ],
    ];

    if (method_exists($this, 'getProvider') && $this->getProvider() == 'leaflet_views') {
      $twig_link = $this->link->generate('Twig', Url::fromUri('https://twig.symfony.com/doc/', [
        'absolute' => TRUE,
        'attributes' => ['target' => 'blank'],
      ])
      );

      $icon_url_description .= '<br>' . $this->t('You may include @twig_link. You may enter data from this view as per the "Replacement patterns" below.', [
        '@twig_link' => $twig_link,
      ]);

      $element['iconUrl']['#description'] = $icon_url_description;
      $element['shadowUrl']['#description'] = $icon_url_description;

      // Set up the tokens for views fields.
      // Code is snatched from Drupal\views\Plugin\views\field\FieldPluginBase.
      $options = [];
      $optgroup_fields = (string) t('Fields');
      if (isset($this->displayHandler)) {
        foreach ($this->displayHandler->getHandlers('field') as $id => $field) {
          /** @var \Drupal\views\Plugin\views\field\EntityField $field */
          $options[$optgroup_fields]["{{ $id }}"] = substr(strrchr($field->label(), ":"), 2);
        }
      }

      // Default text.
      $output = [];
      // We have some options, so make a list.
      if (!empty($options)) {
        $output[] = [
          '#markup' => '<p>' . $this->t("The following replacement tokens are available. Fields may be marked as <em>Exclude from display</em> if you prefer.") . '</p>',
        ];
        foreach (array_keys($options) as $type) {
          if (!empty($options[$type])) {
            $items = [];
            foreach ($options[$type] as $key => $value) {
              $items[] = $key;
            }
            $item_list = [
              '#theme' => 'item_list',
              '#items' => $items,
            ];
            $output[] = $item_list;
          }
        }
      }

      $element['help'] = [
        '#type' => 'details',
        '#title' => $this->t('Replacement patterns'),
        '#value' => $output,
      ];
    }

    $element['iconSize'] = [
      '#title' => $this->t('Icon Size'),
      '#type' => 'fieldset',
      '#description' => $this->t("Size of the icon image in pixels (if empty the natural icon image size will be used).<br>Both support <b>Replacement Patterns</b> and should end up into an Integer (positive value)<br>If one value is null it will be derived from the populated one, according to the natural icon image size rate."),
    ];

    $element['iconSize']['x'] = [
      '#title' => $this->t('Width'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $icon_options['iconSize']['x'] ?? NULL,
    ];

    $element['iconSize']['y'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $icon_options['iconSize']['y'] ?? NULL,
    ];

    $element['iconAnchor'] = [
      '#title' => $this->t('Icon Anchor'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#description' => $this->t("The coordinates of the 'tip' of the icon (relative to its top left corner). The icon will be aligned so that this point is at the marker\'s geographical location.<br>Both the values shouldn't be null to be valid.<br>These support <b>Replacement Patterns</b> and should end up into an Integer (positive value)."),
    ];

    $element['iconAnchor']['x'] = [
      '#title' => $this->t('X'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['iconAnchor']) ? $icon_options['iconAnchor']['x'] : NULL,
    ];

    $element['iconAnchor']['y'] = [
      '#title' => $this->t('Y'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['iconAnchor']) ? $icon_options['iconAnchor']['y'] : NULL,
    ];

    $element['shadowSize'] = [
      '#title' => $this->t('Shadow Size'),
      '#type' => 'fieldset',
      '#description' => $this->t("Size of the shadow image in pixels (if empty the natural shadow image size will be used). <br>Both support <b>Replacement Patterns</b> and should end up into an Integer (positive value)<br>If one value is null it will be derived from the populated one, according to the natural icon image size rate."),
    ];

    $element['shadowSize']['x'] = [
      '#title' => $this->t('Width'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $icon_options['shadowSize']['x'] ?? NULL,
    ];

    $element['shadowSize']['y'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $icon_options['shadowSize']['y'] ?? NULL,
    ];

    $element['shadowAnchor'] = [
      '#title' => $this->t('Shadow Anchor'),
      '#type' => 'fieldset',
      '#description' => $this->t("The coordinates of the 'tip' of the shadow (relative to its top left corner) (the same as iconAnchor if not specified).<br>Both the values shouldn't be null to be valid.<br>These support <b>Replacement Patterns</b> and should end up into an Integer (positive value)."),
    ];

    $element['shadowAnchor']['x'] = [
      '#title' => $this->t('X'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['shadowAnchor']) ? $icon_options['shadowAnchor']['x'] : NULL,
    ];

    $element['shadowAnchor']['y'] = [
      '#title' => $this->t('Y'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['shadowAnchor']) ? $icon_options['shadowAnchor']['y'] : NULL,
    ];

    $element['popupAnchor'] = [
      '#title' => $this->t('Popup Anchor'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#description' => $this->t("The coordinates of the point from which popups will 'open', relative to the icon anchor.<br>Both the values shouldn't be null to be valid.<br>These support <b>Replacement Patterns</b> and should end up into an Integer (positive value)."),
    ];

    $element['popupAnchor']['x'] = [
      '#title' => $this->t('X'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['popupAnchor']) ? $icon_options['popupAnchor']['x'] : NULL,
    ];

    $element['popupAnchor']['y'] = [
      '#title' => $this->t('Y'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => isset($icon_options['popupAnchor']) ? $icon_options['popupAnchor']['y'] : NULL,
    ];

    return $element;
  }

  /**
   * Set Replacement Patterns Element.
   *
   * @param array $element
   *   The Form element to alter.
   */
  protected function setReplacementPatternsElement(array &$element) {
    $element['replacement_patterns'] = [
      '#type' => 'details',
      '#title' => 'Replacement patterns',
      '#description' => $this->t('The following Replacement Tokens are available when declared in the following Leaflet settings.'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $element['replacement_patterns']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$this->fieldDefinition->getTargetEntityTypeId()],
      ];
    }
    else {
      $element['replacement_patterns']['#description'] = $this->t('The @token_link is needed to browse and use @entity_type entity token replacements.', [
        '@token_link' => $this->link->generate(t('Token module'), Url::fromUri('https://www.drupal.org/project/token', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
        '@entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
      ]);
    }
  }

  /**
   * Set Map Geometries Options Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setMapPathOptionsElement(array &$element, array $settings) {

    $path_description = $this->t('Set here options that will be applied to the rendering of Map Path Geometries (Lines & Polylines, Polygons, Multipolygons, etc.).<br>Refer to the @polygons_documentation.<br>Note: If empty the default Leaflet path style, or the one choosen and defined in leaflet.api/hook_leaflet_map_info, will be used.<br>@token_replacement_disclaimer<br>Single Token or Replacement containing the whole Json specification are supported.', [
      '@polygons_documentation' => $this->link->generate($this->t('Leaflet Path Documentation'), Url::fromUri('https://leafletjs.com/reference.html#path', [
        'absolute' => TRUE,
        'attributes' => ['target' => 'blank'],
      ])),
      '@token_replacement_disclaimer' => $this->getTokenReplacementDisclaimer(),
    ]);

    $element['path'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Geometries Options'),
      '#rows' => 3,
      '#description' => $path_description,
      '#default_value' => $settings['path'],
      '#placeholder' => $this::getDefaultSettings()['path'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Set Map additional map Settings.
   *
   * @param array $map
   *   The map object.
   * @param array $options
   *   The options from where to set additional options.
   */
  protected function setAdditionalMapOptions(array &$map, array $options) {
    $default_settings = $this::getDefaultSettings();

    // Add additional settings to the Map, with fallback on the
    // hook_leaflet_map_info ones.
    $map['settings']['map_position_force'] = $options['map_position']['force'] ?? $default_settings['map_position']['force'];
    $map['settings']['zoom'] = isset($options['map_position']['zoom']) ? (int) $options['map_position']['zoom'] : $default_settings['map_position']['zoom'];
    $map['settings']['zoomFiner'] = isset($options['map_position']['zoomFiner']) ? (int) $options['map_position']['zoomFiner'] : $default_settings['map_position']['zoomFiner'];
    $map['settings']['minZoom'] = isset($options['map_position']['minZoom']) ? (int) $options['map_position']['minZoom'] : $default_settings['map_position']['minZoom'];
    $map['settings']['maxZoom'] = isset($options['map_position']['maxZoom']) ? (int) $options['map_position']['maxZoom'] : $default_settings['map_position']['maxZoom'];

    // Disable zoom control if the minimum and maximum are the same.
    if ($map['settings']['minZoom'] === $map['settings']['maxZoom']) {
      $map['settings']['zoomControl'] = FALSE;
    }

    $map['settings']['zoomControlPosition'] = $options['map_position']['zoomControlPosition'] ?? $default_settings['map_position']['zoomControlPosition'];

    $map['settings']['center'] = (isset($options['map_position']['center']['lat']) && isset($options['map_position']['center']['lon'])) ? [
      'lat' => floatval($options['map_position']['center']['lat']),
      'lon' => floatval($options['map_position']['center']['lon']),
    ] : $default_settings['map_position']['center'];
    $map['settings']['scrollWheelZoom'] = !empty($options['disable_wheel']) ? !(bool) $options['disable_wheel'] : ($map['settings']['scrollWheelZoom'] ?? TRUE);

    $map['settings']['path'] = isset($options['path']) && !empty($options['path']) ? $options['path'] : (isset($map['path']) ? Json::encode($map['path']) : Json::encode($default_settings['path']));

    $map['settings']['leaflet_markercluster'] = $options['leaflet_markercluster'] ?? $default_settings['leaflet_markercluster'];
    $map['settings']['fullscreen'] = $options['fullscreen'] ?? $default_settings['fullscreen'];
    $map['settings']['gestureHandling'] = $options['gesture_handling'] ?? $default_settings['gesture_handling'];
    $map['settings']['reset_map'] = $options['reset_map'] ?? $default_settings['reset_map'];
    $map['settings']['map_scale'] = $options['map_scale'] ?? $default_settings['map_scale'];
    $map['settings']['locate'] = $options['locate'] ?? $default_settings['locate'];
    $map['settings']['fitbounds_options'] = $options['fitbounds_options'] ?? $default_settings['fitbounds_options'];
    $map['settings']['geocoder'] = $options['geocoder'] ?? $default_settings['geocoder'];
    $map['settings']['map_lazy_load'] = $options['map_lazy_load'] ?? $default_settings['map_lazy_load'];
  }

  /**
   * Set Tooltip Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   * @param array $view_fields
   *   The view fields.
   */
  protected function setTooltipElement(array &$element, array $settings, array $view_fields = []) {
    $default_settings = $this::getDefaultSettings();
    $element['leaflet_tooltip'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaflet Tooltip'),
    ];

    if (isset($this->fieldDefinition)) {
      $leaflet_tooltip_visibility = [
        'invisible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][leaflet_tooltip][value]"]' => ['value' => ''],
        ],
      ];
    }
    else {
      $leaflet_tooltip_visibility = [
        'invisible' => [
          'select[name="style_options[leaflet_tooltip][value]"]' => ['value' => ''],
        ],
      ];
    }

    $tooltip_description = $this->t('Use this to insert a @leaflet_tooltip (Feature by Feature).', [
      '@leaflet_tooltip' => $this->link->generate("Leaflet Tooltip", Url::fromUri("https://leafletjs.com/reference.html#tooltip", ['attributes' => ['target' => 'blank']])),
    ]);

    if (isset($this->fieldDefinition)) {
      $element['leaflet_tooltip']['value'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Tooltip Source'),
        '#rows' => 2,
        '#default_value' => $settings['leaflet_tooltip']['value'] ?? $default_settings['leaflet_tooltip']['value'],
        '#description' => $tooltip_description,
      ];
    }
    elseif (!empty($view_fields)) {

      $tooltip_options = array_merge(['' => ' - None - '], $view_fields);
      if ($this->entityType) {
        $tooltip_options += [
          '#rendered_view_fields' => $this->t('# Rendered View Fields (with field label, format, classes, etc)'),
        ];
      }

      $element['leaflet_tooltip']['value'] = [
        '#type' => 'select',
        '#title' => $this->t('Tooltip Source'),
        '#options' => $tooltip_options,
        '#default_value' => $settings['leaflet_tooltip']['value'] ?? $default_settings['leaflet_tooltip']['value'],
        '#description' => $tooltip_description,
      ];
    }

    $element['leaflet_tooltip']['options'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('Tooltip Options'),
      '#description' => $this->t('An object literal of options, that comply with the Leaflet Tooltip object definition.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>Supports <b>Replacement Patterns</b><br>Single Token or Replacement containing the whole Json specification are supported.'),
      '#default_value' => $settings['leaflet_tooltip']['options'] ?? $default_settings['leaflet_tooltip']['options'],
      '#placeholder' => $default_settings['leaflet_tooltip']['options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
      '#states' => $leaflet_tooltip_visibility,
    ];
  }

  /**
   * Set Popup Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   * @param array $view_fields
   *   The view fields.
   * @param string $entity_type
   *   The entity type.
   * @param array $view_mode_options
   *   The view modes options list.
   */
  protected function setPopupElement(array &$element, array $settings, array $view_fields = [], string $entity_type = '', array $view_mode_options = []) {
    $default_settings = $this::getDefaultSettings();
    $element['leaflet_popup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaflet Popup'),
    ];

    if (isset($this->fieldDefinition)) {
      $leaflet_popup_selector = 'fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][leaflet_popup][control]';

      // Define the Popup Control and Popup Content with backward
      // compatibility with Leaflet release < 2.x.
      $popup_control = !empty($settings['popup']) ? $settings['popup'] : ($settings['leaflet_popup']['control'] ?? NULL);
      $popup_content = !empty($settings['popup_content']) ? $settings['popup_content'] : ($settings['leaflet_popup']['content'] ?? NULL);

      $element['leaflet_popup']['control'] = [
        '#title' => $this->t('Enable Leaflet Popup'),
        '#description' => $this->t('Enable a @leaflet_popup that will appear on Marker click.', [
          '@leaflet_popup' => $this->link->generate("Leaflet Popup", Url::fromUri("https://leafletjs.com/reference.html#tilelayer", ['attributes' => ['target' => 'blank']])),
        ]),
        '#type' => 'checkbox',
        '#default_value' => $popup_control ?? $default_settings['leaflet_popup']['control'],
      ];

      $element['leaflet_popup']['content'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Popup content'),
        '#rows' => 2,
        '#description' => $this->t('Define the custom content for the Leaflet Popup. If empty the Content Title will be output.<br>Supports <b>Replacement Patterns</b>.'),
        '#default_value' => $popup_content ?? $default_settings['leaflet_popup']['content'],
        '#states' => [
          'visible' => [
            'input[name="' . $leaflet_popup_selector . '"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $element['leaflet_popup']['options'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Popup Options'),
        '#rows' => 3,
        '#description' => $this->t('An object literal of options, that comply with the Leaflet Popup object definition.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br><u>Note: if omitted, the "offset" option will be set on top of the feature icon size.</u><br>Supports <b>Replacement Patterns</b>.'),
        '#default_value' => $settings['leaflet_popup']['options'] ?? $default_settings['leaflet_popup']['options'],
        '#placeholder' => $default_settings['leaflet_popup']['options'],
        '#element_validate' => [[get_class($this), 'jsonValidate']],
        '#states' => [
          'visible' => [
            'input[name="' . $leaflet_popup_selector . '"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    elseif (!empty($view_fields)) {
      $leaflet_popup_selector = 'style_options[leaflet_popup][value]';

      $popup_options = array_merge(['' => ' - None - '], $view_fields);
      // Add an option to render the entire entity using a view mode.
      if ($this->entityType) {
        $popup_options += [
          '#rendered_entity' => $this->t('< @entity entity >', ['@entity' => $entity_type]),
          '#rendered_entity_ajax' => $this->t('< @entity entity via ajax >', ['@entity' => $entity_type]),
          '#rendered_view_fields' => $this->t('# Rendered View Fields (with field label, format, classes, etc)'),
        ];
      }

      // Define the Popup source and Popup view mode with backward
      // compatibility with Leaflet release < 2.x.
      $popup_source = !empty($settings['description_field']) ? $settings['description_field'] : ($settings['leaflet_popup']['value'] ?? NULL);
      $popup_view_mode = !empty($settings['view_mode']) ? $settings['view_mode'] : ($settings['leaflet_popup']['view_mode'] ?? NULL);

      $element['leaflet_popup']['value'] = [
        '#type' => 'select',
        '#title' => $this->t('Popup Source'),
        '#options' => $popup_options,
        '#default_value' => $popup_source ?? $default_settings['leaflet_popup']['value'],
        '#description' => $this->t("Enable and choose content of a @leaflet_popup that will appear on Marker click.", [
          '@leaflet_popup' => $this->link->generate("Leaflet Popup", Url::fromUri("https://leafletjs.com/reference.html#tilelayer", ['attributes' => ['target' => 'blank']])),
        ]),
      ];

      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $element['leaflet_popup']['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Popup Source View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Leaflet Popup.'),
        '#options' => $view_mode_options,
        '#default_value' => $popup_view_mode ?? $default_settings['leaflet_popup']['view_mode'],
        '#states' => [
          'visible' => [
            ':input[name="' . $leaflet_popup_selector . '"]' => [
              ['value' => '#rendered_entity'],
              ['value' => '#rendered_entity_ajax'],
            ],
          ],
        ],
      ];
      $element['leaflet_popup']['options'] = [
        '#type' => 'textarea',
        '#rows' => 3,
        '#title' => $this->t('Popup Options'),
        '#description' => $this->t('An object literal of options, that comply with the Leaflet Popup object definition.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br><u>Note: if omitted, the "offset" option will be set on top of the feature icon size.</u><br>Supports <b>Replacement Patterns</b>.'),
        '#default_value' => $settings['leaflet_popup']['options'] ?? $default_settings['leaflet_popup']['options'],
        '#placeholder' => $default_settings['leaflet_popup']['options'],
        '#element_validate' => [[get_class($this), 'jsonValidate']],
        '#states' => [
          'invisible' => [
            'select[name="' . $leaflet_popup_selector . '"]' => ['value' => ''],
          ],
        ],
      ];
    }
  }

  /**
   * Set Map MarkerCluster Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   * @param array $view_fields
   *   The view fields.
   */
  protected function setMapMarkerclusterElement(array &$element, array $settings, array $view_fields = []) {

    $default_settings = $this::getDefaultSettings();
    $leaflet_markercluster_submodule_warning = $this->t("<u>Note</u>: This functionality and settings are related to the Leaflet Markercluster submodule, present inside the Leaflet module itself.<br><u>(DON'T USE the external self standing Leaflet Markecluster module).</u>");

    $element['leaflet_markercluster'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Marker Clustering'),
    ];

    if ($this->moduleHandler->moduleExists('leaflet_markercluster')) {
      $element['leaflet_markercluster']['control'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable @markeclusterer_api_link functionality.', [
          '@markeclusterer_api_link' => $this->link->generate($this->t('Leaflet Markercluster Js Library'), Url::fromUri('https://github.com/Leaflet/Leaflet.markercluster', [
            'absolute' => TRUE,
            'attributes' => ['target' => 'blank'],
          ])),
        ]),
        '#default_value' => $settings['leaflet_markercluster']['control'] ?? $default_settings['leaflet_markercluster']['control'],
        '#description' => $this->t("@leaflet_markercluster_submodule_warning", [
          '@leaflet_markercluster_submodule_warning' => $leaflet_markercluster_submodule_warning,
        ]),
        '#return_value' => 1,
      ];
      if (isset($this->fieldDefinition)) {
        $leaflet_markercluster_visibility = [
          'visible' => [
            ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][leaflet_markercluster][control]"]' => ['checked' => TRUE],
          ],
        ];
      }
      else {
        $leaflet_markercluster_visibility = [
          'visible' => [
            ':input[name="style_options[leaflet_markercluster][control]"]' => ['checked' => TRUE],
          ],
        ];
      }

      // Add the Markecluster Excluded flag element, in case of View Fields.
      if (!empty($view_fields)) {
        $element['leaflet_markercluster']['excluded'] = [
          '#type' => 'select',
          '#title' => $this->t('Exclude flag'),
          '#options' => array_merge([0 => 'No'], $view_fields),
          '#default_value' => $settings['leaflet_markercluster']['excluded'] ?? $default_settings['leaflet_markercluster']['excluded'],
          '#description' => $this->t("Choose a View Field as option to dynamically exclude a Leaflet Feature from being Marker Clustered (not Empty/FALSE/NULL instances/values will be excluded)."),
          '#states' => $leaflet_markercluster_visibility,
        ];
      }

      $element['leaflet_markercluster']['options'] = [
        '#type' => 'textarea',
        '#rows' => 4,
        '#title' => $this->t('Marker Cluster Additional Options'),
        '#description' => $this->t('An object literal of options, that comply with the Leaflet Markercluster Js Library.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
        '#default_value' => $settings['leaflet_markercluster']['options'] ?? $default_settings['leaflet_markercluster']['options'],
        '#placeholder' => $default_settings['leaflet_markercluster']['options'],
        '#element_validate' => [[get_class($this), 'jsonValidate']],
        '#states' => $leaflet_markercluster_visibility,
      ];

      $element['leaflet_markercluster']['include_path'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Markeclustering of Paths elements'),
        '#default_value' => $settings['leaflet_markercluster']['include_path'] ?? $default_settings['leaflet_markercluster']['include_path'],
        '#description' => $this->t("Check this options to extend Markerclustering to the Leaflet Map features extending the @path_class_link (Polygon, Polyline, Circle).", [
          '@path_class_link' => $this->link->generate($this->t('Leaflet Path class'), Url::fromUri('https://leafletjs.com/reference.html#path', [
            'absolute' => TRUE,
            'attributes' => ['target' => 'blank'],
          ])),
        ]),
        '#return_value' => 1,
        '#states' => $leaflet_markercluster_visibility,
      ];
    }
    else {
      $element['leaflet_markercluster']['markup'] = [
        '#markup' => $this->t("Enable the Leaflet Markecluster submodule to activate this functionality.<br>@leaflet_markercluster_submodule_warning", [
          '@leaflet_markercluster_submodule_warning' => $leaflet_markercluster_submodule_warning,
        ]),
      ];
    }
  }

  /**
   * Set Fullscreen Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setFullscreenElement(array &$element, array $settings) {

    $default_settings = $this::getDefaultSettings();

    $element['fullscreen'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fullscreen'),
    ];

    $element['fullscreen']['control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the functionality of the @fullscreen_api_link.', [
        '@fullscreen_api_link' => $this->link->generate($this->t('Leaflet Fullscreen JS Library'), Url::fromUri('https://github.com/Leaflet/Leaflet.fullscreen', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      // Eventually fallback to previous "fullscreen_control" settings,
      // for backword compatibility with Leaflet < 2.x.
      '#default_value' => $settings['fullscreen']['control'] ?? $default_settings['fullscreen']['control'],
      '#return_value' => 1,
    ];
    $element['fullscreen']['options'] = [
      '#type' => 'textarea',
      '#rows' => 4,
      '#title' => $this->t('Fullscreen Additional Options'),
      '#description' => $this->t('An object literal of options, that comply with the Leaflet Fullscreen JS Library.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['fullscreen']['options'] ?? $default_settings['fullscreen']['options'],
      '#placeholder' => $default_settings['fullscreen']['options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Set Reset Map View Control Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setResetMapViewControl(array &$element, array $settings) {
    $default_settings = $this::getDefaultSettings();

    $element['reset_map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reset Map View Control'),
    ];

    $element['reset_map']['control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable @reset_map_view_link functionality.', [
        '@reset_map_view_link' => $this->link->generate($this->t('Reset Map View Leaflet Plugin'), Url::fromUri('https://github.com/drustack/Leaflet.ResetView', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('This enables a "Reset Map View" control to reset the Map to its initial center & zoom state<br><b><u>Warning: </u></b>Due to an issue in the Leaflet library (@see https://github.com/Leaflet/Leaflet/issues/6172) the Map Reset control doesn\'t work correctly in Fitting Bounds of coordinates having mixed positive and negative values of latitude &longitudes.<br>In this case the Map will be Reset to the default set Map Center.'),
      '#default_value' => $settings['reset_map']['control'] ?? $default_settings['reset_map']['control'],
      '#return_value' => 1,
    ];

    $element['reset_map']['options'] = [
      '#type' => 'textarea',
      '#rows' => 4,
      '#title' => $this->t('Reset Map View Options'),
      '#description' => $this->t('An object literal of options, that comply with the Leaflet.ResetView Plugin<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['reset_map']['options'] ?? $default_settings['reset_map']['options'],
      '#placeholder' => $default_settings['reset_map']['options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    if (isset($this->fieldDefinition)) {
      $element['reset_map']['options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][reset_map][control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $element['reset_map']['options']['#states'] = [
        'visible' => [
          ':input[name="style_options[reset_map][control]"]' => ['checked' => TRUE],
        ],
      ];
    }

  }

  /**
   * Set Map Scale Control Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setMapScaleControl(array &$element, array $settings) {
    $default_settings = $this::getDefaultSettings();

    $element['map_scale'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Scale Control'),
    ];

    $element['map_scale']['control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable @map_scale_link.', [
        '@map_scale_link' => $this->link->generate($this->t('Map Scale Control'), Url::fromUri('https://leafletjs.com/reference.html#control-scale', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('A simple scale control that shows the scale of the current center of screen in metric (m/km) and imperial (mi/ft) systems.'),
      '#default_value' => $settings['map_scale']['control'] ?? $default_settings['map_scale']['control'],
      '#return_value' => 1,
    ];

    $element['map_scale']['options'] = [
      '#type' => 'textarea',
      '#rows' => 4,
      '#title' => $this->t('Map Scale Options'),
      '#description' => $this->t('An object literal of options, that comply with the Leaflet Map Scale Options.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['map_scale']['options'] ?? $default_settings['map_scale']['options'],
      '#placeholder' => $default_settings['map_scale']['options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    if (isset($this->fieldDefinition)) {
      $element['map_scale']['options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_scale][control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $element['map_scale']['options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_scale][control]"]' => ['checked' => TRUE],
        ],
      ];
    }

  }

  /**
   * Set Locate Control Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setLocateControl(array &$element, array $settings) {
    $default_settings = $this::getDefaultSettings();

    if (isset($this->fieldDefinition)) {
      $leaflet_locate_visibility = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][locate][control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $leaflet_locate_visibility = [
        'visible' => [
          ':input[name="style_options[locate][control]"]' => ['checked' => TRUE],
        ],
      ];
    }

    $element['locate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Locate and Show User Position'),
    ];

    $element['locate']['control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable @locate_link functionality.', [
        '@locate_link' => $this->link->generate($this->t('Locate Leaflet Plugin'), Url::fromUri('https://github.com/domoritz/leaflet-locatecontrol', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('This enables a "Locate User Position" control to geolocate the user.'),
      '#default_value' => $settings['locate']['control'] ?? $default_settings['locate']['control'],
    ];

    $element['locate']['options'] = [
      '#type' => 'textarea',
      '#rows' => 4,
      '#title' => $this->t('Locate Options'),
      '#description' => $this->t('An object literal of options, that comply with the Locate Leaflet Plugin<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['locate']['options'] ?? $default_settings['locate']['options'],
      '#placeholder' => $default_settings['locate']['options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
      '#states' => $leaflet_locate_visibility,
    ];

    // Define the Automatic Locate Settings
    // for backwardcompatibility with Leaflet release < 2.x.
    $automatic_locate_settings = !empty($settings['map']['locate']) ? $settings['map']['locate'] : ($settings['locate']['automatic'] ?? NULL);

    $element['locate']['automatic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically locate & show user current position.'),
      '#description' => $this->t("This option initially centers the map to the user position (only if the Map Center is not forced)."),
      '#default_value' => $automatic_locate_settings ?? $default_settings['locate']['automatic'],
      '#states' => $leaflet_locate_visibility,
    ];

    if ($this->getPluginId() === 'leaflet_widget_default') {
      $element['locate']['automatic']['#description'] .= '<br>' . $this->t('<u>NOTE:</u> This will work only in case of Empty Map / New Insert.');
    }

  }

  /**
   * Set Feature additional Properties Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setFeatureAdditionalPropertiesElement(array &$element, array $settings) {
    $default_settings = $this::getDefaultSettings();

    $element['feature_properties'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Feature Additional Properties'),
    ];

    $element['feature_properties']['values'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('Values'),
      '#description' => $this->t('Add additional key/value(s) that will be added in the "properties" index for each Leaflet Map "feature" (in the drupalSettings js object)<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>This is as advanced functionality, useful to dynamically alter Leaflet Map and each feature representation/behaviour on the basis of its properties.<br>Supports <b>Replacement Patterns</b>.'),
      '#default_value' => $settings['feature_properties']['values'] ?? $default_settings['feature_properties']['values'],
      '#placeholder' => '{"content_type":"{{ type }}"}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Set Map Geocoder Control Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setGeocoderMapControl(array &$element, array $settings) {
    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    if ($this->moduleHandler->moduleExists('geocoder') && class_exists('\Drupal\geocoder\Controller\GeocoderApiEnpoints')) {
      $default_settings = $this::getDefaultSettings();
      $element['geocoder'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Map Control - Geocoder'),
      ];

      $element['geocoder']['control'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Map Geocoder Control'),
        '#description' => $this->t('This will add a Geocoder control element to the Leaflet Map'),
        '#default_value' => $settings['geocoder']['control'] ?? $default_settings['geocoder']['control'],
      ];

      $element['geocoder']['access_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('<strong>Note: </strong>This shows up only to users with permissions to <u>Access Geocoder Api Url Enpoints.</u>'),
      ];

      $element['geocoder']['settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Settings'),
      ];

      // Option to set a Marker on Geocode, only in case of Leaflet Widget.
      if ($this instanceof LeafletDefaultWidget) {
        $element['geocoder']['settings']['set_marker'] = [
          '#title' => $this->t('<b>Place a Marker on Geocode</b>'),
          '#type' => 'checkbox',
          '#default_value' => $settings['geocoder']['settings']['set_marker'] ?? $default_settings['geocoder']['settings']['set_marker'],
          '#description' => $this->t('Check this to place a Marker on the Map when Geocoding the Address.'),
        ];
      }

      $element['geocoder']['settings']['popup'] = [
        '#title' => $this->t('Open Leaflet Popup on Geocode Focus'),
        '#type' => 'checkbox',
        '#default_value' => $settings['geocoder']['settings']['popup'] ?? $default_settings['geocoder']['settings']['popup'],
        '#description' => $this->t('Check this to open a Popup on the Map (with the found Address) upon the Geocode Focus.'),
      ];

      // In case of LeafletDefaultWidget, hide the Popup option, if set_marker'
      // is checked.
      if ($this instanceof LeafletDefaultWidget && method_exists($this->fieldDefinition, 'getName')) {
        $element['geocoder']['settings']['popup']['#states'] = [
          'invisible' => [
            ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geocoder][settings][set_marker]"]' => ['checked' => TRUE],
          ],
        ];
      }

      $element['geocoder']['settings']['autocomplete'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Map Control - Geocoder'),
        'placeholder' => [
          '#title' => $this->t('Placeholder attribute'),
          '#type' => 'textfield',
          '#description' => $this->t('Specifies a short hint displayed in the input field before the user enters a value.'),
          '#default_value' => $settings['geocoder']['settings']['autocomplete']['placeholder'] ?? $default_settings['geocoder']['settings']['autocomplete']['placeholder'],
        ],
        'title' => [
          '#title' => $this->t('Title attribute'),
          '#type' => 'textfield',
          '#description' => $this->t('Adds a tooltip that appears hovering the mouse over the input element.'),
          '#default_value' => $settings['geocoder']['settings']['autocomplete']['title'] ?? $default_settings['geocoder']['settings']['autocomplete']['title'],
        ],
      ];

      $element['geocoder']['settings']['position'] = [
        '#type' => 'select',
        '#title' => $this->t('Position'),
        '#options' => $this->controlPositionsOptions,
        '#default_value' => $settings['geocoder']['settings']['position'] ?? $default_settings['geocoder']['settings']['position'],
      ];

      $element['geocoder']['settings']['input_size'] = [
        '#title' => $this->t('Input Size'),
        '#type' => 'number',
        '#min' => 10,
        '#max' => 100,
        '#default_value' => $settings['geocoder']['settings']['input_size'] ?? $default_settings['geocoder']['settings']['input_size'],
        '#description' => $this->t('The characters size/length of the Geocoder Input element.'),
      ];

      $providers_settings = $settings['geocoder']['settings']['providers'] ?? [];

      // Get the enabled/selected providers.
      $enabled_providers = [];
      foreach ($providers_settings as $plugin_id => $plugin) {
        if (!empty($plugin['checked'])) {
          $enabled_providers[] = $plugin_id;
        }
      }

      // Generates the Draggable Table of Selectable Geocoder Providers.
      /** @var \Drupal\geocoder\ProviderPluginManager  $geocoder_provider */
      $geocoder_provider = \Drupal::service('plugin.manager.geocoder.provider');
      $element['geocoder']['settings']['providers'] = $geocoder_provider->providersPluginsTableList($enabled_providers);

      // Set a validation for the providers' selection.
      $element['geocoder']['settings']['providers']['#element_validate'] = [
        [
          get_class($this), 'validateGeocoderProviders',
        ],
      ];

      $element['geocoder']['settings']['min_terms'] = [
        '#type' => 'number',
        '#default_value' => $settings['geocoder']['settings']['min_terms'] ?? $default_settings['geocoder']['settings']['min_terms'],
        '#title' => $this->t('The (minimum) number of terms for the Geocoder to start processing.'),
        '#description' => $this->t('Valid values for the widget are between 2 and 10. A too low value (<= 3) will affect the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 2,
        '#max' => 10,
        '#size' => 3,
      ];

      $element['geocoder']['settings']['delay'] = [
        '#type' => 'number',
        '#default_value' => $settings['geocoder']['settings']['delay'] ?? $default_settings['geocoder']['settings']['delay'],
        '#title' => $this->t('The delay (in milliseconds) between pressing a key in the Address Input field and starting the Geocoder search.'),
        '#description' => $this->t('Valid value for the widget are multiples of 100, between 300 and 3000. A too low value (<= 300) will affect / increase the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 300,
        '#max' => 3000,
        '#step' => 100,
        '#size' => 4,
      ];

      $element['geocoder']['settings']['zoom'] = [
        '#title' => $this->t('Zoom to Focus'),
        '#type' => 'number',
        '#min' => 1,
        '#max' => 22,
        '#default_value' => $settings['geocoder']['settings']['zoom'] ?? $default_settings['geocoder']['settings']['zoom'],
        '#description' => $this->t('Zoom level to Focus on the Map upon the Geocoder Address selection.'),
      ];

      $element['geocoder']['settings']['options'] = [
        '#type' => 'textarea',
        '#rows' => 4,
        '#title' => $this->t('Geocoder Control Specific Options'),
        '#description' => $this->t('This settings would override general Geocoder Providers options. (<u>Note: This would work only for Geocoder 2.x branch/version.</u>)<br>An object literal of specific Geocoder options. The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
        '#default_value' => $settings['geocoder']['settings']['options'] ?? $default_settings['geocoder']['settings']['options'],
        '#placeholder' => '{"googlemaps":{"locale": "it", "region": "it"}, "nominatim":{"locale": "it"}}',
        '#element_validate' => [[get_class($this), 'jsonValidate']],
      ];
      if (isset($this->fieldDefinition)) {
        $element['geocoder']['settings']['#states'] = [
          'visible' => [
            ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geocoder][control]"]' => ['checked' => TRUE],
          ],
        ];
      }
      else {
        $element['geocoder']['settings']['#states'] = [
          'visible' => [
            ':input[name="style_options[geocoder][control]"]' => ['checked' => TRUE],
          ],
        ];
      }
    }
    else {
      $element['geocoder'] = [
        '#markup' => $this->t('<strong>Note: </strong>it is possible to enable a <u>Geocoder controller on the Leaflet Map</u> throughout the @geocoder_module_link integration (version higher than 8.x-2.3 and 8.x-3.0-alpha2).', [
          '@geocoder_module_link' => $this->link->generate('Geocoder Module', Url::fromUri('https://www.drupal.org/project/geocoder', ['attributes' => ['target' => 'blank']])),
        ]),
      ];
    }
  }

  /**
   * Set Map Lazy Load Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setMapLazyLoad(array &$element, array $settings) {
    $element['map_lazy_load'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Lazy Loading'),
    ];

    $intersection_observer_compatibility_link = $this->link->generate('check IntersectionObserver Browser Compatibility', Url::fromUri('https://developer.mozilla.org/en-US/docs/Web/API/IntersectionObserver#browser_compatibility', ['attributes' => ['target' => 'blank']]));

    $element['map_lazy_load']['lazy_load'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lazy load map'),
      '#description' => $this->t('If checked, the map will be loaded when it enters the user\'s viewport. This can be useful to reduce unnecessary load time or API calls.<br><u>Note:This will only work with not too old browsers, that support "Intersection Observer API"</u> (link: @intersection_observer_compatibility_link).', [
        '@intersection_observer_compatibility_link' => $intersection_observer_compatibility_link,
      ]),
      '#default_value' => !empty($settings['map_lazy_load']['lazy_load']) ? $settings['map_lazy_load']['lazy_load'] : 0,
      '#return_value' => 1,
    ];
  }

  /**
   * Set Map GeoJSON Overlays Element.
   *
   * @param array $element
   *   The Form element to alter.
   * @param array $settings
   *   The Form Settings.
   */
  protected function setMapGeoJsonOverlays(array &$element, array $settings): void {

    // At the moment this is only supported by Leaflet widget.
    if (isset($this->fieldDefinition)) {
      $fields_list = array_merge_recursive(
        $this->entityFieldManager->getFieldMapByFieldType('string_long'),
        $this->entityFieldManager->getFieldMapByFieldType('link'),
        $this->entityFieldManager->getFieldMapByFieldType('json'),
        $this->entityFieldManager->getFieldMapByFieldType('json_native'),
        $this->entityFieldManager->getFieldMapByFieldType('json_native_binary'),
      );

      $string_fields_options = [];

      // Filter out the not acceptable values from the options.
      if (!empty($fields_list[$element['#entity_type']])) {
        foreach ($fields_list[$element['#entity_type']] as $k => $field) {
          if (in_array(
              $element['#bundle'], $field['bundles']) &&
            !in_array($k, [
              'revision_log',
              'behavior_settings',
              'parent_id',
              'parent_type',
              'parent_field_name',
            ])) {
            $string_fields_options[$k] = $k;
          }
        }
      }

      $element['geojson_overlays'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Map (GeoJSON) Overlays'),
        '#description' => $this->t('Use this section to select sources and add <a href="https://en.wikipedia.org/wiki/GeoJSON" target="blank">GeoJSON</a> content Overlays to the Leaflet widget map, that can act as useful drawing (snappable) references.<br>At the moment specific fields of the entity (being edited) can be chosen as sources of content of (or links to) the geojson overlays that should be added.<br><em><b>Hint:</b> Reload the widget after having populated those fields, to have the expected geojson overlays added to the Leaflet map ...</em><br><em><b>Note: </b>Mutliple/Different GeoJSON Sources are supported, but their content will be merged into a unique GeoJSON Overlay on the Leaflet Widget Map.</em>'),
        '#description_display' => 'before',
      ];

      $element['geojson_overlays']['sources'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Sources'),
        '#description_display' => 'before',
      ];

      $source_fields_selector = 'fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geojson_overlays][sources][fields][]';
      $supported_field_types_text = $this->t('Supported field types: "Text (plain, long)" field (string_long), "Link" field (link), "<a href="https://www.drupal.org/project/json_field" target="blank">Json</a>" field (json).');

      if (!empty($string_fields_options)) {
        $element['geojson_overlays']['sources']['fields'] = [
          '#type' => 'select',
          '#title' => $this->t('Fields'),
          '#description' => $this->t('Choose the entity fields to retrieve GeoJSON content from.<br>@supported_field_types_text<br><em><b>Hint:</b> This works great with an internal Link pointing to a <a href="https://www.drupal.org/project/json_field" target="blank">Views GeoJSON module</a> endpoint/route ...</em>', [
            '@supported_field_types_text' => $supported_field_types_text,
          ]),
          '#options' => $string_fields_options,
          '#default_value' => $settings['geojson_overlays']['sources']['fields'] ?? [],
          '#multiple' => TRUE,
          '#size' => count($string_fields_options) + 1,
        ];

        $path_description = $this->t('Set here options that will be applied to the rendering of Map Overlay (Lines & Polylines, Polygons, Multipolygons, etc.).<br>Refer to the @polygons_documentation.<br>Note: If empty the default Leaflet path style, or the one choosen and defined in leaflet.api/hook_leaflet_map_info, will be used.<br>@token_replacement_disclaimer', [
          '@polygons_documentation' => $this->link->generate($this->t('Leaflet Path Documentation'), Url::fromUri('https://leafletjs.com/reference.html#path', [
            'absolute' => TRUE,
            'attributes' => ['target' => 'blank'],
          ])),
          '@token_replacement_disclaimer' => $this->getTokenReplacementDisclaimer(),
        ]);

        $element['geojson_overlays']['path'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Map Overlay Style'),
          '#rows' => 3,
          '#description' => $path_description,
          '#default_value' => $settings['geojson_overlays']['path'],
          '#placeholder' => $this::defaultSettings()['geojson_overlays']['path'],
          '#element_validate' => [[get_class($this), 'jsonValidate']],
          '#states' => [
            'visible' => [
              'select[name="' . $source_fields_selector . '"]' => ['!value' => []],
            ],
          ],
        ];

        $element['geojson_overlays']['zoom_to_geojson'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Zoom to GeoJSON'),
          '#description' => $this->t('Check this option to initially Zoom the (new empty) Leaflet Map on the (GeoJSON) Overlays bounds.'),
          '#default_value' => $settings['geojson_overlays']['zoom_to_geojson'] ?? 1,
          '#return_value' => 1,
          '#states' => [
            'visible' => [
              'select[name="' . $source_fields_selector . '"]' => ['!value' => []],
            ],
          ],
        ];

        $element['geojson_overlays']['snapping'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Snapping enabled'),
          '#description' => $this->t('Check this option to be able to snap to (GeoJSON) Overlays markers/vertices, for precision drawing.'),
          '#default_value' => $settings['geojson_overlays']['snapping'] ?? 1,
          '#return_value' => 1,
          '#states' => [
            'visible' => [
              'select[name="' . $source_fields_selector . '"]' => ['!value' => []],
            ],
          ],
        ];

      }
      else {
        $element['geojson_overlays']['sources']['fields']['no_fields_help']['#markup'] = $this->t('<p>No eligible fields were found for this Entity Type.<br>Please add any of the supported fields: @supported_field_types_text</p>', [
          '@supported_field_types_text' => $supported_field_types_text,
        ]);
      }
    }
  }

  /**
   * Form element validation handler for a Map Zoom level.
   */
  public static function zoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the initial map zoom level.
    $zoom = $element['#value'];
    $min_zoom = $values['minZoom'];
    $max_zoom = $values['maxZoom'];
    if ($min_zoom !== $max_zoom && ($zoom < $min_zoom || $zoom > $max_zoom)) {
      $form_state->setError($element, t('The @zoom_field should be between the Minimum and the Maximum Zoom levels.', ['@zoom_field' => $element['#title']]));
    }

    // If coincident, force the Zoom value to be the same of Max and Min Zoom.
    if ($max_zoom && $max_zoom === $min_zoom) {
      $form_state->setValueForElement($element, $max_zoom);
    }

  }

  /**
   * Form element validation handler for the Map Max Zoom level.
   */
  public static function maxZoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the max zoom level.
    $min_zoom = $values['minZoom'];
    $max_zoom = $element['#value'];
    if ($max_zoom && $max_zoom < $min_zoom) {
      $form_state->setError($element, t('The Max Zoom level should be above the Minimum Zoom level.'));
    }
  }

  /**
   * Form element json format validation handler.
   *
   * @param array $element
   *   The Form Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form State.
   */
  public static function jsonValidate($element, FormStateInterface &$form_state) {
    // Check Json validity only in case the element value is not wrapped by
    // brackets (Views Replacement) or square brackets (Token).
    if (preg_match('/^\{{.*\}}$/', $element['#value']) !== 1 &&
      preg_match('/^\[.*\]$/', $element['#value']) !== 1
    ) {
      $element_values_array = Json::decode($element['#value']);
      // Check the jsonValue.
      if (!empty($element['#value']) && $element_values_array == NULL) {
        $form_state->setError($element, t('The @field field is not valid Json Format.', ['@field' => $element['#title']]));
      }
      elseif (!empty($element['#value'])) {
        $form_state->setValueForElement($element, Json::encode($element_values_array));
      }
    }
  }

  /**
   * Validates the Geocoder Providers element.
   *
   * @param array $element
   *   The form element to build.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateGeocoderProviders(array $element, FormStateInterface &$form_state) {
    $form_state_input = $form_state->getUserInput();
    if (isset($form_state_input['style_options'])) {
      $geocoder_control = $form_state_input['style_options']['geocoder']['control'];
    }
    if (isset($form_state_input['fields'])) {
      $geocoder_control = $form_state_input['fields'][$element['#array_parents'][1]]['settings_edit_form']['settings']['geocoder']['control'];
    }
    if (isset($geocoder_control) && $geocoder_control) {
      $providers = is_array($element['#value']) ? array_filter($element['#value'], function ($value) {
        return isset($value['checked']) && TRUE == $value['checked'];
      }) : [];

      if (empty($providers)) {
        $form_state->setError($element, t('The Geocode Origin option needs at least one geocoder plugin selected.'));
      }
    }
  }

}
