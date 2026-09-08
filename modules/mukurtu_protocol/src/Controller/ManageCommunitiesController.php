<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;

/**
 * Controller for community management pages.
 */
class ManageCommunitiesController extends ControllerBase {

  /**
   * Redirect to the community's members list.
   */
  public function manageCommunityRedirect(CommunityInterface $community) {
    return $this->redirect('mukurtu_protocol.community_members_list', ['group' => $community->id()], ['parameters' => ['group' => ['type' => 'entity:community']]]);
  }

}
