<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Browse by Community landing page block.
 *
 * Displays only top-level (non-sub) communities, ordered by the weight
 * configured at /admin/community-organization.
 */
#[Block(
  id: 'mukurtu_browse_by_community',
  admin_label: new TranslatableMarkup('Browse by Community'),
  category: new TranslatableMarkup('Mukurtu'),
)]
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
    $config = $this->configFactory->get('mukurtu_protocol.community_organization');
    $org = $config->get('organization');

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
    $cacheability = CacheableMetadata::createFromObject($config);
    foreach ($communities as $community) {
      // Only list communities the current user can view.
      /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
      $access = $community->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($access);
      // Membership-based access varies per user, but the access result only
      // carries a user cache tag, so add the context here.
      if ($community->getSharingSetting() === 'community-only') {
        $cacheability->addCacheContexts(['user']);
      }
      if (!$access->isAllowed()) {
        continue;
      }
      $renderedCommunities[] = $builder->view($community, 'browse');
    }

    $build = [
      '#theme' => 'browse_by_community_block',
      '#communities' => $renderedCommunities,
    ];
    $cacheability->applyTo($build);

    return $build;
  }

}
