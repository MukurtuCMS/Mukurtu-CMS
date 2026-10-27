<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests invalid community access modes, manager delete, and orphan cleanup.
 *
 * @see \Drupal\mukurtu_protocol\CommunityAccessControlHandler
 * @see \Drupal\mukurtu_protocol\Hook\MukurtuProtocolHooks::ogUserAccessAlter()
 * @see mukurtu_protocol_entity_delete()
 * @see mukurtu_protocol_update_40201()
 * @see mukurtu_protocol_update_40202()
 */
#[Group('mukurtu_protocol')]
class CommunityAccessModeAndCleanupTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   *
   * mukurtu_protocol_entity_delete() clears mukurtu_local_contexts.settings,
   * whose schema comes from mukurtu_local_contexts.
   */
  protected static $modules = [
    'path_alias',
    'mukurtu_local_contexts',
  ];

  /**
   * A user with Mukurtu's community admin permissions but no memberships.
   */
  protected UserInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $role = Role::load('authenticated');
    $role->grantPermission('view published community entities');
    $role->save();

    $manager_role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $manager_role->grantPermission('administer community entities');
    $manager_role->grantPermission('delete community entities');
    $manager_role->grantPermission('administer protocol entities');
    $manager_role->save();
    $this->manager = $this->createUser([], NULL, FALSE, ['roles' => ['manager']]);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_protocol.install';
  }

  /**
   * Creates a community.
   */
  protected function createCommunity(string $access_mode): Community {
    $community = Community::create([
      'name' => $this->randomMachineName(),
      'field_access_mode' => $access_mode,
    ]);
    $community->save();
    return $community;
  }

  /**
   * Counts a group's memberships in every state.
   */
  protected function countMemberships(string $entity_type, int|string $id): int {
    return (int) \Drupal::entityTypeManager()->getStorage('og_membership')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $id)
      ->count()
      ->execute();
  }

  /**
   * Tests that an invalid access mode is treated like community-only.
   */
  public function testInvalidAccessModeViewedLikeCommunityOnly(): void {
    $member = $this->createUser();
    $outsider = $this->createUser();
    foreach (['open', 'strict', 'community-only'] as $mode) {
      $community = $this->createCommunity($mode);
      $community->addMember($member);

      $this->assertTrue($community->access('view', $member), $mode);
      $this->assertFalse($community->access('view', $outsider), $mode);
      $this->assertFalse($community->access('view', $this->manager), $mode);
    }

    $community = $this->createCommunity('public');
    $this->assertTrue($community->access('view', $outsider));
  }

  /**
   * Tests that 'delete community entities' satisfies OG for communities only.
   */
  public function testManagerCanDeleteCommunityWithoutMembership(): void {
    $community = $this->createCommunity('community-only');
    $this->assertTrue($community->access('delete', $this->manager));
    $this->assertFalse($community->access('delete', $this->createUser()));

    // The handler's own blockers still apply.
    $protocol = Protocol::create([
      'name' => $this->randomMachineName(),
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('community');
    $access_handler->resetCache();
    $community = Community::load($community->id());
    $this->assertFalse($community->access('delete', $this->manager));

    // The OG grant is limited to communities.
    $og_access = \Drupal::service('og.access');
    $this->assertFalse($og_access->userAccess($protocol, 'delete group', $this->manager)->isAllowed());
    $this->assertTrue($og_access->userAccess($community, 'delete group', $this->manager)->isAllowed());
  }

  /**
   * Tests that deleting a community or protocol deletes its memberships.
   */
  public function testDeletingGroupDeletesItsMemberships(): void {
    $user = $this->createUser();
    $doomed = $this->createCommunity('public');
    $kept = $this->createCommunity('public');
    $doomed->addMember($user);
    $kept->addMember($user);
    $blocked = Og::createMembership($doomed, $this->createUser());
    $blocked->setState('blocked')->save();
    // The creating user is also a member, so compare against a baseline.
    $this->assertGreaterThanOrEqual(2, $this->countMemberships('community', $doomed->id()));
    $kept_count = $this->countMemberships('community', $kept->id());

    $doomed_id = $doomed->id();
    $doomed->delete();
    $this->assertSame(0, $this->countMemberships('community', $doomed_id));
    $this->assertSame($kept_count, $this->countMemberships('community', $kept->id()));

    $protocol = Protocol::create([
      'name' => $this->randomMachineName(),
      'field_communities' => [$kept->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user);
    $protocol_id = $protocol->id();
    $this->assertGreaterThanOrEqual(1, $this->countMemberships('protocol', $protocol_id));
    $protocol->delete();
    $this->assertSame(0, $this->countMemberships('protocol', $protocol_id));
    $this->assertSame($kept_count, $this->countMemberships('community', $kept->id()));
  }

  /**
   * Tests that update 40201 repairs only invalid access modes.
   */
  public function testUpdate40201RepairsInvalidAccessModes(): void {
    $open = $this->createCommunity('open');
    $strict = $this->createCommunity('strict');
    $public = $this->createCommunity('public');
    $private = $this->createCommunity('community-only');

    $this->assertSame('Set 2 communities to Community only.', mukurtu_protocol_update_40201());

    $storage = \Drupal::entityTypeManager()->getStorage('community');
    $this->assertSame('community-only', $storage->load($open->id())->get('field_access_mode')->value);
    $this->assertSame('community-only', $storage->load($strict->id())->get('field_access_mode')->value);
    $this->assertSame('public', $storage->load($public->id())->get('field_access_mode')->value);
    $this->assertSame('community-only', $storage->load($private->id())->get('field_access_mode')->value);
    $revision_values = \Drupal::database()->select('community_field_revision', 'r')
      ->fields('r', ['field_access_mode'])
      ->distinct()
      ->execute()
      ->fetchCol();
    sort($revision_values);
    $this->assertSame(['community-only', 'public'], $revision_values);

    $this->assertNull(mukurtu_protocol_update_40201());
  }

  /**
   * Tests that update 40202 deletes only orphaned memberships.
   */
  public function testUpdate40202DeletesOrphanedMemberships(): void {
    $user = $this->createUser();
    $orphaned = $this->createCommunity('public');
    $kept = $this->createCommunity('public');
    $orphaned->addMember($user);
    $kept->addMember($user);
    $protocol = Protocol::create([
      'name' => $this->randomMachineName(),
      'field_communities' => [$kept->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user);
    // The creating user is also a member, so compare against a baseline.
    $orphan_count = $this->countMemberships('community', $orphaned->id()) + $this->countMemberships('protocol', $protocol->id());
    $kept_count = $this->countMemberships('community', $kept->id());

    // Remove the groups' rows directly, as older deletes left memberships.
    $database = \Drupal::database();
    foreach (['community', 'community_field_data', 'community_field_revision', 'community_revision'] as $table) {
      $database->delete($table)->condition('id', $orphaned->id())->execute();
    }
    foreach (['protocol', 'protocol_field_data', 'protocol_field_revision', 'protocol_revision'] as $table) {
      $database->delete($table)->condition('id', $protocol->id())->execute();
    }

    $this->assertSame("Deleted $orphan_count memberships.", mukurtu_protocol_update_40202());
    $this->assertSame(0, $this->countMemberships('community', $orphaned->id()));
    $this->assertSame(0, $this->countMemberships('protocol', $protocol->id()));
    $this->assertSame($kept_count, $this->countMemberships('community', $kept->id()));

    $this->assertNull(mukurtu_protocol_update_40202());
  }

}
