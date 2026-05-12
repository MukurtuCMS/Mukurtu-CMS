<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Approves the Drupal user account of the selected pending community/protocol members.
 *
 * @Action(
 *   id = "mukurtu_approve_user_from_membership_action",
 *   label = @Translation("Approve pending user account(s)"),
 *   type = "og_membership"
 * )
 */
class MukurtuApproveUserFromMembershipAction extends ActionBase implements ContainerFactoryPluginInterface {

  protected OgAccessInterface $ogAccess;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OgAccessInterface $og_access) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ogAccess = $og_access;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('og.access'));
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?OgMembership $membership = NULL) {
    if (!$membership) {
      return;
    }
    $owner = $membership->getOwner();
    if (!$owner || $owner->isActive()) {
      return;
    }
    $owner->set('status', 1);
    $owner->save();
    \Drupal::messenger()->addStatus(t('%user has been approved.', ['%user' => $owner->getDisplayName()]));
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof OgMembership) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    $owner = $object->getOwner();
    if (!$owner || $owner->isActive()) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    $access = $this->ogAccess->userAccess($object->getGroup(), 'manage members', $account);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
