<?php

namespace Drupal\facets_query_processor\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Display Generated Link block.
 *
 * @Block(
 *   id = "display_generated_link",
 *   admin_label = @Translation("Display Generated Link"),
 * )
 */
class DisplayGeneratedLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The facets URL generator service.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $urlGeneratorService;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs an DisplayGeneratedLinkBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets\Utility\FacetsUrlGenerator $facets_url_generator
   *   The facets URL generator service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FacetsUrlGenerator $facets_url_generator, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->urlGeneratorService = $facets_url_generator;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('facets.utility.url_generator'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = $this->urlGeneratorService->getUrl(['owl' => ['item']], $this->state->get('facets_url_generator_keep_active', FALSE));
    $link = new Link('Link to owl item', $url);

    return $link->toRenderable() + ['#cache' => ['max-age' => 0]];
  }

}
