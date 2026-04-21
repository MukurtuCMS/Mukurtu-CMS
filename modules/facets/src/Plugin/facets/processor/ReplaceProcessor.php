<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\PostQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Replaces facet values based on a given mapping.
 *
 * @FacetsProcessor(
 *   id = "replace",
 *   label = @Translation("Replace display values"),
 *   description = @Translation("Replace display values with a mapping."),
 *   stages = {
 *     "post_query" = 50,
 *   },
 * )
 */
class ReplaceProcessor extends ProcessorPluginBase implements PostQueryProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'replacements' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $replacements = $this->getConfiguration()['replacements'];
    $build['replacements'] = [
      '#title' => $this->t('Replacement values'),
      '#type' => 'textarea',
      '#default_value' => $replacements,
      '#description' => $this->t("Enter one replacement per line, in the format <em>raw_value|display_value</em>. If the facet's raw value is <em>raw_value</em>, then its display value will be set to <em>display_value</em>."),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function postQuery(FacetInterface $facet) {
    $replacements = $this->extractReplacements();

    foreach ($facet->getResults() as $result) {
      $raw_value = $result->getRawValue();
      if (array_key_exists($raw_value, $replacements)) {
        $result->setDisplayValue($replacements[$raw_value]);
      }
    }
  }

  /**
   * Returns replacements in array format.
   *
   * @return array
   *   Array of replacements (old_values => new_values).
   *
   * @see \Drupal\options\Plugin\Field\FieldType\ListItemBase::extractAllowedValues()
   */
  protected function extractReplacements() {
    $replacements = [];

    $config = $this->getConfiguration()['replacements'];
    $lines = explode("\n", $config);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, 'strlen');

    foreach ($lines as $line) {
      // Make sure the line follows the pattern old_value|new_value, otherwise
      // skip the line.
      $matches = [];
      if (preg_match('/(.+)\|(.*)/', $line, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $old_value = trim($matches[1]);
        $new_value = trim($matches[2]);
        $replacements[$old_value] = $new_value;
      }
    }

    return $replacements;
  }

}
