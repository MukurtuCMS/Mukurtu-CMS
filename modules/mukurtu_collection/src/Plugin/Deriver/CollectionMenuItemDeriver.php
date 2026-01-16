<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\mukurtu_collection\CollectionMenuLinkDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives menu links for collection hierarchies.
 */
class CollectionMenuItemDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a new CollectionMenuItemDeriver.
   *
   * @param \Drupal\mukurtu_collection\CollectionMenuLinkDiscoveryInterface $menuLinkDiscovery
   *   The menu link discovery service.
   */
  public function __construct(protected CollectionMenuLinkDiscoveryInterface $menuLinkDiscovery) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('mukurtu_collection.menu_link_discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = $this->menuLinkDiscovery->getMenuLinkDefinitions();
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
