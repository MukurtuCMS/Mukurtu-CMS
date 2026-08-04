<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Entity\OgRole;
use Drupal\user\Entity\Role;

/**
 * Tests importing community/protocol membership via user account import.
 */
class ImportUserMembershipTest extends MukurtuImportTestBase {

  /**
   * A second community, distinct from the base class's default community.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected $secondCommunity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $import_users_role = Role::create(['id' => 'import_users', 'label' => 'Import Users']);
    $import_users_role->grantPermission('import mukurtu users');
    $import_users_role->save();
    $this->currentUser->addRole('import_users');
    $this->currentUser->save();

    // The community_manager/community_member/community_affiliate OG roles
    // aren't shipped as installable config (only community_manager's is,
    // and this test base doesn't install mukurtu_protocol's config), so
    // they need to be created directly, matching the pattern used in
    // ManageBulkRolesFormTest.
    $manager = OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => ['manage members', 'update group', 'add user'],
    ]);
    $manager->setGroupType('community');
    $manager->setGroupBundle('community');
    $manager->save();

    $member = OgRole::create([
      'name' => 'community_member',
      'label' => 'Community Member',
      'permissions' => [],
    ]);
    $member->setGroupType('community');
    $member->setGroupBundle('community');
    $member->save();

    $affiliate = OgRole::create([
      'name' => 'community_affiliate',
      'label' => 'Community Affiliate',
      'permissions' => [],
    ]);
    $affiliate->setGroupType('community');
    $affiliate->setGroupBundle('community');
    $affiliate->save();

    // Membership grants are gated by the importing user's own 'manage
    // members' access to the target group (see
    // ProtocolAwareUserContent::applyGroupMembershipUpdates()); the base
    // class only adds $this->currentUser to $this->community with no role,
    // so give them the manager role there. (They already have
    // 'protocol_steward' -- which grants 'manage members' -- on
    // $this->protocol via the base class.)
    $this->community->setRoles($this->currentUser, ['community_manager']);

    $this->secondCommunity = Community::create(['name' => 'Second Community']);
    $this->secondCommunity->save();
    // Community::create() with no explicit owner defaults to the current
    // user, and OG auto-adds the owner as a roleless member on save -- use
    // setRoles() rather than addMember() so the manager role actually takes
    // (addMember() no-ops without updating roles if a membership already
    // exists in any state).
    $this->secondCommunity->setRoles($this->currentUser, ['community_manager']);
  }

  /**
   * Test that granting membership in a group the importer doesn't manage fails.
   *
   * 'import mukurtu users' alone is not sufficient authority to grant
   * community/protocol membership anywhere on the site -- the importer
   * also needs 'manage members' access (via an OG role, or a broader OG
   * admin permission) on the *specific* group named in the CSV, so a
   * community/protocol's own stewards retain control over their own
   * membership.
   */
  public function testImporterWithoutGroupAccessCannotGrantMembership() {
    $unmanaged_community = Community::create(['name' => 'Unmanaged Community', 'user_id' => $this->otherUserForOwnership()->id()]);
    $unmanaged_community->save();

    // uid 1 -- $this->currentUser, the importer for every other test in
    // this class -- unconditionally bypasses every OG access check
    // (OgAccess::userAccess() hardcodes "User ID 1 has all privileges"),
    // so this negative case can't be proven from that account no matter
    // what the target group's membership looks like. Switch to a
    // non-superuser importer who holds 'import mukurtu users' but has no
    // relationship at all to the target community, mirroring
    // ImportUserAccountTest::testNonSuperuserWithPermissionCanImport()'s
    // pattern for exercising a non-uid-1 code path.
    $importer = $this->createUser();
    $importer->addRole('import_users');
    $importer->save();
    $this->setCurrentUser($importer);

    $data = [
      ['Username', 'Email', 'Communities'],
      ['blockedgrant', 'blockedgrant@example.com', 'Unmanaged Community:community_member'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];

    $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'blockedgrant']);
    $this->assertEmpty($users, 'A row granting membership in a group the importer does not manage must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('does not have permission to manage membership', reset($messages)->message);
  }

  /**
   * Creates a fresh user to own a community the test's currentUser has no
   * relationship to, so OG's auto-added-owner membership doesn't
   * accidentally give currentUser access.
   */
  protected function otherUserForOwnership() {
    $user = $this->createUser();
    $user->save();
    return $user;
  }

