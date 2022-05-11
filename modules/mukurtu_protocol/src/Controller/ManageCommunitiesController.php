<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;

/**
 * Controller for community management pages.
 */
class ManageCommunitiesController extends ControllerBase {

  /**
   * Page for managing a single community.
   */
  public function manageCommunity(CommunityInterface $group) {
    $community = $group;
    $build = [];

    // Protocols.
    $protocols = $community->getProtocols();

    // Build management Links.
    $links = [];
    $links[] = [
      '#title' => $this->t('View'),
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.community.canonical', ['community' => $community->id()]),
    ];

    $links[] = [
      '#title' => $this->t('Edit'),
      '#type' => 'link',
      '#url' => $group->toUrl('edit-form'),
    ];

    $links[] = [
      '#title' => $this->t('Manage Members'),
      '#type' => 'link',
      '#url' => Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $community->id()]),
    ];

    $links[] = [
      '#title' => $this->t('Add Member'),
      '#type' => 'link',
      '#url' => Url::fromRoute('mukurtu_protocol.community_add_membership', ['group' => $community->id()]),
    ];

    $links[] = [
      '#title' => $this->t('Add Cultural Protocol'),
      '#type' => 'link',
      '#url' => Url::fromRoute('mukurtu_protocol.community_add_protocol', ['community' => $community->id()]),
    ];

    // Sharing Setting.
    $visibilityMarkup['strict'] = $this->t('Strict: This community is visible to community members only.');
    $visibilityMarkup['open'] = $this->t('Open: This community is visible to all.');
    $sharing = [
      '#type' => 'item',
      '#title' => $this->t('Sharing Protocol'),
      '#markup' => $visibilityMarkup[$group->getSharingSetting()],
    ];

    // Description.
    $description = $group->getDescription();
    if ($description) {
      $description = [
        '#type' => 'item',
        '#title' => $this->t('Description'),
        '#markup' => $description,
      ];
    }

    $build['template'] = [
      '#theme' => 'manage-community',
      '#links' => $links,
      '#community' => $community,
      '#sharing' => $sharing,
      '#description' => $description,
      '#protocols' => $protocols,
    ];

    return $build;
  }

  /**
   * Redirect to management page.
   */
  public function manageCommunityRedirect(CommunityInterface $community) {
    return $this->redirect('mukurtu_protocol.manage_community', ['group' => $community->id()], ['parameters' => ['group' => ['type' => 'entity:community']]]);
  }

  /**
   * Access check for redirect to management page.
   */
  public function manageCommunityRedirectAccess(AccountInterface $account, CommunityInterface $community) {
    /** @var \Drupal\og\Access\OgMembershipAddAccessCheck $og_access */
    $og_access = \Drupal::service('access_check.og.membership.add');
    return $og_access->access(\Drupal::routeMatch(), $account, $community);
  }

  /**
   * Title callback for single community management page.
   */
  public function getManageCommunityTitle(CommunityInterface $group) {
    return $this->t('Manage %community', ['%community' => $group->getName()]);
  }

}
