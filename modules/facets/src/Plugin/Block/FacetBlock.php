<?php

namespace Drupal\facets\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes a facet rendered as a block.
 *
 * @Block(
 *   id = "facet_block",
 *   deriver = "Drupal\facets\Plugin\Block\FacetBlockDeriver"
 * )
 */
class FacetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * The entity storage used for facets.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * The facet being displayed.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * Construct a FacetBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   *   The facet manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $facet_storage
   *   The entity storage used for facets.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DefaultFacetManager $facet_manager, EntityStorageInterface $facet_storage) {
    $this->facetManager = $facet_manager;
    $this->facetStorage = $facet_storage;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('entity_type.manager')->getStorage('facets_facet')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Do not build the facet if the block is being previewed.
    if ($this->getContextValue('in_preview')) {
      return [];
    }

    $facet = $this->getFacet();

    // Let the facet_manager build the facets.
    $build = $this->facetManager->build($facet);

    if (!empty($build)) {
      // Add contextual links only when the facet gets rendered.
      $build['#contextual_links']['facets_facet'] = [
        'route_parameters' => ['facets_facet' => $facet->id()],
      ];

      if (!empty($build[0]['#attributes']['class']) && in_array('facet-active', $build[0]['#attributes']['class'], TRUE)) {
        $build['#attributes']['class'][] = 'facet-active';
      }
      else {
        $build['#attributes']['class'][] = 'facet-inactive';
      }
    }

    // Even empty facet results should be cached.
    CacheableMetadata::createFromObject($this)->applyTo($build);

    return $build;
  }

  /**
   * Get facet entity.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet entity.
   */
  protected function getFacet(): FacetInterface {
    if (!$this->facet) {
      $this->facet = $this->facetStorage->load($this->getDerivativeId());
    }
    return $this->facet;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), $this->getFacet()->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), $this->getFacet()->getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::mergeMaxAges(parent::getCacheMaxAge(), $this->getFacet()->getCacheMaxAge());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return ['config' => [$this->getFacet()->getConfigDependencyName()]];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Checks for a valid form id. Panelizer does not generate one.
    if (isset($form['id']['#value'])) {
      // Save block id to configuration, we do this for loading the original
      // block with ajax.
      $block_id = $form['id']['#value'];
      $this->configuration['block_id'] = $block_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewFallbackString() {
    return $this->t('Placeholder for the "@facet" facet', ['@facet' => $this->getDerivativeId()]);
  }

  /**
   * Allow to render facet block if one of the following conditions are met.
   *
   * - facet is allowed to be displayed regardless of the source visibility
   * - facet source is rendered in the same request as facet.
   */
  public function blockAccess(AccountInterface $account) {
    $facet = $this->getFacet();
    return AccessResult::allowedIf(
      !$facet->getOnlyVisibleWhenFacetSourceIsVisible()
      || ($facet->getFacetSource() && $facet->getFacetSource()->isRenderedInCurrentRequest())
    )->addCacheableDependency($facet);
  }

}
