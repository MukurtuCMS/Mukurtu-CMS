<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_dictionary\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Processor\SortProcessorInterface;
use Drupal\facets\Result\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A processor that orders glossary results by custom user-defined weights.
 *
 * @FacetsProcessor(
 *   id = "glossary_custom_order",
 *   label = @Translation("Sort by custom glossary order"),
 *   description = @Translation("Sorts glossary entries by user-defined weights from the glossary order configuration."),
 *   stages = {
 *     "sort" = 35
 *   }
 * )
 */
class GlossaryCustomOrderProcessor extends SortProcessorPluginBase implements SortProcessorInterface, ContainerFactoryPluginInterface {
  use UnchangingCacheableDependencyTrait;

  /**
   * Constructs a GlossaryCustomOrderProcessor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b): int {
    // Load the glossary order configuration.
    $config = $this->configFactory->get('mukurtu_dictionary_glossary_order.settings');
    $sort_mode = $config->get('sort_mode') ?? 'default';

    // If default mode, don't apply custom sorting (let other processors handle
    // it).
    if ($sort_mode === 'default') {
      return 0;
    }

    // Build weights map from config.
    $weights_config = $config->get('weights') ?? [];
    $weights = [];
    foreach ($weights_config as $item) {
      if (isset($item['glossary_entry']) && isset($item['weight'])) {
        $weights[$item['glossary_entry']] = $item['weight'];
      }
    }

    // Get the display values for comparison.
    $value_a = $a->getDisplayValue();
    $value_b = $b->getDisplayValue();

    // Get weights for both values.
    // If a value doesn't have a weight, give it a very high weight
    // so it sorts to the end, then use unicode comparison.
    $weight_a = $weights[$value_a] ?? PHP_INT_MAX;
    $weight_b = $weights[$value_b] ?? PHP_INT_MAX;

    // First compare by weight.
    if ($weight_a != $weight_b) {
      return $weight_a <=> $weight_b;
    }

    // If weights are equal (including both being unweighted),
    // use unicode/alphabetical comparison.
    return strnatcasecmp($value_a, $value_b);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
