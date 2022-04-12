<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;

/**
 * Controller to manage pages for protocol creation.
 */
class CreateProtocolFromCommunityController extends ControllerBase {

  /**
   * Page to create a new protocol given a community.
   */
  public function createProtocolFromCommunityPage($community) {
    $community = $this->entityTypeManager()->getStorage('community')->load($community);

    return [
      'form' => \Drupal::formBuilder()->getForm('\Drupal\mukurtu_protocol\Form\ProtocolAddForm', $community),
    ];
  }

  /**
   * Title callback for the add protocol form.
   */
  public function getTitle($community) {
    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    $community = $this->entityTypeManager()->getStorage('community')->load($community);
    return $community ? $this->t('Creating cultural protocol for %community', ['%community' => $community->getName()]) : $this->t('Creating a new cultural protocol');
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param int $community
   *   The ID of the community to own the new protocol.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $community) {
    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    $community = $this->entityTypeManager()->getStorage('community')->load($community);

    if (!$community) {
      return AccessResult::forbidden();
    }

    // User must have permission to create protocols for this
    // specific community.
    $membership = Og::getMembership($community, $account);
    if ($membership && $membership->hasPermission("create protocol protocol")) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
