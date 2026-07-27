<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\Hook\CommunityProtocolList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Communities and Cultural Protocols sidebar block.
 *
 * Lists the communities and cultural protocols applied to a node, for use in
 * Layout Builder-managed displays. Reuses the same rendering logic as
 * \Drupal\mukurtu_protocol\Hook\CommunityProtocolList, which injects this
 * list directly for content types not yet using Layout Builder.
 *
 * @Block(
 *   id = "mukurtu_community_protocol_list",
 *   admin_label = @Translation("Communities and Cultural Protocols"),
 *   category = @Translation("Mukurtu"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class CommunityProtocolListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CommunityProtocolList $communityProtocolList,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('class_resolver')->getInstanceFromDefinition(CommunityProtocolList::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->getContextValue('node');
    if (!$node instanceof CulturalProtocolControlledInterface) {
      return [];
    }

    return $this->communityProtocolList->buildCommunityProtocolList($node);
  }

}
