<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\Html;
use Drupal\leaflet\Plugin\Field\FieldFormatter\LeafletDefaultFormatter;

/**
 * Plugin implementation of the 'mukurtu_leaflet_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "mukurtu_leaflet_formatter",
 *   label = @Translation("Mukurtu Leaflet Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class MukurtuLeafletFormatter extends LeafletDefaultFormatter implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * This function is called from parent::view().
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    // Take the entity translation, if existing.
    /* @var \Drupal\Core\TypedData\TranslatableInterface $entity */
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_id = $entity->id();
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $items->getFieldDefinition();

    // Sets/consider possibly existing previous Zoom settings.
    $this->setExistingZoomSettings();
    $settings = $this->getSettings();

    // Always render the map, even if we do not have any data.
    $map = leaflet_map_get_info($settings['leaflet_map']);

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
    foreach ($items as $delta => $item) {
      // GeoJSON handling. Chop featurecollections into multiple features.
      $geoFeatures = [];
      $geoJson = json_decode($item->value, TRUE);
      if (isset($geoJson['type']) && $geoJson['type'] == 'FeatureCollection') {
        foreach ($geoJson['features'] as $g_delta => $geoFeature) {
          $geometry = $geoFeature['geometry'] ?? NULL;
          if ($geometry) {
            $geoFeatures[$g_delta]['properties'] = $geoFeature['properties'] ?? [];
            $geoFeatures[$g_delta]['geometry'] = $this->leafletService->leafletProcessGeofield(json_encode($geometry));
          }
        }
      }
      else {
        // If not GeoJSON or the GeoJSON is in a format we weren't expecting,
        // default back to standard geofield/leaflet behavior.
        $geoFeatures[] = ['geometry' => $this->leafletService->leafletProcessGeofield($item->value)];
      }

      foreach ($geoFeatures as $geoFeature) {
        $feature = $geoFeature['geometry'][0];
        $locationDescription = $geoFeature['properties']['location_description'] ?? NULL;
        $feature['entity_id'] = $entity_id;

        // Eventually set the popup content.
        if ($settings['popup']) {
          // Construct the renderable array for popup title / text. As we later
          // convert that to plain text, losing attachments and cacheability, save
          // them to $results.
          $build = [];

          if ($this->getSetting('popup_content')) {
            $bubbleable_metadata = new BubbleableMetadata();
            $popup_content = $this->token->replace($this->getSetting('popup_content'), $tokens, ['clear' => TRUE], $bubbleable_metadata);
            $build[] = [
              '#markup' => $popup_content,
            ];
            $bubbleable_metadata->applyTo($results);
          }

          // We need a string for using it inside the popup. Save attachments and
          // cacheability to $results.
          $render_context = new RenderContext();
          $rendered = $this->renderer->executeInRenderContext($render_context, function () use (&$build) {
            return $this->renderer->render($build, TRUE);
          });
          $feature['popup'] = !empty($rendered) ? $rendered : ($locationDescription ?? $entity->label());
          if (!$render_context->isEmpty()) {
            $render_context->update($results);
          }
        }

        // Add/merge eventual map icon definition from hook_leaflet_map_info.
        if (!empty($map['icon'])) {
          $settings['icon'] = $settings['icon'] ?: [];
          // Remove empty icon options so thxat they might be replaced by the
          // ones set by the hook_leaflet_map_info.
          foreach ($settings['icon'] as $k => $icon_option) {
            if (empty($icon_option) || (is_array($icon_option) && $this->leafletService->multipleEmpty($icon_option))) {
              unset($settings['icon'][$k]);
            }
          }
          $settings['icon'] = array_replace($map['icon'], $settings['icon']);
        }

        $icon_type = isset($settings['icon']['iconType']) ? $settings['icon']['iconType'] : 'marker';

        // Eventually set the custom Marker icon (DivIcon, Icon Url or
        // Circle Marker).
        if ($feature['type'] === 'point' && isset($settings['icon'])) {

          // Set Feature Icon properties.
          $feature['icon'] = $settings['icon'];

          // Transforms Icon Options that support Replacement Patterns/Tokens.
          if (!empty($settings["icon"]["iconSize"]["x"])) {
            $feature['icon']["iconSize"]["x"] = $this->token->replace($settings["icon"]["iconSize"]["x"], $tokens);
          }
          if (!empty($settings["icon"]["iconSize"]["y"])) {
            $feature['icon']["iconSize"]["y"] = $this->token->replace($settings["icon"]["iconSize"]["y"], $tokens);
          }
          if (!empty($settings["icon"]["shadowSize"]["x"])) {
            $feature['icon']["shadowSize"]["x"] = $this->token->replace($settings["icon"]["shadowSize"]["x"], $tokens);
          }
          if (!empty($settings["icon"]["shadowSize"]["y"])) {
            $feature['icon']["shadowSize"]["y"] = $this->token->replace($settings["icon"]["shadowSize"]["y"], $tokens);
          }

          switch ($icon_type) {
            case 'html':
              $feature['icon']['html'] = $this->token->replace($settings['icon']['html'], $tokens);
              $feature['icon']['html_class'] = isset($settings['icon']['html_class']) ? $settings['icon']['html_class'] : '';
              break;

            case 'circle_marker':
              $feature['icon']['options'] = $this->token->replace($settings['icon']['circle_marker_options'], $tokens);
              break;

            default:
              if (!empty($settings['icon']['iconUrl'])) {
                $feature['icon']['iconUrl'] = str_replace(["\n", "\r"], "", $this->token->replace($settings['icon']['iconUrl'], $tokens));
                if (!empty($feature['icon']['iconUrl'])) {
                  // Generate Absolute iconUrl , if not external.
                  $feature['icon']['iconUrl'] = $this->leafletService->pathToAbsolute($feature['icon']['iconUrl']);
                  // Set the Feature IconSize to the IconUrl Image sizes
                  // (if empty).
                }
              }
              if (!empty($settings['icon']['shadowUrl'])) {
                $feature['icon']['shadowUrl'] = str_replace(["\n", "\r"], "", $this->token->replace($settings['icon']['shadowUrl'], $tokens));
                if (!empty($feature['icon']['shadowUrl'])) {
                  // Generate Absolute shadowUrl, if not external.
                  $feature['icon']['shadowUrl'] = $this->leafletService->pathToAbsolute($feature['icon']['shadowUrl']);
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
          $feature['path'] = str_replace(["\n", "\r"], "", $this->token->replace($settings['path'], $tokens));
        }

        // Associate dynamic className property (token based) to icon.
        $feature['className'] = !empty($settings['className']) ? str_replace(["\n", "\r"], "", $this->token->replace($settings['className'], $tokens)) : '';

        // Allow modules to adjust the marker.
        $this->moduleHandler->alter('leaflet_formatter_feature', $feature, $item, $entity);
        $features[] = $feature;
      }
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
        $map['id'] = $map['id'] . "-{$k}";
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
  protected function setExistingZoomSettings() {
    $settings = $this->getSettings();
    if (isset($settings['zoom'])) {
      $settings['map_position']['zoom'] = (int) $settings['zoom'];
      $settings['map_position']['minZoom'] = (int) $settings['minZoom'];
      $settings['map_position']['maxZoom'] = (int) $settings['maxZoom'];
      $this->setSettings($settings);
    }
  }

}
