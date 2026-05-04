<?php

namespace Drupal\mukurtu_browse\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides toggle links for collapsing multipage items and community records.
 *
 * @Block(
 *   id = "mukurtu_search_collapse_toggle",
 *   admin_label = @Translation("Search collapse toggle"),
 *   category = @Translation("Mukurtu")
 * )
 */
class SearchCollapseToggleBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RequestStack $requestStack,
  ) {
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
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $request = $this->requestStack->getCurrentRequest();
    $config = $this->configFactory->get('mukurtu_search.settings');
    $query_params = $request->query->all();

    $collapse_mpi = array_key_exists('mpi_collapse', $query_params)
      ? (bool) $query_params['mpi_collapse']
      : (bool) ($config->get('collapse_multipage_pages') ?? TRUE);

    $collapse_cr = array_key_exists('cr_collapse', $query_params)
      ? (bool) $query_params['cr_collapse']
      : (bool) ($config->get('collapse_community_records') ?? FALSE);

    return [
      '#theme' => 'search_collapse_toggle',
      '#show_all_pages' => !$collapse_mpi,
      '#show_community_records' => !$collapse_cr,
      '#attached' => ['library' => ['mukurtu_browse/search-collapse-toggle']],
      '#cache' => ['contexts' => ['url.query_args']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args']);
  }

}
