<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Result\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A processor that orders the term-results by their weight.
 *
 * @FacetsProcessor(
 *   id = "term_weight_widget_order",
 *   label = @Translation("Sort by taxonomy term weight"),
 *   description = @Translation("Sorts the widget results by taxonomy term weight. This sort is only applicable for term-based facets."),
 *   stages = {
 *     "sort" = 60
 *   }
 * )
 */
class TermWeightWidgetOrderProcessor extends SortProcessorPluginBase implements ContainerFactoryPluginInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b) {
    // Get the term weight once.
    if ($a->getTermWeight() === NULL || $b->getTermWeight() === NULL) {
      $ids = [];
      if ($a->getTermWeight() === NULL) {
        $a_raw = $a->getRawValue();
        $ids[] = $a_raw;
      }
      if ($b->getTermWeight() === NULL) {
        $b_raw = $b->getRawValue();
        $ids[] = $b_raw;
      }
      $entities = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadMultiple($ids);

      if ($a->getTermWeight() === NULL) {
        if (empty($entities[$a_raw])) {
          return 0;
        }
        $a->setTermWeight($entities[$a_raw]->getWeight());
      }

      if ($b->getTermWeight() === NULL) {
        if (empty($entities[$b_raw])) {
          return 0;
        }
        $b->setTermWeight($entities[$b_raw]->getWeight());
      }
    }

    // Return the sort value.
    if ($a->getTermWeight() === $b->getTermWeight()) {
      return 0;
    }
    return ($a->getTermWeight() < $b->getTermWeight()) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $data_definition = $facet->getDataDefinition();
    if ($data_definition->getDataType() === 'entity_reference') {
      return TRUE;
    }
    if (!($data_definition instanceof ComplexDataDefinitionInterface)) {
      return FALSE;
    }

    $data_definition = $facet->getDataDefinition();
    $property_definitions = $data_definition->getPropertyDefinitions();
    foreach ($property_definitions as $definition) {
      if ($definition instanceof DataReferenceDefinitionInterface
        && $definition->getDataType() === 'entity_reference'
        && $definition->getConstraint('EntityType') === 'taxonomy_term'
      ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['taxonomy_term_list']);
  }

}
