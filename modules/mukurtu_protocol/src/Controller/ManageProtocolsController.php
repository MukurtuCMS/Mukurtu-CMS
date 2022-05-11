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

    $links[] = [
      '#title' => $this->t('View'),
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.protocol.canonical', ['protocol' => $protocol->id()]),
    ];

    $links[] = [
      '#title' => $this->t('Edit'),
      '#type' => 'link',
      '#url' => $group->toUrl('edit-form'),
    ];

    $links[] = [
      '#title' => $this->t('Manage Members'),
      '#type' => 'link',
      '#url' => Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()]),
    ];

    $links[] = [
      '#title' => $this->t('Add Member'),
      '#type' => 'link',
      '#url' => Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()]),
    ];

    // Sharing Protocol.
    $visibilityMarkup['strict'] = $this->t('Strict: This cultural protocol is visible to members only.');
    $visibilityMarkup['open'] = $this->t('Open: This cultural protocol is visible to all.');
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

    $communities = $protocol->getCommunities();

    $build['template'] = [
      '#theme' => 'manage-protocol',
      '#links' => $links,
      '#protocol' => $protocol,
      '#sharing' => $sharing,
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
