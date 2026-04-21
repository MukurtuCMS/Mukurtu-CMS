<?php

namespace Drupal\geolocation\Element;

/**
 * Provides a render element for a single geolocation map location.
 *
 * Usage example:
 * @code
 * $form['map'] = [
 *   '#type' => 'geolocation_map_polyline',
 *   '#coordinates' => NULL,
 *   '#id' => NULL,
 *   '#stroke_color' => NULL,
 *   '#stroke_width' => NULL,
 *   '#stroke_opacity' => NULL,
 * ];
 * @endcode
 *
 * @FormElement("geolocation_map_polyline")
 */
class GeolocationMapPolyline extends GeolocationMapShapeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#process' => [
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
        [$this, 'preRenderPolyline'],
      ],
      '#title' => NULL,
      '#coordinates' => NULL,
      '#id' => NULL,
      '#stroke_color' => NULL,
      '#stroke_width' => NULL,
      '#stroke_opacity' => NULL,
    ];
  }

  /**
   * Polyline element.
   *
   * @param array $render_array
   *   Element.
   *
   * @return array
   *   Renderable map.
   */
  public function preRenderPolyline(array $render_array) {
    $render_array = parent::preRenderGeolocationShape($render_array);

    $render_array['#theme'] = 'geolocation_map_polyline';

    $render_array['#attributes']->addClass('geolocation-polyline');

    return $render_array;
  }

}
