<?php

namespace Drupal\geofield\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;

/**
 * Plugin to compare the latitude and longitude for geofields.
 *
 * @FieldDiffBuilder(
 *   id = "geofield_field_diff_builder",
 *   label = @Translation("Geofield Field Diff"),
 *   field_types = {
 *     "geofield"
 *   },
 * )
 */
class GeofieldFieldBuilder extends CoreFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): array {
    $result = [];

    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $value = $field_item->view([
          'label' => 'hidden',
          'type' => 'geofield_latlon',
        ]);
        $rendered_value = $this->renderer->renderInIsolation($value);
        $result[$field_key][] = $rendered_value;
      }
    }

    return $result;
  }

}
