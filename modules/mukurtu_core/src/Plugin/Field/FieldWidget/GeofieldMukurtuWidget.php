<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\leaflet\Plugin\Field\FieldWidget\LeafletDefaultWidget;

/**
 * Widget implementation of the 'geofield_mukurtu' widget.
 *
 * @FieldWidget(
 *   id = "geofield_mukurtu",
 *   label = @Translation("Mukurtu Leaflet (GeoJSON)"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldMukurtuWidget extends LeafletDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $defaults = parent::defaultSettings();
    $defaults['map']['map_position']['zoom'] = '2';
    $defaults['locate']['control'] = TRUE;
    $defaults['locate']['automatic'] = TRUE;
    return $defaults;
  }

  /**
   * Return the specific Geofield Backend Value.
   *
   * Use GeoJSON.
   *
   * @param mixed|null $value
   *   The data to load.
   *
   * @return mixed|null
   *   The specific backend format value.
   */
  protected function geofieldBackendValue($value) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // LeafletDefaultWidget smashes our default value. We want to keep
    // our GeoJSON untouched.
    $element['value']['#default_value'] = $items[$delta]->value ?: NULL;

    // Visually-hidden instructions for keyboard/screen reader users,
    // referenced by the map container's aria-describedby (set in
    // mukurtu-leaflet-widget.js) - placing a new point still requires a
    // pointing device, so this points to the accessible alternative.
    if (!empty($element['map']['#map_id'])) {
      $element['keyboard_instructions'] = [
        '#type' => 'markup',
        '#markup' => '<p id="' . $element['map']['#map_id'] . '-keyboard-instructions" class="visually-hidden">'
          . $this->t('Use the arrow keys to pan the map and the plus and minus keys to zoom. Tab reaches the toolbar buttons. While drawing, press Escape to cancel or Enter to finish a shape. Existing points are reachable by Tab; press Enter to open a point and edit its description. Placing a new point requires a pointing device - use the "Mukurtu Map Points (latitude/longitude)" widget for a keyboard-only alternative.')
          . '</p>',
        '#weight' => -1,
      ];
    }

    return $element;
  }

}
