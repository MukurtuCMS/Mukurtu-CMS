<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
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

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
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
      $container->get('current_user'),
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

    $items = [];
    $protocols = $node->getProtocolEntities();
    foreach ($protocols as $protocol) {
      // Check access to the protocol and community, since some
      // protocols/communities applied to content may not be accessible to the
      // current user. In the case where the protocol/community is not
      // accessible, just display the protocol/community label.
      $protocol_access = $protocol->access('view', $this->currentUser, TRUE);
      $protocol_display = $protocol_access->isAllowed()
        ? $protocol->toLink()->toString()
        : $protocol->label();
      $communities = $protocol->getCommunities();
      foreach ($communities as $community) {
        $community_access = $community->access('view', $this->currentUser, TRUE);
        $community_display = $community_access->isAllowed()
          ? $community->toLink()->toString()
          : $community->label();
        $item_render_array = ['#markup' => sprintf('%s: %s', $community_display, $protocol_display)];
        CacheableMetadata::createFromRenderArray($item_render_array)
          ->addCacheableDependency($protocol)
          ->addCacheableDependency($community)
          ->addCacheContexts(['user'])
          ->applyTo($item_render_array);
        $items[] = $item_render_array;
      }
    }

    return [
      '#theme' => 'community_protocol_list',
      '#title' => $this->t('Communities and Cultural Protocols'),
      '#items' => $items,
    ];
  }

}
