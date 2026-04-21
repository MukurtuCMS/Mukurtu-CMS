<?php

namespace Drupal\geolocation\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Template\Attribute;

/**
 * Provides a render element for a single geolocation map location.
 *
 * Usage example:
 * @code
 * $form['location'] = [
 *   '#type' => 'geolocation_map_location',
 *   '#prefix' => $this->t('Geolocation Map Render Element'),
 *   '#description' => $this->t('Render element type "geolocation_map"'),
 *   '#title' => NULL,
 *   '#coordinates' => NULL,
 *   '#id' => NULL,
 *   '#hidden' => NULL,
 *   '#icon' => NULL,
 *   '#label' => NULL,
 * ];
 * @endcode
 *
 * @FormElement("geolocation_map_location")
 */
class GeolocationMapLocation extends RenderElementBase {

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
        [$this, 'preRenderLocation'],
      ],
      '#title' => NULL,
      '#coordinates' => NULL,
      '#id' => NULL,
      '#hidden' => NULL,
      '#icon' => NULL,
      '#label' => NULL,
    ];
  }

  /**
   * Location element.
   *
   * @param array $render_array
   *   Element.
   *
   * @return array
   *   Renderable map.
   */
  public function preRenderLocation(array $render_array) {
    $render_array['#theme'] = 'geolocation_map_location';

    if (empty($render_array['#id'])) {
      $id = uniqid();
      $render_array['#id'] = $id;
    }

    foreach (Element::children($render_array) as $child) {
      $render_array['#children'][] = $render_array[$child];
    }

    if (empty($render_array['#attributes'])) {
      $render_array['#attributes'] = [];
    }

    $render_array['#attributes'] = new Attribute($render_array['#attributes']);
    $render_array['#attributes']->addClass('geolocation-location');
    $render_array['#attributes']->addClass('js-hide');
    if (!empty($render_array['#id'])) {
      $render_array['#attributes']->setAttribute('id', $render_array['#id']);
    }

    if (!empty($render_array['#coordinates'])) {
      $render_array['#attributes']->setAttribute('data-lat', $render_array['#coordinates']['lat']);
      $render_array['#attributes']->setAttribute('data-lng', $render_array['#coordinates']['lng']);
    }

    if (empty($render_array['#hidden'])) {
      $render_array['#attributes']->setAttribute('data-set-marker', 'true');

      if (!empty($render_array['#icon'])) {
        $render_array['#attributes']->setAttribute('data-icon', $render_array['#icon']);
      }

      if (!empty($render_array['#label'])) {
        $render_array['#attributes']->setAttribute('data-label', $render_array['#label']);
      }
    }

    return $render_array;
  }

}
