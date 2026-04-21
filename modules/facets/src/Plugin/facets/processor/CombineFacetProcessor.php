<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a processor that combines results of different facets.
 *
 * @FacetsProcessor(
 *   id = "combine_processor",
 *   label = @Translation("Combine facets"),
 *   description = @Translation("Combine the results of two or more facets. The raw value of a result item is used to identify a results item. It is up to you to ensure that the combination of the result sets makes sense. As the combination bases on the raw values it makes sense to place this processor on an early position, especially before the URL handler"),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class CombineFacetProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facets_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DefaultFacetManager $facets_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->facetsManager = $facets_manager;
    $this->facetStorage = $entity_type_manager->getStorage('facets_facet');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('facets.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $current_facet) {
    $build = [];

    $config = $this->getConfiguration();

    // Loop over all defined blocks and filter them by provider, this builds an
    // array of blocks that are provided by the facets module.
    /** @var \Drupal\facets\Entity\Facet[] $facets */
    $facets = $this->facetStorage->loadMultiple();
    foreach ($facets as $facet) {
      if ($facet->id() === $current_facet->id()) {
        continue;
      }

      $build[$facet->id()]['label'] = [
        '#title' => $facet->getName() . ' (' . $facet->getFacetSourceId() . ')',
        '#type' => 'label',
      ];

      $build[$facet->id()]['combine'] = [
        '#title' => $this->t('Combine'),
        '#type' => 'checkbox',
        '#default_value' => !empty($config[$facet->id()]['combine']),
      ];

      $build[$facet->id()]['mode'] = [
        '#title' => $this->t('Mode'),
        '#type' => 'radios',
        '#options' => [
          'union' => $this->t("Add that facet's results to this facet's results (union)."),
          'diff' => $this->t("Only keep this facet's results that are not present in that facet's results (diff)."),
          'intersect' => $this->t('Only keep results that occur in both facets (intersect).'),
        ],
        '#default_value' => empty($config[$facet->id()]['mode']) ? NULL : $config[$facet->id()]['mode'],
        '#states' => [
          'visible' => [
            ':input[name="facet_settings[' . $this->getPluginId() . '][settings][' . $facet->id() . '][combine]"]' => ['checked' => TRUE],
          ],
        ],
      ];

    }

    return parent::buildConfigurationForm($form, $form_state, $current_facet) + $build;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $conditions = $this->getConfiguration();
    $enabled_combinations = [];

    foreach ($conditions as $facet_id => $condition) {
      if (empty($condition['combine'])) {
        continue;
      }
      $enabled_combinations[$facet_id] = $condition;
    }

    // Return as early as possible when there are no settings for allowed
    // facets.
    if (empty($enabled_combinations)) {
      return $results;
    }

    $keyed_results = $facet->getResultsKeyedByRawValue($results);

    foreach ($enabled_combinations as $facet_id => $settings) {
      /** @var \Drupal\facets\Entity\Facet $current_facet */
      $current_facet = $this->facetStorage->load($facet_id);
      $current_facet = $this->facetsManager->returnBuiltFacet($current_facet);
      switch ($settings['mode']) {
        case 'union':
          $results = $keyed_results + $current_facet->getResultsKeyedByRawValue();
          break;

        case 'diff':
          $results = array_diff_key($keyed_results, $current_facet->getResultsKeyedByRawValue());
          break;

        case 'intersect':
          $results = array_intersect_key($keyed_results, $current_facet->getResultsKeyedByRawValue());
          break;
      }
      // Pass build processor information into current facet.
      $facet->addCacheableDependency($current_facet);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    // Only support facets as entities, not e.g. facets_exposed_filters.
    return $facet->getFacetType() == 'facet_entity';
  }

}
