<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Functional;

/**
 * Tests that bulk og_membership deletion respects the community/protocol guard.
 *
 * Verifies that a user who still belongs to a child protocol cannot be removed
 * from the parent community via the og_membership_delete_action bulk action,
 * while users with no protocol roles can be removed normally.
 *
 * @group mukurtu_protocol
 */
class CommunityMemberBulkRemovalTest extends ProtocolAwareFunctionalTestBase {

  /**
   * User enrolled in a community and one of its child protocols.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $memberWithProtocolRole;

  /**
   * User enrolled in a community only, with no protocol roles.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $memberWithoutProtocolRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->memberWithProtocolRole = $this->createUser();
    $this->community1->addMember($this->memberWithProtocolRole);
    $this->community1_open->addMember($this->memberWithProtocolRole);

    $this->memberWithoutProtocolRole = $this->createUser();
    $this->community1->addMember($this->memberWithoutProtocolRole);
  }

  /**
   * Returns the og_membership_delete_action plugin (wired to Mukurtu's class).
   */
  protected function getBulkDeleteAction() {
    return \Drupal::service('plugin.manager.action')
      ->createInstance('og_membership_delete_action', []);
  }

  /**
   * Bulk delete is blocked when the target user still has protocol roles.
   */
  public function testBulkDeleteBlockedForUserWithProtocolRoles(): void {
    $membership = $this->community1->getMembership($this->memberWithProtocolRole);
    $this->assertNotNull($membership, 'User has a community membership before the action runs.');

    $action = $this->getBulkDeleteAction();
    $action->execute($membership);

    $this->assertNotNull(
      $this->community1->getMembership($this->memberWithProtocolRole),
      'Community membership was not removed because the user still has protocol roles.'
    );
  }

  /**
   * Bulk delete succeeds for a community member with no protocol roles.
   */
  public function testBulkDeleteSucceedsForUserWithoutProtocolRoles(): void {
    $membership = $this->community1->getMembership($this->memberWithoutProtocolRole);
    $this->assertNotNull($membership, 'User has a community membership before the action runs.');

    $action = $this->getBulkDeleteAction();
    $action->execute($membership);

    $this->assertNull(
      $this->community1->getMembership($this->memberWithoutProtocolRole),
      'Community membership was removed because the user has no protocol roles.'
    );
  }

  /**
   * Community::removeMember() silently skips users with protocol roles.
   */
  public function testRemoveMemberSilentlySkipsUserWithProtocolRoles(): void {
    $this->community1->removeMember($this->memberWithProtocolRole);

    $this->assertNotNull(
      $this->community1->getMembership($this->memberWithProtocolRole),
      'removeMember() left the membership intact when the user has protocol roles.'
    );
  }

  /**
   * Community::removeMember() removes users with no protocol roles.
   */
  public function testRemoveMemberSucceedsForUserWithoutProtocolRoles(): void {
    $this->community1->removeMember($this->memberWithoutProtocolRole);

    $this->assertNull(
      $this->community1->getMembership($this->memberWithoutProtocolRole),
      'removeMember() deleted the membership when the user has no protocol roles.'
    );
  }

}
