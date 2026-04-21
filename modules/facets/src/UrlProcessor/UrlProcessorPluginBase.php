<?php

namespace Drupal\facets\UrlProcessor;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A base class for plugins that implements most of the boilerplate.
 *
 * By default all plugins that will extend this class will disable facets
 * caching mechanism. It is strongly recommended to turn it on by implementing
 * own methods for the CacheableDependencyInterface interface.
 */
abstract class UrlProcessorPluginBase extends ProcessorPluginBase implements UrlProcessorInterface, ContainerFactoryPluginInterface, CacheableDependencyInterface {

  use UncacheableDependencyTrait;

  /**
   * The query string variable.
   *
   * @var string
   *   The query string variable that holds all the facet information.
   */
  protected $filterKey = 'f';

  /**
   * The url separator variable.
   *
   * @var string
   *   The separator to use between field and value.
   */
  protected $separator;

  /**
   * The delimiter for multiple values.
   *
   * @var string
   *   The delimiter to use between multiple values.
   */
  protected $delimiter = '|';

  /**
   * The clone of the current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * An array of active filters.
   *
   * @var array
   *   An array containing the active filters with key being the facet id and
   *   value being an array of raw values.
   */
  protected $activeFilters = [];

  /**
   * {@inheritdoc}
   */
  public function getFilterKey() {
    return $this->filterKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeparator() {
    return $this->separator;
  }

  /**
   * {@inheritdoc}
   */
  public function getDelimiter(): string {
    return $this->delimiter;
  }

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object for the current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = clone $request;
    $this->entityTypeManager = $entity_type_manager;

    if (!isset($configuration['facet'])) {
      throw new InvalidProcessorException("The url processor doesn't have the required 'facet' in the configuration array.");
    }

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $configuration['facet'];

    /** @var \Drupal\facets\FacetSourceInterface $facet_source_config */
    $facet_source_config = $facet->getFacetSourceConfig();

    $this->filterKey = $facet_source_config->getFilterKey() ?: 'f';

    // Set the separator to the predefined colon char but override if passed
    // along as part of the plugin configuration.
    $this->separator = ':';
    if (isset($configuration['separator'])) {
      $this->separator = $configuration['separator'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $request_stack = $container->get('request_stack');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $request_stack->getMainRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveFilters() {
    return $this->activeFilters;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveFilters(array $active_filters) {
    $this->activeFilters = $active_filters;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveItems(FacetInterface $facet) {
    // Get the filter key of the facet.
    if (isset($this->activeFilters[$facet->id()])) {
      foreach ($this->activeFilters[$facet->id()] as $value) {
        $facet->setActiveItem(trim($value, '"'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRequest(): Request {
    return $this->request;
  }

}
