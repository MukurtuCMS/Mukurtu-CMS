<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;

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
    foreach ($communities as $community) {
      $renderedCommunities[] = $builder->view($community, 'browse');
    }

    $build['template'] = [
      '#theme' => 'communities_page',
      '#communities' => $renderedCommunities,
    ];

    return $build;
  }

}
