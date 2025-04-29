<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\leaflet\Plugin\Field\FieldFormatter\LeafletDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Because the parent class LeafletDefaultFormatter implements a create()
   * method, this child class also needs to implement it in order to be picked
   * up. See \Drupal\Core\Field\FormatterPluginManager::createInstance().
   *
   * This method should match LeafletDefaultFormatter::create() exactly.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
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
   *
   * This custom Mukurtu formatter renders the "location_description" from the
   * stored GeoJSON values within each Leaflet feature's popup.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Collect all descriptions from all Leaflet features and split up multiple
    // points from a single feature into separate features. This allows each
    // point to have a different popup description.
    $new_values = [];
    $descriptions = [];
    foreach ($items as $delta => $item) {
      // GeoJSON handling. Chop FeatureCollections into multiple features.
      $value = $item->getValue();
      $geo_json = json_decode($value['value'], TRUE);
      if (isset($geo_json['type']) && $geo_json['type'] == 'FeatureCollection') {
        foreach ($geo_json['features'] as $g_delta => $geo_feature) {
          $new_values_delta = count($new_values);
          $new_geo_json = $geo_json;
          $new_geo_json['features'] = [$geo_feature];
          $value['value'] = json_encode($new_geo_json);
          $descriptions[$new_values_delta] = $geo_feature['properties']['location_description'] ?? NULL;
          $new_values[] = $value;
        }
      }
      else {
        // If not GeoJSON or the GeoJSON is in a format we weren't expecting,
        // default back to standard geofield/leaflet behavior.
        $new_values[] = $item->getValue();
      }
    }

    // Update the values used in the $items object, which we can then let
    // LeafletDefaultFormatter render as normal.
    $items->setValue($new_values);
    $render = parent::viewElements($items, $langcode);

    // Now take the finished render object and modify the JavaScript settings
    // for the Leaflet map to render the descriptions in each popup, rather than
    // repeating the entity label for every popup.
    foreach ($render as $delta => &$item) {
      $settings = &$item['#attached']['drupalSettings']['leaflet'];
      $settings_key = key($settings);
      $features = &$settings[$settings_key]['features'];
      foreach ($features as $feature_delta => &$feature) {
        // If a NULL description or an empty string, no modification is made,
        // and the entity label continues being used as the popup value.
        if (!empty($descriptions[$feature_delta])) {
          // Note $feature is modified by reference.
          $feature['popup']['value'] = $descriptions[$feature_delta];
        }
      }
    }

    return $render;
  }

}