  /**
   * Test that a new user's community/protocol memberships and roles are set.
   */
  public function testNewUserGetsCommunityAndProtocolMembership() {
    $data = [
      ['Username', 'Email', 'Communities', 'Protocols'],
      [
        'newmember',
        'newmember@example.com',
        $this->community->label() . ':community_manager',
        $this->protocol->label() . ':protocol_steward',
      ],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
      ['target' => 'protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'newmember']);
    $user = reset($users);
    $this->assertNotFalse($user);

    $community_membership = $this->community->getMembership($user);
    $this->assertNotNull($community_membership);
    $this->assertContains('community_manager', array_map(fn($role) => $role->getName(), $community_membership->getRoles()));

    $protocol_membership = $this->protocol->getMembership($user);
    $this->assertNotNull($protocol_membership);
    $this->assertContains('protocol_steward', array_map(fn($role) => $role->getName(), $protocol_membership->getRoles()));
  }

  /**
   * Test a user with no memberships in the CSV cell is left without one.
   */
  public function testBlankMembershipColumnGrantsNoMembership() {
    $data = [
      ['Username', 'Email', 'Communities', 'Protocols'],
      ['nomembership', 'nomembership@example.com', '', ''],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
      ['target' => 'protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'nomembership']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertNull($this->community->getMembership($user));
  }

  /**
   * Test that re-importing a user with a changed role updates it.
   *
   * Community::addMember() no-ops without updating roles when a membership
   * already exists; this covers the explicit getMembership()-then-setRoles()
   * branch added to ProtocolAwareUserContent to handle that case.
   */
  public function testReimportWithChangedRoleUpdatesMembership() {
    $create_data = [
      ['Username', 'Email', 'Communities'],
      ['reimported', 'reimported@example.com', $this->community->label() . ':community_member'],
    ];
    $create_mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];
    $result = $this->importCsvFile($this->createCsvFile($create_data), $create_mapping, 'user', 'user');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'reimported']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $membership = $this->community->getMembership($user);
    $this->assertContains('community_member', array_map(fn($role) => $role->getName(), $membership->getRoles()));

    $update_data = [
      ['ID', 'Communities'],
      [$user->id(), $this->community->label() . ':community_manager'],
    ];
    $update_mapping = [
      ['target' => 'uid', 'source' => 'ID'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];
    $result = $this->importCsvFile($this->createCsvFile($update_data), $update_mapping, 'user', 'user');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_membership = $this->community->getMembership($user);
    $updated_roles = array_map(fn($role) => $role->getName(), $updated_membership->getRoles());
    $this->assertContains('community_manager', $updated_roles);
    $this->assertNotContains('community_member', $updated_roles);
  }

  /**
   * Test that a typo'd/unrecognized role name fails the row.
   *
   * Community::addMember()/Protocol::addMember() silently drop any role
   * that doesn't resolve to a real OgRole rather than erroring, which would
   * otherwise let a typo'd role name produce a successfully-created but
   * silently role-less membership.
   */
  public function testInvalidRoleNameFailsRow() {
    $data = [
      ['Username', 'Email', 'Communities'],
      ['typorole', 'typorole@example.com', $this->community->label() . ':comunity_manager'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];

    $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'typorole']);
    $this->assertEmpty($users, 'A row referencing an invalid role name must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('is not a valid role', reset($messages)->message);
  }

  /**
   * Test that an unresolvable community name fails the row.
   *
   * Membership grants are a core access-control primitive, so a typo'd
   * community/protocol name must hard-fail the row rather than silently
   * import the account without the intended membership.
   */
  public function testUnresolvableCommunityNameFailsRow() {
    $data = [
      ['Username', 'Email', 'Communities'],
      ['typocommunity', 'typocommunity@example.com', 'Nonexistent Community:community_member'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];

    $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'typocommunity']);
    $this->assertEmpty($users, 'A row referencing an unresolvable community name must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('could not be resolved', reset($messages)->message);
  }

  /**
   * Test that an ambiguous community name fails the row.
   */
  public function testAmbiguousCommunityNameFailsRow() {
    Community::create(['name' => $this->community->label()])->save();

    $data = [
      ['Username', 'Email', 'Communities'],
      ['ambiguous', 'ambiguous@example.com', $this->community->label() . ':community_member'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];

    $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'ambiguous']);
    $this->assertEmpty($users, 'A row referencing an ambiguous community name must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('is ambiguous', reset($messages)->message);
  }

  /**
   * Test that a user can be given membership in multiple communities.
   */
  public function testMultipleCommunityMemberships() {
    $data = [
      ['Username', 'Email', 'Communities'],
      [
        'multimember',
        'multimember@example.com',
        $this->community->label() . ':community_manager;' . $this->secondCommunity->label() . ':community_affiliate',
      ],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'communities', 'source' => 'Communities'],
    ];

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping, 'user', 'user');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'multimember']);
    $user = reset($users);
    $this->assertNotFalse($user);

    $first_roles = array_map(fn($role) => $role->getName(), $this->community->getMembership($user)->getRoles());
    $this->assertContains('community_manager', $first_roles);

    $second_roles = array_map(fn($role) => $role->getName(), $this->secondCommunity->getMembership($user)->getRoles());
    $this->assertContains('community_affiliate', $second_roles);
  }

}
