<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet\Plugin\Field\FieldWidget\LeafletDefaultWidget;

/**
 * Widget implementation of the 'geofield_mukurtu' widget.
 */
#[FieldWidget(
  id: 'geofield_mukurtu',
  label: new TranslatableMarkup('Mukurtu Leaflet (GeoJSON)'),
  field_types: ['geofield'],
)]
class GeofieldMukurtuWidget extends LeafletDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $defaults = parent::defaultSettings();
    $defaults['map']['map_position']['zoom'] = '2';
    $defaults['map']['map_position']['singlePointZoom'] = 12;
    $defaults['locate']['control'] = TRUE;
    $defaults['locate']['automatic'] = TRUE;
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $map_settings = $this->getSetting('map');
    $default_settings = self::defaultSettings();
    $map_position_options = $map_settings['map_position'] ?? $default_settings['map']['map_position'];

    $form['map']['map_position']['singlePointZoom'] = [
      '#title' => $this->t('Single Point Zoom'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 22,
      '#description' => $this->t('The zoom level to use when the map centers on exactly one saved point (e.g. editing an existing location). This is separate from Initial Zoom, which only applies to an empty map or when multiple points/shapes are present.'),
      '#default_value' => $map_position_options['singlePointZoom'] ?? $default_settings['map']['map_position']['singlePointZoom'],
      '#required' => TRUE,
      // Has no effect once Force Map Center & Zoom is checked, same as
      // Zoom Finer, so hide it under the same condition.
      '#states' => $form['map']['map_position']['zoomFiner']['#states'] ?? [],
    ];

    // Circles drawn in the widget are converted to polygon approximations
    // before being serialized to GeoJSON (see mukurtu-leaflet-widget.js),
    // so the contrib module's GeoJSON limitation doesn't apply here.
    $form['toolbar']['drawCircle']['#disabled'] = FALSE;
    $form['toolbar']['drawCircle']['#title'] = $this->t('Adds button to draw circle.');

    return $form;
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
    return $element;
  }

}
