<?php

namespace Drupal\geolocation\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Template\Attribute;

/**
 * Class GeolocationMapShape Base.
 *
 * @package Drupal\geolocation\Element
 */
abstract class GeolocationMapShapeBase extends RenderElementBase {

  /**
   * Shape element.
   *
   * @param array $render_array
   *   Element.
   *
   * @return array
   *   Renderable map.
   */
  public function preRenderGeolocationShape(array $render_array) {
    if (empty($render_array['#id'])) {
      $id = uniqid();
      $render_array['#id'] = $id;
    }

    if (is_array($render_array['#coordinates'])) {
      $coordinates = '';
      foreach ($render_array['#coordinates'] as $coordinate) {
        $coordinates .= $coordinate['lat'] . ',' . $coordinate['lng'] . ' ';
      }
      $render_array['#coordinates'] = $coordinates;
    }

    foreach (Element::children($render_array) as $child) {
      $render_array['#children'][] = $render_array[$child];
    }

    if (empty($render_array['#attributes'])) {
      $render_array['#attributes'] = [];
    }

    $render_array['#attributes'] = new Attribute($render_array['#attributes']);
    $render_array['#attributes']->addClass('geolocation-shape');
    $render_array['#attributes']->addClass('js-hide');
    if (!empty($render_array['#id'])) {
      $render_array['#attributes']->setAttribute('id', $render_array['#id']);
    }

    if (empty($render_array['#stroke_color'])) {
      $render_array['#stroke_color'] = '#0000FF';
    }
    $render_array['#attributes']->setAttribute('data-stroke-color', $render_array['#stroke_color']);
    if (empty($render_array['#stroke_width'])) {
      $render_array['#stroke_width'] = '2';
    }
    $render_array['#attributes']->setAttribute('data-stroke-width', (int) $render_array['#stroke_width']);
    if (!empty($render_array['#stroke_opacity'])) {
      $render_array['#attributes']->setAttribute('data-stroke-opacity', (float) $render_array['#stroke_opacity']);
    }

    return $render_array;
  }

}
