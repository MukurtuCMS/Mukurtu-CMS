<?php

namespace Drupal\leaflet\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Utility\Token;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldDefaultWidget;
use Drupal\geofield\Plugin\GeofieldBackendManager;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\leaflet\LeafletService;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the "leaflet_widget" widget.
 *
 * @FieldWidget(
 *   id = "leaflet_widget_default",
 *   label = @Translation("Leaflet Map (default)"),
 *   description = @Translation("Provides a Leaflet Widget with Geoman Js Library."),
 *   field_types = {
 *     "geofield",
 *   },
 * )
 */
class LeafletDefaultWidget extends GeofieldDefaultWidget {

  use LeafletSettingsElementsTrait;

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * LeafletWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   * @param \Drupal\geofield\Plugin\GeofieldBackendManager $geofield_backend_manager
   *   The geofieldBackendManager.
   * @param \Drupal\leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    GeoPHPInterface $geophp_wrapper,
    WktGeneratorInterface $wkt_generator,
    GeofieldBackendManager $geofield_backend_manager,
    LeafletService $leaflet_service,
    ModuleHandlerInterface $module_handler,
    LinkGeneratorInterface $link_generator,
    Token $token,
    LanguageManagerInterface $languageManager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $geophp_wrapper,
      $wkt_generator,
      $geofield_backend_manager
    );
    $this->leafletService = $leaflet_service;
    $this->moduleHandler = $module_handler;
    $this->link = $link_generator;
    $this->token = $token;
    $this->languageManager = $languageManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('geofield.geophp'),
      $container->get('geofield.wkt_generator'),
      $container->get('plugin.manager.geofield_backend'),
      $container->get('leaflet.service'),
      $container->get('module_handler'),
      $container->get('link_generator'),
      $container->get('token'),
      $container->get('language_manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $base_layers = self::getLeafletMaps();

    return array_merge(parent::defaultSettings(), [
      'map' => [
        'leaflet_map' => $base_layers['OSM Mapnik'] ? 'OSM Mapnik' : array_shift($base_layers),
        'height' => 400,
        'auto_center' => TRUE,
        'map_position' => self::getDefaultSettings()['map_position'],
        'scroll_zoom_enabled' => TRUE,
      ],
      'input' => [
        'show' => TRUE,
        'readonly' => FALSE,
      ],
      'toolbar' => [
        'position' => 'topright',
        'marker' => 'defaultMarker',
        'drawPolyline' => TRUE,
        'drawRectangle' => TRUE,
        'drawPolygon' => TRUE,
        'drawCircle' => FALSE,
        'drawText' => FALSE,
        'editMode' => TRUE,
        'dragMode' => TRUE,
        'cutPolygon' => FALSE,
        'removalMode' => TRUE,
        'rotateMode' => FALSE,
      ],
      'reset_map' => [
        'control' => FALSE,
        'options' => '{"position": "topleft", "title": "Reset View"}',
      ],
      'map_scale' => [
        'control' => FALSE,
        'options' => '{"position":"bottomright","maxWidth":100,"metric":true,"imperial":false,"updateWhenIdle":false}',
      ],
      'fullscreen' => [
        'control' => FALSE,
        'options' => '{"position":"topleft","pseudoFullscreen":false}',
      ],
      'path' => '{"color":"#3388ff","opacity":"1.0","stroke":true,"weight":3,"fill":"depends","fillColor":"*","fillOpacity":"0.2","radius":"6"}',
      'feature_properties' => [
        'values' => '',
      ],
      'locate' => [
        'control' => FALSE,
        'options' => '{"position": "topright", "setView": "untilPanOrZoom", "returnToPrevBounds":true, "keepCurrentZoomLevel": true, "strings": {"title": "Locate my position"}}',
        'automatic' => FALSE,
      ],
      'geocoder' => [
        'control' => FALSE,
        'settings' => [
          'position' => 'topright',
          'input_size' => 20,
          'providers' => [],
          'min_terms' => 4,
          'delay' => 800,
          'zoom' => 16,
          'popup' => FALSE,
          'options' => '',
        ],
      ],
      'geojson_overlays' => [
        'sources' => [
          'fields' => [],
        ],
        'path' => '{"color":"#f71ed3","opacity":"0.7","stroke":true,"weight":2,"fillColor":"#ffddfe","fillOpacity":"0.1","radius":3,"dashArray":"5, 5"}',
        'zoom_to_geojson' => TRUE,
        'snapping' => TRUE,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    // Fixes possible error related to
    // Warning: Undefined array key in
    // Drupal\field ui\Form\EntityDisplayFormBase->copyFrom Values Entity()
    // @see: https://www.drupal.org/project/office_hours/issues/3413697
    if (isset($form['#after_build'])) {
      $form['#after_build'] = NULL;
    }

    // Inherit basic settings form from GeofieldDefaultWidget:
    $form = array_merge($form, parent::settingsForm($form, $form_state));
    $map_settings = $this->getSetting('map');
    $default_settings = self::defaultSettings();

    // Set Replacement Patterns Element.
    $this->setReplacementPatternsElement($form);

    $form['map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Settings'),
    ];
    $form['map']['leaflet_map'] = [
      '#title' => $this->t('Leaflet Map'),
      '#type' => 'select',
      '#options' => ['' => $this->t('-- Empty --')] + $this->getLeafletMaps(),
      '#default_value' => $map_settings['leaflet_map'] ?? $default_settings['map']['leaflet_map'],
      '#required' => TRUE,
    ];
    $form['map']['height'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $map_settings['height'] ?? $default_settings['map']['height'],
    ];
    $form['map']['auto_center'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically center map on existing features'),
      '#description' => $this->t("This option overrides the widget's default center (in case of not empty map)."),
      '#default_value' => $map_settings['auto_center'] ?? $default_settings['map']['auto_center'],
    ];

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $map_settings['map_position'] ?? $default_settings['map']['map_position'];
    $form['map']['map_position'] = $this->generateMapPositionElement($map_position_options);

    $form['map']['scroll_zoom_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Scroll Wheel Zoom on click'),
      '#description' => $this->t("This option enables zooming by mousewheel as soon as the user clicked on the map."),
      '#default_value' => $map_settings['scroll_zoom_enabled'] ?? $default_settings['map']['scroll_zoom_enabled'],
    ];

    $input_settings = $this->getSetting('input');
    $form['input'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geofield Settings'),
    ];
    $form['input']['show'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show geofield input element'),
      '#default_value' => $input_settings['show'] ?? $default_settings['input']['show'],
    ];
    $form['input']['readonly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make geofield input element read-only'),
      '#default_value' => $input_settings['readonly'] ?? $default_settings['input']['readonly'],
      '#states' => [
        'invisible' => [
          ':input[name="fields[field_geofield][settings_edit_form][settings][input][show]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $toolbar_settings = $this->getSetting('toolbar');

    $form['toolbar'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaflet PM Settings'),
    ];

    $form['toolbar']['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Toolbar position.'),
      '#options' => [
        'topleft' => $this->t('topleft'),
        'topright' => $this->t('topright'),
        'bottomleft' => $this->t('bottomleft'),
        'bottomright' => $this->t('bottomright'),
      ],
      '#default_value' => $toolbar_settings['position'] ?? $default_settings['toolbar']['position'],
    ];

    $form['toolbar']['marker'] = [
      '#type' => 'radios',
      '#title' => $this->t('Marker button.'),
      '#options' => [
        'none' => $this->t('None'),
        'defaultMarker' => $this->t('Default marker'),
        'circleMarker' => $this->t('Circle marker'),
      ],
      '#description' => $this->t('Use <b>Default marker</b> for default Point Marker. In case of <b>Circle marker</b> size can be changed by setting the <em>radius</em> property in <strong>Path Geometries Options</strong> below'),
      '#default_value' => $toolbar_settings['marker'] ?? $default_settings['toolbar']['marker'],
    ];
    $form['toolbar']['drawPolyline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to draw polyline.'),
      '#default_value' => $toolbar_settings['drawPolyline'] ?? $default_settings['toolbar']['drawPolyline'],
    ];

    $form['toolbar']['drawRectangle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to draw rectangle.'),
      '#default_value' => $toolbar_settings['drawRectangle'] ?? $default_settings['toolbar']['drawRectangle'],
    ];

    $form['toolbar']['drawPolygon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to draw polygon.'),
      '#default_value' => $toolbar_settings['drawPolygon'] ?? $default_settings['toolbar']['drawPolygon'],
    ];

    $form['toolbar']['drawCircle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to draw circle. (unsupported by GeoJSON)'),
      '#default_value' => $toolbar_settings['drawCircle'] ?? $default_settings['toolbar']['drawCircle'],
      '#disabled' => TRUE,
    ];

    $form['toolbar']['drawText'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to draw text. (unsupported by GeoJSON)'),
      '#default_value' => $toolbar_settings['drawText'] ?? $default_settings['toolbar']['drawText'],
      '#disabled' => TRUE,
    ];

    $form['toolbar']['editMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to toggle edit mode for all layers.'),
      '#default_value' => $toolbar_settings['editMode'] ?? $default_settings['toolbar']['editMode'],
    ];

    $form['toolbar']['dragMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to toggle drag mode for all layers.'),
      '#default_value' => $toolbar_settings['dragMode'] ?? $default_settings['toolbar']['dragMode'],
    ];

    $form['toolbar']['cutPolygon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to cut hole in polygon.'),
      '#default_value' => $toolbar_settings['cutPolygon'] ?? $default_settings['toolbar']['cutPolygon'],
    ];

    $form['toolbar']['removalMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to remove layers.'),
      '#default_value' => $toolbar_settings['removalMode'] ?? $default_settings['toolbar']['removalMode'],
    ];

    $form['toolbar']['rotateMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adds button to rotate layers.'),
      '#default_value' => $toolbar_settings['rotateMode'] ?? $default_settings['toolbar']['rotateMode'],
    ];

    // Generate the Leaflet Map Reset Control.
    $this->setResetMapViewControl($form, $this->getSettings());

    // Generate the Leaflet Map Scale Control.
    $this->setMapScaleControl($form, $this->getSettings());

    // Set Fullscreen Element.
    $this->setFullscreenElement($form, $this->getSettings());

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $this->getSettings());

    // Set Locate User Position Control Element.
    $this->setLocateControl($form, $this->getSettings());

    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    $this->setGeocoderMapControl($form, $this->getSettings());

    // Set the Map GeoJSON Overlay Field and Paths Styles.
    $this->setMapGeoJsonOverlays($form, $this->getSettings());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_id = $entity->id();
    $field = $items->getFieldDefinition();

    // Get token context.
    $tokens = [
      'field' => $items,
      $field->getTargetEntityTypeId() => $items->getEntity(),
    ];

    // Determine the widget map, default and input settings.
    $map_settings = $this->getSetting('map');
    $default_settings = self::defaultSettings();
    $input_settings = $this->getSetting('input');

    $user_input = $form_state->getUserInput();

    // Get the base Map info.
    $map = leaflet_map_get_info($map_settings['leaflet_map'] ?? $default_settings['map']['leaflet_map']);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_widget_{$entity_type}_{$bundle}_{$entity_id}_{$field->getName()}");

    // Get and set the Geofield cardinality.
    $map['geofield_cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Set the widget context info into the map.
    $map['context'] = 'widget';

    // Fetch previous automatic locate setting
    // for backward compatibility with Leaflet release < 2.x.
    if (!empty($map_settings["locate"])) {
      $previous_automatic_locate_settings = TRUE;
    }

    // Extend map settings to additional options.
    $map_settings = array_merge($map_settings, [
      'reset_map' => $this->getSetting('reset_map'),
      'map_scale' => $this->getSetting('map_scale'),
      'fullscreen' => $this->getSetting('fullscreen'),
      'path' => htmlspecialchars_decode(str_replace(["\n", "\r"], "", $this->token->replace($this->getSetting('path'), $tokens))),
      'geocoder' => $this->getSetting('geocoder'),
      'locate' => $this->getSetting('locate'),
      'geojson_overlays' => $this->getSetting('geojson_overlays'),
    ]);

    // Add leaflet_widget geojson overlays_info to general Form definition
    // to attach info for dynamic #ajax interactivity with sources fields.
    if (isset($map_settings['geojson_overlays']['sources']['fields']) && is_array($map_settings['geojson_overlays']['sources']['fields'])) {
      $form['#leaflet_widget_geojson_overlays'] = [
        'sources_fields' => $map_settings['geojson_overlays']['sources']['fields'],
        'widget_id' => 'edit-' . str_replace('_', '-', $field->getName()) . '-wrapper',
        'entity_widget_geofield' => $field->getName(),
      ];
    }

    // Get GeoJSON Overlays contents.
    // Use the drupal static cache if present and no #ajax triggered form reload
    // (by and with user input).
    $cachePrefix = $this->getPluginId() . '_geojson_overlay_contents';
    $entity_info = $entity->getEntityTypeId() . '_' . $entity->id();
    $page_cache = &drupal_static("$cachePrefix:$entity_info");
    if (empty($user_input) && is_array($page_cache)) {
      $geojson_overlays_contents = $page_cache;
    }
    // Else generate new GeoJSON Overlays contents.
    else {
      $geojson_overlays_contents = $this->getGeoJsonOverlayContents($map_settings, $user_input, $entity);
      // And set the page cache for the geojson overlays contents.
      $page_cache = $geojson_overlays_contents;
    }

    // Set the $map_settings['geojson_overlays']['contents'], if not empty.
    if (!empty($geojson_overlays_contents)) {
      $map_settings['geojson_overlays']['contents'] = $geojson_overlays_contents;
    }

    // Set previos automatic locate setting
    // for backward compatibility with Leaflet release < 2.x.
    if (!empty($previous_automatic_locate_settings)) {
      $map_settings['locate']['automatic'] = TRUE;
    }

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $map_settings);

    // Attach class to wkt input element, so we can find it in js.
    $json_element_name = 'leaflet-widget-input';
    $element['value']['#attributes']['class'][] = $json_element_name;
    // Set the readonly for styling, if readonly.
    if (isset($input_settings['input']["readonly"]) &&  $input_settings['input']["readonly"]) {
      $element['value']['#attributes']['class'][] = "readonly";
    }

    // Allow other modules to add/alter the map js settings.
    $this->moduleHandler->alter('leaflet_default_widget', $map, $this);

    // Define the Map element.
    $element['map'] = $this->leafletService->leafletRenderMap($map, [], $map_settings['height'] . 'px');

    // Set the Element Map weight, to put it ahead of the Title.
    $element['map']['#weight'] = -1;

    // Add the Map Overlays Text message, eventually.
    if (isset($map_settings["geojson_overlays"]["sources"]["fields"]) && is_array($map_settings["geojson_overlays"]["sources"]["fields"]) && count($map_settings["geojson_overlays"]["sources"]["fields"]) > 0) {
      $map_overlays_fields_text = implode(", ", $map_settings["geojson_overlays"]["sources"]["fields"]);
      $map_overlays_text = $this->t('<div class="description form-item__description">Map (<a href="https://en.wikipedia.org/wiki/GeoJSON" target="blank">GeoJSON</a>) Overlays added and sourced from the following fields: @map_overlays_fields_text.</div>', [
        '@map_overlays_fields_text' => $map_overlays_fields_text,
      ]);
      $element['map']['#suffix'] = $map_overlays_text;
    }

    $element['title'] = [
      '#type' => 'item',
      '#title' => $element['value']['#title'],
      '#weight' => -2,
    ];

    // Build JS settings for the Leaflet Widget.
    $leaflet_widget_js_settings = [
      'map_id' => $element['map']['#map_id'],
      'jsonElement' => '.' . $json_element_name,
      'multiple' => !($map['geofield_cardinality'] == 1),
      'cardinality' => max($map['geofield_cardinality'], 0),
      'autoCenter' => $map_settings['auto_center'] ?? $default_settings['auto_center'],
      'inputHidden' => empty($input_settings['show']),
      'inputReadonly' => !empty($input_settings['readonly']),
      'toolbarSettings' => $this->getSetting('toolbar') ?? $default_settings['toolbar'],
      'scrollZoomEnabled' => !empty($map_settings['scroll_zoom_enabled']) ? $map_settings['scroll_zoom_enabled'] : FALSE,
      'map_position' => $map_settings['map_position'] ?? [],
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      'geojsonFieldOverlay' => $map_settings['geojson_overlays'] ?? $default_settings['geojson_overlays'],
    ];

    // Leaflet.widget plugin.
    $element['map']['#attached']['library'][] = 'leaflet/leaflet-widget';

    // Add the Leaflet GeoJSON Overlays library, if requested.
    if (!empty($map_settings['geojson_overlays']['contents'])) {
      $element['map']['#attached']['library'][] = 'leaflet/leaflet-geojson-overlay';
    }

    // Settings and geo-data are passed to the widget keyed by field id.
    $element['map']['#attached']['drupalSettings']['leaflet'][$element['map']['#map_id']]['leaflet_widget'] = $leaflet_widget_js_settings;

    // Convert default value to geoJSON format.
    /** @var \Geometry|null $geom */
    if ($geom = $this->geoPhpWrapper->load($element['value']['#default_value'])) {
      $element['value']['#default_value'] = $geom->out('json');
    }
    return $element;
  }

  /**
   * Get GeoJSON Overlays contents.
   *
   * @param array|null $map_settings
   *   Map Settings.
   * @param array|null $user_input
   *   Form State User Input.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The Widget Entity.
   *
   * @return array|null
   *   The Map Settings array.
   */
  protected function getGeoJsonOverlayContents(?array $map_settings, array $user_input, EntityInterface $entity): ?array {
    $geojson_overlays_contents = [];

    // Add geojson_overlays source Entity Fields contents.
    if (isset($map_settings['geojson_overlays']['sources']['fields']) && is_array($map_settings['geojson_overlays']['sources']['fields'])) {
      foreach ($map_settings['geojson_overlays']['sources']['fields'] as $field) {
        try {
          $field_values = $user_input[$field] ?? $entity->get($field)->getValue();
          foreach ($field_values as $k => $field_value) {
            // In case of Link field, eventually parse the internal link, and
            // generate an absolute value of it.
            if (isset($field_value['uri'])) {
              try {
                if (str_starts_with($field_value['uri'], '/')) {
                  $field_values[$k]['uri'] = Url::fromUserInput($field_value['uri'], ['absolute' => TRUE])->toString();
                }
                else {
                  $field_values[$k]['uri'] = Url::fromUri($field_value['uri'], ['absolute' => TRUE])->toString();
                }
              }
              catch (\Exception $e) {
                unset($field_values[$k]);
                continue;
              }
            }
          }
          $geojson_overlays_contents = array_merge($field_values, $geojson_overlays_contents ?? []);
        }
        catch (\Exception $e) {
          $geojson_overlays_contents = [];
        }
      }
    }
    return $geojson_overlays_contents;
  }

  /**
   * Perform argument parsing and token replacement.
   *
   * Code from the
   * Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection.
   *
   * @param string $argument_string
   *   The raw argument string.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity containing this field.
   *
   * @return array
   *   The array of processed arguments.
   */
  protected function processArguments($argument_string, FieldableEntityInterface $entity) {
    $arguments = [];

    if (!empty($argument_string)) {
      $pos = 0;
      while ($pos < strlen($argument_string)) {
        $found = FALSE;
        // If string starts with a quote, start after quote and get everything
        // before next quote.
        if (strpos($argument_string, '"', $pos) === $pos) {
          if (($quote = strpos($argument_string, '"', ++$pos)) !== FALSE) {
            // Skip pairs of quotes.
            while (!(($ql = strspn($argument_string, '"', $quote)) & 1)) {
              $quote = strpos($argument_string, '"', $quote + $ql);
            }
            $arguments[] = str_replace('""', '"', substr($argument_string, $pos, $quote + $ql - $pos - 1));
            $pos = $quote + $ql + 1;
            $found = TRUE;
          }
        }
        else {
          $arguments = explode('/', $argument_string);
          $pos = strlen($argument_string) + 1;
          $found = TRUE;
        }
        if (!$found) {
          $arguments[] = substr($argument_string, $pos);
          $pos = strlen($argument_string);
        }
      }

      $token_service = \Drupal::token();
      $token_data = [$entity->getEntityTypeId() => $entity];
      foreach ($arguments as $key => $value) {
        $arguments[$key] = $token_service->replace($value, $token_data);
      }
    }

    return $arguments;
  }

  /**
   * Ajax callback to reload the GeoJSON Overlays after data source change.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return mixed
   *   The returned result.
   */
  public static function updateLeafletWidgetGeoJsonOverlaysAjax(array $form, FormStateInterface $form_state) {
    $form["field_geofield"]['#id'] = $form["#leaflet_widget_geojson_overlays"]["widget_id"];
    return $form[$form["#leaflet_widget_geojson_overlays"]["entity_widget_geofield"]];
  }

}
