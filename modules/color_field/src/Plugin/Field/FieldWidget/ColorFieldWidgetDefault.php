<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the color_widget default input widget.
 *
 * @FieldWidget(
 *   id = "color_field_widget_default",
 *   module = "color_field",
 *   label = @Translation("Color default"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldWidgetDefault extends ColorFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];
    $element['placeholder_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Color placeholder'),
      '#default_value' => $this->getSetting('placeholder_color'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['placeholder_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity placeholder'),
      '#default_value' => $this->getSetting('placeholder_opacity'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $placeholder_color = $this->getSetting('placeholder_color');
    $placeholder_opacity = $this->getSetting('placeholder_opacity');

    if (!empty($placeholder_color)) {
      $summary[] = $this->t('Color placeholder: @placeholder_color', ['@placeholder_color' => $placeholder_color]);
    }

    if (!empty($placeholder_opacity)) {
      $summary[] = $this->t('Opacity placeholder: @placeholder_opacity', ['@placeholder_opacity' => $placeholder_opacity]);
    }

    if (empty($summary)) {
      $summary[] = $this->t('No placeholder');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['color']['#placeholder'] = $this->getSetting('placeholder_color');

    if ($this->getFieldSetting('opacity')) {
      $element['opacity']['#placeholder'] = $this->getSetting('placeholder_opacity');
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'placeholder_color' => '',
      'placeholder_opacity' => '',
    ] + parent::defaultSettings();
  }

}
