<?php

namespace Drupal\geofield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'geofield_latlon' widget.
 *
 * @FieldWidget(
 *   id = "geofield_latlon",
 *   label = @Translation("Latitude/Longitude"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldLatLonWidget extends GeofieldBaseWidget {

  /**
   * Lat Lon widget components.
   *
   * @var array
   */
  public $components = ['lon', 'lat'];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'html5_geolocation' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['html5_geolocation'] = [
      '#type' => 'checkbox',
      '#title' => 'Use HTML5 Geolocation to set default values',
      '#default_value' => $this->getSetting('html5_geolocation'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      $this->t('Use HTML5 Geolocation of user: @state', ['@state' => $this->getSetting('html5_geolocation') ? $this->t('enabled') : $this->t('disabled')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\geofield\Plugin\Field\FieldType\GeofieldItem $geofield_item */
    $geofield_item = $items->getValue()[$delta];
    if (empty($geofield_item) || $geofield_item['geo_type'] == 'Point') {
      $latlon_value = [];

      foreach ($this->components as $component) {
        $latlon_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
      }

      $element['value'] += [
        '#type' => 'geofield_latlon',
        '#default_value' => $latlon_value,
        '#geolocation' => $this->getSetting('html5_geolocation'),
        '#error_label' => !empty($element['#title']) ? $element['#title'] : $this->fieldDefinition->getLabel(),
      ];
    }
    else {
      $widget_label = $this->getPluginDefinition()['label']->render();
      $element['value'] += [
        '#prefix' => '<div class="geofield-warning">' . $this->t('The "@widget_label" widget cannot be applied because it doesn\'t support Geometries (Polylines, Polygons, etc.).', [
          '@widget_label' => $widget_label,
        ]) . '</div>',
        '#type' => 'textarea',
        '#default_value' => $items[$delta]->value ?: NULL,
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (!isset($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }

      }
      $components = $value['value'];
      $values[$delta]['value'] = $this->geofieldBackendValue($this->wktGenerator->wktBuildPoint([
        trim($components['lon']),
        trim($components['lat']),
      ]));
    }

    return $values;
  }

}
