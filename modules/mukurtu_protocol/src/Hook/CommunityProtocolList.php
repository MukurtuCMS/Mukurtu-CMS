<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations to get a list of community protocol pairs.
 */
class CommunityProtocolList implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * Constructs a new CommunityProtocolList object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('current_user'));
  }

  /**
   * Implements hook_ENTITY_TYPE_view_alter().
   *
   * Put the Communities and Cultural Protocols together for the sidebar
   * display.
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof CulturalProtocolControlledInterface) {
      return;
    }
    $build['community_protocol_list'] = $this->buildCommunityProtocolList($entity);
  }

  /**
   * Build the community protocol list.
   *
   * @param \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface $node
   *   The node entity that is enabled for protocol sharing.
   *
   * @return array
   *   Render array for the community protocol list.
   */
  protected function buildCommunityProtocolList(CulturalProtocolControlledInterface $node): array {
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
