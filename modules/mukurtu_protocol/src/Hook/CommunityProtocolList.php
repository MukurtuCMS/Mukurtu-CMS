<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Hook implementations to get a list of community protocol pairs.
 */
class CommunityProtocolList {
  use StringTranslationTrait;

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
      $protocol_link = $protocol->toLink()->toString();
      $communities = $protocol->getCommunities();
      foreach ($communities as $community) {
        $community_link = $community->toLink()->toString();
        $item_render_array = ['#markup' => sprintf('%s: %s', $community_link, $protocol_link)];
        CacheableMetadata::createFromRenderArray($item_render_array)
          ->addCacheableDependency($protocol_link)
          ->addCacheableDependency($community_link)
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
