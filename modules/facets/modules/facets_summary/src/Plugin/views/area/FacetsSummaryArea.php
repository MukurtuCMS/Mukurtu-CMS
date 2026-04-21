<?php

namespace Drupal\facets_summary\Plugin\views\area;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets_summary\FacetsSummaryManager\DefaultFacetsSummaryManager;
use Drupal\facets_summary\Plugin\views\FacetsSummaryViewsPluginTrait;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides exposing facet summaries in an area.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("facets_summary_area")
 */
class FacetsSummaryArea extends AreaPluginBase {

  use FacetsSummaryViewsPluginTrait;

  /**
   * The facet summary manager.
   *
   * @var \Drupal\facets_summary\FacetsSummaryManager\DefaultFacetsSummaryManager
   */
  protected $facetSummaryManager;

  /**
   * The entity storage used for facets.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetSummaryStorage;

  /**
   * Constructs a new FacetsSummaryArea instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets_summary\FacetsSummaryManager\DefaultFacetsSummaryManager $facet_summary_manager
   *   The facet manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $facet_storage
   *   The entity storage used for facets summaries.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DefaultFacetsSummaryManager $facet_summary_manager, EntityStorageInterface $facet_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->facetSummaryManager = $facet_summary_manager;
    $this->facetSummaryStorage = $facet_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('facets_summary.manager'),
      $container->get('entity_type.manager')->getStorage('facets_summary')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // Set the default to TRUE so it shows on empty pages by default.
    $options['empty']['default'] = TRUE;
    $options['facet_summary'] = ['default' => ''];
    $options['label_display']['default'] = BlockPluginInterface::BLOCK_LABEL_VISIBLE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $this->facetsSummaryViewsBuildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    return $this->facetsViewsGetFacetSummary();
  }

}
