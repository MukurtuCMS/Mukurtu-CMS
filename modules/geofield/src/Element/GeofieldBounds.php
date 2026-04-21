<?php

namespace Drupal\geofield\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Geofield bounds form element.
 *
 * @FormElement("geofield_bounds")
 */
class GeofieldBounds extends GeofieldElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getComponents() {
    return [
      'top' => [
        'title' => t('Top'),
        'range' => 90,
      ],
      'right' => [
        'title' => t('Right'),
        'range' => 180,
      ],
      'bottom' => [
        'title' => t('Bottom'),
        'range' => 90,
      ],
      'left' => [
        'title' => t('Left'),
        'range' => 180,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'elementProcess'],
      ],
      '#element_validate' => [
        [$class, 'boundsValidate'],
      ],
      '#theme' => 'geofield_bounds',
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * Validates a Geofield bounds element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function boundsValidate(array &$element, FormStateInterface $form_state, array &$complete_form) {
    static::elementValidate($element, $form_state, $complete_form);

    $pairs = [
      [
        'bigger' => 'top',
        'smaller' => 'bottom',
      ],
      [
        'bigger' => 'right',
        'smaller' => 'left',
      ],
    ];

    foreach ($pairs as $pair) {
      if ($element[$pair['smaller']]['#value'] > $element[$pair['bigger']]['#value']) {
        $components = static::getComponents();
        $form_state->setError(
          $element[$pair['smaller']],
          t('@title: @component_bigger must be greater than @component_smaller.', [
            '@title' => $element['#title'],
            '@component_bigger' => $components[$pair['bigger']]['title'],
            '@component_smaller' => $components[$pair['smaller']]['title'],
          ])
        );
      }
    }
  }

}
