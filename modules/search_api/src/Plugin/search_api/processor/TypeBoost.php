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
 * Adds a boost to indexed items based on their datasource and/or bundle.
 */
#[SearchApiProcessor(
  id: 'type_boost',
  label: new TranslatableMarkup('Type-specific boosting'),
  description: new TranslatableMarkup('Adds a boost to indexed items based on their datasource and/or bundle.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
class TypeBoost extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'boosts' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState) {
    $datasource_configurations = [];
    $additional_factors = [];
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $datasource_configuration = $this->configuration['boosts'][$datasource_id] ?? [];
      $datasource_configuration += [
        'datasource_boost' => Utility::formatBoostFactor(1),
        'bundle_boosts' => [],
      ];
      $datasource_configurations[$datasource_id] = $datasource_configuration;
      $additional_factors = array_merge(
        $additional_factors,
        [$datasource_configuration['datasource_boost']],
        $datasource_configuration['bundle_boosts']
      );
    }

    $boost_factors = Utility::getBoostFactors($additional_factors);
    $bundle_boost_options = [
      '' => $this->t('Use datasource default'),
    ] + $boost_factors;
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $datasource_config = $datasource_configurations[$datasource_id];
      $form['boosts'][$datasource_id] = [
        '#type' => 'details',
        '#title' => $this->t('Boost settings for %datasource', ['%datasource' => $datasource->label()]),
        '#open' => TRUE,
        'datasource_boost' => [
          '#type' => 'select',
          '#title' => $this->t('Default boost for items from this datasource'),
          '#options' => $boost_factors,
          '#description' => $this->t('A boost of 1.00 is the default. Assign a boost of 0.00 to not score the item at all.'),
          '#default_value' => Utility::formatBoostFactor($datasource_config['datasource_boost']),
        ],
      ];

      // Add a boost for every available bundle. Drop the "pseudo-bundle" that
      // is added when the datasource does not contain any bundles.
      $bundles = $datasource->getBundles();
      if (count($bundles) === 1) {
        // Depending on the datasource, the pseudo-bundle might use the
        // datasource ID or the entity type ID.
        unset($bundles[$datasource_id], $bundles[$datasource->getEntityTypeId()]);
      }

      $bundle_boosts = $datasource_config['bundle_boosts'];
      foreach ($bundles as $bundle => $bundle_label) {
        $bundle_boost = $bundle_boosts[$bundle] ?? '';
        if ($bundle_boost !== '') {
          $bundle_boost = Utility::formatBoostFactor($bundle_boost);
        }
        $form['boosts'][$datasource_id]['bundle_boosts'][$bundle] = [
          '#type' => 'select',
          '#title' => $this->t('Boost for the %bundle bundle', ['%bundle' => $bundle_label]),
          '#options' => $bundle_boost_options,
          '#default_value' => $bundle_boost,
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
    foreach ($this->index->getDatasourceIds() as $datasource_id) {
      foreach ($values['boosts'][$datasource_id]['bundle_boosts'] ?? [] as $bundle => $boost) {
        if ($boost === '') {
          unset($values['boosts'][$datasource_id]['bundle_boosts'][$bundle]);
        }
      }
      if (empty($values['boosts'][$datasource_id]['bundle_boosts'])) {
        unset($values['boosts'][$datasource_id]['bundle_boosts']);
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
      $datasource_id = $item->getDatasourceId();
      $bundle = $item->getDatasource()->getItemBundle($item->getOriginalObject());

      $item_boost = (double) ($boosts[$datasource_id]['datasource_boost'] ?? 1.0);
      if ($bundle && isset($boosts[$datasource_id]['bundle_boosts'][$bundle])) {
        $item_boost = (double) $boosts[$datasource_id]['bundle_boosts'][$bundle];
      }

      $item->setBoost($item->getBoost() * $item_boost);
    }
  }

}
