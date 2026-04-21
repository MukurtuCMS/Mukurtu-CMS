<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'color_type' field type.
 *
 * @FieldType(
 *   id = "color_field_type",
 *   label = @Translation("Color"),
 *   description = @Translation("Create and store color value."),
 *   default_widget = "color_field_widget_default",
 *   default_formatter = "color_field_formatter_text"
 * )
 */
class ColorFieldType extends FieldItemBase {

  /**
   * Hex value of color.
   *
   * @var string
   */
  protected string $color;

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $element = [];

    $element['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format storage'),
      '#description' => $this->t('Choose how to store the color.'),
      '#default_value' => $this->getSetting('format'),
      '#options' => [
        '#HEXHEX' => $this->t('#123ABC'),
        'HEXHEX' => $this->t('123ABC'),
        '#hexhex' => $this->t('#123abc'),
        'hexhex' => $this->t('123abc'),
      ],
    ];

    return $element + parent::storageSettingsForm($form, $form_state, $has_data);
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['opacity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Record opacity'),
      '#description' => $this->t('Whether or not to record.'),
      '#default_value' => $this->getSetting('opacity'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('color')->getValue();

    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $label = $this->getFieldDefinition()->getLabel();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'color' => [
        'Regex' => [
          'pattern' => '/^#?(([0-9a-fA-F]{2}){3}|([0-9a-fA-F]){3})$/i',
        ],
      ],
    ]);

    if ($this->getSetting('opacity')) {
      $min = 0;
      $constraints[] = $constraint_manager->create('ComplexData', [
        'opacity' => [
          'Range' => [
            'min' => $min,
            'minMessage' => $this->t('%name: the opacity may be no less than %min.', [
              '%name' => $label,
              '%min' => $min,
            ]),
          ],
        ],
      ]);

      $max = 1;
      $constraints[] = $constraint_manager->create('ComplexData', [
        'opacity' => [
          'Range' => [
            'max' => $max,
            'maxMessage' => $this->t('%name: the opacity may be no greater than %max.', [
              '%name' => $label,
              '%max' => $max,
            ]),
          ],
        ],
      ]);
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    if ($format = $this->getSetting('format')) {
      $color = $this->get('color')->getValue();

      // Clean up data and format it.
      $color = trim($color);

      if (str_starts_with($color, '#')) {
        $color = substr($color, 1);
      }
      switch ($format) {
        case '#HEXHEX':
          $color = '#' . strtoupper($color);

          break;

        case 'HEXHEX':
          $color = strtoupper($color);

          break;

        case '#hexhex':
          $color = '#' . strtolower($color);

          break;

        case 'hexhex':
          $color = strtolower($color);

          break;
      }
      $this->set('color', $color);
    }
    if ($this->getSetting('opacity')) {
      return;
    }

    $this->set('opacity', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName(): string {
    return 'color';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'opacity' => TRUE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return [
      'format' => '#HEXHEX',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $format = $field_definition->getSetting('format');
    $color_length = isset($format)
        ? strlen($format)
        : 7;

    return [
      'columns' => [
        'color' => [
          'description' => 'The color value',
          'type' => 'varchar',
          'length' => $color_length,
          'not null' => FALSE,
        ],
        'opacity' => [
          'description' => 'The opacity/alphavalue property',
          'type' => 'float',
          'size' => 'tiny',
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'color' => ['color'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];
    $properties['color'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Color'));

    $properties['opacity'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Opacity'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $settings = $field_definition->getSettings();
    $values = [];

    if ($format = $settings['format']) {
      switch ($format) {
        case '#HEXHEX':
          $values['color'] = '#111AAA';

          break;

        case 'HEXHEX':
          $values['color'] = '111111';
          break;

        case '#hexhex':
          $values['color'] = '#111aaa';
          break;

        case 'hexhex':
          $values['color'] = '111111';
          break;
      }
    }

    $values['opacity'] = 1;

    return $values;
  }

}
