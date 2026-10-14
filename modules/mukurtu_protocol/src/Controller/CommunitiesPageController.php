<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for communities page.
 */
class CommunitiesPageController extends ControllerBase {

  /**
   * The communities page.
   */
  public function page($parent = 0) {
    $config = $this->config('mukurtu_protocol.community_organization');
    $org = $config->get('organization');

    $communityIDs = [];
    if ($org != NULL) {
      foreach ($org as $id => $settings) {
        if (intval($settings['parent']) === intval($parent)) {
          $communityIDs[$settings['weight']] = $id;
        }
      }
    }

    $communities = empty($communityIDs) ? [] : $this->entityTypeManager()->getStorage('community')->loadMultiple($communityIDs);

    $builder = $this->entityTypeManager()->getViewBuilder('community');
    $renderedCommunities = [];
    $cacheability = CacheableMetadata::createFromObject($config);
    foreach ($communities as $community) {
      // Only list communities the current user can view.
      /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
      $access = $community->access('view', $this->currentUser(), TRUE);
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

    $build['template'] = [
      '#theme' => 'communities_page',
      '#communities' => $renderedCommunities,
    ];
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Redirects the legacy /admin/protocols path to the current collection page.
   */
  public function protocolsDashboardRedirect(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('entity.protocol.collection')->toString(), 301);
  }

}
