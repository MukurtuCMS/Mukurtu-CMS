<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the color_field box widget.
 *
 * @FieldWidget(
 *   id = "color_field_widget_box",
 *   module = "color_field",
 *   label = @Translation("Color boxes"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldWidgetBox extends ColorFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];
    $element['default_colors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default colors'),
      '#default_value' => $this->getSetting('default_colors'),
      '#required' => TRUE,
      '#element_validate' => [
        [$this, 'settingsColorValidate'],
      ],
      '#description' => $this->t('Default colors for pre-selected color boxes. Enter as 6 digit upper case hex - such as #FF0000.'),
    ];

    return $element;
  }

  /**
   * Use element validator to make sure that hex values are in correct format.
   *
   * @param mixed[] $element
   *   The Default colors element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function settingsColorValidate(array $element, FormStateInterface $form_state): void {
    $default_colors = $form_state->getValue($element['#parents']);
    $colors = '';

    if (!empty($default_colors)) {
      preg_match_all("/#[0-9a-f]{6}/i", $default_colors, $default_colors, PREG_SET_ORDER);

      foreach ($default_colors as $color) {
        if (!empty($colors)) {
          $colors .= ',';
        }

        $colors .= strtolower($color[0]);
      }
    }

    $form_state->setValue($element['#parents'], $colors);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $default_colors = $this->getSetting('default_colors');

    if (!empty($default_colors)) {
      preg_match_all("/#[0-9A-F]{6}/i", $default_colors, $default_colors, PREG_SET_ORDER);

      foreach ($default_colors as $color) {
        $colors = $color[0];
        $summary[] = $colors;
      }
    }

    if (empty($summary)) {
      $summary[] = $this->t('No default colors');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $settings = [];
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Ensure the default value is the required format.
    if ($element['color']['#default_value']) {
      $element['color']['#default_value'] = strtoupper($element['color']['#default_value']);

      if (strlen($element['color']['#default_value']) === 6) {
        $element['color']['#default_value'] = '#' . $element['color']['#default_value'];
      }
    }
    elseif ($element['#required']) {
      // If the element is required but has no default value and the element is
      // hidden like the color boxes widget does, prevent HTML5 Validation from
      // being invisible and blocking save with no apparent reason.
      $element['color']['#attributes']['class'][] = 'color_field_widget_box__color';
    }

    $element['#attached']['library'][] = 'color_field/color-field-widget-box';

    // Set Drupal settings.
    $settings[$element['#uid']] = [
      'required' => $this->fieldDefinition->isRequired(),
    ];
    $default_colors = $this->getSetting('default_colors');
    preg_match_all("/#[0-9A-F]{6}/i", $default_colors, $default_colors, PREG_SET_ORDER);

    foreach ($default_colors as $color) {
      $settings[$element['#uid']]['palette'][] = $color[0];
    }

    $element['#attached']['drupalSettings']['color_field']['color_field_widget_box']['settings'] = $settings;

    $element['color']['#suffix'] = "<div class='color-field-widget-box-form' id='" . $element['#uid'] . "'></div>";

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'default_colors' => '
#ac725e,#d06b64,#f83a22,#fa573c,#ff7537,#ffad46
#42d692,#16a765,#7bd148,#b3dc6c,#fbe983
#92e1c0,#9fe1e7,#9fc6e7,#4986e7,#9a9cff
#b99aff,#c2c2c2,#cabdbf,#cca6ac,#f691b2
#cd74e6,#a47ae2',
    ] + parent::defaultSettings();
  }

}
