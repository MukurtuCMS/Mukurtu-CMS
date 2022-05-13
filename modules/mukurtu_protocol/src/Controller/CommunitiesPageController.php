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
  public function page($parent = NULL) {
    $op = is_null($parent) ? 'IS NULL' : '=';
    $query = $this->entityTypeManager()->getStorage('community')->getQuery();
    $query->condition('field_parent_community', $parent, $op)
      ->sort('name')
      ->accessCheck(TRUE);

    $communityIDs = $query->execute();

    $communities = empty($communityIDs) ? [] : $this->entityTypeManager()->getStorage('community')->loadMultiple($communityIDs);

    $builder = $this->entityTypeManager()->getViewBuilder('community');
    $renderedCommunities = [];
    foreach ($communities as $community) {
      $renderedCommunities[] = $builder->view($community, 'browse');
    }

    $build['template'] = [
      '#theme' => 'communities-page',
      '#communities' => $renderedCommunities,
    ];

    return $build;
  }

}
