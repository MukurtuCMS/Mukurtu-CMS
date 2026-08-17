<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the redundant "View" operation is removed from OG membership
 * rows on the community and protocol member management pages.
 *
 * @see \Drupal\mukurtu_protocol\Hook\MukurtuProtocolHooks::entityOperationAlterOgMembership()
 */
#[Group('mukurtu_protocol')]
class OgMembershipViewOperationTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Community group role with "manage members", mirroring the
    // protocol_steward role the parent class sets up for protocols.
    OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => ['manage members'],
    ])->setGroupType('community')->setGroupBundle('community')->save();
  }

  /**
   * A steward viewing another member's row sees no "View" operation.
   */
  public function testViewOperationRemovedForProtocolMembership(): void {
    $steward = $this->createUser();
    $steward->save();
    $target = $this->createUser();
    $target->save();

    $protocol = Protocol::create([
      'name' => 'Protocol under test',
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($steward, ['protocol_steward']);
    $protocol->addMember($target);

    $this->setCurrentUser($steward);

    $membership = Og::getMembership($protocol, $target);
    $operations = \Drupal::entityTypeManager()
      ->getListBuilder('og_membership')
      ->getOperations($membership);

    $this->assertArrayNotHasKey('view', $operations);
    $this->assertArrayHasKey('edit', $operations);
    $this->assertStringContainsString('Manage roles for', (string) $operations['edit']['title']);
    $this->assertArrayHasKey('delete', $operations);
    $this->assertStringContainsString('from protocol', (string) $operations['delete']['title']);
  }

  /**
   * A manager viewing another member's row sees no "View" operation.
   */
  public function testViewOperationRemovedForCommunityMembership(): void {
    $manager = $this->createUser();
    $manager->save();
    $target = $this->createUser();
    $target->save();

    $community = Community::create(['name' => 'Community under test']);
    $community->save();
    $community->addMember($manager, ['community_manager']);
    $community->addMember($target);

    $this->setCurrentUser($manager);

    $membership = Og::getMembership($community, $target);
    $operations = \Drupal::entityTypeManager()
      ->getListBuilder('og_membership')
      ->getOperations($membership);

    $this->assertArrayNotHasKey('view', $operations);
    $this->assertArrayHasKey('edit', $operations);
    $this->assertStringContainsString('Manage roles for', (string) $operations['edit']['title']);
    $this->assertArrayHasKey('delete', $operations);
    $this->assertStringContainsString('from community', (string) $operations['delete']['title']);
  }

}
