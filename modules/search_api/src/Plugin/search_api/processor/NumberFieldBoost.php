<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * Adds a boost based on a number field value.
 */
#[SearchApiProcessor(
  id: 'number_field_boost',
  label: new TranslatableMarkup('Number field-based boosting'),
  description: new TranslatableMarkup('Adds a boost to indexed items based on the value of a numeric field.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
class NumberFieldBoost extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'boosts' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configuration['boosts'];
    $additional_factors = [];
    foreach ($config as $field_configs) {
      if (isset($field_configs['boost_factor'])) {
        $additional_factors[] = $field_configs['boost_factor'];
      }
    }
    $boost_factors = Utility::getBoostFactors($additional_factors);
    $boost_factors[Utility::formatBoostFactor(0)] = $this->t('Ignore');

    foreach ($this->index->getFields(TRUE) as $field_id => $field) {
      if (in_array($field->getType(), ['integer', 'decimal', 'date'])) {
        $form['boosts'][$field_id] = [
          '#type' => 'details',
          '#title' => $field->getLabel(),
        ];

        $form['boosts'][$field_id]['boost_factor'] = [
          '#type' => 'select',
          '#title' => $this->t('Boost factor'),
          '#options' => $boost_factors,
          '#description' => $this->t('The boost factor the field value gets multiplied with. Setting it to 1.00 means using the field value as a boost as it is.'),
          '#default_value' => Utility::formatBoostFactor($config[$field_id]['boost_factor'] ?? 0),
        ];

        $form['boosts'][$field_id]['aggregation'] = [
          '#type' => 'select',
          '#title' => $this->t('Aggregation'),
          '#options' => [
            'max' => $this->t('maximum'),
            'min' => $this->t('minimum'),
            'avg' => $this->t('average'),
            'sum' => $this->t('sum'),
            'mul' => $this->t('product'),
            'first' => $this->t('use first value'),
          ],
          '#description' => $this->t('Select the method of aggregation to use in case the field has multiple values.'),
          '#default_value' => $config[$field_id]['aggregation'] ?? 'max',
          // @todo This shouldn't be dependent on the form array structure.
          //   Use the '#process' trick instead.
          '#states' => [
            'invisible' => [
              ":input[name=\"processors[number_field_boost][settings][boosts][$field_id][boost_factor]\"]" => [
                'value' => '',
              ],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $ignore = Utility::formatBoostFactor(0);
    foreach ($values['boosts'] ?? [] as $field_id => $settings) {
      if (!$settings['boost_factor'] || $settings['boost_factor'] === $ignore) {
        unset($values['boosts'][$field_id]);
      }
    }
    $form_state->setValues($values);
    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    $boosts = $this->configuration['boosts'];

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      foreach ($boosts as $field_id => $settings) {
        if ($field = $item->getField($field_id)) {
          if ($values = $field->getValues()) {
            $value = match ($settings['aggregation']) {
              'min' => min($values),
              'avg' => array_sum($values) / count($values),
              'sum' => array_sum($values),
              'mul' => array_product($values),
              'first' => reset($values),
              default => max($values),
            };
            if ($value) {
              // Normalize values from dates (which are represented by UNIX
              // timestamps) to be not too large to store in the database.
              if ($field->getType() === 'date') {
                $value /= 1000000;
              }
              // Make sure the value is never negative.
              $value = max($value, 0);
              $item->setBoost($item->getBoost() * (double) $value * (double) $settings['boost_factor']);
            }
          }
        }
      }
    }
  }

}
