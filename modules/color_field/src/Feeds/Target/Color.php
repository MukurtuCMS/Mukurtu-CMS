<?php

declare(strict_types=1);

namespace Drupal\color_field\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a color field mapper.
 *
 * @FeedsTarget(
 *   id = "color",
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class Color extends FieldTargetBase implements ConfigurableTargetInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + ['format' => '#hexhex'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        '#HEXHEX' => $this->t('#123ABC'),
        'HEXHEX' => $this->t('123ABC'),
        '#hexhex' => $this->t('#123abc'),
        'hexhex' => $this->t('123abc'),
      ],
      '#default_value' => $this->configuration['format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

    $summary[] = $this->configuration['format'] ?
      $summary[] = $this->t('Color with format: %format', ['%format' => $this->configuration['format']]) :
      $this->t('Color by default');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    // Clean up data and format it.
    $color = trim($values['color']);

    if (str_starts_with($color, '#')) {
      $color = substr($color, 1);
    }

    switch ($this->configuration['format']) {
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

    $values['color'] = $color;
    $values['opacity'] = $values['opacity']
        ? (float) $values['opacity']
        : 0.0;
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('color')
      ->addProperty('opacity');
  }

}
