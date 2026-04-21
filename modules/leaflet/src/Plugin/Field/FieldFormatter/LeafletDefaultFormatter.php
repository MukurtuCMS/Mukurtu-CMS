<?php

namespace Drupal\leaflet\Plugin\Field\FieldFormatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\leaflet\LeafletService;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Plugin implementation of the 'leaflet_default' formatter.
 *
 * @FieldFormatter(
 *   id = "leaflet_formatter_default",
 *   label = @Translation("Leaflet Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class LeafletDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use LeafletSettingsElementsTrait;

  /**
   * The Default Settings.
   *
   * @var array
   */
  protected $defaultSettings;

  /**
   * Leaflet service.
   *
   * @var \Drupal\leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
   * LeafletDefaultFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LeafletService $leaflet_service,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LinkGeneratorInterface $link_generator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->defaultSettings = self::getDefaultSettings();
    $this->leafletService = $leaflet_service;
    $this->entityFieldManager = $entity_field_manager;
    $this->token = $token;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->link = $link_generator;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('leaflet.service'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::getDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    $form = parent::settingsForm($form, $form_state);
    $form['#tree'] = TRUE;

    // Get the Cardinality set for the Formatter Field.
    $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();

    // Set Replacement Patterns Element.
    $this->setReplacementPatternsElement($form);

    if ($field_cardinality !== 1) {
      $form['multiple_map'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Multiple Maps'),
        '#description' => $this->t('Check this option if you want to render a single Map for every single Geo Point.'),
        '#default_value' => $settings['multiple_map'],
        '#return_value' => 1,
      ];
    }
    else {
      $form['multiple_map'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
    }

    // Insert the Tooltip Element.
    $this->setTooltipElement($form, $settings);

    // Insert the Popup Element.
    $this->setPopupElement($form, $settings);

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($form, $settings);

    // Set the FitBounds Options Element.
    $this->setFitBoundsOptionsElement($form, $settings);

    // Generate the Leaflet Map Reset Control.
    $this->setResetMapViewControl($form, $settings);

    // Generate the Leaflet Map Scale Control.
    $this->setMapScaleControl($form, $settings);

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $settings['map_position'];
    $form['map_position'] = $this->generateMapPositionElement($map_position_options);

    // Generate Icon form element.
    $icon_options = $settings['icon'];
    $form['icon'] = $this->generateIconFormElement($icon_options);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($form, $settings);

    // Set Fullscreen Element.
    $this->setFullscreenElement($form, $settings);

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $settings);

    // Set the Feature Additional Properties Element.
    $this->setFeatureAdditionalPropertiesElement($form, $settings);

    // Set Locate User Position Control Element.
    $this->setLocateControl($form, $settings);

    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    $this->setGeocoderMapControl($form, $settings);

    // Set Map Lazy Load Element.
    $this->setMapLazyLoad($form, $settings);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    // Define the Popup Control and Popup Content with backward
    // compatibility with Leaflet release < 2.x.
    $popup_control = !empty($settings['popup']) ? $settings['popup'] : ($settings['leaflet_popup']['control'] ?? NULL);
    $popup_content = !empty($settings['popup_content']) ? $settings['popup_content'] : ($settings['leaflet_popup']['content'] ?? NULL);

    $summary = [];
    $summary[] = $this->t('Leaflet Map: @map', ['@map' => $settings['leaflet_map']]);
    $summary[] = $this->t('Map height: @height @height_unit', [
      '@height' => $settings['height'],
      '@height_unit' => $settings['height_unit'],
    ],
    );
    $summary[] = $this->t('Popup Infowindow: @popup', ['@popup' => $popup_control ? $this->t('Yes') : $this->t('No')]);
    if ($popup_control && $popup_content) {
      $summary[] = $this->t('Popup content: @popup_content', ['@popup_content' => $popup_content]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This function is called from parent::view().
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    // Take the entity translation, if existing.
    /** @var \Drupal\Core\TypedData\TranslatableInterface $entity */
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_id = $entity->id();
    $field = $items->getFieldDefinition();

    // Sets/consider possibly existing previous Zoom settings.
    $this->setExistingZoomSettings();

    // Determine the formatter default and input settings.
    $default_settings = self::defaultSettings();
    $settings = $this->getSettings();

    // Get the base Map info.
    $map = leaflet_map_get_info($settings['leaflet_map']) ?? $default_settings['leaflet_map'];

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_{$entity_type}_{$bundle}_{$entity_id}_{$field->getName()}");

    // Get and set the Geofield cardinality.
    $map['geofield_cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $settings);

    // Get token context.
    $tokens = [
      'field' => $items,
      $this->fieldDefinition->getTargetEntityTypeId() => $items->getEntity(),
    ];

    $results = [];
    $features = [];
    foreach ($items as $item) {

      $points = $this->leafletService->leafletProcessGeofield($item->value);
      $feature = $points[0];
      $feature['entity_id'] = $entity_id;

      // Attach tooltip data (value & options).
      if (isset($settings['leaflet_tooltip']) && !empty($settings['leaflet_tooltip']['value'])) {
        $feature['tooltip'] = $settings['leaflet_tooltip'];
        // Decode any entities because JS will encode them again,
        // and we don't want double encoding.
        $feature['tooltip']['value'] = $this->tokenResolvedContent($entity, (string) $settings['leaflet_tooltip']['value'], $tokens, $results);

        // Associate dynamic tooltip options (token based).
        if (!empty($settings['leaflet_tooltip']['options'])) {
          $feature['tooltip']['options'] = $this->tokenResolvedContent($entity, $settings['leaflet_tooltip']['options'], $tokens, $results);
        }
      }

      // Define the Popup Control and Popup Content with backward
      // compatibility with Leaflet release < 2.x.
      $popup_control = !empty($settings['popup']) ? $settings['popup'] : ($settings['leaflet_popup']['control'] ?? NULL);
      $popup_content = !empty($settings['popup_content']) ? $settings['popup_content'] : ($settings['leaflet_popup']['content'] ?? NULL);

      // Eventually set the popup content.
      if ($popup_control) {
        $feature['popup'] = $settings['leaflet_popup'];
        // Generate the Popup Content render array transforming the
        // 'popup_content' text area through replacements tokens.
        $feature['popup']['value'] = $this->tokenResolvedContent($entity, $popup_content, $tokens, $results);

        // Associate dynamic popup options (token based).
        if (!empty($settings['leaflet_popup']['options'])) {
          $feature['popup']['options'] = $this->tokenResolvedContent($entity, $settings['leaflet_popup']['options'], $tokens, $results);
        }
      }

      // Add/merge eventual map icon definition from hook_leaflet_map_info.
      if (!empty($map['icon'])) {
        $settings['icon'] = $settings['icon'] ?: [];
        // Remove empty icon options so that they might be replaced by the
        // ones set by the hook_leaflet_map_info.
        foreach ($settings['icon'] as $k => $icon_option) {
          if (empty($icon_option) || (is_array($icon_option) && $this->leafletService->multipleEmpty($icon_option))) {
            unset($settings['icon'][$k]);
          }
        }
        $settings['icon'] = array_replace($map['icon'], $settings['icon']);
      }

      $icon_type = $settings['icon']['iconType'] ?? 'marker';

      // Eventually set the custom Marker icon (DivIcon, Icon Url or
      // Circle Marker).
      if (in_array($feature['type'], [
        'point',
        'multipoint',
        'geometrycollection',
      ]) && isset($settings['icon'])) {

        // Set Feature Icon properties.
        $feature['icon'] = $settings['icon'];

        // Transforms Icon Options that support Replacement Patterns/Tokens.
        if (!empty($settings["icon"]["iconSize"]["x"])) {
          $feature['icon']["iconSize"]["x"] = intval($this->token->replace($settings["icon"]["iconSize"]["x"], $tokens));
        }
        if (!empty($settings["icon"]["iconSize"]["y"])) {
          $feature['icon']["iconSize"]["y"] = intval($this->token->replace($settings["icon"]["iconSize"]["y"], $tokens));
        }
        if (!empty($settings["icon"]["iconAnchor"]["x"])) {
          $feature['icon']["iconAnchor"]["x"] = $this->token->replace($settings["icon"]["iconAnchor"]["x"], $tokens);
        }
        if (!empty($settings["icon"]["iconAnchor"]["y"])) {
          $feature['icon']["iconAnchor"]["y"] = $this->token->replace($settings["icon"]["iconAnchor"]["y"], $tokens);
        }
        if (!empty($settings["icon"]["popupAnchor"]["x"])) {
          $feature['icon']["popupAnchor"]["x"] = $this->token->replace($settings["icon"]["popupAnchor"]["x"], $tokens);
        }
        if (!empty($settings["icon"]["popupAnchor"]["y"])) {
          $feature['icon']["popupAnchor"]["y"] = $this->token->replace($settings["icon"]["popupAnchor"]["y"], $tokens);
        }
        if (!empty($settings["icon"]["shadowSize"]["x"])) {
          $feature['icon']["shadowSize"]["x"] = intval($this->token->replace($settings["icon"]["shadowSize"]["x"], $tokens));
        }
        if (!empty($settings["icon"]["shadowSize"]["y"])) {
          $feature['icon']["shadowSize"]["y"] = intval($this->token->replace($settings["icon"]["shadowSize"]["y"], $tokens));
        }

        switch ($icon_type) {
          case 'html':
            $feature['icon']['html'] = $this->token->replace($settings['icon']['html'], $tokens, ['clear' => TRUE]);
            $feature['icon']['html_class'] = $settings['icon']['html_class'] ?? '';
            break;

          case 'circle_marker':
            $feature['icon']['circle_marker_options'] = $this->token->replace($settings['icon']['circle_marker_options'], $tokens);
            break;

          default:
            // Apply Token Replacements to iconUrl & shadowUrl.
            if (!empty($settings['icon']['iconUrl'])) {
              $feature['icon']['iconUrl'] = str_replace(["\n", "\r"], "", $this->token->replace($settings['icon']['iconUrl'], $tokens));
              // Generate correct Absolute iconUrl,
              // if not external.
              if (!empty($feature['icon']['iconUrl'])) {
                $feature['icon']['iconUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['iconUrl']);
              }
            }
            if (!empty($settings['icon']['shadowUrl'])) {
              $feature['icon']['shadowUrl'] = str_replace(["\n", "\r"], "", $this->token->replace($settings['icon']['shadowUrl'], $tokens));
              // Generate correct Absolute shadowUrl,
              // if not external.
              if (!empty($feature['icon']['shadowUrl'])) {
                $feature['icon']['shadowUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['shadowUrl']);
              }
            }
            // Set the Feature IconSize and ShadowSize to the IconUrl or
            // ShadowUrl Image sizes (if empty or invalid).
            $this->leafletService->setFeatureIconSizesIfEmptyOrInvalid($feature);
            break;
        }
      }

      // Associate dynamic path properties (token based) to the feature,
      // in case of not point.
      if ($feature['type'] !== 'point') {
        $feature['path'] = htmlspecialchars_decode(
          str_replace([
            "\n",
            "\r",
          ], "", $this->token->replace($settings['path'], $tokens)
          ),
        );
      }

      // Associate dynamic className property (token based) to icon.
      $feature['icon']['className'] = !empty($settings["icon"]["className"]) ?
        htmlspecialchars_decode(str_replace([
          "\n",
          "\r",
        ], "", $this->token->replace($settings["icon"]["className"], $tokens)
        )) : '';

      // Add Feature additional Properties (if present).
      if (!empty($settings['feature_properties']['values'])) {
        $feature['properties'] = htmlspecialchars_decode(str_replace([
          "\n",
          "\r",
        ], "", $this->token->replace($settings['feature_properties']['values'], $tokens)
        ));
      }

      // Allow modules to adjust the marker.
      $this->moduleHandler->alter('leaflet_formatter_feature', $feature, $item, $entity);
      $features[] = $feature;
    }

    $js_settings = [
      'map' => $map,
      'features' => $features,
    ];

    // Allow other modules to add/alter the map js settings.
    $this->moduleHandler->alter('leaflet_default_map_formatter', $js_settings, $items);

    $map_height = !empty($settings['height']) ? $settings['height'] . $settings['height_unit'] : '';

    if (!empty($settings['multiple_map'])) {
      foreach ($js_settings['features'] as $k => $feature) {
        $map = $js_settings['map'];
        $map['id'] = $map['id'] . "-$k";
        $results[] = $this->leafletService->leafletRenderMap($map, [$feature], $map_height);
      }
    }
    // Render the map, if we do have data or the hide option is unchecked.
    elseif (!empty($js_settings['features']) || empty($settings['hide_empty_map'])) {
      $results[] = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);
    }

    return $results;
  }

  /**
   * Sets possibly existing previous settings for the Zoom Form Element.
   */
  protected function setExistingZoomSettings(): void {
    $settings = $this->getSettings();
    if (isset($settings['zoom'])) {
      $settings['map_position']['zoom'] = (int) $settings['zoom'] ?? 10;
      $settings['map_position']['minZoom'] = (int) $settings['minZoom'] ?? 3;
      $settings['map_position']['maxZoom'] = (int) $settings['maxZoom'] ?? 16;
      $this->setSettings($settings);
    }
  }

  /**
   * Returns a Token Resolved Content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity.
   * @param string $element_content
   *   The Element Content.
   * @param array $tokens
   *   The Tokens list array.
   * @param array $results
   *   The results array.
   *
   * @return array
   *   The result render array.
   */
  protected function tokenResolvedContent(EntityInterface $entity, string $element_content, array $tokens, array $results) {
    // Construct the renderable array for popup title / text. As we later
    // convert that to plain text, losing attachments and cacheability, save
    // them to $results.
    $build = [];
    if (!empty($element_content)) {
      $bubbleable_metadata = new BubbleableMetadata();
      $content = htmlspecialchars_decode(str_replace([
        "\n",
        "\r",
      ], "",
        $this->token->replace($element_content, $tokens, ['clear' => TRUE], $bubbleable_metadata)));
      $build[] = [
        '#markup' => $content,
      ];
      $bubbleable_metadata->applyTo($results);
    }

    // We need a string for using it inside the popup. Save attachments and
    // cache-ability to $results.
    $render_context = new RenderContext();
    $rendered = $this->renderer->executeInRenderContext($render_context, function () use (&$build) {
      return $this->renderer->render($build);
    });
    $result = !empty($rendered) ? $rendered : $entity->label();
    if (!$render_context->isEmpty()) {
      $render_context->update($results);
    }
    return $result;
  }

}
