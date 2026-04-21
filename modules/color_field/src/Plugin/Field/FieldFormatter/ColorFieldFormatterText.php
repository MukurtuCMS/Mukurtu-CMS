<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldFormatter;

use Drupal\color_field\ColorHex;
use Drupal\color_field\Plugin\Field\FieldType\ColorFieldType;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the color_field text formatter.
 *
 * @FieldFormatter(
 *   id = "color_field_formatter_text",
 *   module = "color_field",
 *   label = @Translation("Color text"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldFormatterText extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $opacity = $this->getFieldSetting('opacity');

    $elements = [];

    $elements['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => $this->getColorFormat(),
      '#default_value' => $this->getSetting('format'),
    ];

    if ($opacity) {
      $elements['opacity'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display opacity'),
        '#default_value' => $this->getSetting('opacity'),
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $opacity = $this->getFieldSetting('opacity');
    $settings = $this->getSettings();

    $summary = [];

    $summary[] = $this->t('@format', [
      '@format' => $this->getColorFormat($settings['format']),
    ]);

    if ($opacity && $settings['opacity']) {
      $summary[] = $this->t('Display with opacity.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $this->viewValue($item)];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'format' => 'hex',
      'opacity' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * This function is used to get the color format.
   *
   * @param string|null $format
   *   Format is of string type.
   *
   * @return \Drupal\Component\Render\MarkupInterface[]|\Drupal\Component\Render\MarkupInterface
   *   Returns array or string.
   */
  protected function getColorFormat(?string $format = NULL): array|MarkupInterface {
    $formats = [];
    $formats['hex'] = $this->t('Hex triplet');
    $formats['rgb'] = $this->t('RGB Decimal');

    if ($format) {
      return $formats[$format];
    }

    return $formats;
  }

  /**
   * View value as text in hex or rgb format.
   *
   * @param \Drupal\color_field\Plugin\Field\FieldType\ColorFieldType $item
   *   The field item.
   *
   * @return string
   *   The color in hex or rgb format (per field settings).
   */
  protected function viewValue(ColorFieldType $item): string {
    $opacity = $this->getFieldSetting('opacity');
    $settings = $this->getSettings();

    $color_hex = new ColorHex($item->color, is_null($item->opacity) ? NULL : (float) $item->opacity);

    switch ($settings['format']) {
      case 'hex':
        $output = $opacity && $settings['opacity']
            ? $color_hex->toString(TRUE)
            : $color_hex->toString(FALSE);

        break;

      case 'rgb':
      default:
        $output = $opacity && $settings['opacity']
            ? $color_hex->toRgb()->toString(TRUE)
            : $color_hex->toRgb()->toString(FALSE);

        break;
    }

    return $output;
  }

}
