<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Browse by Community landing page block.
 *
 * Displays only top-level (non-sub) communities, ordered by the weight
 * configured at /admin/community-organization.
 *
 * @Block(
 *   id = "mukurtu_browse_by_community",
 *   admin_label = @Translation("Browse by Community"),
 *   category = @Translation("Mukurtu")
 * )
 */
class BrowseByCommunityBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $org = $this->configFactory
      ->get('mukurtu_protocol.community_organization')
      ->get('organization');

    // Collect IDs of top-level communities (parent == 0), keyed by weight so
    // ksort() gives us the admin-configured display order.
    $communityIds = [];
    if (!empty($org)) {
      foreach ($org as $id => $settings) {
        if (intval($settings['parent']) === 0) {
          $communityIds[$settings['weight']] = $id;
        }
      }
    }
    ksort($communityIds);

    $communities = empty($communityIds)
      ? []
      : $this->entityTypeManager->getStorage('community')->loadMultiple($communityIds);

    $builder = $this->entityTypeManager->getViewBuilder('community');
    $renderedCommunities = [];
    foreach ($communities as $community) {
      // Skip private communities for non-members.
      /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
      if ($community->getSharingSetting() === 'community-only' && !Og::isMember($community, $this->currentUser)) {
        continue;
      }
      $renderedCommunities[] = $builder->view($community, 'browse');
    }

    return [
      '#theme' => 'browse_by_community_block',
      '#communities' => $renderedCommunities,
    ];
  }

}
