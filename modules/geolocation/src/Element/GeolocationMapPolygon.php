<?php

namespace Drupal\geolocation\Element;

/**
 * Provides a render element for a single geolocation map location.
 *
 * Usage example:
 * @code
 * $form['map'] = [
 *   '#type' => 'geolocation_map_polygon',
 *   '#coordinates' => NULL,
 *   '#id' => NULL,
 *   '#stroke_color' => NULL,
 *   '#stroke_width' => NULL,
 *   '#stroke_opacity' => NULL,
 *   '#fill_color' => NULL,
 *   '#fill_opacity' => NULL,
 * ];
 * @endcode
 *
 * @FormElement("geolocation_map_polygon")
 */
class GeolocationMapPolygon extends GeolocationMapShapeBase {

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
        [$this, 'preRenderPolygon'],
      ],
      '#title' => NULL,
      '#coordinates' => NULL,
      '#id' => NULL,
      '#stroke_color' => NULL,
      '#stroke_width' => NULL,
      '#stroke_opacity' => NULL,
      '#fill_color' => NULL,
      '#fill_opacity' => NULL,
    ];
  }

  /**
   * Polygon element.
   *
   * @param array $render_array
   *   Element.
   *
   * @return array
   *   Renderable map.
   */
  public function preRenderPolygon(array $render_array) {
    $render_array = parent::preRenderGeolocationShape($render_array);

    $render_array['#theme'] = 'geolocation_map_polygon';

    $render_array['#attributes']->addClass('geolocation-polygon');

    if (!empty($render_array['#fill_color'])) {
      $render_array['#attributes']->setAttribute('data-fill-color', $render_array['#fill_color']);
    }
    if (!empty($render_array['#fill_opacity'])) {
      $render_array['#attributes']->setAttribute('data-fill-opacity', (float) $render_array['#fill_opacity']);
    }

    return $render_array;
  }

}
