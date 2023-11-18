<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;

/**
 * Controller for protocol management pages.
 */
class ManageProtocolsController extends ControllerBase {

  /**
   * Page for managing a single protocol.
   */
  public function manageProtocol(ProtocolInterface $group) {
    $protocol = $group;
    $build = [];

    // Build management Links.
    $links = [];

    $access_manager = \Drupal::service('access_manager');
    if ($access_manager->checkNamedRoute('entity.protocol.canonical', ['protocol' => $protocol->id()])) {
      $links[] = [
        '#title' => $this->t('View'),
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.protocol.canonical', ['protocol' => $protocol->id()]),
      ];
    }

    $editUrl = $group->toUrl('edit-form');
    if ($editUrl->access()) {
      $links[] = [
        '#title' => $this->t('Edit'),
        '#type' => 'link',
        '#url' => $editUrl,
      ];
    }

    $manageUrl = Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()]);
    if ($manageUrl->access()) {
      $links[] = [
        '#title' => $this->t('Manage Members'),
        '#type' => 'link',
        '#url' => $manageUrl,
      ];
    }

    $addMemberUrl = Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()]);
    if ($addMemberUrl->access()) {
      $links[] = [
        '#title' => $this->t('Add Member'),
        '#type' => 'link',
        '#url' => $addMemberUrl,
      ];
    }

    $membership = $group->getMembership($this->currentUser());
    if ($membership && $membership->hasPermission('administer comments')) {
      $links[] = [
        '#title' => $this->t('Comment Settings'),
        '#type' => 'link',
        '#url' => Url::fromRoute('mukurtu_protocol.manage_protocol_comment_settings', ['group' => $protocol->id()]),
      ];
    }

    $manageProjectsUrl = Url::fromRoute('mukurtu_local_contexts.manage_protocol_supported_projects', ['group' => $protocol->id()]);
    if ($manageProjectsUrl->access()) {
      $links[] = [
        '#title' => $this->t('Manage Local Contexts Projects'),
        '#type' => 'link',
        '#url' => $manageProjectsUrl,
      ];
    }

    $protocolProjectDirectoryUrl = Url::fromRoute('mukurtu_local_contexts.protocol_projects_directory', ['group' => $protocol->id()]);
    if ($protocolProjectDirectoryUrl->access()) {
      $links[] = [
        '#title' => $this->t('Local Contexts Project Directory'),
        '#type' => 'link',
        '#url' => $protocolProjectDirectoryUrl,
      ];
    }

    $manageProtocolProjectDirectoryUrl = Url::fromRoute('mukurtu_local_contexts.manage_protocol_project_directory', ['group' => $protocol->id()]);
    if ($manageProtocolProjectDirectoryUrl->access()) {
      $links[] = [
        '#title' => $this->t('Manage Local Contexts Project Directory'),
        '#type' => 'link',
        '#url' => $manageProtocolProjectDirectoryUrl,
      ];
    }

    // Sharing Protocol.
    $visibilityMarkup['strict'] = $this->t('Strict: This cultural protocol is visible to members only.');
    $visibilityMarkup['open'] = $this->t('Open: This cultural protocol is visible to all.');
    $sharing = [
      '#type' => 'item',
      '#title' => $this->t('Sharing Protocol'),
      '#markup' => $visibilityMarkup[$group->getSharingSetting()],
    ];

    // Comment status.
    $commentStatus = [
      '#type' => 'item',
      '#title' => $this->t('Commenting'),
      '#markup' => $group->getCommentStatus() ? $this->t('Comments are enabled for this protocol.') : $this->t('Comments are disabled for this protocol.'),
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

    $communities = $protocol->getCommunities();

    $build['template'] = [
      '#theme' => 'manage-protocol',
      '#links' => $links,
      '#protocol' => $protocol,
      '#sharing' => $sharing,
      '#comment_status' => $commentStatus,
      '#description' => $description,
      '#communities' => $communities,
    ];

    return $build;
  }

  /**
   * Redirect to management page.
   */
  public function manageProtocolRedirect(ProtocolInterface $protocol) {
    return $this->redirect('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()], ['parameters' => ['group' => ['type' => 'entity:protocol']]]);
  }

  /**
   * Access check for redirect to management page.
   */
  public function manageProtocolRedirectAccess(AccountInterface $account, ProtocolInterface $protocol) {
    /** @var \Drupal\og\Access\OgMembershipAddAccessCheck $og_access */
    $og_access = \Drupal::service('access_check.og.membership.add');
    return $og_access->access(\Drupal::routeMatch(), $account, $protocol);
  }

  /**
   * Title callback for single protocol management page.
   */
  public function getManageProtocolTitle(ProtocolInterface $group) {
    return $this->t('Manage %protocol', ['%protocol' => $group->getName()]);
  }

}
